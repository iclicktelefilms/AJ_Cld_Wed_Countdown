<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin Controller — AI Assistant Settings & Dashboard
 */
class Admin extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (!is_admin()) {
            access_denied('AI Assistant Admin');
        }

        $this->load->helper('ai_assistant');
        $this->load->model([
            'Ai_assistant_model' => 'ai_assistant_model',
            'Ai_logs_model'      => 'ai_logs_model',
        ]);
        $this->load->language('ai_assistant', 'ai_assistant');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    /**
     * GET /admin/ai_assistant/dashboard
     */
    public function dashboard()
    {
        $period = $this->input->get('period') ?? 'month';
        $stats  = $this->ai_assistant_model->get_usage_stats($period);

        $data = [
            'title'        => lang('ai_assistant_dashboard'),
            'stats'        => $stats,
            'period'       => $period,
            'recent_logs'  => $this->ai_logs_model->get_logs(20, 0),
        ];

        $this->load->view('admin/header_open', ['title' => lang('ai_assistant_dashboard')]);
        $this->load->view(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'views/admin/dashboard'), $data);
        $this->load->view('admin/footer');
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    /**
     * GET /admin/ai_assistant/settings
     */
    public function settings()
    {
        $all_staff = $this->db->select('staffid, firstname, lastname')->get('staff')->result_array();

        $data = [
            'title'     => lang('ai_assistant_settings'),
            'settings'  => ai_assistant_get_settings(),
            'all_staff' => $all_staff,
            'models'    => $this->get_available_models(),
        ];

        $this->load->view('admin/header_open', ['title' => lang('ai_assistant_settings')]);
        $this->load->view(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'views/admin/settings'), $data);
        $this->load->view('admin/footer');
    }

    /**
     * POST /admin/ai_assistant/save_settings
     */
    public function save_settings()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        // API key — never expose or log
        $api_key = $this->input->post('api_key');
        if (!empty($api_key)) {
            update_option('ai_assistant_api_key', $api_key);
        }

        $option_map = [
            'ai_assistant_model'            => 'model',
            'ai_assistant_temperature'      => 'temperature',
            'ai_assistant_max_tokens'       => 'max_tokens',
            'ai_assistant_streaming'        => 'streaming',
            'ai_assistant_voice'            => 'voice',
            'ai_assistant_memory_limit'     => 'memory_limit',
            'ai_assistant_retention_days'   => 'retention_days',
            'ai_assistant_enabled'          => 'enabled',
        ];

        foreach ($option_map as $option_key => $post_key) {
            $value = $this->input->post($post_key);
            if ($value !== null) {
                update_option($option_key, sanitize_option_value($option_key, $value));
            }
        }

        // Allowed modules (array)
        $allowed_modules = $this->input->post('allowed_modules') ?? [];
        if (is_array($allowed_modules)) {
            update_option('ai_assistant_allowed_modules', json_encode(array_map('sanitize_text_field', $allowed_modules)));
        }

        // Tool permissions per role
        $tool_permissions = $this->input->post('tool_permissions');
        if (!empty($tool_permissions) && is_array($tool_permissions)) {
            $safe_perms = [];
            $allowed_roles  = ['admin', 'manager', 'staff', 'viewer'];
            $allowed_perms  = ['read', 'write', 'delete', 'report'];

            foreach ($tool_permissions as $role => $perms) {
                if (!in_array($role, $allowed_roles, true)) continue;
                $safe_perms[$role] = array_values(
                    array_filter($perms, fn($p) => in_array($p, $allowed_perms, true))
                );
            }

            update_option('ai_assistant_tool_permissions', json_encode($safe_perms));
        }

        // Bust settings cache
        if (function_exists('clear_options_cache')) {
            clear_options_cache();
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'success', 'message' => lang('ai_settings_saved')]));
    }

    /**
     * POST /admin/ai_assistant/test_connection
     * Test Gemini API connectivity
     */
    public function test_connection()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!class_exists('Gemini_client')) {
            require_once module_dir_path(AI_ASSISTANT_MODULE_NAME, 'libraries/Gemini_client.php');
        }

        $gemini = new Gemini_client();
        $result = $gemini->test_connection();

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    // ── Logs ──────────────────────────────────────────────────────────────────

    /**
     * GET /admin/ai_assistant/logs
     */
    public function logs()
    {
        $page    = max(1, (int)($this->input->get('page') ?? 1));
        $limit   = 50;
        $offset  = ($page - 1) * $limit;
        $filters = [
            'status'    => $this->input->get('status'),
            'user_id'   => $this->input->get('user_id'),
            'date_from' => $this->input->get('date_from'),
            'date_to'   => $this->input->get('date_to'),
        ];

        $logs  = $this->ai_logs_model->get_logs($limit, $offset, $filters);
        $total = $this->ai_assistant_model->count_action_logs($filters);
        $pages = ceil($total / $limit);

        $all_staff = $this->db->select('staffid, firstname, lastname')->get('staff')->result_array();

        $data = [
            'title'     => lang('ai_audit_log'),
            'logs'      => $logs,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'filters'   => $filters,
            'all_staff' => $all_staff,
        ];

        $this->load->view('admin/header_open', ['title' => lang('ai_audit_log')]);
        $this->load->view(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'views/admin/logs'), $data);
        $this->load->view('admin/footer');
    }

    /**
     * POST /admin/ai_assistant/purge_old_data
     */
    public function purge_old_data()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $deleted = $this->ai_assistant_model->purge_old_data();

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => 'success',
                'deleted' => $deleted,
                'message' => "Purged {$deleted} old records.",
            ]));
    }

    // ── Internal Helpers ──────────────────────────────────────────────────────

    private function get_available_models(): array
    {
        return [
            'gemini-2.5-pro'          => 'Gemini 2.5 Pro (Most capable)',
            'gemini-2.5-flash'        => 'Gemini 2.5 Flash (Fast & efficient)',
            'gemini-2.0-flash-exp'    => 'Gemini 2.0 Flash Experimental',
            'gemini-1.5-pro'          => 'Gemini 1.5 Pro',
            'gemini-1.5-flash'        => 'Gemini 1.5 Flash',
        ];
    }
}

/**
 * Sanitize an option value before storing it
 */
function sanitize_option_value(string $key, $value): string
{
    $numeric_options = [
        'ai_assistant_temperature',
        'ai_assistant_max_tokens',
        'ai_assistant_memory_limit',
        'ai_assistant_retention_days',
    ];

    $bool_options = [
        'ai_assistant_streaming',
        'ai_assistant_voice',
        'ai_assistant_enabled',
    ];

    if (in_array($key, $numeric_options, true)) {
        return (string)(float)$value;
    }

    if (in_array($key, $bool_options, true)) {
        return $value ? '1' : '0';
    }

    return htmlspecialchars(strip_tags((string)$value), ENT_QUOTES);
}
