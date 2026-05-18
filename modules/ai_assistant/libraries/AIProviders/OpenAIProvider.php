<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpenAI Provider
 *
 * Implements the OpenAI Chat Completions API (/v1/chat/completions).
 * Also serves as the base for OpenRouter and Ollama (both OpenAI-compatible).
 */
class OpenAIProvider extends BaseProvider
{
    public function get_name(): string { return 'openai'; }

    protected function get_default_base_url(): string
    {
        return 'https://api.openai.com/v1';
    }

    protected function get_default_model(): string
    {
        return 'gpt-4o';
    }

    // ── Chat ─────────────────────────────────────────────────────────────────

    public function chat(array $messages, string $system_prompt = '', array $tools = []): array
    {
        if (empty($this->api_key) && $this->get_name() !== 'ollama') {
            throw new RuntimeException(ucfirst($this->get_name()) . ' API key is not configured.');
        }

        $url = "{$this->base_url}/chat/completions";

        $formatted = $this->format_messages($messages);

        if (!empty($system_prompt)) {
            array_unshift($formatted, ['role' => 'system', 'content' => $system_prompt]);
        }

        $body = [
            'model'       => $this->model,
            'messages'    => $formatted,
            'temperature' => $this->temperature,
            'max_tokens'  => $this->max_tokens,
        ];

        if (!empty($tools)) {
            $body['tools']       = $this->format_tools($tools);
            $body['tool_choice'] = 'auto';
        }

        $response = $this->request('POST', $url, $body, $this->extra_headers());
        return $this->parse_response($response);
    }

    // ── Audio ─────────────────────────────────────────────────────────────────

    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string
    {
        $url = "{$this->base_url}/audio/transcriptions";

        // OpenAI Whisper expects multipart/form-data
        $audio_data   = base64_decode($audio_base64);
        $tmp_file     = tempnam(sys_get_temp_dir(), 'ai_audio_');
        $ext          = $this->mime_to_ext($mime_type);
        $tmp_with_ext = $tmp_file . '.' . $ext;
        file_put_contents($tmp_with_ext, $audio_data);

        $ch = curl_init();
        $post_fields = [
            'file'  => new CURLFile($tmp_with_ext, $mime_type, 'audio.' . $ext),
            'model' => 'whisper-1',
        ];

        if (!empty($language_hint)) {
            $post_fields['language'] = $language_hint;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->api_key],
        ]);

        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Cleanup temp files
        @unlink($tmp_file);
        @unlink($tmp_with_ext);

        if ($raw === false || $code >= 400) {
            throw new RuntimeException("OpenAI transcription failed (HTTP {$code}).");
        }

        $decoded = json_decode($raw, true);
        return trim($decoded['text'] ?? '');
    }

    public function text_to_speech(string $text, string $language = 'en-US'): array
    {
        $url        = "{$this->base_url}/audio/speech";
        $speak_text = mb_substr(strip_tags($text), 0, 4096);
        $voice      = (strpos($language, 'hi') === 0) ? 'onyx' : 'alloy';

        $body = [
            'model' => 'tts-1',
            'input' => $speak_text,
            'voice' => $voice,
        ];

        // OpenAI TTS returns raw audio bytes, not JSON
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
            ],
        ]);

        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw && $code < 400 && !$this->is_json($raw)) {
            return [
                'audio_base64'    => base64_encode($raw),
                'use_browser_tts' => false,
                'text'            => $speak_text,
                'mime_type'       => 'audio/mpeg',
                'language'        => $language,
            ];
        }

        // Fallback to browser TTS
        return parent::text_to_speech($text, $language);
    }

    // ── Format conversion ─────────────────────────────────────────────────────

    protected function format_messages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $msg) {
            $role = $msg['role'];

            if ($role === 'user') {
                $formatted[] = ['role' => 'user', 'content' => $msg['content']];
                continue;
            }

            if ($role === 'assistant') {
                if (!empty($msg['tool_call'])) {
                    $formatted[] = [
                        'role'       => 'assistant',
                        'content'    => $msg['content'] ?? null,
                        'tool_calls' => [[
                            'id'       => 'call_' . substr(md5(uniqid()), 0, 8),
                            'type'     => 'function',
                            'function' => [
                                'name'      => $msg['tool_call']['name'],
                                'arguments' => json_encode($msg['tool_call']['args'] ?? []),
                            ],
                        ]],
                    ];
                } else {
                    $formatted[] = ['role' => 'assistant', 'content' => $msg['content']];
                }
                continue;
            }

            if ($role === 'tool') {
                $formatted[] = [
                    'role'         => 'tool',
                    'tool_call_id' => 'call_' . substr(md5($msg['tool_name']), 0, 8),
                    'content'      => is_array($msg['result'])
                        ? json_encode($msg['result'])
                        : (string)($msg['result'] ?? ''),
                ];
                continue;
            }
        }

        return $formatted;
    }

    protected function format_tools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $formatted[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $tool['name'],
                    'description' => $tool['description'],
                    'parameters'  => $tool['parameters'],
                ],
            ];
        }
        return $formatted;
    }

    protected function parse_response(array $response): array
    {
        $choice = $response['choices'][0] ?? null;
        if (!$choice) {
            throw new RuntimeException("Empty response from {$this->get_name()} API.");
        }

        $message    = $choice['message'] ?? [];
        $content    = $message['content'] ?? '';
        $tool_calls = null;

        if (!empty($message['tool_calls'])) {
            $tc         = $message['tool_calls'][0]; // Handle first tool call
            $tool_calls = [
                'name' => $tc['function']['name'],
                'args' => json_decode($tc['function']['arguments'] ?? '{}', true) ?: [],
            ];
        }

        $usage       = $response['usage'] ?? [];
        $tokens_used = (int)(($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0));

        return [
            'content'      => trim((string)$content),
            'tool_calls'   => $tool_calls,
            'tokens_used'  => $tokens_used,
            'model'        => $response['model'] ?? $this->model,
            'finish_reason'=> $choice['finish_reason'] ?? 'stop',
            'provider'     => $this->get_name(),
        ];
    }

    // ── Subclass hooks ────────────────────────────────────────────────────────

    /** Extra HTTP headers (override in subclasses) */
    protected function extra_headers(): array
    {
        return [];
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    private function mime_to_ext(string $mime): string
    {
        if ($mime === 'audio/webm') return 'webm';
        if ($mime === 'audio/mp4')  return 'mp4';
        if ($mime === 'audio/mpeg') return 'mp3';
        if ($mime === 'audio/ogg')  return 'ogg';
        if ($mime === 'audio/wav')  return 'wav';
        if ($mime === 'audio/x-m4a') return 'm4a';
        return 'webm';
    }

    private function is_json(string $str): bool
    {
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
