<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Core AI Assistant Model
 * Handles sessions, usage stats, and settings persistence.
 */
class Ai_assistant_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('ai_assistant');
    }

    // ── Sessions ─────────────────────────────────────────────────────────────

    /**
     * Get or create an active session for the given staff member
     */
    public function get_or_create_session(int $staff_id, string $session_id = ''): array
    {
        if (!empty($session_id)) {
            $row = $this->db->get_where('ai_sessions', [
                'session_id' => $session_id,
                'staff_id'   => $staff_id,
            ])->row_array();

            if ($row) {
                return $row;
            }
        }

        // Create new session
        $new_session_id = $session_id ?: ai_generate_session_id();
        $this->db->insert('ai_sessions', [
            'session_id' => $new_session_id,
            'staff_id'   => $staff_id,
            'title'      => 'New Conversation',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'id'         => $this->db->insert_id(),
            'session_id' => $new_session_id,
            'staff_id'   => $staff_id,
            'title'      => 'New Conversation',
            'is_active'  => 1,
        ];
    }

    /**
     * Get all sessions for a staff member (ordered by most recent)
     */
    public function get_staff_sessions(int $staff_id, int $limit = 20): array
    {
        return $this->db
            ->where('staff_id', $staff_id)
            ->where('is_active', 1)
            ->order_by('updated_at', 'DESC')
            ->limit($limit)
            ->get('ai_sessions')
            ->result_array();
    }

    /**
     * Update session title based on first user message
     */
    public function update_session_title(string $session_id, string $title): void
    {
        $short_title = mb_substr(strip_tags($title), 0, 80);
        $this->db->where('session_id', $session_id)
            ->update('ai_sessions', [
                'title'      => $short_title,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Archive (soft-delete) a session
     */
    public function archive_session(string $session_id, int $staff_id): bool
    {
        $this->db->where(['session_id' => $session_id, 'staff_id' => $staff_id])
            ->update('ai_sessions', ['is_active' => 0]);

        return $this->db->affected_rows() > 0;
    }

    // ── Usage Statistics ─────────────────────────────────────────────────────

    /**
     * Get aggregated usage stats for the admin dashboard
     */
    public function get_usage_stats(string $period = 'month'): array
    {
        $date_from = $this->period_to_date($period);

        // Total messages
        $total_messages = $this->db
            ->where('created_at >=', $date_from)
            ->count_all_results('ai_chat_history');

        // Total tokens
        $tokens_row = $this->db
            ->select_sum('tokens_used')
            ->where('created_at >=', $date_from)
            ->get('ai_chat_history')
            ->row();
        $total_tokens = (int)($tokens_row->tokens_used ?? 0);

        // Actions executed
        $total_actions = $this->db
            ->where('created_at >=', $date_from)
            ->where('status', 'success')
            ->count_all_results('ai_action_logs');

        // Failed requests
        $failed_requests = $this->db
            ->where('created_at >=', $date_from)
            ->where('status', 'error')
            ->count_all_results('ai_action_logs');

        // Most used tools
        $top_tools = $this->db
            ->select('tool_used, COUNT(*) as usage_count')
            ->where('created_at >=', $date_from)
            ->where('status', 'success')
            ->where('tool_used IS NOT NULL')
            ->group_by('tool_used')
            ->order_by('usage_count', 'DESC')
            ->limit(10)
            ->get('ai_action_logs')
            ->result_array();

        // Daily trend (last 30 days)
        $daily_trend = $this->db
            ->select('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at >=', date('Y-m-d', strtotime('-30 days')))
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('ai_chat_history')
            ->result_array();

        return [
            'total_messages'  => $total_messages,
            'total_tokens'    => $total_tokens,
            'total_actions'   => $total_actions,
            'failed_requests' => $failed_requests,
            'top_tools'       => $top_tools,
            'daily_trend'     => $daily_trend,
        ];
    }

    /**
     * Get recent action logs for admin audit view
     */
    public function get_recent_action_logs(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $this->db->select('l.*, s.firstname, s.lastname')
            ->from('ai_action_logs l')
            ->join('staff s', 's.staffid = l.user_id', 'left')
            ->order_by('l.created_at', 'DESC')
            ->limit($limit, $offset);

        if (!empty($filters['status'])) {
            $this->db->where('l.status', $filters['status']);
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

    /**
     * Count action logs with optional filters (for pagination)
     */
    public function count_action_logs(array $filters = []): int
    {
        $this->db->from('ai_action_logs');

        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('user_id', (int)$filters['user_id']);
        }

        return $this->db->count_all_results();
    }

    /**
     * Purge old chat history and voice logs per retention settings
     */
    public function purge_old_data(): int
    {
        $retention = (int)(get_option('ai_assistant_retention_days') ?: 30);
        $cutoff    = date('Y-m-d H:i:s', strtotime("-{$retention} days"));

        $this->db->where('created_at <', $cutoff)->delete('ai_chat_history');
        $deleted = $this->db->affected_rows();

        $this->db->where('created_at <', $cutoff)->delete('ai_voice_logs');

        // Remove expired context entries
        $this->db->where('expires_at <', date('Y-m-d H:i:s'))
            ->where('expires_at IS NOT NULL')
            ->delete('ai_saved_context');

        return $deleted;
    }

    // ── Internal Helpers ─────────────────────────────────────────────────────

    private function period_to_date(string $period): string
    {
        return match($period) {
            'today'   => date('Y-m-d 00:00:00'),
            'week'    => date('Y-m-d 00:00:00', strtotime('monday this week')),
            'month'   => date('Y-m-01 00:00:00'),
            'quarter' => date('Y-m-d 00:00:00', strtotime('first day of -3 month')),
            'year'    => date('Y-01-01 00:00:00'),
            default   => date('Y-m-01 00:00:00'),
        };
    }
}
