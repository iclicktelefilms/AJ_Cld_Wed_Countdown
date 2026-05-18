<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ollama Provider (Local AI)
 *
 * Extends OpenAIProvider — Ollama exposes an OpenAI-compatible API
 * at a user-configurable local endpoint.
 *
 * Differences from OpenAI:
 * - No API key required
 * - Configurable base URL (default: http://localhost:11434)
 * - Model names are local model names (e.g. llama3.2, mistral)
 * - No audio/TTS support
 * - Function calling support depends on the model
 */
class OllamaProvider extends OpenAIProvider
{
    public function get_name(): string { return 'ollama'; }

    protected function get_default_base_url(): string
    {
        return 'http://localhost:11434';
    }

    protected function get_default_model(): string
    {
        return 'llama3.2';
    }

    protected function load_settings(): array
    {
        return [
            'api_key'     => '',  // Ollama doesn't require a key
            'model'       => get_option('ai_assistant_ollama_model')    ?: $this->get_default_model(),
            'base_url'    => get_option('ai_assistant_ollama_base_url') ?: $this->get_default_base_url(),
            'temperature' => (float)(get_option('ai_assistant_temperature') ?: 0.7),
            'max_tokens'  => (int)(get_option('ai_assistant_max_tokens')    ?: 4096),
        ];
    }

    /**
     * Ollama's OpenAI-compatible endpoint is at /v1
     */
    public function chat(array $messages, string $system_prompt = '', array $tools = []): array
    {
        // Ensure base_url ends with /v1 for OpenAI-compatible endpoint
        $original_url = $this->base_url;
        if (substr($this->base_url, -3) !== '/v1') {
            $this->base_url = rtrim($this->base_url, '/') . '/v1';
        }

        try {
            return parent::chat($messages, $system_prompt, $tools);
        } finally {
            $this->base_url = $original_url;
        }
    }

    public function test_connection(): array
    {
        // Test if Ollama is running by checking /api/tags
        $start  = microtime(true);
        $base   = rtrim($this->base_url, '/');
        if (substr($base, -3) === '/v1') {
            $base = substr($base, 0, -3);
        }

        $ch = curl_init($base . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latency = (int)((microtime(true) - $start) * 1000);

        if ($code === 200 && $raw) {
            $data   = json_decode($raw, true);
            $models = array_column($data['models'] ?? [], 'name');
            return [
                'success'       => true,
                'model'         => $this->model,
                'error'         => '',
                'latency_ms'    => $latency,
                'provider'      => 'ollama',
                'available_models' => $models,
            ];
        }

        return [
            'success'    => false,
            'model'      => $this->model,
            'error'      => "Ollama not reachable at {$this->base_url} (HTTP {$code}). Is Ollama running?",
            'latency_ms' => $latency,
            'provider'   => 'ollama',
        ];
    }

    // No auth header needed for Ollama
    protected function get_auth_header(): string { return ''; }

    // Ollama doesn't support audio
    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string
    {
        throw new RuntimeException('Ollama does not support audio transcription. Configure Gemini or OpenAI for voice features.');
    }
}
