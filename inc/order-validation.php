<?php
if (!defined('ABSPATH')) exit;

function raha_order_statuses() {
    return ['new', 'review', 'progress', 'done', 'canceled'];
}

function raha_validate_order_status($status) {
    return is_string($status) && in_array($status, raha_order_statuses(), true);
}

function raha_validate_order_financials($amount, $paid) {
    foreach ([$amount, $paid] as $value) {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value)
            || strlen((string) $value) > 15) {
            return new WP_Error('invalid_amount', 'مبلغ باید عدد صحیح و غیرمنفی باشد.');
        }
    }
    if ((int) $paid > (int) $amount) {
        return new WP_Error('invalid_paid', 'مبلغ پرداخت‌شده نمی‌تواند از مبلغ کل بیشتر باشد.');
    }
    return [(int) $amount, (int) $paid];
}

function raha_validate_order_update($status, $amount, $paid) {
    if (!raha_validate_order_status($status)) {
        return new WP_Error('invalid_status', 'وضعیت سفارش نامعتبر است.');
    }
    return raha_validate_order_financials($amount, $paid);
}

add_action('admin_notices', function () {
    $key = 'raha_order_validation_' . get_current_user_id();
    $message = get_transient($key);
    if (!$message) return;
    delete_transient($key);
    echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
});
