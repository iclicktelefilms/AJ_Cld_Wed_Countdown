<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module installation — creates all required database tables
 */

$CI = &get_instance();

// ─── 1. ai_chat_history ──────────────────────────────────────────────────────
if (!$CI->db->table_exists('ai_chat_history')) {
    $CI->db->query("
        CREATE TABLE IF NOT EXISTS `ai_chat_history` (
            `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `staff_id`     INT(11) UNSIGNED NOT NULL,
            `session_id`   VARCHAR(64)       NOT NULL,
            `message_type` ENUM('user','assistant','system','tool_result') NOT NULL DEFAULT 'user',
            `message`      LONGTEXT          NOT NULL,
            `tool_calls`   JSON              DEFAULT NULL COMMENT 'Structured tool call payloads',
            `ai_provider`  VARCHAR(50)       NOT NULL DEFAULT 'gemini',
            `model_used`   VARCHAR(100)      DEFAULT NULL,
            `tokens_used`  INT(11) UNSIGNED  DEFAULT 0,
            `created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_staff_session` (`staff_id`, `session_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// ─── 2. ai_action_logs ───────────────────────────────────────────────────────
if (!$CI->db->table_exists('ai_action_logs')) {
    $CI->db->query("
        CREATE TABLE IF NOT EXISTS `ai_action_logs` (
            `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id`      INT(11) UNSIGNED NOT NULL,
            `staff_name`   VARCHAR(200)     DEFAULT NULL,
            `action_name`  VARCHAR(100)     NOT NULL,
            `tool_used`    VARCHAR(100)     DEFAULT NULL,
            `payload`      JSON             DEFAULT NULL,
            `before_state` JSON             DEFAULT NULL,
            `after_state`  JSON             DEFAULT NULL,
            `result`       LONGTEXT         DEFAULT NULL,
            `status`       ENUM('success','error','pending','blocked') NOT NULL DEFAULT 'pending',
            `ip_address`   VARCHAR(45)      DEFAULT NULL,
            `ai_provider`  VARCHAR(50)      DEFAULT 'gemini',
            `error_msg`    TEXT             DEFAULT NULL,
            `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_action` (`user_id`, `action_name`),
            KEY `idx_status` (`status`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// ─── 3. ai_saved_context ─────────────────────────────────────────────────────
if (!$CI->db->table_exists('ai_saved_context')) {
    $CI->db->query("
        CREATE TABLE IF NOT EXISTS `ai_saved_context` (
            `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id`       INT(11) UNSIGNED NOT NULL,
            `context_key`   VARCHAR(100)     NOT NULL,
            `context_value` LONGTEXT         DEFAULT NULL,
            `expires_at`    DATETIME         DEFAULT NULL,
            `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_user_key` (`user_id`, `context_key`),
            KEY `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// ─── 4. ai_voice_logs ────────────────────────────────────────────────────────
if (!$CI->db->table_exists('ai_voice_logs')) {
    $CI->db->query("
        CREATE TABLE IF NOT EXISTS `ai_voice_logs` (
            `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id`     INT(11) UNSIGNED NOT NULL,
            `audio_path`  VARCHAR(500)     DEFAULT NULL,
            `transcript`  TEXT             DEFAULT NULL,
            `language`    VARCHAR(10)      DEFAULT 'en',
            `duration_ms` INT(11)          DEFAULT 0,
            `provider`    VARCHAR(50)      DEFAULT 'gemini',
            `status`      ENUM('processing','done','error') NOT NULL DEFAULT 'processing',
            `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// ─── 5. ai_sessions ──────────────────────────────────────────────────────────
if (!$CI->db->table_exists('ai_sessions')) {
    $CI->db->query("
        CREATE TABLE IF NOT EXISTS `ai_sessions` (
            `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `session_id`  VARCHAR(64)      NOT NULL,
            `staff_id`    INT(11) UNSIGNED NOT NULL,
            `title`       VARCHAR(255)     DEFAULT 'New Conversation',
            `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
            `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_session` (`session_id`),
            KEY `idx_staff` (`staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// ─── Default option values ────────────────────────────────────────────────────
$default_options = [
    'ai_assistant_enabled'          => '1',
    'ai_assistant_model'            => 'gemini-2.5-pro',
    'ai_assistant_temperature'      => '0.7',
    'ai_assistant_max_tokens'       => '8192',
    'ai_assistant_streaming'        => '1',
    'ai_assistant_voice'            => '1',
    'ai_assistant_memory_limit'     => '20',
    'ai_assistant_retention_days'   => '30',
    'ai_assistant_allowed_modules'  => json_encode(['leads','clients','invoices','estimates','tasks','projects','tickets','contracts','expenses','payments']),
    'ai_assistant_tool_permissions' => json_encode([
        'admin'   => ['read','write','delete','report'],
        'manager' => ['read','write','report'],
        'staff'   => ['read','write'],
        'viewer'  => ['read'],
    ]),
];

foreach ($default_options as $key => $value) {
    if (get_option($key) === '') {
        add_option($key, $value);
    }
}
