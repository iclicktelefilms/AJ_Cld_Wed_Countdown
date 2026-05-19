<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Tool Dispatcher
 *
 * Central hub for AI tool/function execution.
 *
 * Flow:
 *   AI calls tool → dispatcher validates → permission checked → sanitized
 *   → correct service method called → result formatted → logged → returned to AI
 *
 * SECURITY: No direct SQL. No raw queries. All through service classes only.
 */
class Tool_dispatcher
{
    private $CI;

    /** @var array Tool configs keyed by name */
    private array $tool_map = [];

    /** @var Permission_middleware */
    private $permission;

    /** @var Ai_logs_model */
    private $logs_model;

    /** Loaded service instances */
    private array $service_cache = [];

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper('ai_assistant');
        $this->CI->load->model('Ai_logs_model', 'ai_logs_model');

        // Load permission middleware
        if (!class_exists('Permission_middleware')) {
            require_once(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'middleware/Permission_middleware.php'));
        }
        $this->permission = new Permission_middleware();
        $this->logs_model = $this->CI->ai_logs_model;

        // Load and index tool config
        $tools = require module_dir_path(AI_ASSISTANT_MODULE_NAME, 'config/ai_tools_config.php');
        foreach ($tools as $tool) {
            $this->tool_map[$tool['name']] = $tool;
        }
    }

    /**
     * Get all tool definitions available to current staff
     *
     * @return array  Array of tool config arrays (filtered by permission)
     */
    public function get_available_tools(): array
    {
        $staff_level = ai_get_staff_permission_level();
        $available   = [];

        foreach ($this->tool_map as $tool) {
            if (ai_permission_allows($tool['permission'], $staff_level)) {
                $available[] = $tool;
            }
        }

        return $available;
    }

    /**
     * Dispatch a tool call from the AI
     *
     * @param  string $tool_name   Function name returned by Gemini
     * @param  array  $params      Parameters returned by Gemini
     * @return array  ['success' => bool, 'data' => mixed, 'error' => string, 'needs_confirm' => bool]
     */
    public function dispatch(string $tool_name, array $params): array
    {
        // 1. Tool must exist in config
        if (!isset($this->tool_map[$tool_name])) {
            return $this->error("Unknown tool: {$tool_name}");
        }

        $tool_def = $this->tool_map[$tool_name];

        // 2. Permission + CSRF check
        $perm_result = $this->permission->check($tool_name, $tool_def, $params);
        if (!$perm_result['allowed']) {
            return $this->error($perm_result['reason']);
        }

        // 3. Input validation against JSON schema
        $validation = ai_validate_tool_params($params, $tool_def['parameters'] ?? []);
        if (!$validation['valid']) {
            return $this->error('Parameter validation failed: ' . implode('; ', $validation['errors']));
        }
        $safe_params = array_merge($params, $validation['sanitized']);

        // 4. Check if action requires confirmation
        if (!empty($tool_def['confirm'])) {
            return [
                'success'       => false,
                'data'          => null,
                'error'         => '',
                'needs_confirm' => true,
                'tool_name'     => $tool_name,
                'params'        => $safe_params,
                'tool_def'      => $tool_def,
            ];
        }

        // 5. Load the service and call the method
        return $this->execute($tool_name, $tool_def, $safe_params);
    }

    /**
     * Execute a pre-confirmed tool call
     */
    public function execute_confirmed(string $tool_name, array $params): array
    {
        if (!isset($this->tool_map[$tool_name])) {
            return $this->error("Unknown tool: {$tool_name}");
        }

        $tool_def   = $this->tool_map[$tool_name];
        $safe_params = $params;

        return $this->execute($tool_name, $tool_def, $safe_params);
    }

    /**
     * Run the actual service method, capture before/after state, and log
     */
    private function execute(string $tool_name, array $tool_def, array $params): array
    {
        $service_name = $tool_def['service'];
        $method       = $tool_def['method'];
        $staff_id     = get_staff_user_id();
        $before_state = null;
        $after_state  = null;
        $log_id       = null;

        try {
            $service = $this->get_service($service_name);

            if (!method_exists($service, $method)) {
                throw new RuntimeException("Service method {$service_name}::{$method} does not exist.");
            }

            // Capture before-state for write/delete operations
            if ($tool_def['permission'] !== 'read') {
                $before_state = $this->capture_before_state($tool_name, $params);
            }

            // Execute the tool
            $result = $service->$method($params);

            // Capture after-state
            if ($tool_def['permission'] !== 'read') {
                $after_state = $this->capture_after_state($tool_name, $result);
            }

            // Log successful execution
            $this->logs_model->log_action([
                'user_id'      => $staff_id,
                'action_name'  => $tool_name,
                'tool_used'    => $tool_name,
                'payload'      => $params,
                'before_state' => $before_state,
                'after_state'  => $after_state,
                'result'       => is_array($result) ? ai_json_encode($result) : (string)$result,
                'status'       => 'success',
            ]);

            return [
                'success'       => true,
                'data'          => $result,
                'error'         => '',
                'needs_confirm' => false,
            ];

        } catch (Throwable $e) {
            $this->logs_model->log_action([
                'user_id'     => $staff_id,
                'action_name' => $tool_name,
                'tool_used'   => $tool_name,
                'payload'     => $params,
                'status'      => 'error',
                'error_msg'   => $e->getMessage(),
            ]);

            return $this->error('Tool execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get or instantiate a service class
     */
    private function get_service(string $service_name)
    {
        if (!isset($this->service_cache[$service_name])) {
            $service_file = module_dir_path(AI_ASSISTANT_MODULE_NAME, "services/{$service_name}.php");

            if (!file_exists($service_file)) {
                throw new RuntimeException("Service file not found: {$service_name}");
            }

            require_once $service_file;

            if (!class_exists($service_name)) {
                throw new RuntimeException("Service class {$service_name} not found.");
            }

            $this->service_cache[$service_name] = new $service_name();
        }

        return $this->service_cache[$service_name];
    }

    /**
     * Capture a record's current state before mutation (for audit log)
     */
    private function capture_before_state(string $tool_name, array $params): ?array
    {
        $id_field = null;

        if (strpos($tool_name, 'lead') !== false && isset($params['lead_id'])) {
            $id_field = ['table' => 'leads', 'id' => $params['lead_id']];
        } elseif (strpos($tool_name, 'task') !== false && isset($params['task_id'])) {
            $id_field = ['table' => 'tasks', 'id' => $params['task_id']];
        } elseif (strpos($tool_name, 'ticket') !== false && isset($params['ticket_id'])) {
            $id_field = ['table' => 'tickets', 'id' => $params['ticket_id']];
        }

        if ($id_field) {
            return $this->CI->db->get_where($id_field['table'], ['id' => $id_field['id']])->row_array() ?: null;
        }

        return null;
    }

    /**
     * Capture state after mutation (record the inserted/updated record)
     */
    private function capture_after_state(string $tool_name, $result): ?array
    {
        if (is_array($result) && isset($result['id'])) {
            return ['inserted_id' => $result['id']];
        }

        if (is_array($result)) {
            return array_slice($result, 0, 5);
        }

        return ['result' => (string)$result];
    }

    /**
     * Helper to construct an error response
     */
    private function error(string $message): array
    {
        return [
            'success'       => false,
            'data'          => null,
            'error'         => $message,
            'needs_confirm' => false,
        ];
    }
}
