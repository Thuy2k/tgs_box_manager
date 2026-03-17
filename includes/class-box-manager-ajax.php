<?php
/**
 * TGS Box Manager — AJAX Handler
 *
 * Xử lý tất cả AJAX actions:
 *  A. CRUD thùng (create, update, delete)
 *  B. Danh sách & chi tiết
 *  C. Quản lý nội dung thùng (add/remove items)
 *  D. Trạng thái & in nhãn
 *
 * @package tgs_box_manager
 */

if (!defined('ABSPATH')) exit;

class TGS_Box_Manager_Ajax
{
    /**
     * Đăng ký tất cả AJAX actions
     */
    public static function register()
    {
        $actions = [
            // ── A. CRUD thùng ──
            'tgs_box_create_boxes',
            'tgs_box_update_info',
            'tgs_box_delete',

            // ── B. Danh sách & Chi tiết ──
            'tgs_box_get_list',
            'tgs_box_get_detail',

            // ── C. Quản lý nội dung ──
            'tgs_box_add_items',
            'tgs_box_remove_items',
            'tgs_box_search_available_lots',

            // ── D. Trạng thái & In ──
            'tgs_box_update_status',
            'tgs_box_print_label',

            // ── E. Tìm sản phẩm (autocomplete) ──
            'tgs_box_search_products',

            // ── F. Tìm thùng (dùng từ lot_generator) ──
            'tgs_box_search_boxes',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [__CLASS__, $action]);
        }
    }

    /* =========================================================================
     * Helpers
     * ========================================================================= */

    private static function verify()
    {
        if (!check_ajax_referer('tgs_box_mgr_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce không hợp lệ.'], 403);
        }
    }

    private static function json_ok($data = [], $msg = 'OK')
    {
        wp_send_json_success(array_merge(['message' => $msg], $data));
    }

    private static function json_err($msg = 'Lỗi', $code = 400)
    {
        wp_send_json_error(['message' => $msg], $code);
    }

    private static function box_table()
    {
        return defined('TGS_TABLE_GLOBAL_BOX_MANAGER') ? TGS_TABLE_GLOBAL_BOX_MANAGER : 'wp_global_box_manager';
    }

    private static function lots_table()
    {
        return defined('TGS_TABLE_GLOBAL_PRODUCT_LOTS') ? TGS_TABLE_GLOBAL_PRODUCT_LOTS : 'wp_global_product_lots';
    }

    /**
     * Tính check digit EAN-13
     */
    private static function ean13_check_digit($code12)
    {
        $code12 = str_pad((string) $code12, 12, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $code12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Sinh mã thùng dạng EAN-13 (13 chữ số + check digit)
     * Prefix '2' để phân biệt với mã định danh SP (prefix '1'-'9' random)
     * Kiểm tra unique trên cả bảng box lẫn product_lots
     */
    private static function generate_box_code()
    {
        global $wpdb;
        $box_table  = self::box_table();
        $lots_table = self::lots_table();
        $max_attempts = 100;

        for ($i = 0; $i < $max_attempts; $i++) {
            // Prefix '2' + 11 random digits = 12 digits → + check digit = 13 digits EAN-13
            $first = '2';
            $remaining = str_pad(mt_rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
            $code12 = $first . $remaining;
            $check = self::ean13_check_digit($code12);
            $code = $code12 . $check; // 13 digits

            // Kiểm tra unique trên bảng box
            $exists_box = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$box_table} WHERE box_code = %s OR box_barcode = %s",
                $code, $code
            ));
            if ($exists_box) continue;

            // Kiểm tra không trùng với mã định danh SP
            $exists_lot = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$lots_table} WHERE global_product_lot_barcode = %s",
                $code
            ));
            if ($exists_lot) continue;

            return $code;
        }

        // Fallback: timestamp-based EAN-13
        $ts12 = '2' . substr(str_pad(time(), 11, '0', STR_PAD_LEFT), -11);
        return $ts12 . self::ean13_check_digit($ts12);
    }

    /**
     * Cập nhật box_actual_qty (cache count) từ product_lots
     */
    private static function sync_box_qty($box_id)
    {
        global $wpdb;
        $lots = self::lots_table();
        $box  = self::box_table();

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$lots} WHERE global_box_manager_id = %d AND is_deleted = 0",
            $box_id
        ));

        $wpdb->update($box, [
            'box_actual_qty' => $count,
            'updated_at'     => current_time('mysql'),
        ], ['box_id' => $box_id]);

        return $count;
    }

    /**
     * Lấy label trạng thái thùng
     */
    private static function status_label($status)
    {
        $map = [
            0  => 'Nháp',
            1  => 'Đang đóng gói',
            2  => 'Đã niêm phong',
            3  => 'Đang vận chuyển',
            4  => 'Đã giao',
            5  => 'Đã mở',
            99 => 'Lưu trữ',
        ];
        return $map[$status] ?? 'Không xác định';
    }

    /**
     * Lấy label loại thùng
     */
    private static function type_label($type)
    {
        $map = [
            1  => 'Thùng carton',
            2  => 'Pallet',
            3  => 'Khay',
            4  => 'Bao / Bì',
            5  => 'Thùng xốp',
            6  => 'Thùng nhựa',
            7  => 'Thùng gỗ',
            8  => 'Hộp quà / Gift box',
            9  => 'Xe đẩy / Trolley',
            10 => 'Khác',
        ];
        return $map[$type] ?? 'Khác';
    }

    /* =========================================================================
     * A. CRUD THÙNG
     * ========================================================================= */

    /**
     * Tạo N thùng mới (bulk)
     *
     * POST: quantity, box_type, box_capacity, box_title, product_sku,
     *       local_product_name_id, variant_id, box_note
     */
    public static function tgs_box_create_boxes()
    {
        self::verify();
        global $wpdb;

        $qty      = max(1, min(500, intval($_POST['quantity'] ?? 1)));
        $type     = intval($_POST['box_type'] ?? 1);
        $capacity = max(0, intval($_POST['box_capacity'] ?? 0));
        $title    = sanitize_text_field($_POST['box_title'] ?? '');
        $sku      = sanitize_text_field($_POST['product_sku'] ?? '');
        $pid      = intval($_POST['local_product_name_id'] ?? 0);
        $vid      = intval($_POST['variant_id'] ?? 0);
        $note     = sanitize_textarea_field($_POST['box_note'] ?? '');
        $blog_id  = get_current_blog_id();
        $user_id  = get_current_user_id();
        $now      = current_time('mysql');

        $table = self::box_table();
        $created = [];

        for ($i = 0; $i < $qty; $i++) {
            $code = self::generate_box_code();

            $data = [
                'box_code'              => $code,
                'box_barcode'           => $code, // default = box_code
                'box_title'             => $title ?: "Thùng #{$code}",
                'box_type'              => $type,
                'box_capacity'          => $capacity,
                'box_actual_qty'        => 0,
                'product_sku'           => $sku ?: null,
                'local_product_name_id' => $pid ?: null,
                'variant_id'            => $vid ?: null,
                'source_blog_id'        => $blog_id,
                'current_blog_id'       => $blog_id,
                'destination_blog_id'   => null,
                'box_status'            => 0, // draft
                'box_note'              => $note ?: null,
                'box_meta'              => null,
                'user_id'               => $user_id,
                'is_deleted'            => 0,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];

            $wpdb->insert($table, $data);

            if ($wpdb->insert_id) {
                $created[] = [
                    'box_id'   => $wpdb->insert_id,
                    'box_code' => $code,
                    'title'    => $data['box_title'],
                ];
            }
        }

        if (empty($created)) {
            self::json_err('Không tạo được thùng nào. Kiểm tra database.');
        }

        self::json_ok([
            'boxes'   => $created,
            'count'   => count($created),
        ], "Đã tạo " . count($created) . " thùng thành công.");
    }

    /**
     * Cập nhật thông tin thùng (title, note, type, capacity)
     */
    public static function tgs_box_update_info()
    {
        self::verify();
        global $wpdb;

        $box_id = intval($_POST['box_id'] ?? 0);
        if (!$box_id) self::json_err('Thiếu box_id.');

        $table = self::box_table();
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE box_id = %d AND is_deleted = 0",
            $box_id
        ));

        if (!$box) self::json_err('Thùng không tồn tại.');

        $update = ['updated_at' => current_time('mysql')];

        if (isset($_POST['box_title']))    $update['box_title']    = sanitize_text_field($_POST['box_title']);
        if (isset($_POST['box_note']))     $update['box_note']     = sanitize_textarea_field($_POST['box_note']);
        if (isset($_POST['box_type']))     $update['box_type']     = intval($_POST['box_type']);
        if (isset($_POST['box_capacity'])) $update['box_capacity'] = max(0, intval($_POST['box_capacity']));

        $wpdb->update($table, $update, ['box_id' => $box_id]);

        self::json_ok([], 'Đã cập nhật thông tin thùng.');
    }

    /**
     * Soft delete thùng (gỡ hết items trước)
     */
    public static function tgs_box_delete()
    {
        self::verify();
        global $wpdb;

        $box_ids = array_map('intval', (array) ($_POST['box_ids'] ?? []));
        $box_ids = array_filter($box_ids);

        if (empty($box_ids)) self::json_err('Chưa chọn thùng nào.');

        $table = self::box_table();
        $lots  = self::lots_table();
        $now   = current_time('mysql');

        $count = 0;
        foreach ($box_ids as $bid) {
            // Gỡ items khỏi thùng (set global_box_manager_id = NULL)
            $wpdb->update($lots, [
                'global_box_manager_id' => null,
            ], ['global_box_manager_id' => $bid]);

            // Soft delete thùng
            $wpdb->update($table, [
                'is_deleted'     => 1,
                'deleted_at'     => $now,
                'updated_at'     => $now,
                'box_actual_qty' => 0,
            ], ['box_id' => $bid]);

            $count++;
        }

        self::json_ok(['deleted' => $count], "Đã xóa {$count} thùng.");
    }

    /* =========================================================================
     * E. TÌM SẢN PHẨM (Autocomplete cho trang tạo thùng)
     * ========================================================================= */

    /**
     * Tìm sản phẩm local theo tên / SKU / barcode
     *
     * POST: search (keyword)
     * Response: products[] { id, name, sku, barcode, thumbnail }
     */
    public static function tgs_box_search_products()
    {
        self::verify();
        global $wpdb;

        $keyword = sanitize_text_field($_POST['search'] ?? '');
        $blog_id = intval($_POST['blog_id'] ?? get_current_blog_id());

        if (strlen($keyword) < 2) {
            self::json_ok(['products' => []]);
            return;
        }

        $table = defined('TGS_TABLE_LOCAL_PRODUCT_NAME')
            ? TGS_TABLE_LOCAL_PRODUCT_NAME
            : $wpdb->prefix . 'local_product_name';

        $like = '%' . $wpdb->esc_like($keyword) . '%';

        $sql = $wpdb->prepare(
            "SELECT local_product_name_id, local_product_name, local_product_barcode_main,
                    local_product_sku, local_product_unit, local_product_thumbnail
             FROM {$table}
             WHERE is_deleted = 0
               AND (local_product_name LIKE %s OR local_product_barcode_main LIKE %s OR local_product_sku LIKE %s)
             ORDER BY local_product_name ASC
             LIMIT 20",
            $like, $like, $like
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        // Map sang field names đơn giản cho JS
        $products = array_map(function ($r) {
            return [
                'id'        => $r['local_product_name_id'],
                'name'      => $r['local_product_name'],
                'sku'       => $r['local_product_sku'] ?? '',
                'barcode'   => $r['local_product_barcode_main'] ?? '',
                'unit'      => $r['local_product_unit'] ?? '',
                'thumbnail' => $r['local_product_thumbnail'] ?? '',
            ];
        }, $rows ?: []);

        self::json_ok(['products' => $products]);
    }

    /* =========================================================================
     * B. DANH SÁCH & CHI TIẾT
     * ========================================================================= */

    /**
     * Lấy danh sách thùng (phân trang + filter)
     *
     * POST: search, status, page, per_page
     */
    public static function tgs_box_get_list()
    {
        self::verify();
        global $wpdb;

        $table   = self::box_table();
        $search  = sanitize_text_field($_POST['search'] ?? '');
        $status  = $_POST['status'] ?? '';
        $page    = max(1, intval($_POST['page'] ?? 1));
        $per     = max(10, min(100, intval($_POST['per_page'] ?? 20)));
        $offset  = ($page - 1) * $per;

        $where  = "b.is_deleted = 0";
        $params = [];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (b.box_code LIKE %s OR b.box_title LIKE %s OR b.box_barcode LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '' && $status !== 'all') {
            $where .= " AND b.box_status = %d";
            $params[] = intval($status);
        }

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$table} b WHERE {$where}";
        $total = (int) $wpdb->get_var(
            $params ? $wpdb->prepare($count_sql, ...$params) : $count_sql
        );

        // Data
        $pn_table = $wpdb->prefix . 'local_product_name';
        $data_sql = "SELECT b.*, pn.local_product_name AS product_name
                     FROM {$table} b
                     LEFT JOIN {$pn_table} pn ON pn.local_product_name_id = b.local_product_name_id
                     WHERE {$where} ORDER BY b.created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge($params, [$per, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, ...$all_params));

        // Enrich
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'box_id'        => $r->box_id,
                'box_code'      => $r->box_code,
                'box_title'     => $r->box_title,
                'box_type'      => (int) $r->box_type,
                'type_label'    => self::type_label($r->box_type),
                'box_status'    => (int) $r->box_status,
                'status_label'  => self::status_label($r->box_status),
                'box_capacity'  => (int) $r->box_capacity,
                'box_actual_qty' => (int) $r->box_actual_qty,
                'product_sku'   => $r->product_sku,
                'product_name'  => $r->product_name ?: '',
                'source_blog_id' => $r->source_blog_id,
                'current_blog_id' => $r->current_blog_id,
                'created_at'    => $r->created_at,
            ];
        }

        self::json_ok([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per,
            'pages'    => ceil($total / $per),
        ]);
    }

    /**
     * Chi tiết 1 thùng + danh sách mã định danh bên trong
     *
     * POST: box_id
     */
    public static function tgs_box_get_detail()
    {
        self::verify();
        global $wpdb;

        $box_id = intval($_POST['box_id'] ?? 0);
        if (!$box_id) self::json_err('Thiếu box_id.');

        $table = self::box_table();
        $lots  = self::lots_table();

        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE box_id = %d AND is_deleted = 0",
            $box_id
        ));

        if (!$box) self::json_err('Thùng không tồn tại hoặc đã xóa.');

        // Sync actual qty
        self::sync_box_qty($box_id);
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE box_id = %d",
            $box_id
        ));

        // Lấy tên sản phẩm chính (nếu có local_product_name_id)
        $product_name = '';
        if (!empty($box->local_product_name_id)) {
            $pn_table = $wpdb->prefix . 'local_product_name';
            $product_name = $wpdb->get_var($wpdb->prepare(
                "SELECT local_product_name FROM {$pn_table} WHERE local_product_name_id = %d",
                $box->local_product_name_id
            )) ?: '';
        }

        // Lấy danh sách lots bên trong
        // Ghi chú: LEFT JOIN local_product_name dùng prefix blog hiện tại
        // Hợp lệ vì thùng luôn thao tác trên blog tạo ra nó (source_blog_id)
        $variants_table = defined('TGS_TABLE_GLOBAL_PRODUCT_VARIANTS')
            ? TGS_TABLE_GLOBAL_PRODUCT_VARIANTS
            : 'wp_global_product_variants';

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*,
                    pn.local_product_name,
                    pn.local_product_sku,
                    pn.local_product_barcode_main,
                    v.variant_label,
                    v.variant_value
             FROM {$lots} l
             LEFT JOIN {$wpdb->prefix}local_product_name pn
                    ON pn.local_product_name_id = l.local_product_name_id
             LEFT JOIN {$variants_table} v
                    ON v.variant_id = l.variant_id AND v.is_deleted = 0
             WHERE l.global_box_manager_id = %d AND l.is_deleted = 0
             ORDER BY l.global_product_lot_id ASC",
            $box_id
        ));

        $lot_list = [];
        foreach ($items as $item) {
            $status = (int) $item->local_product_lot_is_active;
            $lot_list[] = [
                'lot_id'       => $item->global_product_lot_id,
                'barcode'      => $item->global_product_lot_barcode,
                'product_name' => $item->local_product_name ?: '-',
                'product_sku'  => $item->local_product_sku ?: '-',
                'lot_code'     => $item->lot_code ?: '-',
                'exp_date'     => $item->exp_date,
                'variant'      => $item->variant_label ? ($item->variant_label . ': ' . $item->variant_value) : '-',
                'status'       => $status,
            ];
        }

        self::json_ok([
            'box' => [
                'box_id'            => $box->box_id,
                'box_code'          => $box->box_code,
                'box_barcode'       => $box->box_barcode,
                'box_title'         => $box->box_title,
                'box_type'          => (int) $box->box_type,
                'type_label'        => self::type_label($box->box_type),
                'box_status'        => (int) $box->box_status,
                'status_label'      => self::status_label($box->box_status),
                'box_capacity'      => (int) $box->box_capacity,
                'box_actual_qty'    => (int) $box->box_actual_qty,
                'product_sku'       => $box->product_sku,
                'product_name'      => $product_name,
                'source_blog_id'    => $box->source_blog_id,
                'current_blog_id'   => $box->current_blog_id,
                'destination_blog_id' => $box->destination_blog_id,
                'box_note'          => $box->box_note,
                'sealed_at'         => $box->sealed_at,
                'shipped_at'        => $box->shipped_at,
                'delivered_at'      => $box->delivered_at,
                'opened_at'         => $box->opened_at,
                'created_at'        => $box->created_at,
                'user_id'           => $box->user_id,
            ],
            'lots'  => $lot_list,
            'count' => count($lot_list),
        ]);
    }

    /* =========================================================================
     * C. QUẢN LÝ NỘI DUNG THÙNG
     * ========================================================================= */

    /**
     * Gán mã định danh vào thùng
     *
     * POST: box_id, lot_ids[] hoặc barcode (scan từng cái)
     */
    public static function tgs_box_add_items()
    {
        self::verify();
        global $wpdb;

        $box_id  = intval($_POST['box_id'] ?? 0);
        $lot_ids = array_map('intval', (array) ($_POST['lot_ids'] ?? []));
        $barcode = sanitize_text_field($_POST['barcode'] ?? '');

        if (!$box_id) self::json_err('Thiếu box_id.');

        $table = self::box_table();
        $lots  = self::lots_table();

        // Kiểm tra thùng tồn tại
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE box_id = %d AND is_deleted = 0",
            $box_id
        ));
        if (!$box) self::json_err('Thùng không tồn tại.');

        // Nếu scan barcode → tìm lot
        if ($barcode && empty($lot_ids)) {
            $lot = $wpdb->get_row($wpdb->prepare(
                "SELECT global_product_lot_id, global_box_manager_id
                 FROM {$lots}
                 WHERE global_product_lot_barcode = %s AND is_deleted = 0
                 LIMIT 1",
                $barcode
            ));

            if (!$lot) self::json_err("Không tìm thấy mã định danh: {$barcode}");
            if ($lot->global_box_manager_id && (int) $lot->global_box_manager_id !== $box_id) {
                self::json_err("Mã {$barcode} đã thuộc thùng khác (box_id={$lot->global_box_manager_id}).");
            }
            if ((int) $lot->global_box_manager_id === $box_id) {
                self::json_err("Mã {$barcode} đã có trong thùng này rồi.");
            }

            $lot_ids = [(int) $lot->global_product_lot_id];
        }

        if (empty($lot_ids)) self::json_err('Chưa chọn mã nào để thêm.');

        // Kiểm tra capacity
        if ((int) $box->box_capacity > 0) {
            $current = (int) $box->box_actual_qty;
            if ($current + count($lot_ids) > (int) $box->box_capacity) {
                self::json_err("Thùng đã đầy! Sức chứa: {$box->box_capacity}, hiện tại: {$current}, muốn thêm: " . count($lot_ids));
            }
        }

        $added = 0;
        $skipped = 0;
        foreach ($lot_ids as $lid) {
            // Chỉ gán nếu lot chưa thuộc thùng nào
            $lot = $wpdb->get_row($wpdb->prepare(
                "SELECT global_product_lot_id, global_box_manager_id
                 FROM {$lots}
                 WHERE global_product_lot_id = %d AND is_deleted = 0",
                $lid
            ));

            if (!$lot) { $skipped++; continue; }
            if ($lot->global_box_manager_id && (int) $lot->global_box_manager_id !== $box_id) {
                $skipped++;
                continue;
            }

            $wpdb->update($lots, [
                'global_box_manager_id' => $box_id,
            ], ['global_product_lot_id' => $lid]);

            $added++;
        }

        // Sync qty
        $new_qty = self::sync_box_qty($box_id);

        // Auto chuyển status → packing nếu đang draft
        if ((int) $box->box_status === 0 && $added > 0) {
            $wpdb->update($table, [
                'box_status' => 1,
                'updated_at' => current_time('mysql'),
            ], ['box_id' => $box_id]);
        }

        $msg = "Đã thêm {$added} mã vào thùng.";
        if ($skipped > 0) $msg .= " Bỏ qua {$skipped} mã (đã thuộc thùng khác).";

        self::json_ok([
            'added'      => $added,
            'skipped'    => $skipped,
            'actual_qty' => $new_qty,
        ], $msg);
    }

    /**
     * Gỡ mã định danh khỏi thùng
     *
     * POST: box_id, lot_ids[]
     */
    public static function tgs_box_remove_items()
    {
        self::verify();
        global $wpdb;

        $box_id  = intval($_POST['box_id'] ?? 0);
        $lot_ids = array_map('intval', (array) ($_POST['lot_ids'] ?? []));
        $lot_ids = array_filter($lot_ids);

        if (!$box_id) self::json_err('Thiếu box_id.');
        if (empty($lot_ids)) self::json_err('Chưa chọn mã nào để gỡ.');

        $lots = self::lots_table();
        $removed = 0;

        foreach ($lot_ids as $lid) {
            $affected = $wpdb->update($lots, [
                'global_box_manager_id' => null,
            ], [
                'global_product_lot_id' => $lid,
                'global_box_manager_id' => $box_id,
            ]);
            if ($affected) $removed++;
        }

        $new_qty = self::sync_box_qty($box_id);

        self::json_ok([
            'removed'    => $removed,
            'actual_qty' => $new_qty,
        ], "Đã gỡ {$removed} mã khỏi thùng.");
    }

    /**
     * Tìm mã định danh chưa thuộc thùng nào (để thêm vào thùng)
     *
     * POST: search (barcode/tên SP), box_id, limit
     */
    public static function tgs_box_search_available_lots()
    {
        self::verify();
        global $wpdb;

        $search = sanitize_text_field($_POST['search'] ?? '');
        $limit  = max(5, min(50, intval($_POST['limit'] ?? 20)));

        if (strlen($search) < 2) self::json_err('Nhập ít nhất 2 ký tự.');

        $lots = self::lots_table();
        $like = '%' . $wpdb->esc_like($search) . '%';

        // JOIN local_product_name từ blog hiện tại
        // Thùng luôn thao tác trên blog tạo ra nó nên prefix đúng
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.global_product_lot_id,
                    l.global_product_lot_barcode,
                    l.lot_code,
                    l.exp_date,
                    l.local_product_name_id,
                    l.local_product_lot_is_active,
                    pn.local_product_name,
                    pn.local_product_sku
             FROM {$lots} l
             LEFT JOIN {$wpdb->prefix}local_product_name pn
                    ON pn.local_product_name_id = l.local_product_name_id
             WHERE l.is_deleted = 0
               AND (l.global_box_manager_id IS NULL OR l.global_box_manager_id = 0)
               AND (l.global_product_lot_barcode LIKE %s
                    OR l.lot_code LIKE %s
                    OR pn.local_product_name LIKE %s
                    OR pn.local_product_sku LIKE %s)
             ORDER BY l.global_product_lot_id DESC
             LIMIT %d",
            $like, $like, $like, $like, $limit
        ));

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'lot_id'       => $r->global_product_lot_id,
                'barcode'      => $r->global_product_lot_barcode,
                'product_name' => $r->local_product_name ?: '-',
                'product_sku'  => $r->local_product_sku ?: '-',
                'lot_code'     => $r->lot_code ?: '-',
                'exp_date'     => $r->exp_date,
                'status'       => (int) $r->local_product_lot_is_active,
            ];
        }

        self::json_ok(['items' => $items, 'count' => count($items)]);
    }

    /* =========================================================================
     * D. TRẠNG THÁI & IN
     * ========================================================================= */

    /**
     * Đổi trạng thái thùng
     *
     * POST: box_id, new_status
     */
    public static function tgs_box_update_status()
    {
        self::verify();
        global $wpdb;

        $box_id    = intval($_POST['box_id'] ?? 0);
        $newStatus = intval($_POST['new_status'] ?? -1);

        if (!$box_id) self::json_err('Thiếu box_id.');
        if ($newStatus < 0) self::json_err('Thiếu trạng thái mới.');

        $table = self::box_table();
        $box = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE box_id = %d AND is_deleted = 0",
            $box_id
        ));

        if (!$box) self::json_err('Thùng không tồn tại.');

        $update = [
            'box_status' => $newStatus,
            'updated_at' => current_time('mysql'),
        ];

        // Timestamps theo trạng thái
        $now = current_time('mysql');
        switch ($newStatus) {
            case 2: $update['sealed_at']    = $now; break;
            case 3: $update['shipped_at']   = $now; break;
            case 4: $update['delivered_at'] = $now; break;
            case 5: $update['opened_at']    = $now; break;
        }

        $wpdb->update($table, $update, ['box_id' => $box_id]);

        self::json_ok([
            'box_id'     => $box_id,
            'new_status' => $newStatus,
            'label'      => self::status_label($newStatus),
        ], 'Đã chuyển trạng thái: ' . self::status_label($newStatus));
    }

    /**
     * In nhãn thùng (standalone HTML output với JsBarcode)
     *
     * GET: box_ids (comma-separated), show_qty, show_type, show_status
     */
    public static function tgs_box_print_label()
    {
        // Support cả GET và POST
        if (!check_ajax_referer('tgs_box_mgr_nonce', 'nonce', false)) {
            wp_die('Nonce không hợp lệ.', 'Lỗi', ['response' => 403]);
        }

        global $wpdb;

        $raw_ids = sanitize_text_field($_REQUEST['box_ids'] ?? '');
        $box_ids = array_filter(array_map('intval', explode(',', $raw_ids)));

        if (empty($box_ids)) {
            wp_die('Chưa chọn thùng nào.', 'Lỗi');
        }

        $show_qty    = intval($_REQUEST['show_qty'] ?? 1);
        $show_type   = intval($_REQUEST['show_type'] ?? 1);
        $show_status = intval($_REQUEST['show_status'] ?? 0);

        $table = self::box_table();
        $pn_table = $wpdb->prefix . 'local_product_name';
        $placeholders = implode(',', array_fill(0, count($box_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, pn.local_product_name AS product_name
             FROM {$table} b
             LEFT JOIN {$pn_table} pn ON pn.local_product_name_id = b.local_product_name_id
             WHERE b.box_id IN ({$placeholders}) AND b.is_deleted = 0",
            ...$box_ids
        ));

        // Giữ thứ tự gốc
        $rows_map = [];
        foreach ($rows as $r) $rows_map[$r->box_id] = $r;
        $sorted = [];
        foreach ($box_ids as $id) {
            if (isset($rows_map[$id])) $sorted[] = $rows_map[$id];
        }

        self::output_box_print_html($sorted, [
            'show_qty'    => $show_qty,
            'show_type'   => $show_type,
            'show_status' => $show_status,
        ]);
        exit;
    }

    /**
     * Output HTML trang in nhãn thùng (50×30mm, 1 nhãn/hàng)
     */
    private static function output_box_print_html($boxes, $options = [])
    {
        $show_qty    = !empty($options['show_qty']);
        $show_type   = !empty($options['show_type']);
        $show_status = !empty($options['show_status']);
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>In Nhãn Thùng Hàng</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
            <style>
                @page { size: 70mm 40mm; margin: 0; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                html, body { width: 70mm; height: auto; margin: 0; padding: 0; font-family: Arial, sans-serif; }
                .label-container { display: flex; flex-wrap: wrap; width: 70mm; }
                .box-label {
                    width: 70mm; height: 40mm;
                    padding: 1mm 2mm 1mm 2mm;
                    text-align: center;
                    display: flex; flex-direction: column; justify-content: center; align-items: center;
                    overflow: hidden; background: #fff; border: 1px dashed #ccc;
                    page-break-after: always;
                }
                .box-label svg { width: 60mm !important; height: 14mm !important; max-width: 60mm; max-height: 14mm; flex-shrink: 0; }
                .box-title {
                    font-size: 7pt; font-weight: bold; line-height: 1.2;
                    max-width: 66mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                    margin-top: 0.5mm; margin-bottom: 0.3mm;
                }
                .box-meta {
                    font-size: 5.5pt; color: #333; line-height: 1.1;
                    max-width: 66mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                    margin-bottom: 0.2mm;
                }
                .box-meta-row { display: flex; gap: 3mm; justify-content: center; flex-wrap: wrap; }
                .box-meta-item { font-size: 5.5pt; color: #555; }
                .box-meta-item b { color: #000; }
                .print-actions {
                    position: fixed; top: 10px; right: 10px; z-index: 100;
                    display: flex; gap: 8px;
                }
                .print-btn {
                    padding: 10px 20px; background: #696cff; color: white;
                    border: none; cursor: pointer; border-radius: 6px; font-size: 14px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                }
                .print-btn:hover { background: #5f63e8; }
                .close-btn {
                    padding: 10px 20px; background: #8592a3; color: white;
                    border: none; cursor: pointer; border-radius: 6px; font-size: 14px;
                }
                .close-btn:hover { background: #6d7a8c; }
                .print-summary {
                    position: fixed; top: 10px; left: 10px; z-index: 100;
                    background: #f0f4ff; border: 1px solid #696cff; border-radius: 6px;
                    padding: 8px 14px; font-size: 12px; color: #333;
                }
                @media print {
                    html, body { width: 70mm; margin: 0; padding: 0; }
                    .print-actions, .print-summary { display: none; }
                    .label-container { width: 70mm; }
                    .box-label { border: none; page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="print-actions">
                <button class="print-btn" onclick="window.print()">🖨️ In nhãn thùng</button>
                <button class="close-btn" onclick="window.close()">✕ Đóng</button>
            </div>
            <div class="print-summary">
                📦 <b><?php echo count($boxes); ?></b> nhãn thùng
            </div>
            <div class="label-container">
                <?php foreach ($boxes as $index => $box): ?>
                    <div class="box-label">
                        <svg id="box-barcode-<?php echo $index; ?>"></svg>
                        <div class="box-title"><?php echo esc_html($box->box_title ?: 'Thùng #' . $box->box_code); ?></div>
                        <div class="box-meta-row">
                            <?php if ($show_qty): ?>
                                <span class="box-meta-item"><b>SL:</b> <?php echo (int)$box->box_actual_qty; ?><?php echo (int)$box->box_capacity > 0 ? '/' . (int)$box->box_capacity : ''; ?></span>
                            <?php endif; ?>
                            <?php if ($show_type): ?>
                                <span class="box-meta-item"><b>Loại:</b> <?php echo esc_html(self::type_label($box->box_type)); ?></span>
                            <?php endif; ?>
                            <?php if ($show_status): ?>
                                <span class="box-meta-item"><b>TT:</b> <?php echo esc_html(self::status_label($box->box_status)); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php
                            $sp_display = '';
                            if (!empty($box->product_name)) {
                                $sp_display = $box->product_name;
                                if (!empty($box->product_sku)) $sp_display .= ' (' . $box->product_sku . ')';
                            } elseif (!empty($box->product_sku)) {
                                $sp_display = $box->product_sku;
                            }
                        ?>
                        <?php if ($sp_display): ?>
                            <div class="box-meta">SP: <?php echo esc_html($sp_display); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                <?php foreach ($boxes as $index => $box):
                    $bc = $box->box_barcode ?: $box->box_code;
                ?>
                try {
                    JsBarcode("#box-barcode-<?php echo $index; ?>", "<?php echo esc_js($bc); ?>", {
                        format: "EAN13",
                        width: 2,
                        height: 48,
                        displayValue: true,
                        fontSize: 14,
                        font: "Arial",
                        fontOptions: "bold",
                        margin: 0, marginTop: 0, marginBottom: 0,
                        textMargin: 2
                    });
                } catch(e) {
                    document.getElementById('box-barcode-<?php echo $index; ?>').parentNode.innerHTML +=
                        '<div style="font-size:16pt;font-weight:bold;letter-spacing:3px;font-family:monospace;"><?php echo esc_js($bc); ?></div>';
                }
                <?php endforeach; ?>
            </script>
        </body>
        </html>
        <?php
    }

    /* =========================================================================
     * F. Tìm thùng (dùng từ lot_generator – Gắn vào thùng)
     * ========================================================================= */

    /**
     * Tìm thùng còn chỗ – dùng cho modal "Gắn vào thùng" trên trang lot_generator
     *
     * POST/GET: keyword, nonce (tgs_box_mgr_nonce)
     * Trả về danh sách thùng chưa đầy (capacity=0 = không giới hạn)
     */
    public static function tgs_box_search_boxes()
    {
        self::verify();
        global $wpdb;

        $keyword = sanitize_text_field($_REQUEST['keyword'] ?? '');
        $table   = self::box_table();

        $where = "b.is_deleted = 0 AND b.box_status IN (0, 1)"; // draft hoặc đang đóng
        $params = [];

        if ($keyword !== '') {
            $like    = '%' . $wpdb->esc_like($keyword) . '%';
            $where  .= " AND (b.box_code LIKE %s OR b.box_title LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT b.box_id, b.box_code, b.box_title, b.box_type,
                       b.box_capacity, b.box_actual_qty, b.box_status
                FROM {$table} b
                WHERE {$where}
                ORDER BY b.updated_at DESC
                LIMIT 20";

        if ($params) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $rows = $wpdb->get_results($sql);

        $type_labels = [
            1 => 'Thùng carton', 2 => 'Pallet', 3 => 'Khay nhựa',
            4 => 'Bao / Bì', 5 => 'Thùng xốp', 6 => 'Thùng nhựa',
            7 => 'Thùng gỗ', 8 => 'Hộp quà', 9 => 'Xe đẩy', 10 => 'Khác',
        ];

        $boxes = [];
        foreach ($rows as $r) {
            $cap   = (int) $r->box_capacity;
            $qty   = (int) $r->box_actual_qty;
            $remain = $cap > 0 ? ($cap - $qty) : -1; // -1 = unlimited

            // Bỏ qua thùng đã đầy
            if ($cap > 0 && $remain <= 0) continue;

            $boxes[] = [
                'box_id'     => (int) $r->box_id,
                'box_code'   => $r->box_code,
                'box_title'  => $r->box_title,
                'box_type'   => $type_labels[(int) $r->box_type] ?? 'Khác',
                'capacity'   => $cap,
                'actual_qty' => $qty,
                'remaining'  => $remain, // -1 = không giới hạn
                'status'     => (int) $r->box_status,
            ];
        }

        self::json_ok(['boxes' => $boxes]);
    }
}
