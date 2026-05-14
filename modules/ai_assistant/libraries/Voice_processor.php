<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Voice Processor
 * Handles audio upload, transcription, and TTS via Gemini.
 */
class Voice_processor
{
    private $CI;
    private array $settings;

    /** Allowed audio MIME types */
    private const ALLOWED_MIME = [
        'audio/webm',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
        'audio/mpeg',
        'audio/x-m4a',
    ];

    /** Max audio size: 10 MB */
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    public function __construct()
    {
        $this->CI       = &get_instance();
        $this->settings = ai_assistant_get_settings();
        $this->CI->load->helper('ai_assistant');
    }

    /**
     * Transcribe uploaded audio to text
     *
     * @param  array  $file_data  $_FILES array entry
     * @param  string $language   'hi'|'en'|''  (auto-detect if empty)
     * @return array  ['success' => bool, 'transcript' => string, 'log_id' => int, 'error' => string]
     */
    public function transcribe_upload(array $file_data, string $language = ''): array
    {
        $staff_id = get_staff_user_id();

        // Validate file
        $validation = $this->validate_audio_file($file_data);
        if (!$validation['valid']) {
            return ['success' => false, 'transcript' => '', 'log_id' => 0, 'error' => $validation['error']];
        }

        // Read audio bytes
        $audio_bytes = file_get_contents($file_data['tmp_name']);
        if ($audio_bytes === false) {
            return ['success' => false, 'transcript' => '', 'log_id' => 0, 'error' => 'Failed to read audio file.'];
        }

        $audio_base64 = base64_encode($audio_bytes);
        $mime_type    = $file_data['type'];
        $duration_ms  = 0;

        // Pre-log the voice event
        $this->CI->load->model('Ai_logs_model', 'ai_logs_model');
        $log_id = $this->CI->ai_logs_model->log_voice($staff_id, '', [
            'language'    => $language,
            'duration_ms' => $duration_ms,
            'provider'    => 'gemini',
        ]);

        try {
            // Load Gemini client
            if (!class_exists('Gemini_client')) {
                require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Gemini_client.php');
            }
            $gemini    = new Gemini_client();
            $transcript = $gemini->transcribe_audio($audio_base64, $mime_type, $language);

            if (empty($transcript)) {
                $this->CI->ai_logs_model->mark_voice_error($log_id);
                return ['success' => false, 'transcript' => '', 'log_id' => $log_id, 'error' => 'No speech detected in audio.'];
            }

            // Update log with transcript
            $this->CI->db->where('id', $log_id)->update('ai_voice_logs', [
                'transcript' => $transcript,
                'status'     => 'done',
            ]);

            return [
                'success'    => true,
                'transcript' => $transcript,
                'log_id'     => $log_id,
                'error'      => '',
                'language'   => ai_detect_language($transcript),
            ];

        } catch (Throwable $e) {
            $this->CI->ai_logs_model->mark_voice_error($log_id);
            return ['success' => false, 'transcript' => '', 'log_id' => $log_id, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transcribe raw base64 audio (from browser MediaRecorder chunks)
     *
     * @param  string $audio_base64
     * @param  string $mime_type
     * @param  string $language
     * @return array
     */
    public function transcribe_base64(string $audio_base64, string $mime_type = 'audio/webm', string $language = ''): array
    {
        $staff_id = get_staff_user_id();

        // Validate size
        $decoded_size = strlen(base64_decode($audio_base64, true) ?: '');
        if ($decoded_size > self::MAX_SIZE_BYTES) {
            return ['success' => false, 'transcript' => '', 'error' => 'Audio too large (max 10MB).'];
        }

        if (!in_array($mime_type, self::ALLOWED_MIME, true)) {
            $mime_type = 'audio/webm'; // safe default
        }

        $this->CI->load->model('Ai_logs_model', 'ai_logs_model');
        $log_id = $this->CI->ai_logs_model->log_voice($staff_id, '', ['language' => $language]);

        try {
            if (!class_exists('Gemini_client')) {
                require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Gemini_client.php');
            }

            $gemini    = new Gemini_client();
            $transcript = $gemini->transcribe_audio($audio_base64, $mime_type, $language);

            if (empty(trim($transcript))) {
                $this->CI->ai_logs_model->mark_voice_error($log_id);
                return ['success' => false, 'transcript' => '', 'error' => 'Could not transcribe audio.', 'log_id' => $log_id];
            }

            $this->CI->db->where('id', $log_id)->update('ai_voice_logs', [
                'transcript' => $transcript,
                'status'     => 'done',
            ]);

            return [
                'success'    => true,
                'transcript' => $transcript,
                'log_id'     => $log_id,
                'error'      => '',
                'language'   => ai_detect_language($transcript),
            ];

        } catch (Throwable $e) {
            $this->CI->ai_logs_model->mark_voice_error($log_id);
            return ['success' => false, 'transcript' => '', 'error' => $e->getMessage(), 'log_id' => $log_id];
        }
    }

    /**
     * Convert AI text response to speech
     *
     * @param  string $text
     * @param  string $language  'hi-IN'|'en-US'
     * @return array  ['success' => bool, 'audio_base64' => string|null, 'use_browser_tts' => bool, 'text' => string]
     */
    public function text_to_speech(string $text, string $language = 'en-US'): array
    {
        if (empty($text)) {
            return ['success' => false, 'audio_base64' => null, 'use_browser_tts' => true, 'text' => ''];
        }

        // Trim very long responses — only speak first 500 chars
        $speak_text = mb_substr(strip_tags($text), 0, 500);

        try {
            if (!class_exists('Gemini_client')) {
                require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Gemini_client.php');
            }

            $gemini = new Gemini_client();
            $result = $gemini->text_to_speech($speak_text, $language);

            return array_merge(['success' => true], $result);

        } catch (Throwable $e) {
            return [
                'success'         => true,
                'audio_base64'    => null,
                'use_browser_tts' => true,
                'text'            => $speak_text,
                'language'        => $language,
            ];
        }
    }

    /**
     * Validate uploaded audio file
     */
    private function validate_audio_file(array $file_data): array
    {
        if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
            return ['valid' => false, 'error' => 'No valid audio file uploaded.'];
        }

        if ($file_data['size'] > self::MAX_SIZE_BYTES) {
            return ['valid' => false, 'error' => 'Audio file too large (max 10MB).'];
        }

        if (!in_array($file_data['type'], self::ALLOWED_MIME, true)) {
            return ['valid' => false, 'error' => 'Unsupported audio format.'];
        }

        return ['valid' => true, 'error' => ''];
    }
}
