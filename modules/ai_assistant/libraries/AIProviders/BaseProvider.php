<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Contracts/AIProviderInterface.php';
require_once __DIR__ . '/ProviderCapabilities.php';

/**
 * Base Provider
 *
 * Shared HTTP transport, retry logic, settings loading, and
 * helper utilities for all provider implementations.
 * Provider-specific subclasses handle format conversion only.
 */
abstract class BaseProvider implements AIProviderInterface
{
    protected const MAX_RETRIES = 3;
    protected const TIMEOUT     = 90;
    protected const CONNECT_TO  = 15;

    protected string $api_key;
    protected string $model;
    protected float  $temperature;
    protected int    $max_tokens;
    protected string $base_url;
    protected array  $settings;

    public function __construct(array $settings = [])
    {
        $this->settings    = $settings ?: $this->load_settings();
        $this->api_key     = $this->settings['api_key']     ?? '';
        $this->model       = $this->settings['model']       ?? $this->get_default_model();
        $this->temperature = (float)($this->settings['temperature'] ?? 0.7);
        $this->max_tokens  = (int)($this->settings['max_tokens']    ?? 8192);
        $this->base_url    = rtrim($this->settings['base_url'] ?? $this->get_default_base_url(), '/');
    }

    // ── Abstract methods each provider must implement ────────────────────────

    abstract protected function get_default_base_url(): string;
    abstract protected function get_default_model(): string;
    abstract protected function format_messages(array $messages): array;
    abstract protected function format_tools(array $tools): array;
    abstract protected function parse_response(array $response): array;

    // ── Shared audio stubs (providers override if supported) ─────────────────

    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string
    {
        throw new RuntimeException(get_class($this) . ' does not support audio transcription.');
    }

    public function text_to_speech(string $text, string $language = 'en-US'): array
    {
        // Default: instruct frontend to use browser TTS
        return [
            'audio_base64'    => null,
            'use_browser_tts' => true,
            'text'            => mb_substr(strip_tags($text), 0, 500),
            'language'        => $language,
            'mime_type'       => null,
        ];
    }

    // ── Shared HTTP transport ────────────────────────────────────────────────

    /**
     * Execute an HTTP request with exponential backoff retry.
     *
     * @param  string $method   POST|GET
     * @param  string $url
     * @param  array  $body     Request body (will be JSON-encoded)
     * @param  array  $headers  Additional headers
     * @return array  Decoded JSON response
     * @throws RuntimeException on unrecoverable failure
     */
    protected function request(string $method, string $url, array $body = [], array $headers = []): array
    {
        $last_error  = '';
        $retry_delay = 1;
        $default_headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: PerfexCRM-AIAssistant/' . AI_ASSISTANT_VERSION,
        ];

        if (!empty($this->api_key)) {
            $default_headers[] = $this->get_auth_header();
        }

        $all_headers = array_merge($default_headers, $headers);

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                sleep($retry_delay);
                $retry_delay = min($retry_delay * 2, 16);
            }

            try {
                [$decoded, $status] = $this->curl($method, $url, $body, $all_headers);

                // Rate limit → retry
                if ($status === 429) {
                    $last_error = 'Rate limit. Retrying...';
                    continue;
                }

                // Server error → retry
                if ($status >= 500) {
                    $last_error = "Server error {$status}. Retrying...";
                    continue;
                }

                // 4xx errors are caller errors — do not retry
                if ($status >= 400) {
                    $msg = $this->extract_error_message($decoded) ?: "HTTP {$status}";
                    throw new RuntimeException("{$this->get_name()} API error ({$status}): {$msg}");
                }

                return $decoded;

            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable $e) {
                $last_error = $e->getMessage();
                if ($attempt === self::MAX_RETRIES) break;
            }
        }

        throw new RuntimeException(
            "{$this->get_name()} API failed after " . self::MAX_RETRIES . " retries: {$last_error}"
        );
    }

    /**
     * Execute one cURL request.
     *
     * @return array  [decoded_body, http_status_code]
     */
    private function curl(string $method, string $url, array $body, array $headers): array
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TO,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
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
            throw new RuntimeException("Non-JSON response from {$this->get_name()} API (HTTP {$status}).");
        }

        return [$decoded, $status];
    }

    /**
     * Return provider-specific Authorization header value.
     * Subclasses override if they use a non-Bearer scheme.
     */
    protected function get_auth_header(): string
    {
        return "Authorization: Bearer {$this->api_key}";
    }

    /**
     * Extract error message from any provider's error response shape.
     */
    protected function extract_error_message(array $response): string
    {
        // OpenAI / OpenRouter / Ollama shape
        if (isset($response['error']['message'])) {
            return $response['error']['message'];
        }
        // Gemini shape
        if (isset($response['error']['status'])) {
            return $response['error']['status'];
        }
        // Claude shape
        if (isset($response['error']['type'])) {
            return $response['error']['type'] . ': ' . ($response['error']['message'] ?? '');
        }
        return '';
    }

    // ── Shared settings loader ───────────────────────────────────────────────

    protected function load_settings(): array
    {
        $provider = $this->get_name();
        return [
            'api_key'     => get_option("ai_assistant_{$provider}_api_key"),
            'model'       => get_option("ai_assistant_{$provider}_model") ?: $this->get_default_model(),
            'base_url'    => get_option("ai_assistant_{$provider}_base_url") ?: $this->get_default_base_url(),
            'temperature' => (float)(get_option('ai_assistant_temperature') ?: 0.7),
            'max_tokens'  => (int)(get_option('ai_assistant_max_tokens')    ?: 8192),
        ];
    }

    // ── Connection test ──────────────────────────────────────────────────────

    public function test_connection(): array
    {
        $start = microtime(true);
        try {
            $result = $this->chat(
                [['role' => 'user', 'content' => 'Reply with exactly: OK']],
                'You are a test assistant.'
            );
            return [
                'success'    => true,
                'model'      => $this->model,
                'error'      => '',
                'latency_ms' => (int)((microtime(true) - $start) * 1000),
                'provider'   => $this->get_name(),
            ];
        } catch (Throwable $e) {
            return [
                'success'    => false,
                'model'      => $this->model,
                'error'      => $e->getMessage(),
                'latency_ms' => (int)((microtime(true) - $start) * 1000),
                'provider'   => $this->get_name(),
            ];
        }
    }

    // ── Getters ──────────────────────────────────────────────────────────────

    public function get_supported_models(): array
    {
        return ProviderCapabilities::models($this->get_name());
    }

    public function get_capabilities(): array
    {
        return ProviderCapabilities::capabilities($this->get_name());
    }

    public function get_current_model(): string
    {
        return $this->model;
    }
}
