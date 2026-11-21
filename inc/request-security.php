<?php
if (!defined('ABSPATH')) exit;

/** Limit public submissions per IP and action. */
function raha_check_submission_limit($action, $limit = 5, $window = 15 * MINUTE_IN_SECONDS) {
    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $key = 'raha_submit_' . md5($action . '|' . $ip);
    $count = (int) get_transient($key);
    if ($count >= $limit) return false;
    set_transient($key, $count + 1, $window);
    return true;
}
