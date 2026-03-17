/**
 * box-create.js — Tạo thùng hàng mới
 *
 * Handles:
 * - Product search autocomplete (optional)
 * - Form validation + submit
 * - Show result table
 * - Reset form
 *
 * @package tgs_box_manager
 */
(function ($) {
    'use strict';

    const C = window.tgsBoxMgr || {};
    let searchTimer = null;

    /* ── Toast ────────────────────────────────────────────────────── */

    function showToast(msg, type) {
        type = type || 'dark';
        const bgMap = { success: '#16a34a', danger: '#dc2626', dark: '#1e293b', info: '#696cff', warning: '#f59e0b' };
        const bg = bgMap[type] || bgMap.dark;
        const duration = type === 'danger' ? 5000 : 3000;
        const $t = $('<div class="box-toast">' + msg + '</div>').css('background', bg);
        $('body').append($t);
        setTimeout(() => $t.addClass('show'), 10);
        setTimeout(() => { $t.removeClass('show'); setTimeout(() => $t.remove(), 300); }, duration);
    }

    /* ── Auto-fill tên thùng ngẫu nhiên ─────────────────────────── */

    const typeNames = {
        1: 'Thùng carton', 2: 'Pallet', 3: 'Khay', 4: 'Bao',
        5: 'Thùng xốp', 6: 'Thùng nhựa', 7: 'Thùng gỗ',
        8: 'Hộp quà', 9: 'Xe đẩy', 10: 'Khác'
    };

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function generateBoxTitle() {
        const typeVal = $('#boxType').val();
        const typeName = typeNames[typeVal] || 'Thùng';
        const now = new Date();
        const d = pad2(now.getDate()) + '/' + pad2(now.getMonth() + 1) + '/' + now.getFullYear();
        const t = pad2(now.getHours()) + ':' + pad2(now.getMinutes());
        const seq = Math.floor(Math.random() * 900 + 100); // 100-999
        return typeName + ' — ' + d + ' ' + t + ' #' + seq;
    }

    // Auto-fill khi load trang
    if (!$('#boxTitle').val()) {
        $('#boxTitle').val(generateBoxTitle());
    }

    // Auto-fill khi đổi loại thùng (chỉ fill nếu user chưa tự sửa hoặc đang là tên auto)
    let userEditedTitle = false;
    $('#boxTitle').on('input', function () { userEditedTitle = true; });
    $('#boxType').on('change', function () {
        if (!userEditedTitle) {
            $('#boxTitle').val(generateBoxTitle());
        }
    });

    /* ── Product Search (optional — SP chính trong thùng) ────────── */

    $('#productSearch').on('input', function () {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();
        if (q.length < 2) { $('#productDropdown').hide(); return; }

        searchTimer = setTimeout(function () {
            $.post(C.ajaxUrl, {
                action: 'tgs_box_search_products',
                nonce: C.nonce,
                search: q
            }, function (res) {
                if (!res.success || !res.data.products || !res.data.products.length) {
                    $('#productDropdown').html('<div class="box-dd-item box-dd-empty">Không tìm thấy sản phẩm</div>').show();
                    return;
                }

                let html = '';
                res.data.products.forEach(function (p) {
                    html += '<div class="box-dd-item" data-id="' + p.id + '" data-sku="' + (p.sku || '') + '">'
                        + '<div class="box-dd-name">' + p.name + '</div>'
                        + '<div class="box-dd-meta">'
                        + (p.sku ? '<span><i class="bx bx-barcode"></i> ' + p.sku + '</span>' : '')
                        + (p.barcode ? '<span class="ms-2"><i class="bx bx-scan"></i> ' + p.barcode + '</span>' : '')
                        + '</div></div>';
                });
                $('#productDropdown').html(html).show();
            }).fail(function() {
                $('#productDropdown').html('<div class="box-dd-item box-dd-empty">Lỗi kết nối</div>').show();
            });
        }, 300);
    });

    $(document).on('click', '.box-dd-item:not(.box-dd-empty)', function () {
        const id = $(this).data('id');
        const sku = $(this).data('sku');
        const name = $(this).find('.box-dd-name').text();
        $('#productId').val(id);
        $('#productSku').val(sku);
        $('#productSearch').val(name);
        $('#productDropdown').hide();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.box-search-wrap').length) {
            $('#productDropdown').hide();
        }
    });

    /* ── Submit Form ─────────────────────────────────────────────── */

    $('#boxCreateForm').on('submit', function (e) {
        e.preventDefault();

        const qty = parseInt($('#boxQuantity').val()) || 1;
        if (qty < 1 || qty > 500) {
            showToast('❌ Số lượng thùng phải từ 1-500.', 'danger');
            return;
        }

        const $btn = $('#btnCreate');
        $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>Đang tạo...');

        $.post(C.ajaxUrl, {
            action: 'tgs_box_create_boxes',
            nonce: C.nonce,
            quantity: qty,
            box_type: $('#boxType').val(),
            box_capacity: $('#boxCapacity').val(),
            box_title: $('#boxTitle').val(),
            product_sku: $('#productSku').val(),
            local_product_name_id: $('#productId').val(),
            box_note: $('#boxNote').val(),
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="bx bx-package me-1"></i>Tạo thùng');

            if (!res.success) {
                showToast('❌ ' + (res.data?.message || 'Không xác định'), 'danger');
                return;
            }

            showToast('✅ ' + res.data.message, 'success');

            // Show result table
            const boxes = res.data.boxes || [];
            let html = '';
            const detailUrl = (typeof tgs_url === 'function')
                ? function (id) { return tgs_url('box-detail') + '&box_id=' + id; }
                : function (id) { return '?page=tgs-shop-management&view=box-detail&box_id=' + id; };

            boxes.forEach(function (b, i) {
                html += '<tr>'
                    + '<td>' + (i + 1) + '</td>'
                    + '<td><code class="fw-bold">' + b.box_code + '</code></td>'
                    + '<td>' + b.title + '</td>'
                    + '<td><a href="' + detailUrl(b.box_id) + '" class="btn btn-sm btn-outline-primary">'
                    + '<i class="bx bx-right-arrow-alt me-1"></i>Xem / Đóng gói</a></td>'
                    + '</tr>';
            });

            $('#resultTableBody').html(html);
            $('#resultCount').text(boxes.length + ' thùng');
            $('#resultCard').show();
        }).fail(function () {
            $btn.prop('disabled', false).html('<i class="bx bx-package me-1"></i>Tạo thùng');
            showToast('❌ Lỗi kết nối server.', 'danger');
        });
    });

    /* ── Reset Form ──────────────────────────────────────────────── */

    $('#btnReset').on('click', function () {
        $('#boxType').val(1);
        $('#boxCapacity').val(0);
        $('#boxQuantity').val(1);
        userEditedTitle = false;
        $('#boxTitle').val(generateBoxTitle());
        $('#productSearch').val('');
        $('#productId').val('');
        $('#productSku').val('');
        $('#boxNote').val('');
        $('#resultCard').hide();
        showToast('Đã làm mới form.', 'info');
    });

})(jQuery);
