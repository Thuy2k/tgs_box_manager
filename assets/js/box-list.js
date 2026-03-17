/**
 * box-list.js — Danh sách thùng hàng
 *
 * Handles:
 * - Load list with AJAX
 * - Search + filter by status
 * - Pagination
 * - Row click → go to detail
 *
 * @package tgs_box_manager
 */
(function ($) {
    'use strict';

    const C = window.tgsBoxMgr || {};
    let currentPage = 1;
    let totalPages = 1;

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

    /* ── Status badge helper ─────────────────────────────────────── */

    function statusBadge(status) {
        const map = {
            0: ['bg-label-secondary', 'Nháp'],
            1: ['bg-label-warning', 'Đang đóng gói'],
            2: ['bg-label-info', 'Đã niêm phong'],
            3: ['bg-label-primary', 'Đang vận chuyển'],
            4: ['bg-label-success', 'Đã giao'],
            5: ['bg-label-dark', 'Đã mở'],
            99: ['bg-label-secondary', 'Lưu trữ'],
        };
        const s = map[status] || ['bg-label-secondary', 'N/A'];
        return '<span class="badge ' + s[0] + '">' + s[1] + '</span>';
    }

    /* ── Build detail URL ────────────────────────────────────────── */

    function detailUrl(boxId) {
        // Sneat admin URL pattern
        const base = window.location.href.split('&view=')[0];
        return base + '&view=box-detail&box_id=' + boxId;
    }

    /* ── Load List ───────────────────────────────────────────────── */

    function loadList(page) {
        page = page || 1;
        currentPage = page;

        const search = $('#searchInput').val().trim();
        const status = $('#statusFilter').val();

        $('#boxTableBody').html('<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Đang tải...</td></tr>');

        $.post(C.ajaxUrl, {
            action: 'tgs_box_get_list',
            nonce: C.nonce,
            search: search,
            status: status,
            page: page,
            per_page: 20,
        }, function (res) {
            if (!res.success) {
                showToast('❌ ' + (res.data?.message || 'Lỗi'), 'danger');
                return;
            }

            const d = res.data;
            totalPages = d.pages || 1;
            $('#totalInfo').text('Tổng: ' + d.total + ' thùng');

            if (!d.items.length) {
                $('#boxTableBody').html('<tr><td colspan="8" class="text-center py-4 text-muted">Không có thùng nào.</td></tr>');
                $('#paginationWrap').hide();
                return;
            }

            let html = '';
            const offset = (d.page - 1) * d.per_page;

            d.items.forEach(function (b, i) {
                const qtyStr = b.box_capacity > 0 ? (b.box_actual_qty + ' / ' + b.box_capacity) : (b.box_actual_qty + ' / ∞');
                html += '<tr class="box-row" data-box-id="' + b.box_id + '" style="cursor:pointer;">'
                    + '<td>' + (offset + i + 1) + '</td>'
                    + '<td><code class="fw-bold">' + b.box_code + '</code></td>'
                    + '<td>' + (b.box_title || '-') + '</td>'
                    + '<td><span class="badge bg-label-secondary">' + b.type_label + '</span></td>'
                    + '<td>' + qtyStr + '</td>'
                    + '<td>' + statusBadge(b.box_status) + '</td>'
                    + '<td>' + (b.created_at ? new Date(b.created_at).toLocaleDateString('vi-VN') : '-') + '</td>'
                    + '<td><a href="' + detailUrl(b.box_id) + '" class="btn btn-sm btn-outline-primary"><i class="bx bx-right-arrow-alt"></i></a></td>'
                    + '</tr>';
            });

            $('#boxTableBody').html(html);

            // Pagination
            if (totalPages > 1) {
                $('#paginationWrap').show();
                $('#pageInfo').text('Trang ' + d.page + ' / ' + totalPages);
                $('#btnPrev').prop('disabled', d.page <= 1);
                $('#btnNext').prop('disabled', d.page >= totalPages);
            } else {
                $('#paginationWrap').hide();
            }
        }).fail(function () {
            showToast('❌ Lỗi kết nối server.', 'danger');
        });
    }

    /* ── Row click → detail ──────────────────────────────────────── */

    $(document).on('click', '.box-row td:not(:last-child)', function () {
        const boxId = $(this).closest('tr').data('box-id');
        window.location.href = detailUrl(boxId);
    });

    /* ── Bindings ────────────────────────────────────────────────── */

    $('#btnSearch').on('click', function () { loadList(1); });
    $('#btnRefresh').on('click', function () {
        $('#searchInput').val('');
        $('#statusFilter').val('all');
        loadList(1);
    });

    $('#searchInput').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); loadList(1); }
    });

    $('#statusFilter').on('change', function () { loadList(1); });

    $('#btnPrev').on('click', function () { if (currentPage > 1) loadList(currentPage - 1); });
    $('#btnNext').on('click', function () { if (currentPage < totalPages) loadList(currentPage + 1); });

    /* ── Init ────────────────────────────────────────────────────── */

    loadList(1);

})(jQuery);
