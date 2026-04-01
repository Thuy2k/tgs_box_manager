<?php
/**
 * Plugin Name: TGS Box Manager
 * Description: Định danh thùng hàng — Bao trùm mã định danh, scan 1 thùng xử lý tất cả SP bên trong
 * Version: 1.0.0
 * Author: TGS Dev Team
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) exit;

// ── Constants ────────────────────────────────────────────────────────────────
define('TGS_BOX_MGR_VERSION', '1.0.0');
define('TGS_BOX_MGR_DIR', plugin_dir_path(__FILE__));
define('TGS_BOX_MGR_URL', plugin_dir_url(__FILE__));
define('TGS_BOX_MGR_VIEWS', TGS_BOX_MGR_DIR . 'admin-views/');

// Bảng global
if (!defined('TGS_TABLE_GLOBAL_BOX_MANAGER')) {
    define('TGS_TABLE_GLOBAL_BOX_MANAGER', 'wp_global_box_manager');
}

// ── Init ─────────────────────────────────────────────────────────────────────
function tgs_box_mgr_init()
{
    // Dependency check
    if (!class_exists('TGS_Shop_Management') && !defined('TGS_SHOP_PLUGIN_DIR')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>TGS Box Manager</strong> cần plugin <strong>TGS Shop Management</strong> được kích hoạt.</p></div>';
        });
        return;
    }

    // Load includes
    require_once TGS_BOX_MGR_DIR . 'includes/class-box-manager-ajax.php';

    // Register AJAX
    TGS_Box_Manager_Ajax::register();
}
add_action('plugins_loaded', 'tgs_box_mgr_init', 26);

// ── Routes ───────────────────────────────────────────────────────────────────
add_filter('tgs_shop_dashboard_routes', function ($routes) {
    $dir = TGS_BOX_MGR_VIEWS;
    $routes['box-create'] = ['Tạo thùng hàng',    $dir . 'box-create.php'];
    $routes['box-list']   = ['Danh sách thùng',    $dir . 'box-list.php'];
    $routes['box-detail'] = ['Chi tiết thùng',      $dir . 'box-detail.php'];
    return $routes;
});

// ── Sidebar Menu ─────────────────────────────────────────────────────────────
add_action('tgs_shop_advanced_menu', function ($current_view) {
    $views = ['box-create', 'box-list', 'box-detail'];
    $is_active = in_array($current_view, $views);
    $open = $is_active ? ' active open' : '';
    $href = function_exists('tgs_url') ? function ($v) { return tgs_url($v); } : function ($v) {
        return admin_url('admin.php?page=tgs-shop-management&view=' . $v);
    };
    ?>
    <li class="menu-item<?php echo $open; ?>">
        <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-package"></i>
            <div>Định danh thùng hàng</div>
        </a>
        <ul class="menu-sub">
            <li class="menu-item<?php echo $current_view === 'box-create' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('box-create')); ?>" class="menu-link">
                    <div>Tạo thùng mới</div>
                </a>
            </li>
            <li class="menu-item<?php echo $current_view === 'box-list' ? ' active' : ''; ?>">
                <a href="<?php echo esc_url($href('box-list')); ?>" class="menu-link">
                    <div>Danh sách thùng</div>
                </a>
            </li>
        </ul>
    </li>
    <?php
});

// ── Enqueue Assets ───────────────────────────────────────────────────────────
add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'tgs-shop-management') === false) return;

    $view = sanitize_text_field($_GET['view'] ?? '');
    if (strpos($view, 'box-') !== 0) return;

    $loc = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tgs_box_mgr_nonce'),
        'blogId'  => get_current_blog_id(),
    ];

    wp_enqueue_style('tgs-box-mgr-css', TGS_BOX_MGR_URL . 'assets/css/box-manager.css', [], TGS_BOX_MGR_VERSION);

    if ($view === 'box-create') {
        wp_enqueue_script('tgs-box-create', TGS_BOX_MGR_URL . 'assets/js/box-create.js', ['jquery'], TGS_BOX_MGR_VERSION, true);
        wp_localize_script('tgs-box-create', 'tgsBoxMgr', $loc);
    }
    if ($view === 'box-list') {
        wp_enqueue_script('tgs-box-list', TGS_BOX_MGR_URL . 'assets/js/box-list.js', ['jquery'], TGS_BOX_MGR_VERSION, true);
        wp_localize_script('tgs-box-list', 'tgsBoxMgr', $loc);
    }
    if ($view === 'box-detail') {
        wp_enqueue_script('tgs-box-detail', TGS_BOX_MGR_URL . 'assets/js/box-detail.js', ['jquery'], TGS_BOX_MGR_VERSION, true);
        wp_localize_script('tgs-box-detail', 'tgsBoxMgr', $loc);
    }
});
