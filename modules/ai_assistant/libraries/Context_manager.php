<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Context Manager
 *
 * Manages AI assistant memory, context injection, and system prompt construction.
 * Persists staff-specific context between sessions.
 */
class Context_manager
{
    private $CI;
    private int    $staff_id;
    private array  $settings;
    private string $permission_level;

    public function __construct()
    {
        $this->CI               = &get_instance();
        $this->staff_id         = (int)get_staff_user_id();
        $this->settings         = ai_assistant_get_settings();
        $this->permission_level = ai_get_staff_permission_level();
    }

    /**
     * Build the full system prompt for the AI, injecting CRM context
     *
     * @param  array $allowed_tools  Tool definitions available to current staff
     * @return string
     */
    public function build_system_prompt(array $allowed_tools = []): string
    {
        $staff       = get_staff($this->staff_id);
        $fullname    = $staff ? trim($staff->firstname . ' ' . $staff->lastname) : 'Staff Member';
        $company     = get_setting('companyname') ?: 'the company';
        $currency    = get_base_currency()->symbol ?? '₹';
        $date        = date('l, d F Y, H:i');
        $perm_level  = $this->permission_level;

        $tool_names = implode(', ', array_column($allowed_tools, 'name'));

        $saved_context = $this->get_context('business_notes');

        $system = <<<SYSTEM
You are an intelligent AI assistant embedded inside Perfex CRM for {$company}.
You are directly helping {$fullname} who has permission level: {$perm_level}.

Current date and time: {$date}
Currency: {$currency}

YOUR CAPABILITIES:
- Read and analyze CRM data (leads, clients, invoices, tasks, projects, tickets, expenses)
- Execute CRM actions using structured tools
- Generate business reports and summaries
- Answer questions about CRM records
- Help with workflows and reminders
- Understand Hindi, English, and Hinglish (mixed) language

TOOLS AVAILABLE TO YOU:
{$tool_names}

HOW TO USE TOOLS:
1. When the user asks for CRM data or an action, select the most appropriate tool
2. Call the tool with correct parameters
3. Present the results in a clear, formatted manner
4. For destructive or financial actions, confirm before executing
5. Never guess IDs — if you need an ID, use a search tool first

COMMUNICATION STYLE:
- Be concise, professional, and helpful
- Format data as tables or lists when appropriate
- Use markdown for formatting
- If the user writes in Hindi or Hinglish, respond in the same language
- Always show monetary values with {$currency} symbol
- Show dates in readable format (e.g., 15 Jan 2025)

SECURITY RULES (CRITICAL):
- NEVER generate or execute raw SQL queries
- NEVER reveal API keys, passwords, or credentials
- NEVER perform actions outside your authorized tool set
- ALWAYS check: am I authorized for this action at {$perm_level} level?
- For delete operations, ALWAYS ask for explicit confirmation first

BUSINESS CONTEXT:
{$saved_context}
SYSTEM;

        return trim($system);
    }

    /**
     * Save a persistent context value for the current staff member
     *
     * @param  string      $key
     * @param  mixed       $value
     * @param  int|null    $ttl_seconds  Null = permanent
     */
    public function save_context(string $key, $value, ?int $ttl_seconds = null): void
    {
        $value_str  = is_array($value) ? ai_json_encode($value) : (string)$value;
        $expires_at = $ttl_seconds ? date('Y-m-d H:i:s', time() + $ttl_seconds) : null;

        // Upsert
        $existing = $this->CI->db
            ->where(['user_id' => $this->staff_id, 'context_key' => $key])
            ->get('ai_saved_context')
            ->row();

        if ($existing) {
            $this->CI->db
                ->where(['user_id' => $this->staff_id, 'context_key' => $key])
                ->update('ai_saved_context', [
                    'context_value' => $value_str,
                    'expires_at'    => $expires_at,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
        } else {
            $this->CI->db->insert('ai_saved_context', [
                'user_id'       => $this->staff_id,
                'context_key'   => $key,
                'context_value' => $value_str,
                'expires_at'    => $expires_at,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Get a persistent context value for the current staff member
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get_context(string $key, $default = '')
    {
        $row = $this->CI->db
            ->where('user_id', $this->staff_id)
            ->where('context_key', $key)
            ->where('(expires_at IS NULL OR expires_at > NOW())')
            ->get('ai_saved_context')
            ->row();

        if (!$row) {
            return $default;
        }

        $value = $row->context_value;

        // Try JSON decode for complex values
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }

    /**
     * Delete a specific context key
     */
    public function delete_context(string $key): void
    {
        $this->CI->db
            ->where(['user_id' => $this->staff_id, 'context_key' => $key])
            ->delete('ai_saved_context');
    }

    /**
     * Get all context for the current staff member
     */
    public function get_all_context(): array
    {
        $rows = $this->CI->db
            ->where('user_id', $this->staff_id)
            ->where('(expires_at IS NULL OR expires_at > NOW())')
            ->get('ai_saved_context')
            ->result_array();

        $context = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['context_value'], true);
            $context[$row['context_key']] = (json_last_error() === JSON_ERROR_NONE)
                ? $decoded
                : $row['context_value'];
        }

        return $context;
    }

    /**
     * Build a summary of recent CRM activity to inject as context
     *
     * @return string
     */
    public function get_crm_context_summary(): string
    {
        $lines = [];

        // Recent leads
        $recent_leads = $this->CI->db
            ->select('name, status, created')
            ->order_by('id', 'DESC')
            ->limit(5)
            ->get('leads')
            ->result_array();

        if ($recent_leads) {
            $lines[] = '**Recent Leads:** ' . implode(', ', array_column($recent_leads, 'name'));
        }

        // Overdue invoices count
        $overdue = $this->CI->db
            ->where('status', 2)
            ->where('duedate <', date('Y-m-d'))
            ->count_all_results('invoices');

        if ($overdue > 0) {
            $lines[] = "**Overdue Invoices:** {$overdue} invoices past due";
        }

        // Open tickets
        $open_tickets = $this->CI->db
            ->where('status', 'open')
            ->count_all_results('tickets');

        if ($open_tickets > 0) {
            $lines[] = "**Open Support Tickets:** {$open_tickets}";
        }

        return empty($lines) ? 'No immediate alerts.' : implode("\n", $lines);
    }

    /**
     * Store the last AI response summary for context recovery after refresh
     */
    public function save_last_response(string $session_id, string $summary): void
    {
        $this->save_context("last_response_{$session_id}", $summary, 86400);
    }

    /**
     * Extract and save business notes from AI conversation (e.g., client preferences)
     */
    public function extract_and_save_business_context(string $user_message, string $ai_response): void
    {
        // Save last interaction for context recovery
        $this->save_context('last_interaction', [
            'user'      => mb_substr($user_message, 0, 200),
            'assistant' => mb_substr($ai_response, 0, 200),
            'time'      => date('Y-m-d H:i:s'),
        ], 7200); // 2 hour TTL
    }
}
