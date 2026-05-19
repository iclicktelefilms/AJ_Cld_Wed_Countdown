<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI Action & Voice Logs Model
 */
class Ai_logs_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('ai_assistant');
    }

    /**
     * Log a tool execution attempt
     *
     * @param  array $data  Keys: user_id, action_name, tool_used, payload, before_state, after_state, result, status, ai_provider, error_msg
     * @return int   Log entry ID
     */
    public function log_action(array $data): int
    {
        $staff    = get_staff($data['user_id'] ?? get_staff_user_id());
        $fullname = $staff ? trim($staff->firstname . ' ' . $staff->lastname) : 'Unknown';

        $insert = [
            'user_id'      => (int)($data['user_id'] ?? get_staff_user_id()),
            'staff_name'   => $fullname,
            'action_name'  => $data['action_name'] ?? '',
            'tool_used'    => $data['tool_used'] ?? null,
            'payload'      => isset($data['payload']) ? ai_json_encode(ai_mask_sensitive((array)$data['payload'])) : null,
            'before_state' => isset($data['before_state']) ? ai_json_encode($data['before_state']) : null,
            'after_state'  => isset($data['after_state']) ? ai_json_encode($data['after_state']) : null,
            'result'       => $data['result'] ?? null,
            'status'       => $data['status'] ?? 'success',
            'ip_address'   => ai_get_client_ip(),
            'ai_provider'  => $data['ai_provider'] ?? 'gemini',
            'error_msg'    => $data['error_msg'] ?? null,
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('ai_action_logs', $insert);
        return $this->db->insert_id();
    }

    /**
     * Log an error result for an action
     */
    public function log_error(int $user_id, string $action_name, string $error_msg, array $payload = []): int
    {
        return $this->log_action([
            'user_id'     => $user_id,
            'action_name' => $action_name,
            'payload'     => $payload,
            'status'      => 'error',
            'error_msg'   => $error_msg,
        ]);
    }

    /**
     * Log a voice transcription event
     *
     * @param  int    $user_id
     * @param  string $transcript
     * @param  array  $meta        audio_path, language, duration_ms, provider
     * @return int    Log entry ID
     */
    public function log_voice(int $user_id, string $transcript, array $meta = []): int
    {
        $this->db->insert('ai_voice_logs', [
            'user_id'     => $user_id,
            'audio_path'  => $meta['audio_path'] ?? null,
            'transcript'  => $transcript,
            'language'    => $meta['language'] ?? 'en',
            'duration_ms' => (int)($meta['duration_ms'] ?? 0),
            'provider'    => $meta['provider'] ?? 'gemini',
            'status'      => 'done',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * Mark a voice log entry as errored
     */
    public function mark_voice_error(int $log_id): void
    {
        $this->db->where('id', $log_id)->update('ai_voice_logs', ['status' => 'error']);
    }

    /**
     * Get paginated action logs with optional search
     */
    public function get_logs(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $this->db->select('l.*, s.firstname, s.lastname, s.email as staff_email')
            ->from('ai_action_logs l')
            ->join('staff s', 's.staffid = l.user_id', 'left')
            ->order_by('l.created_at', 'DESC')
            ->limit($limit, $offset);

        if (!empty($filters['status'])) {
            $this->db->where('l.status', $filters['status']);
        }
        if (!empty($filters['tool_used'])) {
            $this->db->where('l.tool_used', $filters['tool_used']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('l.user_id', (int)$filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('l.created_at >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('l.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        return $this->db->get()->result_array();
    }
}
