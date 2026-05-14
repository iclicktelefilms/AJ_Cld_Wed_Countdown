<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Provider Capabilities Registry
 *
 * Single source of truth for what each supported provider can do,
 * which models it exposes, and metadata used in the settings UI.
 */
class ProviderCapabilities
{
    /** @var array Provider metadata keyed by slug */
    private static array $registry = [

        'gemini' => [
            'label'        => 'Gemini',
            'company'      => 'Google',
            'description'  => 'Best cost/performance balance. Large context window, strong multilingual support, fast function calling.',
            'requires_key' => true,
            'requires_url' => false,
            'default_url'  => 'https://generativelanguage.googleapis.com/v1beta',
            'capabilities' => ['chat', 'tool_calling', 'streaming', 'vision', 'audio', 'tts'],
            'models'       => [
                ['id' => 'gemini-2.5-pro',   'label' => 'Gemini 2.5 Pro (Most capable)'],
                ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash (Fast & efficient)'],
            ],
            'default_model'    => 'gemini-2.5-pro',
            'key_placeholder'  => 'AIza...',
            'url_placeholder'  => '',
            'docs_url'         => 'https://aistudio.google.com',
        ],

        'openai' => [
            'label'        => 'OpenAI',
            'company'      => 'OpenAI',
            'description'  => 'Best-in-class function/tool calling. Industry standard. GPT models.',
            'requires_key' => true,
            'requires_url' => false,
            'default_url'  => 'https://api.openai.com/v1',
            'capabilities' => ['chat', 'tool_calling', 'streaming', 'vision', 'audio', 'tts'],
            'models'       => [
                ['id' => 'gpt-5',       'label' => 'GPT-5 (Most capable)'],
                ['id' => 'gpt-5-mini',  'label' => 'GPT-5 Mini (Fast & efficient)'],
                ['id' => 'gpt-4o',      'label' => 'GPT-4o'],
                ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o Mini'],
            ],
            'default_model'    => 'gpt-5',
            'key_placeholder'  => 'sk-...',
            'url_placeholder'  => '',
            'docs_url'         => 'https://platform.openai.com',
        ],

        'claude' => [
            'label'        => 'Claude',
            'company'      => 'Anthropic',
            'description'  => 'Strong reasoning and coding. Excellent for complex CRM analysis and report generation.',
            'requires_key' => true,
            'requires_url' => false,
            'default_url'  => 'https://api.anthropic.com/v1',
            'capabilities' => ['chat', 'tool_calling', 'streaming', 'vision'],
            'models'       => [
                ['id' => 'claude-opus-4-5',   'label' => 'Claude Opus 4.5 (Most capable)'],
                ['id' => 'claude-sonnet-4-5', 'label' => 'Claude Sonnet 4.5 (Balanced)'],
                ['id' => 'claude-haiku-4-5',  'label' => 'Claude Haiku 4.5 (Fast)'],
            ],
            'default_model'    => 'claude-sonnet-4-5',
            'key_placeholder'  => 'sk-ant-...',
            'url_placeholder'  => '',
            'docs_url'         => 'https://console.anthropic.com',
        ],

        'openrouter' => [
            'label'        => 'OpenRouter',
            'company'      => 'OpenRouter',
            'description'  => 'Access 200+ models (OpenAI, Claude, Gemini, Llama) through a single API key. Best flexibility.',
            'requires_key' => true,
            'requires_url' => false,
            'default_url'  => 'https://openrouter.ai/api/v1',
            'capabilities' => ['chat', 'tool_calling', 'streaming', 'vision'],
            'models'       => [],  // User-defined
            'default_model'    => 'openai/gpt-4o',
            'key_placeholder'  => 'sk-or-...',
            'url_placeholder'  => '',
            'docs_url'         => 'https://openrouter.ai',
            'model_is_free_text' => true,
            'model_hint'         => 'e.g. openai/gpt-4o, anthropic/claude-3-5-sonnet, google/gemini-2.5-flash',
        ],

        'ollama' => [
            'label'        => 'Ollama',
            'company'      => 'Ollama',
            'description'  => 'Run AI locally for maximum privacy and zero API cost. Requires Ollama running on your server.',
            'requires_key' => false,
            'requires_url' => true,
            'default_url'  => 'http://localhost:11434',
            'capabilities' => ['chat', 'tool_calling', 'streaming'],
            'models'       => [],  // User-defined (local models)
            'default_model'    => 'llama3.2',
            'key_placeholder'  => '(not required)',
            'url_placeholder'  => 'http://localhost:11434',
            'docs_url'         => 'https://ollama.com',
            'model_is_free_text' => true,
            'model_hint'         => 'e.g. llama3.2, mistral, qwen2.5-coder',
        ],
    ];

    /**
     * Get full metadata for a provider
     *
     * @param  string $provider  Provider slug
     * @return array|null
     */
    public static function get(string $provider): ?array
    {
        return self::$registry[$provider] ?? null;
    }

    /**
     * Get all registered providers as an ordered list
     *
     * @return array  Keyed by slug
     */
    public static function all(): array
    {
        return self::$registry;
    }

    /**
     * Get capabilities for a provider
     *
     * @param  string $provider
     * @return string[]
     */
    public static function capabilities(string $provider): array
    {
        return self::$registry[$provider]['capabilities'] ?? [];
    }

    /**
     * Check if provider supports a specific capability
     *
     * @param  string $provider
     * @param  string $capability  chat|tool_calling|streaming|vision|audio|tts
     * @return bool
     */
    public static function supports(string $provider, string $capability): bool
    {
        return in_array($capability, self::capabilities($provider), true);
    }

    /**
     * Get recommended models for a provider
     *
     * @param  string $provider
     * @return array  [['id' => string, 'label' => string], ...]
     */
    public static function models(string $provider): array
    {
        return self::$registry[$provider]['models'] ?? [];
    }

    /**
     * Get default model ID for a provider
     *
     * @param  string $provider
     * @return string
     */
    public static function default_model(string $provider): string
    {
        return self::$registry[$provider]['default_model'] ?? '';
    }

    /**
     * Returns the best provider for audio transcription
     * (prioritising providers with native audio support)
     *
     * @param  string $active_provider  Currently active chat provider
     * @return string  Provider slug to use for transcription
     */
    public static function best_audio_provider(string $active_provider): string
    {
        // Prefer same provider if it supports audio
        if (self::supports($active_provider, 'audio')) {
            return $active_provider;
        }
        // Fallback preference order
        foreach (['gemini', 'openai'] as $fallback) {
            if (self::supports($fallback, 'audio')) {
                return $fallback;
            }
        }
        return $active_provider;
    }

    /**
     * Check if provider slug is valid
     *
     * @param  string $provider
     * @return bool
     */
    public static function is_valid(string $provider): bool
    {
        return isset(self::$registry[$provider]);
    }
}
