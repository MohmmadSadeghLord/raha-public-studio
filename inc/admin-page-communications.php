<?php
if (!defined('ABSPATH')) exit;

/* ============================================================
   ایجاد منوی مدیریت ارتباطات
   ============================================================ */
add_action('admin_menu', function() {
    add_menu_page(
        'مدیریت ارتباطات',
        'مدیریت ارتباطات',
        'raha_manage_orders',
        'raha-communications',
        'raha_render_communications_page',
        'dashicons-networking',
        55
    );
});

function raha_render_communications_page() {
    if (!Raha_RBAC::can_cap('raha_manage_orders')) return;

    // دریافت تعداد آیتم‌های جدید برای نشانگرهای دایره‌ای
    $counts = [
        'consultation' => count(get_posts(['post_type' => 'raha_consultation', 'post_status' => 'publish', 'meta_key' => 'lead_status', 'meta_value' => 'new', 'fields' => 'ids'])),
        'order'        => count(get_posts(['post_type' => 'raha_order', 'post_status' => 'publish', 'meta_key' => 'order_status', 'meta_value' => 'new', 'fields' => 'ids'])),
        'lead'         => count(get_posts(['post_type' => 'raha_lead', 'post_status' => 'publish', 'meta_key' => 'lead_status', 'meta_value' => 'unread', 'fields' => 'ids'])),
    ];

    // آمار کلی
    $total_consult = count(get_posts(['post_type' => 'raha_consultation', 'post_status' => 'publish', 'fields' => 'ids']));
    $total_orders  = count(get_posts(['post_type' => 'raha_order', 'post_status' => 'publish', 'fields' => 'ids']));
    $total_leads   = count(get_posts(['post_type' => 'raha_lead', 'post_status' => 'publish', 'fields' => 'ids']));
    ?>
    <div class="wrap raha-comm-wrap" id="rahaCommApp">
        
        <div class="raha-comm-header">
            <h1>مدیریت ارتباطات</h1>
            <span class="raha-badge">استودیو رها</span>
        </div>

        <!-- آمار کلی -->
        <div class="raha-comm-stats">
            <div class="stat-card">
                <span class="stat-icon">📞</span>
                <div class="stat-info">
                    <span class="stat-label">کل مشاوره‌ها</span>
                    <strong class="stat-value"><?php echo esc_html($total_consult); ?></strong>
                </div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📦</span>
                <div class="stat-info">
                    <span class="stat-label">کل سفارشات</span>
                    <strong class="stat-value"><?php echo esc_html($total_orders); ?></strong>
                </div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🔔</span>
                <div class="stat-info">
                    <span class="stat-label">کل لیدها</span>
                    <strong class="stat-value"><?php echo esc_html($total_leads); ?></strong>
                </div>
            </div>
        </div>

        <!-- تب‌ها -->
        <div class="raha-comm-tabs">
            <button class="comm-tab is-active" data-tab="consultation">
                <span class="tab-icon">📞</span> وقت مشاوره
                <?php if ($counts['consultation'] > 0) : ?>
                    <span class="tab-badge"><?php echo esc_html($counts['consultation']); ?></span>
                <?php endif; ?>
            </button>
            <button class="comm-tab" data-tab="order">
                <span class="tab-icon">📦</span> سفارشات ثبت شده
                <?php if ($counts['order'] > 0) : ?>
                    <span class="tab-badge"><?php echo esc_html($counts['order']); ?></span>
                <?php endif; ?>
            </button>
            <button class="comm-tab" data-tab="lead">
                <span class="tab-icon">🔔</span> شماره‌های ثبت شده
                <?php if ($counts['lead'] > 0) : ?>
                    <span class="tab-badge"><?php echo esc_html($counts['lead']); ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- نوار ابزار (جستجو) -->
        <div class="raha-comm-toolbar">
            <div class="comm-search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" id="commSearchInput" placeholder="جستجوی نام یا شماره...">
            </div>
        </div>

        <!-- محتوای تب‌ها -->
        <div class="raha-comm-content">
            
            <!-- تب مشاوره -->
            <div class="comm-panel is-active" id="panel-consultation">
                <?php raha_render_comm_table('raha_consultation', 'lead_status', 'consultation'); ?>
            </div>

            <!-- تب سفارشات -->
            <div class="comm-panel" id="panel-order">
                <?php raha_render_comm_table('raha_order', 'order_status', 'order'); ?>
            </div>

            <!-- تب لیدها -->
            <div class="comm-panel" id="panel-lead">
                <?php raha_render_comm_table('raha_lead', 'lead_status', 'lead'); ?>
            </div>

        </div>

        <!-- مودال جزئیات -->
        <div class="raha-comm-modal" id="commModal">
            <div class="comm-modal-content">
                <div class="comm-modal-header">
                    <h2 id="modalTitle">جزئیات درخواست</h2>
                    <button type="button" class="comm-modal-close" id="closeModalBtn">✕</button>
                </div>
                <div class="comm-modal-body" id="modalBody">
                    <!-- با JS پر می‌شود -->
                </div>
                <div class="comm-modal-footer">
                    <button type="button" class="btn-save" id="saveModalBtn">💾 ذخیره تغییرات</button>
                </div>
            </div>
        </div>

    </div>

    <script>
        window.RahaCommData = {
            ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_attr(wp_create_nonce('raha_comm_nonce')); ?>'
        };
    </script>
    <?php
}

