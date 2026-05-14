<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Provider Factory
 *
 * Single entry point for instantiating AI providers.
 * Reads active provider from settings and returns the correct implementation.
 */
class ProviderFactory
{
    /** Maps provider slugs to their class files and class names */
    private static array $provider_map = [
        'gemini'      => ['file' => 'GeminiProvider.php',      'class' => 'GeminiProvider'],
        'openai'      => ['file' => 'OpenAIProvider.php',       'class' => 'OpenAIProvider'],
        'claude'      => ['file' => 'ClaudeProvider.php',       'class' => 'ClaudeProvider'],
        'openrouter'  => ['file' => 'OpenRouterProvider.php',   'class' => 'OpenRouterProvider'],
        'ollama'      => ['file' => 'OllamaProvider.php',       'class' => 'OllamaProvider'],
    ];

    /**
     * Create and return the active AI provider instance.
     *
     * @param  array|null $settings  Override settings (null = load from DB)
     * @param  string     $provider  Override provider slug (null = read from DB)
     * @return AIProviderInterface
     * @throws RuntimeException if provider is unknown or file missing
     */
    public static function create(?array $settings = null, string $provider = ''): AIProviderInterface
    {
        if (empty($provider)) {
            $provider = get_option('ai_assistant_active_provider') ?: 'gemini';
        }

        return self::make($provider, $settings);
    }

    /**
     * Create a provider guaranteed to support audio transcription.
     * Falls back to Gemini if active provider doesn't have audio capability.
     *
     * @return AIProviderInterface
     */
    public static function create_for_audio(): AIProviderInterface
    {
        $active = get_option('ai_assistant_active_provider') ?: 'gemini';

        require_once __DIR__ . '/ProviderCapabilities.php';
        $audio_provider = ProviderCapabilities::best_audio_provider($active);

        return self::make($audio_provider);
    }

    /**
     * Create the configured fallback provider (if any).
     *
     * @return AIProviderInterface|null
     */
    public static function create_fallback(): ?AIProviderInterface
    {
        $fallback = get_option('ai_assistant_fallback_provider');
        if (empty($fallback) || $fallback === 'none') {
            return null;
        }

        try {
            return self::make($fallback);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Return an instance with an empty API key for testing purposes.
     * Useful for admin UI to check provider class loads correctly.
     *
     * @param  string $provider
     * @return AIProviderInterface
     */
    public static function create_for_test(string $provider): AIProviderInterface
    {
        return self::make($provider);
    }

    /**
     * Check if a provider slug is supported.
     *
     * @param  string $provider
     * @return bool
     */
    public static function is_supported(string $provider): bool
    {
        return isset(self::$provider_map[$provider]);
    }

    /**
     * Return list of all supported provider slugs.
     *
     * @return string[]
     */
    public static function supported_providers(): array
    {
        return array_keys(self::$provider_map);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function make(string $provider, ?array $settings = null): AIProviderInterface
    {
        if (!isset(self::$provider_map[$provider])) {
            throw new RuntimeException("Unknown AI provider: '{$provider}'. Supported: " . implode(', ', array_keys(self::$provider_map)));
        }

        $entry = self::$provider_map[$provider];
        $file  = __DIR__ . '/' . $entry['file'];
        $class = $entry['class'];

        if (!file_exists($file)) {
            throw new RuntimeException("Provider file not found: {$file}");
        }

        // Load dependencies in correct order
        self::require_once_safe(__DIR__ . '/Contracts/AIProviderInterface.php');
        self::require_once_safe(__DIR__ . '/ProviderCapabilities.php');
        self::require_once_safe(__DIR__ . '/BaseProvider.php');
        self::require_once_safe($file);

        if (!class_exists($class)) {
            throw new RuntimeException("Provider class '{$class}' not found in {$file}");
        }

        return new $class($settings);
    }

    private static function require_once_safe(string $file): void
    {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
