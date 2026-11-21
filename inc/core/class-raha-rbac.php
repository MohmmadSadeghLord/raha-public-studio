<?php
if (!defined('ABSPATH')) exit;

class Raha_RBAC {

    public static function init() {
        add_action('init', [__CLASS__, 'register_roles'], 5);
    }

    public static function register_roles() {
        // نقش مدیر ارشد (همه دسترسی‌ها)
        $super_admin = get_role('administrator');
        if ($super_admin) {
            foreach (['raha_manage_all', 'raha_access_studio', 'raha_manage_orders', 'raha_manage_tickets', 'raha_view_invoices'] as $capability) {
                if (!$super_admin->has_cap($capability)) $super_admin->add_cap($capability);
            }
        }

        // نقش مدیر عملیات
        add_role('raha_manager', 'مدیر عملیات', [
            'read' => true,
            'raha_access_studio' => true,
            'raha_manage_orders' => true,
            'raha_view_reports' => true,
            'raha_manage_projects' => true,
            'raha_view_invoices' => true,
        ]);

        // نقش پشتیبانی
        add_role('raha_support', 'پشتیبان', [
            'read' => true,
            'raha_access_studio' => true,
            'raha_manage_tickets' => true,
            'raha_view_users' => true,
        ]);

        // نقش مالی
        add_role('raha_finance', 'مدیر مالی', [
            'read' => true,
            'raha_access_studio' => true,
            'raha_manage_invoices' => true,
            'raha_manage_payments' => true,
            'raha_view_reports' => true,
        ]);

        // add_role does not update capabilities for roles that already exist.
        foreach (['raha_manager', 'raha_support', 'raha_finance'] as $role_name) {
            $role = get_role($role_name);
            if ($role && !$role->has_cap('raha_access_studio')) $role->add_cap('raha_access_studio');
        }
    }

    /**
     * بررسی دقیق مجوزها با ساختار Resource:Action
     * نمونه: Raha_RBAC::can('orders', 'update-status')
     */
    public static function can($resource, $action) {
        $user = wp_get_current_user();
        if (!$user->exists()) return false;
        
        if (current_user_can('manage_options')) return true;

        $map = [
            'orders' => ['view' => 'raha_manage_orders', 'update-status' => 'raha_manage_orders'],
            'tickets' => ['view' => 'raha_manage_tickets', 'update' => 'raha_manage_tickets'],
            'invoices' => ['view' => 'raha_view_invoices', 'update' => 'raha_manage_invoices'],
            'reports' => ['view' => 'raha_view_reports'],
            'users' => ['view' => 'raha_view_users'],
            'projects' => ['manage' => 'raha_manage_projects'],
        ];
        $capability = $map[$resource][$action] ?? null;
        if ($resource === 'invoices' && $action === 'view' && current_user_can('raha_manage_invoices')) return true;
        return $capability ? current_user_can($capability) : false;
    }

    public static function can_cap($capability) {
        return current_user_can('manage_options') || current_user_can($capability);
    }
}