/**
 * تابع رندر جدول برای هر تب
 */
function raha_comm_statuses($post_type) {
    $statuses = [
        'raha_order' => ['new' => 'جدید', 'review' => 'در حال بررسی', 'progress' => 'در حال انجام', 'done' => 'تکمیل شده', 'canceled' => 'لغو شده'],
        'raha_consultation' => ['new' => 'جدید', 'review' => 'در حال بررسی', 'contacted' => 'تماس گرفته شد', 'scheduled' => 'جلسه تنظیم شد', 'converted' => 'تبدیل شد', 'spam' => 'اسپم'],
        'raha_lead' => ['unread' => 'خوانده نشده', 'read' => 'خوانده شده', 'contacted' => 'تماس گرفته شد', 'following' => 'در حال پیگیری', 'converted' => 'تبدیل شد', 'spam' => 'اسپم'],
    ];
    return $statuses[$post_type] ?? [];
}

function raha_render_comm_table($post_type, $status_meta_key, $type) {
    $posts = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1, // در پروژه‌های بزرگتر باید صفحه‌بندی (WP_Query pagination) اضافه شود
        'orderby'        => 'date',
        'order'          => 'DESC'
    ]);

    if (empty($posts)) {
        echo '<div class="comm-empty">هیچ درخواستی در این بخش ثبت نشده است.</div>';
        return;
    }

    $statuses = [
        'new'              => ['label' => 'جدید', 'color' => 'badge-yellow'],
        'unread'           => ['label' => 'خوانده نشده', 'color' => 'badge-yellow'],
        'read'             => ['label' => 'خوانده شده', 'color' => 'badge-blue'],
        'review'           => ['label' => 'در حال بررسی', 'color' => 'badge-blue'],
        'contacted'        => ['label' => 'تماس گرفته شد', 'color' => 'badge-orange'],
        'scheduled'        => ['label' => 'جلسه تنظیم شد', 'color' => 'badge-purple'],
        'awaiting_payment' => ['label' => 'در انتظار پرداخت', 'color' => 'badge-orange'],
        'progress'         => ['label' => 'در حال انجام', 'color' => 'badge-blue'],
        'done'             => ['label' => 'تکمیل شده', 'color' => 'badge-green'],
        'converted'        => ['label' => 'تبدیل شد', 'color' => 'badge-green'],
        'following'        => ['label' => 'در حال پیگیری', 'color' => 'badge-orange'],
        'canceled'         => ['label' => 'لغو شده', 'color' => 'badge-red'],
        'spam'             => ['label' => 'اسپم', 'color' => 'badge-gray'],
    ];
    ?>
    <table class="wp-list-table widefat fixed striped raha-comm-table">
        <thead>
            <tr>
                <th style="width:20%;">مشتری</th>
                <th style="width:15%;">راه ارتباطی</th>
                <th style="width:25%;">خلاصه</th>
                <th style="width:15%;">وضعیت</th>
                <th style="width:15%;">تاریخ</th>
                <th style="width:10%;">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $post) : 
                $name    = get_post_meta($post->ID, 'lead_name', true) ?: ($post->post_type === 'raha_order' ? get_post_meta($post->ID, 'full_name', true) : '—');
                $contact = get_post_meta($post->ID, 'lead_contact', true) ?: get_post_meta($post->ID, 'contact_value', true);
                $status  = get_post_meta($post->ID, $status_meta_key, true) ?: ($post_type === 'raha_lead' ? 'unread' : 'new');
                
                // خلاصه
                $summary = '';
                if ($type === 'consultation') {
                    $summary = get_post_meta($post->ID, 'lead_service', true) ?: 'مشاوره';
                } elseif ($type === 'order') {
                    $summary = get_post_meta($post->ID, 'service', true) ?: 'سفارش جدید';
                } else {
                    $summary = get_post_meta($post->ID, 'lead_source', true) ?: 'فرم تماس';
                }

                $date = get_the_date('Y/m/d H:i', $post->ID);
                $status_data = $statuses[$status] ?? ['label' => $status, 'color' => 'badge-gray'];
            ?>
                <tr data-id="<?php echo esc_attr($post->ID); ?>" data-type="<?php echo esc_attr($type); ?>" class="comm-row">
                    <td><strong><?php echo esc_html($name); ?></strong></td>
                    <td>
                        <span class="copy-contact" data-contact="<?php echo esc_attr($contact); ?>" title="کپی">
                            <?php echo esc_html($contact); ?> 📋
                        </span>
                    </td>
                    <td><span class="comm-summary"><?php echo esc_html($summary); ?></span></td>
                    <td>
                        <select class="comm-status-select" data-id="<?php echo esc_attr($post->ID); ?>" data-meta="<?php echo esc_attr($status_meta_key); ?>">
                            <?php if (!array_key_exists($status, raha_comm_statuses($post_type))) : ?>
                                <option value="" selected disabled><?php echo esc_html($status); ?></option>
                            <?php endif; ?>
                            <?php foreach (raha_comm_statuses($post_type) as $val => $label) : ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected($status, $val); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><span dir="ltr"><?php echo esc_html($date); ?></span></td>
                    <td>
                        <button type="button" class="btn-view" data-id="<?php echo esc_attr($post->ID); ?>" data-type="<?php echo esc_attr($type); ?>">مشاهده</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/* ============================================================
   AJAX: ذخیره وضعیت جدید
   ============================================================ */
