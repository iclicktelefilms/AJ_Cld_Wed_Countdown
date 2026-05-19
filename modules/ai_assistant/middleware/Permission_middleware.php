<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Permission Middleware
 *
 * Validates staff permissions before any AI action is dispatched.
 * Also enforces rate-limiting, CSRF, and session validity checks.
 */
class Permission_middleware
{
    /** @var CI_Controller */
    private $CI;

    /** @var array Rate limit counters stored in DB/cache */
    private array $rate_limits = [
        'read'   => 200,  // per hour
        'write'  => 100,
        'delete' => 20,
        'report' => 50,
    ];

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper('ai_assistant');
    }

    /**
     * Run all checks before executing an AI tool
     *
     * @param  string $tool_name
     * @param  array  $tool_definition  The tool config entry
     * @param  array  $params           Sanitized tool parameters
     * @return array  ['allowed' => bool, 'reason' => string]
     */
    public function check(string $tool_name, array $tool_definition, array $params = []): array
    {
        // 1. Session must be valid
        if (!is_staff_logged_in()) {
            return $this->deny('Not authenticated.');
        }

        // 2. CSRF token validation for write/delete operations
        $permission_required = $tool_definition['permission'] ?? 'read';
        if (in_array($permission_required, ['write', 'delete'], true)) {
            if (!$this->validate_csrf()) {
                return $this->deny('CSRF validation failed.');
            }
        }

        // 3. Staff permission level check
        $staff_level = ai_get_staff_permission_level();
        if (!ai_permission_allows($permission_required, $staff_level)) {
            $this->log_blocked_attempt($tool_name, $params, $staff_level, $permission_required);
            return $this->deny("Your role ({$staff_level}) does not have '{$permission_required}' permission.");
        }

        // 4. Check module-level tool permissions from settings
        $settings = ai_assistant_get_settings();
        $tool_perms = $settings['tool_permissions'][$staff_level] ?? [];
        if (!empty($tool_perms) && !in_array($permission_required, $tool_perms, true)) {
            return $this->deny('This tool is disabled for your role in AI settings.');
        }

        // 5. Rate limiting
        $rate_result = $this->check_rate_limit($permission_required);
        if (!$rate_result['allowed']) {
            return $this->deny('Rate limit exceeded. Please wait before making more requests.');
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Validate CSRF token from request headers or POST body
     */
    private function validate_csrf(): bool
    {
        $token = $this->CI->input->get_request_header('X-CSRF-Token', true)
            ?? $this->CI->input->post('csrf_token');

        if (empty($token)) {
            return false;
        }

        // Use Perfex's built-in CSRF validation
        $csrf_hash = $this->CI->security->get_csrf_hash();
        return hash_equals($csrf_hash, $token);
    }

    /**
     * Simple per-staff rate limiter using DB transient storage
     *
     * @param  string $permission_type
     * @return array  ['allowed' => bool, 'remaining' => int]
     */
    private function check_rate_limit(string $permission_type): array
    {
        $staff_id  = get_staff_user_id();
        $limit     = $this->rate_limits[$permission_type] ?? 100;
        $window    = 3600; // 1 hour in seconds
        $cache_key = "ai_rl_{$staff_id}_{$permission_type}";

        $this->CI->db->where('user_id', $staff_id)
            ->where('context_key', $cache_key)
            ->where('expires_at >', date('Y-m-d H:i:s'));

        $row = $this->CI->db->get('ai_saved_context')->row();

        if ($row) {
            $count = (int)$row->context_value;
            if ($count >= $limit) {
                return ['allowed' => false, 'remaining' => 0];
            }
            // Increment
            $this->CI->db->where('user_id', $staff_id)
                ->where('context_key', $cache_key)
                ->update('ai_saved_context', ['context_value' => $count + 1]);

            return ['allowed' => true, 'remaining' => $limit - $count - 1];
        }

        // First request in window
        $this->CI->db->insert('ai_saved_context', [
            'user_id'       => $staff_id,
            'context_key'   => $cache_key,
            'context_value' => 1,
            'expires_at'    => date('Y-m-d H:i:s', time() + $window),
        ]);

        return ['allowed' => true, 'remaining' => $limit - 1];
    }

    /**
     * Log a blocked/denied permission attempt
     */
    private function log_blocked_attempt(string $tool_name, array $params, string $staff_level, string $required): void
    {
        $staff = get_staff(get_staff_user_id());

        $this->CI->db->insert('ai_action_logs', [
            'user_id'     => get_staff_user_id(),
            'staff_name'  => $staff ? trim($staff->firstname . ' ' . $staff->lastname) : 'Unknown',
            'action_name' => $tool_name,
            'tool_used'   => $tool_name,
            'payload'     => ai_json_encode(ai_mask_sensitive($params)),
            'status'      => 'blocked',
            'error_msg'   => "Required: {$required}, Staff level: {$staff_level}",
            'ip_address'  => ai_get_client_ip(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Helper to construct a deny response
     */
    private function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
