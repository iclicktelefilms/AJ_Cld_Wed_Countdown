<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Claude Provider (Anthropic)
 *
 * Implements the Anthropic Messages API.
 * Claude uses a distinct API schema with different tool calling format.
 */
class ClaudeProvider extends BaseProvider
{
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const BETA_HEADER       = 'tools-2024-04-04';

    public function get_name(): string { return 'claude'; }

    protected function get_default_base_url(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    protected function get_default_model(): string
    {
        return 'claude-sonnet-4-5';
    }

    // ── Chat ─────────────────────────────────────────────────────────────────

    public function chat(array $messages, string $system_prompt = '', array $tools = []): array
    {
        if (empty($this->api_key)) {
            throw new RuntimeException('Claude (Anthropic) API key is not configured.');
        }

        $url = "{$this->base_url}/messages";

        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages'   => $this->format_messages($messages),
        ];

        if (!empty($system_prompt)) {
            $body['system'] = $system_prompt;
        }

        if (!empty($tools)) {
            $body['tools'] = $this->format_tools($tools);
        }

        $headers = [
            "x-api-key: {$this->api_key}",
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
            'anthropic-beta: ' . self::BETA_HEADER,
        ];

        $response = $this->request('POST', $url, $body, $headers);
        return $this->parse_response($response);
    }

    // ── Format conversion ─────────────────────────────────────────────────────

    protected function format_messages(array $messages): array
    {
        $formatted  = [];
        $prev_role  = null;

        foreach ($messages as $msg) {
            $role = $msg['role'];

            if ($role === 'user') {
                $formatted[] = ['role' => 'user', 'content' => $msg['content']];
                $prev_role = 'user';
                continue;
            }

            if ($role === 'assistant') {
                if (!empty($msg['tool_call'])) {
                    $formatted[] = [
                        'role'    => 'assistant',
                        'content' => [
                            ['type' => 'text', 'text' => $msg['content'] ?? ''],
                            [
                                'type'  => 'tool_use',
                                'id'    => 'toolu_' . substr(md5($msg['tool_call']['name'] . uniqid()), 0, 10),
                                'name'  => $msg['tool_call']['name'],
                                'input' => $msg['tool_call']['args'] ?? [],
                            ],
                        ],
                    ];
                } else {
                    $formatted[] = ['role' => 'assistant', 'content' => $msg['content']];
                }
                $prev_role = 'assistant';
                continue;
            }

            if ($role === 'tool') {
                // Claude requires tool results as user messages with tool_result content
                $result_content = is_array($msg['result'])
                    ? json_encode($msg['result'])
                    : (string)($msg['result'] ?? '');

                // Find the matching tool_use id from previous assistant message
                $tool_use_id = 'toolu_' . substr(md5($msg['tool_name']), 0, 10);

                $formatted[] = [
                    'role'    => 'user',
                    'content' => [[
                        'type'         => 'tool_result',
                        'tool_use_id'  => $tool_use_id,
                        'content'      => $result_content,
                    ]],
                ];
                $prev_role = 'user';
                continue;
            }
        }

        // Claude requires alternating user/assistant — merge consecutive same-role messages
        return $this->merge_consecutive_roles($formatted);
    }

    protected function format_tools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $formatted[] = [
                'name'         => $tool['name'],
                'description'  => $tool['description'],
                'input_schema' => $tool['parameters'],  // Claude uses input_schema
            ];
        }
        return $formatted;
    }

    protected function parse_response(array $response): array
    {
        if (!empty($response['error'])) {
            throw new RuntimeException(
                "Claude API error ({$response['error']['type']}): {$response['error']['message']}"
            );
        }

        $content    = '';
        $tool_calls = null;
        $blocks     = $response['content'] ?? [];

        foreach ($blocks as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
            if ($block['type'] === 'tool_use') {
                $tool_calls = [
                    'name' => $block['name'],
                    'args' => $block['input'] ?? [],
                ];
            }
        }

        $usage       = $response['usage'] ?? [];
        $tokens_used = (int)(($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0));

        return [
            'content'      => trim($content),
            'tool_calls'   => $tool_calls,
            'tokens_used'  => $tokens_used,
            'model'        => $response['model'] ?? $this->model,
            'finish_reason'=> $response['stop_reason'] ?? 'end_turn',
            'provider'     => 'claude',
        ];
    }

    // ── Claude-specific auth ──────────────────────────────────────────────────

    protected function get_auth_header(): string
    {
        return "x-api-key: {$this->api_key}";
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Merge consecutive messages of the same role.
     * Claude requires strict user/assistant alternation.
     */
    private function merge_consecutive_roles(array $messages): array
    {
        if (empty($messages)) return [];

        $merged = [$messages[0]];

        for ($i = 1; $i < count($messages); $i++) {
            $current  = $messages[$i];
            $previous = &$merged[count($merged) - 1];

            if ($current['role'] === $previous['role']) {
                // Merge content
                if (is_string($previous['content']) && is_string($current['content'])) {
                    $previous['content'] .= "\n" . $current['content'];
                } elseif (is_array($previous['content']) && is_string($current['content'])) {
                    $previous['content'][] = ['type' => 'text', 'text' => $current['content']];
                } elseif (is_string($previous['content']) && is_array($current['content'])) {
                    $previous['content'] = array_merge(
                        [['type' => 'text', 'text' => $previous['content']]],
                        $current['content']
                    );
                } else {
                    $previous['content'] = array_merge(
                        (array)$previous['content'],
                        (array)$current['content']
                    );
                }
            } else {
                $merged[] = $current;
            }
        }

        return $merged;
    }
}
