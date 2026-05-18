<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Sanitize string input for AI context injection — strips tags and trims
 *
 * @param  string $input
 * @param  int    $max_length
 * @return string
 */
function ai_sanitize_input(string $input, int $max_length = 4096): string
{
    $clean = strip_tags(trim($input));
    $clean = htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return mb_substr($clean, 0, $max_length);
}

/**
 * Generate a cryptographically safe session ID for AI chat sessions
 *
 * @return string
 */
function ai_generate_session_id(): string
{
    return bin2hex(random_bytes(24));
}

/**
 * Convert a relative human time expression to a MySQL datetime string.
 * Handles phrases like "tomorrow 11am", "next Monday 3pm", "in 2 hours".
 *
 * @param  string $expression
 * @return string|null  MySQL datetime or null if unparseable
 */
function ai_parse_relative_time(string $expression): ?string
{
    $expression = strtolower(trim($expression));

    // Replace common Hindi-English patterns
    $replacements = [
        'kal'        => 'tomorrow',
        'aaj'        => 'today',
        'parso'      => '+2 days',
        'subah'      => '9:00 AM',
        'dopahar'    => '12:00 PM',
        'shaam'      => '6:00 PM',
        'raat'       => '9:00 PM',
    ];

    foreach ($replacements as $hindi => $english) {
        $expression = str_replace($hindi, $english, $expression);
    }

    $timestamp = strtotime($expression);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * Format a currency amount using CRM settings
 *
 * @param  float  $amount
 * @param  string $currency_symbol
 * @return string
 */
function ai_format_currency(float $amount, string $currency_symbol = ''): string
{
    if (empty($currency_symbol)) {
        $currency_symbol = get_base_currency()->symbol ?? '₹';
    }
    return $currency_symbol . number_format($amount, 2);
}

/**
 * Truncate text to a safe length for AI context window
 *
 * @param  string $text
 * @param  int    $max_chars
 * @return string
 */
function ai_truncate_context(string $text, int $max_chars = 2000): string
{
    if (mb_strlen($text) <= $max_chars) {
        return $text;
    }
    return mb_substr($text, 0, $max_chars - 3) . '...';
}

/**
 * Mask sensitive data in log payloads (email, phone, etc.)
 *
 * @param  array $data
 * @return array
 */
function ai_mask_sensitive(array $data): array
{
    $sensitive_keys = ['api_key', 'password', 'token', 'secret', 'credit_card'];

    array_walk_recursive($data, function (&$value, $key) use ($sensitive_keys) {
        if (in_array(strtolower((string)$key), $sensitive_keys, true)) {
            $value = '***REDACTED***';
        }
    });

    return $data;
}

/**
 * Return the current staff's highest AI permission level
 *
 * @return string  admin|manager|staff|viewer
 */
function ai_get_staff_permission_level(): string
{
    if (is_admin()) {
        return 'admin';
    }

    $staff_id = get_staff_user_id();
    $staff    = get_staff($staff_id);

    if (!$staff) {
        return 'viewer';
    }

    // Check role-based permissions from Perfex
    if (has_permission('ai_assistant', '', 'manager')) {
        return 'manager';
    }

    if (has_permission('ai_assistant', '', 'write')) {
        return 'staff';
    }

    return 'viewer';
}

/**
 * Check if an AI tool is allowed for the current staff permission level
 *
 * @param  string $required_permission  read|write|delete|report
 * @param  string $staff_level          admin|manager|staff|viewer
 * @return bool
 */
function ai_permission_allows(string $required_permission, string $staff_level): bool
{
    $hierarchy = [
        'viewer'  => ['read'],
        'staff'   => ['read', 'write'],
        'manager' => ['read', 'write', 'report'],
        'admin'   => ['read', 'write', 'delete', 'report'],
    ];

    $allowed = $hierarchy[$staff_level] ?? [];
    return in_array($required_permission, $allowed, true);
}

/**
 * Build a safe markdown table from an array of associative arrays
 *
 * @param  array $rows
 * @param  array $columns  Column keys to include
 * @return string
 */
function ai_build_markdown_table(array $rows, array $columns = []): string
{
    if (empty($rows)) {
        return '_No records found._';
    }

    if (empty($columns)) {
        $columns = array_keys($rows[0]);
    }

    $header    = '| ' . implode(' | ', array_map('ucwords', array_map(fn($c) => str_replace('_', ' ', $c), $columns))) . ' |';
    $separator = '| ' . implode(' | ', array_fill(0, count($columns), '---')) . ' |';

    $body_lines = [];
    foreach ($rows as $row) {
        $cells = [];
        foreach ($columns as $col) {
            $val = $row[$col] ?? '';
            $cells[] = str_replace(['|', "\n"], ['-', ' '], (string)$val);
        }
        $body_lines[] = '| ' . implode(' | ', $cells) . ' |';
    }

    return implode("\n", array_merge([$header, $separator], $body_lines));
}

/**
 * Detect probable language from text (returns 'hi' for Hindi, 'en' otherwise)
 *
 * @param  string $text
 * @return string
 */
function ai_detect_language(string $text): string
{
    // Devanagari Unicode range: U+0900–U+097F
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
        return 'hi';
    }
    return 'en';
}

