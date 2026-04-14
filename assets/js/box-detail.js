/**
 * box-detail.js — Chi tiết thùng hàng
 *
 * Handles:
 * - Load box info + contents
 * - Scan barcode → add item
 * - Search & pick items → add
 * - Checkbox select → remove items
 * - Status change buttons
 * - Print label
 * - Delete box
 *
 * @package tgs_box_manager
 */
(function ($) {
    'use strict';

    const C = window.tgsBoxMgr || {};
    const boxId = parseInt($('#detailBoxId').val()) || 0;
    let allLots = [];
    let pendingLotIds = []; // lots đã chọn qua tìm kiếm, chờ thêm

    if (!boxId) {
        $('#lotsTableBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Không có box_id. Vui lòng chọn thùng từ danh sách.</td></tr>');
    }

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

    /* ── Error Banner (hiển thị lỗi rõ ràng trên giao diện) ────── */

    function showError(msg) {
        let $alert = $('#boxErrorBanner');
        if (!$alert.length) {
            $alert = $('<div id="boxErrorBanner" class="alert alert-danger alert-dismissible fade show box-error-banner" role="alert" style="display:none;">' +
                '<i class="bx bx-error-circle me-2"></i><strong>Lỗi:</strong> <span class="box-error-msg"></span>' +
                '<button type="button" class="btn-close" onclick="$(this).parent().slideUp(200);"></button></div>');
            // Chèn ngay sau card thông tin thùng
            $('.container-xxl').find('.card').first().before($alert);
        }
        $alert.find('.box-error-msg').text(msg);
        $alert.slideDown(200);

        // Auto-hide sau 8 giây
        clearTimeout($alert.data('timer'));
        $alert.data('timer', setTimeout(function () { $alert.slideUp(200); }, 8000));
    }

    function hideError() {
        $('#boxErrorBanner').slideUp(200);
    }

    /* ── Status badge ────────────────────────────────────────────── */

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

    function lotStatusBadge(status) {
        const map = {
            100: ['bg-label-warning', 'Trống'],
            22: ['bg-label-secondary', 'Mới sinh'],
            2: ['bg-label-warning', 'Chờ duyệt'],
            1: ['bg-label-success', 'Kho'],
            0: ['bg-label-dark', 'Đã bán'],
            '-1': ['bg-label-danger', 'Hư hỏng'],
        };
        const s = map[status] || ['bg-label-secondary', status];
        return '<span class="badge ' + s[0] + '">' + s[1] + '</span>';
    }

    /* ── Load Detail ─────────────────────────────────────────────── */

    function loadDetail() {
        if (!boxId) return;

        $.post(C.ajaxUrl, {
            action: 'tgs_box_get_detail',
            nonce: C.nonce,
            box_id: boxId
        }, function (res) {
            if (!res.success) {
                showToast('❌ ' + (res.data?.message || 'Không xác định'), 'danger');
                return;
            }

            const d = res.data;
            const b = d.box;

            // Info
            $('#boxCode').text(b.box_code);
            $('#infoTitle').text(b.box_title || '-');
            $('#infoType').text(b.type_label);
            $('#infoStatus').html(statusBadge(b.box_status));
            $('#infoQty').text(b.box_actual_qty + ' / ' + (b.box_capacity > 0 ? b.box_capacity : '∞'));
            $('#infoDate').text(b.created_at ? new Date(b.created_at).toLocaleString('vi-VN') : '-');
            $('#infoNote').text(b.box_note || '-');
            // Hiển thị tên SP chính (nếu có)
            let spDisplay = '-';
            if (b.product_name) {
                spDisplay = b.product_name;
                if (b.product_sku) spDisplay += ' (' + b.product_sku + ')';
            } else if (b.product_sku) {
                spDisplay = b.product_sku;
            }
            $('#infoSku').text(spDisplay);
            $('#infoShop').text('Blog #' + (b.current_blog_id || b.source_blog_id));

            // Highlight current status button
            $('.box-status-btn').removeClass('active btn-warning btn-info btn-primary btn-success btn-secondary')
                .addClass('btn-outline-warning btn-outline-info btn-outline-primary btn-outline-success btn-outline-secondary');

            // Contents
            allLots = d.lots || [];
            renderLots();
            updateItemCount();
        }).fail(function (xhr) {
            const msg = xhr.responseJSON?.data?.message || 'Lỗi kết nối, không tải được chi tiết thùng.';
            showError(msg);
            showToast('❌ ' + msg, 'danger');
        });
    }

    /* ── Render Lots Table ───────────────────────────────────────── */

    function renderLots() {
        if (!allLots.length) {
            $('#lotsTableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="bx bx-package me-1"></i>Thùng trống. Quét hoặc tìm mã để thêm vào.</td></tr>');
            return;
        }

        let html = '';
        allLots.forEach(function (lot, idx) {
            const expStr = lot.exp_date && lot.exp_date !== '0000-00-00'
                ? new Date(lot.exp_date).toLocaleDateString('vi-VN')
                : '-';

            html += '<tr class="lot-row" data-lot-id="' + lot.lot_id + '">'
                + '<td class="lot-check-cell"><input type="checkbox" class="form-check-input lot-check" value="' + lot.lot_id + '" /></td>'
                + '<td>' + (idx + 1) + '</td>'
                + '<td><code>' + lot.barcode + '</code></td>'
                + '<td>' + lot.product_name + '</td>'
                + '<td>' + lot.product_sku + '</td>'
                + '<td>' + lot.variant + '</td>'
                + '<td>' + lot.lot_code + '</td>'
                + '<td>' + expStr + '</td>'
                + '<td>' + lotStatusBadge(lot.status) + '</td>'
                + '</tr>';
        });

        $('#lotsTableBody').html(html);
    }

    function updateItemCount() {
        $('#itemCountBadge').text(allLots.length + ' mã');
    }

    /* ── Row click → toggle checkbox ─────────────────────────────── */

    $(document).on('click', '.lot-row td:not(.lot-check-cell)', function () {
        const $cb = $(this).closest('tr').find('.lot-check');
        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    });

    $(document).on('change', '.lot-check', function () {
        const $row = $(this).closest('tr');
        if ($(this).is(':checked')) {
            $row.addClass('box-row-selected');
        } else {
            $row.removeClass('box-row-selected');
        }
        syncRemoveBtn();
    });

    $('#checkAll').on('change', function () {
        const checked = $(this).is(':checked');
        $('.lot-check').prop('checked', checked).trigger('change');
    });

    function syncRemoveBtn() {
        const count = $('.lot-check:checked').length;
        $('#removeCount').text(count);
        $('#btnRemoveSelected').prop('disabled', count === 0);
        $('#printLotCount').text(count);
        $('#btnPrintLots').prop('disabled', count === 0);
    }

    /* ── Scan Barcode → Add ──────────────────────────────────────── */

    let scanBuffer = '';
    let scanTimer = null;
    let isSubmitting = false;

    function addByBarcode(barcode) {
        barcode = barcode || $('#scanBarcodeInput').val().trim();
        if (!barcode || isSubmitting) return;

        isSubmitting = true;
        const $input = $('#scanBarcodeInput');
        $input.prop('disabled', true).css('opacity', 0.6);

        $.post(C.ajaxUrl, {
            action: 'tgs_box_add_items',
            nonce: C.nonce,
            box_id: boxId,
            barcode: barcode,
        }, function (res) {
            isSubmitting = false;
            $input.prop('disabled', false).css('opacity', 1).val('').focus();

            if (!res.success) {
                showError(res.data?.message || 'Lỗi không xác định');
                showToast('❌ ' + (res.data?.message || 'Lỗi'), 'danger');
                return;
            }
            showToast('✅ ' + res.data.message, 'success');
            hideError();
            loadDetail();
        }).fail(function (xhr) {
            isSubmitting = false;
            $input.prop('disabled', false).css('opacity', 1).val('').focus();
            const msg = xhr.responseJSON?.data?.message || 'Lỗi kết nối server.';
            showError(msg);
            showToast('❌ ' + msg, 'danger');
        });
    }

    // Barcode scanner: tự nhận diện input nhanh (scanner gửi ký tự rất nhanh rồi Enter hoặc dừng)
    $('#scanBarcodeInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(scanTimer);
            addByBarcode();
            return;
        }
    });

    // Auto-submit sau 150ms không có ký tự mới (scanner gõ rất nhanh, dừng = xong)
    $('#scanBarcodeInput').on('input', function () {
        clearTimeout(scanTimer);
        const val = $(this).val().trim();
        // Chỉ auto-submit nếu barcode đủ dài (>= 5 ký tự)
        if (val.length >= 5) {
            scanTimer = setTimeout(function () {
                addByBarcode(val);
            }, 200);
        }
    });

    $('#btnScanAdd').on('click', function () { addByBarcode(); });

    /* ── Search Available Lots → Pick ────────────────────────────── */

    let searchTimer = null;

    $('#searchLotsInput').on('input', function () {
        clearTimeout(searchTimer);
        const q = $(this).val().trim();
        if (q.length < 2) { $('#searchLotsDropdown').hide(); return; }

        searchTimer = setTimeout(function () {
            $.post(C.ajaxUrl, {
                action: 'tgs_box_search_available_lots',
                nonce: C.nonce,
                search: q,
                limit: 20,
            }, function (res) {
                if (!res.success || !res.data.items.length) {
                    $('#searchLotsDropdown').html('<div class="box-dd-item box-dd-empty">Không tìm thấy mã nào khả dụng</div>').show();
                    return;
                }

                let html = '';
                res.data.items.forEach(function (lot) {
                    const isPending = pendingLotIds.indexOf(lot.lot_id) !== -1;
                    html += '<div class="box-dd-item box-dd-lot' + (isPending ? ' box-dd-selected' : '') + '" data-lot-id="' + lot.lot_id + '" data-barcode="' + lot.barcode + '" data-name="' + lot.product_name + '">'
                        + '<div class="d-flex justify-content-between align-items-center">'
                        + '<div>'
                        + '<div class="box-dd-name"><code>' + lot.barcode + '</code> — ' + lot.product_name + '</div>'
                        + '<div class="box-dd-meta"><span>SKU: ' + lot.product_sku + '</span><span>Lô: ' + lot.lot_code + '</span></div>'
                        + '</div>'
                        + '<div><i class="bx ' + (isPending ? 'bx-check-circle text-success' : 'bx-plus-circle text-primary') + '" style="font-size:20px;"></i></div>'
                        + '</div></div>';
                });

                $('#searchLotsDropdown').html(html).show();
            }).fail(function (xhr) {
                const msg = xhr.responseJSON?.data?.message || 'Lỗi kết nối';
                showError(msg);
                $('#searchLotsDropdown').html('<div class="box-dd-item box-dd-empty">❌ ' + msg + '</div>').show();
            });
        }, 300);
    });

    // Click lot in dropdown → toggle pending
    $(document).on('click', '.box-dd-lot', function () {
        const lotId = $(this).data('lot-id');
        const barcode = $(this).data('barcode');
        const name = $(this).data('name');
        const idx = pendingLotIds.indexOf(lotId);

        if (idx !== -1) {
            pendingLotIds.splice(idx, 1);
            $(this).removeClass('box-dd-selected').find('.bx').removeClass('bx-check-circle text-success').addClass('bx-plus-circle text-primary');
        } else {
            pendingLotIds.push(lotId);
            $(this).addClass('box-dd-selected').find('.bx').removeClass('bx-plus-circle text-primary').addClass('bx-check-circle text-success');
        }

        updatePendingChips();
    });

    function updatePendingChips() {
        if (pendingLotIds.length === 0) {
            $('#pendingItemsWrap').hide();
            $('#btnAddSelected').prop('disabled', true);
            return;
        }

        let html = '';
        pendingLotIds.forEach(function (id) {
            html += '<span class="badge bg-label-primary me-1 mb-1">ID:' + id + ' <i class="bx bx-x box-chip-remove" data-lot-id="' + id + '" style="cursor:pointer;"></i></span>';
        });
        html += '<span class="badge bg-primary">' + pendingLotIds.length + ' đã chọn</span>';

        $('#pendingItemsChips').html(html);
        $('#pendingItemsWrap').show();
        $('#btnAddSelected').prop('disabled', false);
    }

    // Remove chip
    $(document).on('click', '.box-chip-remove', function () {
        const lotId = $(this).data('lot-id');
        const idx = pendingLotIds.indexOf(lotId);
        if (idx !== -1) pendingLotIds.splice(idx, 1);
        updatePendingChips();
    });

    // Add selected → box
    $('#btnAddSelected').on('click', function () {
        if (!pendingLotIds.length) return;

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.post(C.ajaxUrl, {
            action: 'tgs_box_add_items',
            nonce: C.nonce,
            box_id: boxId,
            lot_ids: pendingLotIds,
        }, function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                showError(res.data?.message || 'Lỗi không xác định');
                showToast('❌ ' + (res.data?.message || 'Lỗi'), 'danger');
                return;
            }

            showToast('✅ ' + res.data.message, 'success');
            hideError();
            pendingLotIds = [];
            updatePendingChips();
            $('#searchLotsInput').val('');
            $('#searchLotsDropdown').hide();
            loadDetail();
        }).fail(function (xhr) {
            $btn.prop('disabled', false);
            const msg = xhr.responseJSON?.data?.message || 'Lỗi kết nối server.';
            showError(msg);
            showToast('❌ ' + msg, 'danger');
        });
    });

    // Close dropdown on outside click
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.box-search-wrap').length) {
            $('#searchLotsDropdown').hide();
        }
    });

    /* ── Remove Items ────────────────────────────────────────────── */

    $('#btnRemoveSelected').on('click', function () {
        const ids = [];
        $('.lot-check:checked').each(function () { ids.push(parseInt($(this).val())); });
        if (!ids.length) return;

        if (!confirm('Gỡ ' + ids.length + ' mã khỏi thùng?')) return;

        $.post(C.ajaxUrl, {
            action: 'tgs_box_remove_items',
            nonce: C.nonce,
            box_id: boxId,
            lot_ids: ids,
        }, function (res) {
            if (!res.success) {
                showError(res.data?.message || 'Lỗi');
                showToast('❌ ' + (res.data?.message || 'Lỗi'), 'danger');
                return;
            }
            showToast('✅ ' + res.data.message, 'success');
            hideError();
            loadDetail();
        }).fail(function (xhr) {
            const msg = xhr.responseJSON?.data?.message || 'Lỗi kết nối server.';
            showError(msg);
            showToast('❌ ' + msg, 'danger');
        });
    });

    /* ── Status Change ───────────────────────────────────────────── */

    $(document).on('click', '.box-status-btn', function () {
        const newStatus = parseInt($(this).data('status'));
        const label = $(this).text().trim();

        if (!confirm('Chuyển trạng thái thùng sang: ' + label + '?')) return;

        $.post(C.ajaxUrl, {
            action: 'tgs_box_update_status',
            nonce: C.nonce,
            box_id: boxId,
            new_status: newStatus,
        }, function (res) {
            if (!res.success) {
                showError(res.data?.message || 'Lỗi');
                showToast('❌ ' + (res.data?.message || 'Lỗi'), 'danger');
                return;
            }
            showToast('✅ ' + res.data.message, 'success');
            hideError();
            loadDetail();
        }).fail(function (xhr) {
            const msg = xhr.responseJSON?.data?.message || 'Lỗi kết nối server.';
            showError(msg);
            showToast('❌ ' + msg, 'danger');
        });
    });

    /* ── Print Config Toggle Buttons ──────────────────────────────── */

    $(document).on('click', '.print-opt-toggle', function () {
        const isActive = $(this).data('active') === 1;
        $(this).data('active', isActive ? 0 : 1);
        $(this).toggleClass('active', !isActive);
        const $icon = $(this).find('.toggle-icon');
        if (!isActive) {
            $icon.removeClass('bx-x').addClass('bx-check');
        } else {
            $icon.removeClass('bx-check').addClass('bx-x');
        }
    });

    /* ── Print Label ─────────────────────────────────────────────── */

    $('#btnPrintLabel').on('click', function () {
        if (!boxId) { showToast('⚠️ Không có thùng để in.', 'warning'); return; }

        const showQty    = $('#optShowQty').data('active') ? 1 : 0;
        const showType   = $('#optShowType').data('active') ? 1 : 0;
        const showStatus = $('#optShowStatus').data('active') ? 1 : 0;

        const url = C.ajaxUrl + '?' + $.param({
            action: 'tgs_box_print_label',
            nonce: C.nonce,
            box_ids: boxId,
            show_qty: showQty,
            show_type: showType,
            show_status: showStatus
        });

        window.open(url, '_blank', 'width=800,height=600');
    });
    /* ── Print Lot Barcodes (in mã định danh đã chọn) ────────── */

    $('#btnPrintLots').on('click', function () {
        const ids = [];
        $('.lot-check:checked').each(function () { ids.push(parseInt($(this).val())); });
        if (!ids.length) { showToast('⚠️ Chưa chọn mã nào.', 'warning'); return; }

        // Lấy barcode từ allLots
        const barcodes = [];
        ids.forEach(function (id) {
            const lot = allLots.find(function (l) { return parseInt(l.lot_id) === id; });
            if (lot && lot.barcode) barcodes.push(lot.barcode);
        });

        if (!barcodes.length) { showToast('❌ Không tìm thấy barcode.', 'danger'); return; }

        const showName    = $('#optLotShowName').data('active') ? 1 : 0;
        const showPrice   = $('#optLotShowPrice').data('active') ? 1 : 0;
        const showVariant = $('#optLotShowVariant').data('active') ? 1 : 0;
        const showLot     = $('#optLotShowLot').data('active') ? 1 : 0;

        const url = C.ajaxUrl + '?' + $.param({
            action: 'tgs_box_print_lot_barcodes',
            nonce: C.nonce,
            barcodes: barcodes.join(','),
            show_name: showName,
            show_price: showPrice,
            show_variant: showVariant,
            show_lot: showLot
        });

        window.open(url, '_blank', 'width=800,height=600');
    });
    /* ── Camera Scan Modal (Mobile-optimized) ─────────────────────── */

    let boxScanner = null;
    let scannerCooldown = false;
    let scannedItems = [];        // [{ barcode, lot_id, product_name, product_sku, status, valid }]
    let cameraActive = false;
    let boxCapacity = 0;          // 0 = vô hạn
    let boxCurrentQty = 0;

    // Open modal
    $('#btnOpenCameraScan').on('click', function () {
        const $modal = $('#modalBoxCameraScan');
        if (!$modal.hasClass('show')) {
            new bootstrap.Modal($modal[0]).show();
        }
    });

    $('#btnBoxScanOpenCam').on('click', function () {
        const $modal = $('#modalBoxCameraScan');
        if ($modal.hasClass('show')) {
            startBoxCamera();
        } else {
            new bootstrap.Modal($modal[0]).show();
        }
    });

    // Close modal when clicking on self-drawn backdrop (outside dialog)
    $(document).on('click', '#modalBoxCameraScan', function (e) {
        if (e.target === this) {
            const inst = bootstrap.Modal.getInstance(this);
            if (inst) inst.hide();
        }
    });

    $(document).on('shown.bs.modal', '#modalBoxCameraScan', function () {
        // Fill box info from current loaded data
        const boxInfo = getBoxInfo();
        boxCapacity = boxInfo.capacity;
        boxCurrentQty = boxInfo.actualQty;

        $('#scanBoxProduct').text(boxInfo.productName || 'Chưa chỉ định SP');
        $('#scanBoxSku').text(boxInfo.sku ? 'SKU: ' + boxInfo.sku : '');
        updateScanQtyDisplay();

        // Auto-open camera on mobile
        if (window.innerWidth <= 768) {
            setTimeout(function () { startBoxCamera(); }, 400);
        }

        // Focus input
        $('#boxScanManualInput').focus();
    });

    // On modal hide → stop camera
    $(document).on('hidden.bs.modal', '#modalBoxCameraScan', function () {
        stopBoxCamera();
        // If items were confirmed, reload detail
        if (scannedItems.length === 0) {
            // nothing to do
        }
    });

    function getBoxInfo() {
        // Extract from currently rendered detail
        const qtyText = $('#infoQty').text(); // e.g. "5 / 10" or "0 / ∞"
        const parts = qtyText.split('/').map(s => s.trim());
        const actualQty = parseInt(parts[0]) || 0;
        const cap = parts[1] === '∞' ? 0 : (parseInt(parts[1]) || 0);
        return {
            capacity: cap,
            actualQty: actualQty,
            productName: $('#infoSku').text(),
            sku: '',
        };
    }

    function updateScanQtyDisplay() {
        const pendingValid = scannedItems.filter(i => i.valid).length;
        const total = boxCurrentQty + pendingValid;
        $('#scanCurrentQty').text(total);
        $('#scanCapacity').text(boxCapacity > 0 ? boxCapacity : '∞');

        if (boxCapacity > 0) {
            const remaining = Math.max(0, boxCapacity - total);
            $('#scanRemaining').text(remaining).toggleClass('text-danger', remaining === 0).toggleClass('text-success', remaining > 0);
        } else {
            $('#scanRemaining').text('∞');
        }
    }

    function startBoxCamera() {
        if (cameraActive) return;

        // Check if TGSBarcodeScanner is available
        if (typeof TGSBarcodeScanner === 'undefined') {
            showScanAlert('Thư viện quét mã chưa được tải. Vui lòng tải lại trang.');
            return;
        }

        boxScanner = new TGSBarcodeScanner({
            containerId: 'boxScanCameraPreview',
            onSuccess: function (result) {
                onCameraScan(result.text);
            },
            onError: function (err) {
                // Camera failed → clean up preview, show placeholder
                cameraActive = false;
                showScanAlert('Không thể mở camera. ' + (err || 'Vui lòng cấp quyền camera hoặc dùng HTTPS.'));
                $('#boxScanCameraPreview').removeClass('camera-active').html(
                    '<div class="box-scan-camera-placeholder">'
                    + '<i class="bx bx-error" style="font-size:48px; color:#dc2626; opacity:0.5;"></i>'
                    + '<div class="text-muted mt-2" style="font-size:13px;">Camera không khả dụng</div>'
                    + '<div class="text-muted" style="font-size:11px;">Dùng ô nhập liệu bên dưới để quét/nhập mã</div>'
                    + '</div>'
                );
                $('#boxScanCamStatus').html('<i class="bx bx-error me-1 text-danger"></i>Camera lỗi');
                $('#btnToggleCamera').html('<i class="bx bx-video me-1"></i>Thử lại camera').removeClass('btn-outline-secondary').addClass('btn-outline-primary');
            },
            onStatusChange: function (status, msg) {
                const $s = $('#boxScanCamStatus');
                if (status === 'scanning') {
                    $s.html('<i class="bx bx-radio-circle-marked me-1 text-success"></i>Đang quét…');
                } else if (status === 'starting') {
                    $s.html('<i class="bx bx-loader-alt bx-spin me-1"></i>' + msg);
                } else if (status === 'error') {
                    $s.html('<i class="bx bx-error me-1 text-danger"></i>' + msg);
                } else {
                    $s.html('<i class="bx bx-info-circle me-1"></i>' + msg);
                }
            }
        });

        boxScanner.start();
        cameraActive = true;
        $('#boxScanCameraPreview').addClass('camera-active');
        $('#btnToggleCamera').html('<i class="bx bx-video-off me-1"></i>Tắt camera').removeClass('btn-outline-primary').addClass('btn-outline-secondary');
    }

    function stopBoxCamera() {
        if (boxScanner) {
            boxScanner.stop();
            boxScanner = null;
        }
        cameraActive = false;
        $('#boxScanCameraPreview').removeClass('camera-active').html('<div class="box-scan-camera-placeholder"><i class="bx bx-camera" style="font-size:48px; opacity:0.3;"></i><div class="text-muted mt-2" style="font-size:13px;">Camera đã tắt</div></div>');
        $('#boxScanCamStatus').html('<i class="bx bx-info-circle me-1"></i>Camera tắt');
        $('#btnToggleCamera').html('<i class="bx bx-video me-1"></i>Bật camera').removeClass('btn-outline-secondary').addClass('btn-outline-primary');
    }

    // Toggle camera
    $('#btnToggleCamera').on('click', function () {
        if (cameraActive) {
            stopBoxCamera();
        } else {
            startBoxCamera();
        }
    });

    // Camera scan callback with cooldown
    function onCameraScan(barcode) {
        if (scannerCooldown || !barcode) return;

        // Pause scanning during cooldown
        scannerCooldown = true;
        if (boxScanner) boxScanner.isScanning = false;

        // Show cooldown
        let countdown = 1.5;
        $('#boxScanCooldown').show();
        $('#boxScanCooldownText').text('Đã quét: ' + barcode + ' — tiếp tục sau ' + countdown.toFixed(1) + 's…');

        const cdInterval = setInterval(function () {
            countdown -= 0.1;
            if (countdown <= 0) {
                clearInterval(cdInterval);
                scannerCooldown = false;
                if (boxScanner) boxScanner.isScanning = true;
                // Restart native scan loop if needed
                if (boxScanner && boxScanner.detector && !boxScanner.isIOS) {
                    boxScanner.scanNativeLoop();
                }
                $('#boxScanCooldown').hide();
            } else {
                $('#boxScanCooldownText').text('Đã quét: ' + barcode + ' — tiếp tục sau ' + countdown.toFixed(1) + 's…');
            }
        }, 100);

        // Process the scanned barcode
        processScannedBarcode(barcode);
    }

    // Process: validate via AJAX, add to list
    function processScannedBarcode(barcode) {
        // Check duplicate in pending list
        if (scannedItems.some(i => i.barcode === barcode)) {
            showScanAlert('Mã "' + barcode + '" đã có trong danh sách quét rồi.');
            return;
        }

        // Check capacity
        if (boxCapacity > 0) {
            const pendingValid = scannedItems.filter(i => i.valid).length;
            if (boxCurrentQty + pendingValid >= boxCapacity) {
                showScanAlert('Thùng đã đầy! Sức chứa: ' + boxCapacity);
                return;
            }
        }

        // AJAX validate
        $.post(C.ajaxUrl, {
            action: 'tgs_box_validate_barcode',
            nonce: C.nonce,
            box_id: boxId,
            barcode: barcode,
        }, function (res) {
            if (!res.success) {
                showScanAlert(res.data?.message || 'Mã không hợp lệ');
                // Still add to list but mark as invalid for visibility
                scannedItems.push({
                    barcode: barcode,
                    lot_id: null,
                    product_name: '-',
                    product_sku: '-',
                    status: 'error',
                    statusMsg: res.data?.message || 'Không hợp lệ',
                    valid: false,
                });
                renderScanList();
                return;
            }

            hideScanAlert();
            const d = res.data;
            scannedItems.push({
                barcode: barcode,
                lot_id: d.lot_id,
                product_name: d.product_name,
                product_sku: d.product_sku,
                status: 'ok',
                statusMsg: 'Hợp lệ',
                valid: true,
            });
            renderScanList();
            updateScanQtyDisplay();
        }).fail(function () {
            showScanAlert('Lỗi kết nối khi kiểm tra mã: ' + barcode);
        });
    }

    // Manual input
    $('#boxScanManualInput').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = $(this).val().trim();
            if (val) {
                processScannedBarcode(val);
                $(this).val('').focus();
            }
        }
    });

    let manualScanTimer = null;
    $('#boxScanManualInput').on('input', function () {
        clearTimeout(manualScanTimer);
        const val = $(this).val().trim();
        if (val.length >= 5) {
            manualScanTimer = setTimeout(() => {
                processScannedBarcode(val);
                $('#boxScanManualInput').val('').focus();
            }, 200);
        }
    });

    $('#btnBoxScanManualAdd').on('click', function () {
        const val = $('#boxScanManualInput').val().trim();
        if (val) {
            processScannedBarcode(val);
            $('#boxScanManualInput').val('').focus();
        }
    });

    // Render scanned list
    function renderScanList() {
        const $body = $('#boxScanListBody');
        const validCount = scannedItems.filter(i => i.valid).length;

        $('#scanListCount').text(scannedItems.length);
        $('#scanConfirmCount').text(validCount);
        $('#btnScanConfirm').prop('disabled', validCount === 0);
        $('#btnScanClearAll').toggle(scannedItems.length > 0);

        if (!scannedItems.length) {
            $body.html('<tr><td colspan="4" class="text-center text-muted py-3">Chưa quét mã nào</td></tr>');
            return;
        }

        let html = '';
        scannedItems.forEach(function (item, idx) {
            const statusClass = item.valid ? 'text-success' : 'text-danger';
            const statusIcon = item.valid ? 'bx-check-circle' : 'bx-x-circle';

            html += '<tr class="box-scan-row ' + (item.valid ? '' : 'box-scan-row-error') + '" data-idx="' + idx + '">'
                + '<td>' + (idx + 1) + '</td>'
                + '<td><code style="font-size:11px;">' + item.barcode + '</code>'
                + (item.valid ? '<div style="font-size:10px; color:#888;">' + item.product_name + '</div>' : '')
                + '</td>'
                + '<td class="' + statusClass + '" style="font-size:11px;" title="' + item.statusMsg + '"><i class="bx ' + statusIcon + '"></i></td>'
                + '<td><button class="btn btn-sm btn-link text-danger p-0 btn-scan-remove" data-idx="' + idx + '"><i class="bx bx-x"></i></button></td>'
                + '</tr>';
        });

        $body.html(html);
    }

    // Remove from scanned list
    $(document).on('click', '.btn-scan-remove', function (e) {
        e.stopPropagation();
        const idx = parseInt($(this).data('idx'));
        scannedItems.splice(idx, 1);
        renderScanList();
        updateScanQtyDisplay();
    });

    // Clear all
    $('#btnScanClearAll').on('click', function () {
        scannedItems = [];
        renderScanList();
        updateScanQtyDisplay();
        hideScanAlert();
    });

    // Alert helpers
    function showScanAlert(msg) {
        $('#boxScanAlertMsg').text(msg);
        $('#boxScanAlert').show();
        clearTimeout($('#boxScanAlert').data('timer'));
        $('#boxScanAlert').data('timer', setTimeout(function () { $('#boxScanAlert').hide(); }, 6000));
    }

    function hideScanAlert() {
        $('#boxScanAlert').hide();
    }

    // Confirm → batch add to box
    $('#btnScanConfirm').on('click', function () {
        const validItems = scannedItems.filter(i => i.valid);
        if (!validItems.length) return;

        const lotIds = validItems.map(i => i.lot_id);
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Đang gán…');

        $.post(C.ajaxUrl, {
            action: 'tgs_box_add_items',
            nonce: C.nonce,
            box_id: boxId,
            lot_ids: lotIds,
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="bx bx-check me-1"></i>Xác nhận gán <span id="scanConfirmCount">0</span> mã');

            if (!res.success) {
                showScanAlert(res.data?.message || 'Lỗi khi gán mã vào thùng');
                return;
            }

            showToast('✅ ' + res.data.message, 'success');

            // Clear scanned list
            scannedItems = [];
            renderScanList();

            // Close modal
            const $modal = $('#modalBoxCameraScan');
            const bsModal = bootstrap.Modal.getInstance($modal[0]);
            if (bsModal) bsModal.hide();

            // Reload detail
            loadDetail();
        }).fail(function (xhr) {
            $btn.prop('disabled', false).html('<i class="bx bx-check me-1"></i>Xác nhận gán <span id="scanConfirmCount">' + validItems.length + '</span> mã');
            showScanAlert(xhr.responseJSON?.data?.message || 'Lỗi kết nối server.');
        });
    });

    /* ── Delete Box ──────────────────────────────────────────────── */

    $('#btnDeleteBox').on('click', function () {
        if (!confirm('Xóa thùng này? Các mã bên trong sẽ được gỡ khỏi thùng.')) return;

        $.post(C.ajaxUrl, {
            action: 'tgs_box_delete',
            nonce: C.nonce,
            box_ids: [boxId],
        }, function (res) {
            if (!res.success) {
                showToast('❌ ' + (res.data?.message || 'Lỗi'), 'danger');
                return;
            }
            showToast('✅ Đã xóa thùng. Đang chuyển về danh sách...', 'success');
            setTimeout(function () {
                const base = window.location.href.split('&view=')[0];
                window.location.href = base + '&view=box-list';
            }, 1500);
        });
    });

    /* ── Init ────────────────────────────────────────────────────── */

    if (boxId) loadDetail();

})(jQuery);
