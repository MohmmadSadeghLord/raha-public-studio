<?php
if (!defined('ABSPATH')) exit;

// آمار و پنج سفارش آخر با ایجاد، حذف، انتشار یا ویرایش وضعیت/جزئیات تازه شوند.
function raha_clear_dashboard_stats_for_post($post_id) {
    if (in_array(get_post_type($post_id), ['raha_order', 'raha_ticket'], true)) {
        delete_transient('raha_dashboard_stats');
    }
}

add_action('save_post_raha_order', 'raha_clear_dashboard_stats_for_post');
add_action('save_post_raha_ticket', 'raha_clear_dashboard_stats_for_post');
add_action('before_delete_post', 'raha_clear_dashboard_stats_for_post');
add_action('trashed_post', 'raha_clear_dashboard_stats_for_post');

function raha_clear_dashboard_stats_for_meta($meta_id, $post_id, $meta_key) {
    $keys = ['order_status', 'ticket_status', 'tracking_code', 'full_name', 'service', 'amount'];
    if (in_array($meta_key, $keys, true)) {
        raha_clear_dashboard_stats_for_post($post_id);
    }
}

add_action('added_post_meta', 'raha_clear_dashboard_stats_for_meta', 10, 3);
add_action('updated_post_meta', 'raha_clear_dashboard_stats_for_meta', 10, 3);
add_action('deleted_post_meta', 'raha_clear_dashboard_stats_for_meta', 10, 3);
