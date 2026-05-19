<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * REST API Controller
 *
 * Provides external/webhook-style access for AI assistant features.
 * All endpoints require valid API authentication.
 */
class Api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('ai_assistant');
        $this->output->set_content_type('application/json');
    }

    /**
     * POST /ai_assistant/api/chat
     * Programmatic chat endpoint (e.g., from integrations)
     */
    public function chat()
    {
        $this->require_auth();

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];

        $message    = ai_sanitize_input($body['message'] ?? '', 4096);
        $session_id = ai_sanitize_input($body['session_id'] ?? '', 64);

        if (empty($message)) {
            $this->api_error('message is required.', 400);
            return;
        }

        // Delegate to main controller logic via internal request
        $this->load->library('libraries/Gemini_client');
        $this->load->model(['Ai_chat_model' => 'ai_chat_model', 'Ai_assistant_model' => 'ai_assistant_model']);

        $staff_id = get_staff_user_id();
        $session  = $this->ai_assistant_model->get_or_create_session($staff_id, $session_id);

        $this->output->set_output(json_encode([
            'status'     => 'success',
            'session_id' => $session['session_id'],
            'message'    => 'Use the primary /ai_assistant/chat endpoint for chat interactions.',
        ]));
    }

    /**
     * GET /ai_assistant/api/stats
     * Return usage statistics (admin only)
     */
    public function stats()
    {
        $this->require_admin_auth();

        $this->load->model('Ai_assistant_model', 'ai_assistant_model');
        $period = $this->input->get('period') ?? 'month';
        $stats  = $this->ai_assistant_model->get_usage_stats($period);

        $this->output->set_output(json_encode(['status' => 'success', 'data' => $stats]));
    }

    /**
     * GET /ai_assistant/api/tools
     * List available tools for current staff member
     */
    public function tools()
    {
        $this->require_auth();

        if (!class_exists('Tool_dispatcher')) {
            require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Tool_dispatcher.php');
        }

        $dispatcher = new Tool_dispatcher();
        $tools      = $dispatcher->get_available_tools();

        $simplified = array_map(fn($t) => [
            'name'       => $t['name'],
            'description'=> $t['description'],
            'permission' => $t['permission'],
        ], $tools);

        $this->output->set_output(json_encode(['status' => 'success', 'tools' => $simplified]));
    }

    // ── Auth Helpers ──────────────────────────────────────────────────────────

    private function require_auth(): void
    {
        if (!is_staff_logged_in()) {
            $this->api_error('Authentication required.', 401);
            exit;
        }
    }

    private function require_admin_auth(): void
    {
        if (!is_admin()) {
            $this->api_error('Admin access required.', 403);
            exit;
        }
    }

    private function api_error(string $message, int $code = 400): void
    {
        $this->output
            ->set_status_header($code)
            ->set_output(json_encode(['status' => 'error', 'message' => $message]));
    }
}
