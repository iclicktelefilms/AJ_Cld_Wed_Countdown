<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI Assistant Main Controller
 *
 * Handles the core chat message pipeline:
 *  1. Receive user message
 *  2. Load conversation history
 *  3. Build system prompt (with CRM context)
 *  4. Call Gemini
 *  5. Dispatch tool calls if returned
 *  6. Loop back to Gemini with tool results
 *  7. Return final response to frontend
 */
class Ai_assistant extends AdminController
{
    /** Max tool-call iterations per single user message (prevents infinite loops) */
    private const MAX_TOOL_LOOPS = 5;

    public function __construct()
    {
        parent::__construct();

        if (!is_staff_logged_in()) {
            redirect(admin_url('authentication/logout'));
        }

        $this->load->helper('ai_assistant');
        $this->load->model([
            'Ai_assistant_model' => 'ai_assistant_model',
            'Ai_chat_model'      => 'ai_chat_model',
            'Ai_logs_model'      => 'ai_logs_model',
        ]);

        // Load core libraries
        $this->load->library([
            'libraries/Gemini_client'   => null,
            'libraries/Tool_dispatcher' => null,
            'libraries/Context_manager' => null,
        ]);

        // Load response formatter service
        if (!class_exists('Response_formatter')) {
            require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'services/Response_formatter.php');
        }
    }

    // ── Chat Endpoints ────────────────────────────────────────────────────────

    /**
     * POST /ai_assistant/chat
     * Primary chat endpoint — processes a user message and returns AI response.
     */
    public function chat()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $staff_id   = get_staff_user_id();
        $user_msg   = trim($this->input->post('message'));
        $session_id = trim($this->input->post('session_id') ?? '');

        if (empty($user_msg)) {
            $this->json_error('Message cannot be empty.');
            return;
        }

        if (mb_strlen($user_msg) > 4096) {
            $this->json_error('Message too long (max 4096 characters).');
            return;
        }

        $settings = ai_assistant_get_settings();
        if (empty($settings['api_key'])) {
            $this->json_error('AI Assistant is not configured. Please ask your administrator to set up the API key.');
            return;
        }

        // Ensure session exists
        $session = $this->ai_assistant_model->get_or_create_session($staff_id, $session_id);
        $session_id = $session['session_id'];

        // Save user message
        $this->ai_chat_model->save_message($session_id, $staff_id, 'user', $user_msg);
        $this->ai_chat_model->maybe_set_session_title($session_id, $user_msg);

        // Get conversation history
        $memory_limit = $settings['memory_limit'] ?? 20;
        $history      = $this->ai_chat_model->get_session_history($session_id, $memory_limit);

        // Build system prompt
        $context_manager = new Context_manager();
        $tool_dispatcher = new Tool_dispatcher();
        $available_tools = $tool_dispatcher->get_available_tools();
        $system_prompt   = $context_manager->build_system_prompt($available_tools);

        // Run the Gemini request + tool loop
        try {
            $result = $this->run_ai_pipeline($history, $system_prompt, $available_tools, $session_id, $staff_id);
        } catch (Throwable $e) {
            $this->ai_logs_model->log_error($staff_id, 'chat', $e->getMessage(), ['message' => $user_msg]);
            $this->json_error('AI request failed: ' . $e->getMessage());
            return;
        }

        // Save final AI response
        $this->ai_chat_model->save_message($session_id, $staff_id, 'assistant', $result['content'], [
            'model_used'  => $result['model'] ?? '',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'ai_provider' => 'gemini',
        ]);

        // Update context memory
        $context_manager->extract_and_save_business_context($user_msg, $result['content']);

        $this->json_success([
            'message'     => $result['content'],
            'session_id'  => $session_id,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'tools_used'  => $result['tools_used'] ?? [],
        ]);
    }

    /**
     * Run the multi-turn AI + tool execution pipeline
     */
    private function run_ai_pipeline(
        array  $history,
        string $system_prompt,
        array  $available_tools,
        string $session_id,
        int    $staff_id
    ): array {
        $gemini          = new Gemini_client();
        $tool_dispatcher = new Tool_dispatcher();
        $formatter       = new Response_formatter();
        $current_history = $history;
        $total_tokens    = 0;
        $tools_used      = [];
        $loops           = 0;

        do {
            $response = $gemini->chat($current_history, $system_prompt, $available_tools);
            $total_tokens += $response['tokens_used'] ?? 0;

            // No tool call → final text response
            if (empty($response['tool_calls'])) {
                return [
                    'content'     => $response['content'],
                    'model'       => $response['model'],
                    'tokens_used' => $total_tokens,
                    'tools_used'  => $tools_used,
                ];
            }

            // Process tool call
            $tool_call  = $response['tool_calls'];
            $tool_name  = $tool_call['name'] ?? '';
            $tool_params = $tool_call['args'] ?? [];

            // Save the model's tool call to history
            $this->ai_chat_model->save_message($session_id, $staff_id, 'assistant', '', [
                'tool_calls' => $tool_call,
                'ai_provider' => 'gemini',
            ]);

            // Dispatch tool
            $tool_result = $tool_dispatcher->dispatch($tool_name, $tool_params);

            // Handle confirmation-required tools
            if (!empty($tool_result['needs_confirm'])) {
                $confirm_msg = $this->build_confirm_message($tool_name, $tool_params, $tool_result['tool_def']);
                return [
                    'content'      => $confirm_msg,
                    'model'        => $response['model'],
                    'tokens_used'  => $total_tokens,
                    'tools_used'   => $tools_used,
                    'needs_confirm'=> true,
                    'confirm_data' => ['tool' => $tool_name, 'params' => $tool_params],
                ];
            }

            $tools_used[] = $tool_name;

            // Format the tool result for the AI
            $formatted_result = $formatter->format($tool_name, $tool_result);

            // Add to history as tool result
            $result_payload = $tool_result['success']
                ? ($tool_result['data'] ?? ['message' => $formatted_result])
                : ['error' => $tool_result['error'] ?? 'Unknown error'];

            // Save tool result to history
            $this->ai_chat_model->save_message($session_id, $staff_id, 'tool_result', $formatted_result, [
                'tool_calls' => ['name' => $tool_name, 'result' => $result_payload],
            ]);

            // Update history for next loop
            $current_history[] = [
                'role'  => 'model',
                'parts' => [
                    ['text' => $response['content'] ?? ''],
                    ['functionCall' => $tool_call],
                ],
            ];
            $current_history[] = [
                'role'  => 'user',
                'parts' => [[
                    'functionResponse' => [
                        'name'     => $tool_name,
                        'response' => $result_payload,
                    ],
                ]],
            ];

            $loops++;

        } while ($loops < self::MAX_TOOL_LOOPS);

        // If we hit the loop limit, return the last text response
        return [
            'content'     => $response['content'] ?? 'I completed the requested operations.',
            'model'       => $response['model'] ?? '',
            'tokens_used' => $total_tokens,
            'tools_used'  => $tools_used,
        ];
    }

    /**
     * POST /ai_assistant/confirm_action
     * Execute a pre-confirmed action
     */
    public function confirm_action()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $tool_name  = trim($this->input->post('tool'));
        $params     = (array)json_decode($this->input->post('params') ?? '{}', true);
        $session_id = trim($this->input->post('session_id') ?? '');
        $staff_id   = get_staff_user_id();

        if (empty($tool_name)) {
            $this->json_error('Invalid tool name.');
            return;
        }

        $tool_dispatcher = new Tool_dispatcher();
        $formatter       = new Response_formatter();

        $result = $tool_dispatcher->execute_confirmed($tool_name, $params);
        $text   = $formatter->format($tool_name, $result);

        if (!empty($session_id)) {
            $this->ai_chat_model->save_message($session_id, $staff_id, 'assistant', $text);
        }

        $this->json_success(['message' => $text, 'tool_result' => $result]);
    }

    // ── Session Management ────────────────────────────────────────────────────

    /**
     * POST /ai_assistant/new_session
     */
    public function new_session()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $staff_id = get_staff_user_id();
        $session  = $this->ai_assistant_model->get_or_create_session($staff_id);

        $this->json_success(['session_id' => $session['session_id']]);
    }

    /**
     * POST /ai_assistant/clear_session
     */
    public function clear_session()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $session_id = trim($this->input->post('session_id') ?? '');
        $staff_id   = get_staff_user_id();

        if (empty($session_id)) {
            $this->json_error('Session ID required.');
            return;
        }

        $this->ai_chat_model->clear_session($session_id, $staff_id);
        $this->ai_assistant_model->archive_session($session_id, $staff_id);

        $this->json_success(['message' => 'Conversation cleared.']);
    }

    /**
     * GET /ai_assistant/sessions
     * Get list of sessions for the current staff member
     */
    public function sessions()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $staff_id = get_staff_user_id();
        $sessions = $this->ai_assistant_model->get_staff_sessions($staff_id, 10);

        $this->json_success(['sessions' => $sessions]);
    }

    /**
     * GET /ai_assistant/messages
     * Get paginated messages for a session
     */
    public function messages()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $session_id = trim($this->input->get('session_id') ?? '');
        $before_id  = (int)($this->input->get('before_id') ?? 0);
        $staff_id   = get_staff_user_id();

        if (empty($session_id)) {
            $this->json_error('Session ID required.');
            return;
        }

        $messages = $this->ai_chat_model->get_messages_for_display($session_id, 30, $before_id);

        $this->json_success(['messages' => $messages]);
    }

    // ── Voice Endpoints ───────────────────────────────────────────────────────

    /**
     * POST /ai_assistant/transcribe
     * Transcribe audio to text
     */
    public function transcribe()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $settings = ai_assistant_get_settings();
        if (!$settings['voice_enabled']) {
            $this->json_error('Voice assistant is disabled.');
            return;
        }

        if (!class_exists('Voice_processor')) {
            require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Voice_processor.php');
        }

        $voice_proc = new Voice_processor();
        $language   = $this->input->post('language') ?? '';

        // Handle base64 audio from browser MediaRecorder
        $audio_b64 = $this->input->post('audio_base64');
        $mime_type = $this->input->post('mime_type') ?? 'audio/webm';

        if ($audio_b64) {
            $result = $voice_proc->transcribe_base64($audio_b64, $mime_type, $language);
        } elseif (!empty($_FILES['audio']['name'])) {
            $result = $voice_proc->transcribe_upload($_FILES['audio'], $language);
        } else {
            $this->json_error('No audio data provided.');
            return;
        }

        if ($result['success']) {
            $this->json_success($result);
        } else {
            $this->json_error($result['error'] ?? 'Transcription failed.');
        }
    }

    /**
     * POST /ai_assistant/speak
     * Convert AI text response to audio
     */
    public function speak()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $text     = trim($this->input->post('text') ?? '');
        $language = $this->input->post('language') ?? 'en-US';

        if (empty($text)) {
            $this->json_error('Text is required.');
            return;
        }

        if (!class_exists('Voice_processor')) {
            require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Voice_processor.php');
        }

        $voice_proc = new Voice_processor();
        $result     = $voice_proc->text_to_speech($text, $language);

        $this->json_success($result);
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private function build_confirm_message(string $tool_name, array $params, array $tool_def): string
    {
        $readable = str_replace('_', ' ', $tool_name);
        $params_str = '';
        foreach ($params as $k => $v) {
            if (!is_array($v)) {
                $params_str .= "- **" . ucwords(str_replace('_', ' ', $k)) . ":** {$v}\n";
            }
        }

        return "I need your confirmation to **{$readable}** with the following details:\n\n{$params_str}\n"
            . "Please confirm or cancel this action.";
    }

    private function json_success(array $data): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'success'] + $data));
    }

    private function json_error(string $message, int $code = 400): void
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'error', 'message' => $message]));
    }
}