/**
 * Return client IP address from request, respecting proxies
 *
 * @return string
 */
function ai_get_client_ip(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip  = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Safely JSON-encode data with fallback
 *
 * @param  mixed $data
 * @return string
 */
function ai_json_encode($data): string
{
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $encoded === false ? '{}' : $encoded;
}

/**
 * Validate and sanitize tool parameters against their JSON Schema
 *
 * @param  array $params   Provided parameter values
 * @param  array $schema   JSON Schema definition
 * @return array           ['valid' => bool, 'errors' => array, 'sanitized' => array]
 */
function ai_validate_tool_params(array $params, array $schema): array
{
    $errors    = [];
    $sanitized = [];
    $required  = $schema['required'] ?? [];
    $props     = $schema['properties'] ?? [];

    foreach ($required as $field) {
        if (!isset($params[$field]) || $params[$field] === '') {
            $errors[] = "Required field '{$field}' is missing.";
        }
    }

    foreach ($props as $field => $spec) {
        if (!isset($params[$field])) {
            continue;
        }

        $value = $params[$field];
        $type  = $spec['type'] ?? 'string';

        switch ($type) {
            case 'integer':
                if (!is_numeric($value) || (int)$value != $value) {
                    $errors[] = "Field '{$field}' must be an integer.";
                } else {
                    $sanitized[$field] = (int)$value;
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$field}' must be a number.";
                } else {
                    $sanitized[$field] = (float)$value;
                }
                break;

            case 'boolean':
                $sanitized[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$value;
                break;

            case 'string':
                $sanitized[$field] = ai_sanitize_input((string)$value);
                break;

            default:
                $sanitized[$field] = $value;
        }
    }

    return [
        'valid'     => empty($errors),
        'errors'    => $errors,
        'sanitized' => $sanitized,
    ];
}

/**
 * PHP 7.4 compatible str_starts_with
 *
 * @param  string $haystack
 * @param  string $needle
 * @return bool
 */
function ai_compat_str_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return strpos($haystack, $needle) === 0;
}

/**
 * PHP 7.4 compatible str_contains
 *
 * @param  string $haystack
 * @param  string $needle
 * @return bool
 */
function ai_compat_str_contains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return strpos($haystack, $needle) !== false;
}

/**
 * PHP 7.4 compatible str_ends_with
 *
 * @param  string $haystack
 * @param  string $suffix
 * @return bool
 */
function ai_compat_str_ends_with(string $haystack, string $suffix): bool
{
    if ($suffix === '') {
        return true;
    }
    $len = strlen($suffix);
    return substr($haystack, -$len) === $suffix;
}
