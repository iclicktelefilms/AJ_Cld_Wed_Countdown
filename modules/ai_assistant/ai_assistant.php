<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI Assistant Module for Perfex CRM
 *
 * @package     ai_assistant
 * @version     1.0.0
 * @author      ICT AJ Claude
 * @license     MIT
 */

// Module constants
define('AI_ASSISTANT_MODULE_NAME', 'ai_assistant');
define('AI_ASSISTANT_VERSION', '1.0.0');
define('AI_ASSISTANT_ASSETS_URL', module_dir_url(AI_ASSISTANT_MODULE_NAME, 'assets'));

// Register module activation hooks
register_activation_hook(AI_ASSISTANT_MODULE_NAME, 'ai_assistant_module_activated');
register_deactivation_hook(AI_ASSISTANT_MODULE_NAME, 'ai_assistant_module_deactivated');
register_language_files(AI_ASSISTANT_MODULE_NAME, [AI_ASSISTANT_MODULE_NAME]);

// Initialize module hooks after Perfex boots
hooks()->add_action('app_admin_head', 'ai_assistant_inject_assets');
hooks()->add_action('app_admin_footer', 'ai_assistant_inject_widget');
hooks()->add_action('admin_init', 'ai_assistant_init');

/**
 * Inject CSS and JS assets into admin head
 */
function ai_assistant_inject_assets(): void
{
    $assets_url = AI_ASSISTANT_ASSETS_URL;
    echo '<link rel="stylesheet" href="' . $assets_url . 'css/ai_assistant.css?v=' . AI_ASSISTANT_VERSION . '">';
    echo '<script>window.AI_ASSISTANT_BASE_URL = "' . admin_url('ai_assistant') . '";</script>';
    echo '<script>window.AI_CSRF_TOKEN = "' . csrf_token() . '";</script>';
}

/**
 * Inject floating chat widget HTML into admin footer
 */
function ai_assistant_inject_widget(): void
{
    if (!is_staff_logged_in()) {
        return;
    }

    $settings = ai_assistant_get_settings();

    if (empty($settings['api_key'])) {
        return;
    }

    $enabled = get_option('ai_assistant_enabled');
    if ($enabled === '0') {
        return;
    }

    $staff_id = get_staff_user_id();
    $staff    = get_staff($staff_id);

    include_once(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'views/widget/chat_widget.php'));

    echo '<script src="' . AI_ASSISTANT_ASSETS_URL . 'js/voice_recorder.js?v=' . AI_ASSISTANT_VERSION . '"></script>';
    echo '<script src="' . AI_ASSISTANT_ASSETS_URL . 'js/ai_assistant.js?v=' . AI_ASSISTANT_VERSION . '"></script>';
}

/**
 * Run any init-time setup tasks
 */
function ai_assistant_init(): void
{
    // Check and run pending migrations
    $CI = &get_instance();
    if (!$CI->db->table_exists('ai_chat_history')) {
        include_once(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'install.php'));
    }
}

/**
 * Hook: module activated
 */
function ai_assistant_module_activated(): void
{
    // Ensure tables created on activation
    include_once(module_dir_path(AI_ASSISTANT_MODULE_NAME, 'install.php'));
}

/**
 * Hook: module deactivated
 */
function ai_assistant_module_deactivated(): void
{
    // Intentionally left empty — data retained on deactivation
}

/**
 * Retrieve merged AI assistant settings from DB options
 *
 * @return array
 */
function ai_assistant_get_settings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'api_key'              => get_option('ai_assistant_api_key'),
        'model'                => get_option('ai_assistant_model') ?: 'gemini-2.5-pro',
        'temperature'          => (float)(get_option('ai_assistant_temperature') ?: 0.7),
        'max_tokens'           => (int)(get_option('ai_assistant_max_tokens') ?: 8192),
        'streaming_enabled'    => get_option('ai_assistant_streaming') !== '0',
        'voice_enabled'        => get_option('ai_assistant_voice') !== '0',
        'memory_limit'         => (int)(get_option('ai_assistant_memory_limit') ?: 20),
        'retention_days'       => (int)(get_option('ai_assistant_retention_days') ?: 30),
        'allowed_modules'      => json_decode(get_option('ai_assistant_allowed_modules') ?: '[]', true) ?: [],
        'tool_permissions'     => json_decode(get_option('ai_assistant_tool_permissions') ?: '{}', true) ?: [],
    ];

    return $settings;
}
