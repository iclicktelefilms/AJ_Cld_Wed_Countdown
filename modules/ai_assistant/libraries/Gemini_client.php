<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Gemini API Client
 *
 * Handles all communication with Google Gemini API including:
 * - Text generation with conversation history
 * - Structured function/tool calling
 * - Voice transcription via Gemini Speech
 * - Text-to-speech conversion
 * - Retry logic, rate limit handling, and timeout management
 */
class Gemini_client
{
    private const API_BASE    = 'https://generativelanguage.googleapis.com/v1beta';
    private const MAX_RETRIES = 3;
    private const TIMEOUT     = 90;

    private string $api_key;
    private string $model;
    private float  $temperature;
    private int    $max_tokens;
    private array  $settings;

    public function __construct()
    {
        $this->settings    = ai_assistant_get_settings();
        $this->api_key     = $this->settings['api_key'] ?? '';
        $this->model       = $this->settings['model'] ?? 'gemini-2.5-pro';
        $this->temperature = $this->settings['temperature'] ?? 0.7;
        $this->max_tokens  = $this->settings['max_tokens'] ?? 8192;
    }

    /**
     * Send a chat completion request to Gemini
     *
     * @param  array  $messages       Conversation history in Gemini format [{role, parts}]
     * @param  string $system_prompt  System instruction text
     * @param  array  $tools          Tool definitions (Gemini function declarations)
     * @return array  ['content' => string, 'tool_calls' => array|null, 'tokens_used' => int, 'model' => string]
     * @throws RuntimeException on unrecoverable API errors
     */
    public function chat(array $messages, string $system_prompt = '', array $tools = []): array
    {
        if (empty($this->api_key)) {
            throw new RuntimeException('Gemini API key not configured.');
        }

        $endpoint = self::API_BASE . "/models/{$this->model}:generateContent?key=" . urlencode($this->api_key);

        $body = [
            'contents'         => $messages,
            'generationConfig' => [
                'temperature'     => $this->temperature,
                'maxOutputTokens' => $this->max_tokens,
                'responseMimeType'=> 'text/plain',
            ],
        ];

        if (!empty($system_prompt)) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $system_prompt]],
            ];
        }

        if (!empty($tools)) {
            $body['tools'] = [
                ['functionDeclarations' => $this->format_tool_declarations($tools)],
            ];
            $body['toolConfig'] = [
                'functionCallingConfig' => ['mode' => 'AUTO'],
            ];
        }

        $response = $this->request_with_retry('POST', $endpoint, $body);

        return $this->parse_response($response);
    }

    /**
     * Send a follow-up message with a tool result back to Gemini
     *
     * @param  array  $messages        Original conversation + model response
     * @param  string $function_name   Tool name that was called
     * @param  array  $function_result Tool execution result
     * @param  string $system_prompt
     * @param  array  $tools
     * @return array
     */
    public function chat_with_tool_result(
        array  $messages,
        string $function_name,
        array  $function_result,
        string $system_prompt = '',
        array  $tools = []
    ): array {
        // Append tool result as a user message with functionResponse
        $messages[] = [
            'role'  => 'user',
            'parts' => [[
                'functionResponse' => [
                    'name'     => $function_name,
                    'response' => $function_result,
                ],
            ]],
        ];

        return $this->chat($messages, $system_prompt, $tools);
    }

    /**
     * Transcribe audio to text using Gemini multimodal API
     *
     * @param  string $audio_base64  Base64-encoded audio data
     * @param  string $mime_type     e.g. audio/webm, audio/mp4
     * @param  string $language_hint 'hi' for Hindi, 'en' for English, '' for auto
     * @return string Transcribed text
     */
    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string
    {
        $endpoint = self::API_BASE . "/models/gemini-2.5-flash:generateContent?key=" . urlencode($this->api_key);

        $lang_instruction = match($language_hint) {
            'hi'    => 'Transcribe the following audio. The speaker may use Hindi, Hinglish, or English. Provide exact transcript in the original language.',
            'en'    => 'Transcribe the following audio in English.',
            default => 'Transcribe the following audio. The speaker may use Hindi, English, or a mix. Provide exact transcript.',
        };

        $body = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['text' => $lang_instruction],
                    [
                        'inlineData' => [
                            'mimeType' => $mime_type,
                            'data'     => $audio_base64,
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0.0,
                'maxOutputTokens' => 2048,
            ],
        ];

        $response = $this->request_with_retry('POST', $endpoint, $body);
        $parsed   = $this->parse_response($response);

        return trim($parsed['content'] ?? '');
    }

    /**
     * Generate spoken audio from text using Gemini TTS
     * Falls back to browser TTS instructions if TTS not available
     *
     * @param  string $text
     * @param  string $language 'hi-IN'|'en-US'
     * @return array  ['audio_base64' => string|null, 'text' => string, 'use_browser_tts' => bool]
     */
    public function text_to_speech(string $text, string $language = 'en-US'): array
    {
        // Gemini TTS endpoint (v1beta speech synthesis)
        $endpoint = self::API_BASE . "/models/gemini-2.5-flash-preview-tts:generateContent?key=" . urlencode($this->api_key);

        $voice_name = $language === 'hi-IN' ? 'hi-IN-Standard-A' : 'en-US-Standard-C';

        $body = [
            'contents'        => [['parts' => [['text' => $text]]]],
            'generationConfig' => [
                'response_modalities' => ['AUDIO'],
                'speech_config'       => [
                    'voice_config' => [
                        'prebuilt_voice_config' => ['voice_name' => $voice_name],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->request_with_retry('POST', $endpoint, $body);

            // Extract audio data from response
            $audio_data = $response['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;

            if ($audio_data) {
                return [
                    'audio_base64'    => $audio_data,
                    'text'            => $text,
                    'use_browser_tts' => false,
                    'mime_type'       => 'audio/wav',
                ];
            }
        } catch (Throwable $e) {
            // Silently fall through to browser TTS
        }

        // Fallback: instruct frontend to use browser TTS
        return [
            'audio_base64'    => null,
            'text'            => $text,
            'use_browser_tts' => true,
            'language'        => $language,
        ];
    }

    /**
     * Test API connection with a minimal request
     *
     * @return array ['success' => bool, 'model' => string, 'error' => string]
     */
    public function test_connection(): array
    {
        try {
            $result = $this->chat(
                [['role' => 'user', 'parts' => [['text' => 'Hello']]]],
                'You are a test assistant. Reply with exactly: "Connection successful."'
            );

            return [
                'success' => true,
                'model'   => $this->model,
                'error'   => '',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'model'   => $this->model,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // ── Private Methods ───────────────────────────────────────────────────────

    /**
     * Execute an HTTP request with exponential backoff retry
     *
     * @param  string $method  POST|GET
     * @param  string $url
     * @param  array  $body
     * @return array  Decoded JSON response
     * @throws RuntimeException
     */
    private function request_with_retry(string $method, string $url, array $body = []): array
    {
        $last_error  = '';
        $retry_delay = 1;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                sleep($retry_delay);
                $retry_delay = min($retry_delay * 2, 16);
            }

            try {
                $response = $this->make_request($method, $url, $body);

                // Check for rate limit — retry
                if (($response['_status_code'] ?? 200) === 429) {
                    $last_error = 'Rate limit hit. Retrying...';
                    continue;
                }

                // Check for server errors — retry
                $status = $response['_status_code'] ?? 200;
                if ($status >= 500) {
                    $last_error = "Server error {$status}. Retrying...";
                    continue;
                }

                // Check for API-level error
                if (isset($response['error'])) {
                    $msg = $response['error']['message'] ?? 'Unknown API error';
                    $code = $response['error']['code'] ?? $status;

                    // 4xx errors are not retryable
                    if ($code >= 400 && $code < 500) {
                        throw new RuntimeException("Gemini API error ({$code}): {$msg}");
                    }

                    $last_error = "API error {$code}: {$msg}";
                    continue;
                }

                unset($response['_status_code']);
                return $response;

            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable $e) {
                $last_error = $e->getMessage();

                if ($attempt === self::MAX_RETRIES) {
                    break;
                }
            }
        }

        throw new RuntimeException("Gemini API failed after " . self::MAX_RETRIES . " retries: {$last_error}");
    }

    /**
     * Execute a single cURL HTTP request
     */
    private function make_request(string $method, string $url, array $body): array
    {
        $json_body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: PerfexCRM-AIAssistant/' . AI_ASSISTANT_VERSION,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
        }

        $raw      = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("cURL error: {$curl_err}");
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON response from Gemini API (HTTP {$status}).");
        }

        $decoded['_status_code'] = $status;
        return $decoded;
    }

    /**
     * Parse Gemini API response into a normalized structure
     */
    private function parse_response(array $response): array
    {
        $candidate = $response['candidates'][0] ?? null;

        if (!$candidate) {
            $block_reason = $response['promptFeedback']['blockReason'] ?? '';
            if ($block_reason) {
                throw new RuntimeException("Request blocked by Gemini safety filters: {$block_reason}");
            }
            throw new RuntimeException('Empty response from Gemini API.');
        }

        $content    = '';
        $tool_calls = null;
        $parts      = $candidate['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }

            if (isset($part['functionCall'])) {
                $tool_calls = $part['functionCall'];
            }
        }

        $usage       = $response['usageMetadata'] ?? [];
        $tokens_used = ($usage['candidatesTokenCount'] ?? 0) + ($usage['promptTokenCount'] ?? 0);

        return [
            'content'     => trim($content),
            'tool_calls'  => $tool_calls,
            'tokens_used' => (int)$tokens_used,
            'model'       => $this->model,
            'finish_reason' => $candidate['finishReason'] ?? 'STOP',
        ];
    }

    /**
     * Convert internal tool config format to Gemini function declarations
     */
    private function format_tool_declarations(array $tools): array
    {
        $declarations = [];

        foreach ($tools as $tool) {
            $declarations[] = [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'parameters'  => $tool['parameters'],
            ];
        }

        return $declarations;
    }
}
