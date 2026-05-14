<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpenRouter Provider
 *
 * Extends OpenAIProvider — OpenRouter exposes an OpenAI-compatible API
 * with access to 200+ models across multiple providers.
 *
 * Differences from OpenAI:
 * - Different base URL
 * - Requires HTTP-Referer and X-Title headers (for analytics/routing)
 * - Model IDs include provider prefix (e.g. "openai/gpt-4o")
 */
class OpenRouterProvider extends OpenAIProvider
{
    public function get_name(): string { return 'openrouter'; }

    protected function get_default_base_url(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    protected function get_default_model(): string
    {
        return 'openai/gpt-4o';
    }

    /**
     * OpenRouter requires site metadata headers
     */
    protected function extra_headers(): array
    {
        $site_url  = base_url();
        $site_name = get_setting('companyname') ?: 'Perfex CRM';

        return [
            "HTTP-Referer: {$site_url}",
            "X-Title: {$site_name}",
        ];
    }

    /**
     * OpenRouter doesn't support Whisper transcription — use Gemini fallback
     */
    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string
    {
        throw new RuntimeException('OpenRouter does not support audio transcription. Configure Gemini or OpenAI as the audio provider.');
    }

    public function text_to_speech(string $text, string $language = 'en-US'): array
    {
        return parent::text_to_speech($text, $language);
    }
}
