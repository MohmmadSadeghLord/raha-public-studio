<?php
if (!defined('ABSPATH')) exit;
$user = wp_get_current_user();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce($_POST['_raha_profile_nonce'] ?? '', 'raha_profile')) {
    $name  = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
    $email_raw = wp_unslash($_POST['email'] ?? '');
    $email_input = is_string($email_raw) ? trim($email_raw) : '';
    $email = sanitize_email($email_input);
    $pass_raw = wp_unslash($_POST['new_password'] ?? '');
    $pass = is_string($pass_raw) ? $pass_raw : '';

    $update = ['ID' => $user->ID];
    if ($name) $update['display_name'] = $name;
    if (!is_string($email_raw) || !is_string($pass_raw)) $error = 'ورودی نامعتبر است.';
    elseif ($email_input !== '' && !is_email($email)) $error = 'ایمیل معتبر وارد کنید.';
    elseif ($email && $email !== $user->user_email && email_exists($email)) $error = 'این ایمیل قبلاً ثبت شده است.';
    elseif ($pass !== '' && mb_strlen($pass, 'UTF-8') < 8) $error = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    else {
        if ($email && $email !== $user->user_email) $update['user_email'] = $email;
        if ($pass !== '') $update['user_pass'] = $pass;
        $result = wp_update_user($update);
        if (is_wp_error($result)) $error = 'تغییرات ذخیره نشد. دوباره تلاش کنید.';
        else { $msg = 'تغییرات ذخیره شد.'; $user = get_userdata($user->ID); }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'نشست منقضی شده است. صفحه را تازه‌سازی کنید.';
}
?>
<div class="raha-tab">
    <div class="raha-tab__head">
        <h1>پروفایل</h1>
        <p>اطلاعات شخصی خود را مدیریت کنید</p>
    </div>

    <?php if ($msg) : ?>
        <div class="raha-auth__alert" role="status" style="background:#dcfce7;border-color:#16a34a;color:#14532d;"><?php echo esc_html($msg); ?></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div class="raha-auth__alert" role="alert"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <form method="post" class="raha-profile-form">
        <?php wp_nonce_field('raha_profile', '_raha_profile_nonce'); ?>
        <label class="raha-field">
            <span class="raha-field__label">نام و نام خانوادگی</span>
            <input type="text" name="display_name" class="raha-field__input" value="<?php echo esc_attr($user->display_name); ?>">
        </label>
        <label class="raha-field">
            <span class="raha-field__label">ایمیل</span>
            <input type="email" name="email" class="raha-field__input" dir="ltr" value="<?php echo esc_attr($user->user_email); ?>">
        </label>
        <label class="raha-field">
            <span class="raha-field__label">رمز عبور جدید (اختیاری)</span>
            <input type="password" name="new_password" class="raha-field__input" autocomplete="new-password">
            <small>حداقل ۸ کاراکتر</small>
        </label>
        <button type="submit" class="raha-btn raha-btn--primary">ذخیره تغییرات</button>
    </form>
</div>
