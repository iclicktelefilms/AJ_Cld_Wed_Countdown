<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI Provider Interface
 *
 * All providers must implement this contract.
 * The internal message format used across all providers:
 *
 *   ['role' => 'user',      'content' => 'text']
 *   ['role' => 'assistant', 'content' => 'text']
 *   ['role' => 'assistant', 'content' => '',   'tool_call' => ['name' => '...', 'args' => []]]
 *   ['role' => 'tool',      'tool_name' => '...', 'result' => [...]]
 *
 * The normalized chat() return format:
 *   [
 *     'content'      => string,        // Text response (empty if tool call)
 *     'tool_calls'   => array|null,    // ['name' => '...', 'args' => [...]]
 *     'tokens_used'  => int,
 *     'model'        => string,
 *     'finish_reason'=> string,
 *     'provider'     => string,
 *   ]
 */
interface AIProviderInterface
{
    /**
     * Send a chat completion request with conversation history.
     *
     * @param  array  $messages      Internal format conversation history
     * @param  string $system_prompt System instruction
     * @param  array  $tools         Tool config array from ai_tools_config.php
     * @return array  Normalized response
     */
    public function chat(array $messages, string $system_prompt = '', array $tools = []): array;

    /**
     * Transcribe audio to text.
     *
     * @param  string $audio_base64  Base64-encoded audio
     * @param  string $mime_type     e.g. audio/webm
     * @param  string $language_hint 'hi' | 'en' | '' (auto)
     * @return string                Transcript text
     */
    public function transcribe_audio(string $audio_base64, string $mime_type = 'audio/webm', string $language_hint = ''): string;

    /**
     * Convert text to spoken audio.
     *
     * @param  string $text
     * @param  string $language  'hi-IN' | 'en-US'
     * @return array  ['audio_base64' => string|null, 'use_browser_tts' => bool, 'text' => string, 'mime_type' => string]
     */
    public function text_to_speech(string $text, string $language = 'en-US'): array;

    /**
     * Verify the provider is reachable with current config.
     *
     * @return array ['success' => bool, 'model' => string, 'error' => string, 'latency_ms' => int]
     */
    public function test_connection(): array;

    /**
     * List of model IDs supported/recommended for this provider.
     *
     * @return array  ['id' => string, 'label' => string][]
     */
    public function get_supported_models(): array;

    /**
     * Capabilities this provider supports.
     *
     * @return string[]  e.g. ['chat', 'tool_calling', 'streaming', 'vision', 'audio', 'tts']
     */
    public function get_capabilities(): array;

    /**
     * Unique slug for this provider.
     *
     * @return string  e.g. 'gemini', 'openai', 'claude', 'openrouter', 'ollama'
     */
    public function get_name(): string;
}
