<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI Chat History Model
 * Manages conversation messages per session with memory windowing.
 */
class Ai_chat_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Save a message to chat history
     *
     * @param  string $session_id
     * @param  int    $staff_id
     * @param  string $message_type  user|assistant|system|tool_result
     * @param  string $message       Message text content
     * @param  array  $meta          Optional: tool_calls, model_used, tokens_used
     * @return int    Inserted row ID
     */
    public function save_message(
        string $session_id,
        int    $staff_id,
        string $message_type,
        string $message,
        array  $meta = []
    ): int {
        $this->db->insert('ai_chat_history', [
            'staff_id'     => $staff_id,
            'session_id'   => $session_id,
            'message_type' => $message_type,
            'message'      => $message,
            'tool_calls'   => isset($meta['tool_calls']) ? ai_json_encode($meta['tool_calls']) : null,
            'ai_provider'  => $meta['ai_provider'] ?? 'gemini',
            'model_used'   => $meta['model_used'] ?? null,
            'tokens_used'  => (int)($meta['tokens_used'] ?? 0),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * Get conversation history for a session
     * Returns messages formatted for Gemini API (role/parts structure)
     *
     * @param  string $session_id
     * @param  int    $limit       Max messages to return (memory window)
     * @return array
     */
    public function get_session_history(string $session_id, int $limit = 20): array
    {
        $rows = $this->db
            ->where('session_id', $session_id)
            ->where_in('message_type', ['user', 'assistant', 'tool_result'])
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->get('ai_chat_history')
            ->result_array();

        // Reverse so oldest is first (correct conversation order)
        $rows = array_reverse($rows);

        $history = [];
        foreach ($rows as $row) {
            $role = match($row['message_type']) {
                'user'        => 'user',
                'assistant'   => 'model',
                'tool_result' => 'user',   // Tool results sent back as user role in Gemini
                default       => 'user',
            };

            $parts = [['text' => $row['message']]];

            // Attach tool call data if present
            if (!empty($row['tool_calls'])) {
                $tool_calls = json_decode($row['tool_calls'], true);
                if (is_array($tool_calls)) {
                    $parts[] = ['functionCall' => $tool_calls];
                }
            }

            $history[] = [
                'role'  => $role,
                'parts' => $parts,
            ];
        }

        return $history;
    }

    /**
     * Get raw message rows for display in chat UI
     *
     * @param  string $session_id
     * @param  int    $limit
     * @param  int    $before_id   Pagination: fetch messages before this ID
     * @return array
     */
    public function get_messages_for_display(string $session_id, int $limit = 50, int $before_id = 0): array
    {
        $this->db->where('session_id', $session_id)
            ->where_in('message_type', ['user', 'assistant'])
            ->order_by('id', 'DESC')
            ->limit($limit);

        if ($before_id > 0) {
            $this->db->where('id <', $before_id);
        }

        $rows = $this->db->get('ai_chat_history')->result_array();
        return array_reverse($rows);
    }

    /**
     * Delete all messages for a session
     */
    public function clear_session(string $session_id, int $staff_id): bool
    {
        $this->db->where(['session_id' => $session_id, 'staff_id' => $staff_id])
            ->delete('ai_chat_history');

        return $this->db->affected_rows() > 0;
    }

    /**
     * Count messages in a session
     */
    public function count_session_messages(string $session_id): int
    {
        return $this->db
            ->where('session_id', $session_id)
            ->count_all_results('ai_chat_history');
    }

    /**
     * Update session context title from first user message
     */
    public function maybe_set_session_title(string $session_id, string $first_user_message): void
    {
        $count = $this->count_session_messages($session_id);
        if ($count === 1) {
            $title = mb_substr($first_user_message, 0, 60);
            $this->db->where('session_id', $session_id)
                ->update('ai_sessions', ['title' => $title, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->where('session_id', $session_id)
                ->update('ai_sessions', ['updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