add_action('wp_ajax_raha_update_comm_status', function() {
    if (!Raha_RBAC::can_cap('raha_manage_orders') || !check_ajax_referer('raha_comm_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
    }
    
    $post_id  = absint($_POST['post_id'] ?? 0);
    $post_type = get_post_type($post_id);
    $expected_key = $post_type === 'raha_order' ? 'order_status' : 'lead_status';
    $meta_key = sanitize_key(wp_unslash($_POST['meta_key'] ?? ''));
    $status = sanitize_key(wp_unslash($_POST['status'] ?? ''));
    if (!$post_id || !$post_type || get_post_status($post_id) !== 'publish' || $meta_key !== $expected_key
        || !array_key_exists($status, raha_comm_statuses($post_type))) {
        wp_send_json_error(['message' => 'وضعیت یا نوع درخواست نامعتبر است.'], 400);
    }
    update_post_meta($post_id, $expected_key, $status);
    if ($post_type === 'raha_order') delete_transient('raha_dashboard_stats');
    wp_send_json_success(['message' => 'وضعیت به‌روزرسانی شد.']);
});

/* ============================================================
   AJAX: دریافت جزئیات برای مودال
   ============================================================ */
add_action('wp_ajax_raha_get_comm_details', function() {
    if (!Raha_RBAC::can_cap('raha_manage_orders') || !check_ajax_referer('raha_comm_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
    }
    
    $post_id = absint($_POST['post_id'] ?? 0);
    $type    = sanitize_key(wp_unslash($_POST['type'] ?? ''));
    $post    = get_post($post_id);

    $types = ['order' => 'raha_order', 'consultation' => 'raha_consultation', 'lead' => 'raha_lead'];
    if (!$post || $post->post_status !== 'publish' || !isset($types[$type]) || $post->post_type !== $types[$type]) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.'], 404);
    }

    // دریافت تمام متادیتا
    $meta = get_post_meta($post_id);
    $data = [];
    foreach ($meta as $key => $values) {
        if (strpos($key, '_') === 0) continue; // فیلدهای مخفی
        $data[$key] = maybe_unserialize($values[0]);
    }

    ob_start();
    ?>
    <div class="modal-details-grid">
        <div class="detail-item">
            <span class="detail-label">شناسه:</span>
            <span class="detail-value" dir="ltr">#<?php echo esc_html($post_id); ?></span>
        </div>
        <?php if (!empty($data['lead_name']) || !empty($data['full_name'])) : ?>
        <div class="detail-item">
            <span class="detail-label">نام:</span>
            <span class="detail-value"><?php echo esc_html($data['lead_name'] ?? $data['full_name']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['lead_contact']) || !empty($data['contact_value'])) : ?>
        <div class="detail-item">
            <span class="detail-label">تماس:</span>
            <span class="detail-value" dir="ltr"><?php echo esc_html($data['lead_contact'] ?? $data['contact_value']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['lead_email']) || !empty($data['contact_method'])) : ?>
        <div class="detail-item">
            <span class="detail-label">ایمیل/روش:</span>
            <span class="detail-value" dir="ltr"><?php echo esc_html($data['lead_email'] ?? $data['contact_method']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php 
        // فیلدهای خاص سفارش
        if ($type === 'order') : 
            $order_fields = ['service' => 'خدمت', 'budget' => 'بودجه', 'purpose' => 'هدف', 'business_name' => 'کسب‌وکار'];
            foreach ($order_fields as $k => $label) :
                if (!empty($data[$k])) :
        ?>
            <div class="detail-item full-width">
                <span class="detail-label"><?php echo esc_html($label); ?>:</span>
                <span class="detail-value"><?php echo esc_html($data[$k]); ?></span>
            </div>
        <?php endif; endforeach; endif; ?>

        <?php 
        // فیلدهای مشاوره
        if ($type === 'consultation') : 
            $consult_fields = ['lead_service' => 'موضوع', 'lead_method' => 'روش تماس', 'lead_desc' => 'توضیحات'];
            foreach ($consult_fields as $k => $label) :
                if (!empty($data[$k])) :
        ?>
            <div class="detail-item full-width">
                <span class="detail-label"><?php echo esc_html($label); ?>:</span>
                <span class="detail-value"><?php echo nl2br(esc_html($data[$k])); ?></span>
            </div>
        <?php endif; endforeach; endif; ?>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html, 'title' => 'جزئیات درخواست #' . $post_id]);
});
