<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Gemini Provider (Google)
 *
 * Implements AIProviderInterface for the Gemini API.
 * Handles format conversion between the internal message format
 * and Gemini's `contents` / `functionCall` / `functionResponse` structures.
 */
class GeminiProvider extends BaseProvider
{
    public function get_name(): string { return 'gemini'; }

    protected function get_default_base_url(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    protected function get_default_model(): string
    {
        return 'gemini-2.5-pro';
    }

    // ── Chat ─────────────────────────────────────────────────────────────────

    public function chat(array $messages, string $system_prompt = '', array $tools = []): array
    {
        if (empty($this->api_key)) {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $url = "{$this->base_url}/models/{$this->model}:generateContent?key=" . urlencode($this->api_key);

        $body = [
            'contents'         => $this->format_messages($messages),
            'generationConfig' => [
                'temperature'     => $this->temperature,
                'maxOutputTokens' => $this->max_tokens,
            ],
        ];

        if (!empty($system_prompt)) {
            $body['systemInstruction'] = ['parts' => [['text' => $system_prompt]]];
        }

        if (!empty($tools)) {
            $body['tools']      = [['functionDeclarations' => $this->format_tools($tools)]];
            $body['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }

        $response = $this->request('POST', $url, $body);
        return $this->parse_response($response);
    }

    // ── Audio ─────────────────────────────────────────────────────────────────

    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string
    {
        $url = "{$this->base_url}/models/gemini-2.5-flash:generateContent?key=" . urlencode($this->api_key);

        $instruction = match($language_hint) {
            'hi'    => 'Transcribe the following audio exactly. The speaker may use Hindi or Hinglish. Provide the exact transcript in the original language.',
            'en'    => 'Transcribe the following audio in English.',
            default => 'Transcribe the following audio exactly. The speaker may use Hindi, English, or a mix (Hinglish). Provide the exact transcript.',
        };

        $body = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['text' => $instruction],
                    ['inlineData' => ['mimeType' => $mime_type, 'data' => $audio_base64]],
                ],
            ]],
            'generationConfig' => ['temperature' => 0.0, 'maxOutputTokens' => 2048],
        ];

        $response = $this->request('POST', $url, $body);
        $parsed   = $this->parse_response($response);
        return trim($parsed['content'] ?? '');
    }

    public function text_to_speech(string $text, string $language = 'en-US'): array
    {
        $url        = "{$this->base_url}/models/gemini-2.5-flash-preview-tts:generateContent?key=" . urlencode($this->api_key);
        $voice_name = str_starts_with($language, 'hi') ? 'hi-IN-Standard-A' : 'en-US-Standard-C';
        $speak_text = mb_substr(strip_tags($text), 0, 500);

        $body = [
            'contents'         => [['parts' => [['text' => $speak_text]]]],
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
            $response   = $this->request('POST', $url, $body);
            $audio_data = $response['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;

            if ($audio_data) {
                return [
                    'audio_base64'    => $audio_data,
                    'use_browser_tts' => false,
                    'text'            => $speak_text,
                    'mime_type'       => 'audio/wav',
                    'language'        => $language,
                ];
            }
        } catch (Throwable $e) {
            // Fall through to browser TTS
        }

        return [
            'audio_base64'    => null,
            'use_browser_tts' => true,
            'text'            => $speak_text,
            'language'        => $language,
            'mime_type'       => null,
        ];
    }

    // ── Format conversion ─────────────────────────────────────────────────────

    protected function format_messages(array $messages): array
    {
        $contents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'];

            if ($role === 'user') {
                $contents[] = ['role' => 'user', 'parts' => [['text' => $msg['content']]]];
                continue;
            }

            if ($role === 'assistant') {
                if (!empty($msg['tool_call'])) {
                    $contents[] = [
                        'role'  => 'model',
                        'parts' => [
                            ['text' => $msg['content'] ?? ''],
                            ['functionCall' => [
                                'name' => $msg['tool_call']['name'],
                                'args' => $msg['tool_call']['args'] ?? [],
                            ]],
                        ],
                    ];
                } else {
                    $contents[] = ['role' => 'model', 'parts' => [['text' => $msg['content']]]];
                }
                continue;
            }

            if ($role === 'tool') {
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name'     => $msg['tool_name'],
                            'response' => $msg['result'] ?? [],
                        ],
                    ]],
                ];
                continue;
            }
        }

        return $contents;
    }

    protected function format_tools(array $tools): array
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

    protected function parse_response(array $response): array
    {
        if (isset($response['promptFeedback']['blockReason'])) {
            throw new RuntimeException('Gemini safety filter blocked: ' . $response['promptFeedback']['blockReason']);
        }

        $candidate = $response['candidates'][0] ?? null;
        if (!$candidate) {
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
                $tool_calls = [
                    'name' => $part['functionCall']['name'],
                    'args' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        $usage       = $response['usageMetadata'] ?? [];
        $tokens_used = (int)(($usage['candidatesTokenCount'] ?? 0) + ($usage['promptTokenCount'] ?? 0));

        return [
            'content'      => trim($content),
            'tool_calls'   => $tool_calls,
            'tokens_used'  => $tokens_used,
            'model'        => $this->model,
            'finish_reason'=> $candidate['finishReason'] ?? 'STOP',
            'provider'     => 'gemini',
        ];
    }

    // Gemini uses URL-param auth, not Bearer header
    protected function get_auth_header(): string { return ''; }
}
