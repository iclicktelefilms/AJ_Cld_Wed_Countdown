<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module uninstall — drops all AI assistant tables and options
 * Only runs on explicit uninstall (not deactivation) to protect data.
 */

$CI = &get_instance();

$tables = [
    'ai_chat_history',
    'ai_action_logs',
    'ai_saved_context',
    'ai_voice_logs',
    'ai_sessions',
];

foreach ($tables as $table) {
    $CI->db->query("DROP TABLE IF EXISTS `{$table}`");
}

$options = [
    'ai_assistant_enabled',
    'ai_assistant_api_key',
    'ai_assistant_model',
    'ai_assistant_temperature',
    'ai_assistant_max_tokens',
    'ai_assistant_streaming',
    'ai_assistant_voice',
    'ai_assistant_memory_limit',
    'ai_assistant_retention_days',
    'ai_assistant_allowed_modules',
    'ai_assistant_tool_permissions',
];

foreach ($options as $option) {
    $CI->db->delete(db_prefix() . 'options', ['name' => $option]);
}
