<?php
/**
 * Chi tiết thùng hàng
 *
 * URL: ?page=tgs-shop-management&view=box-detail&box_id=XXX
 *
 * Hiển thị:
 * - Thông tin thùng (code, title, status, qty)
 * - Toolbar: trạng thái, in nhãn, xóa
 * - Scan / tìm nhanh mã định danh để thêm vào thùng
 * - Danh sách mã bên trong → checkbox gỡ
 *
 * @package tgs_box_manager
 */

if (!defined('ABSPATH')) exit;

$box_id = intval($_GET['box_id'] ?? 0);
?>

<!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bx bx-detail me-2"></i>Chi tiết thùng hàng
        </h4>
        <div>
            <a href="<?php echo function_exists('tgs_url') ? tgs_url('box-list') : '#'; ?>" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i>Quay lại
            </a>
            <a href="<?php echo function_exists('tgs_url') ? tgs_url('box-create') : '#'; ?>" class="btn btn-primary ms-1">
                <i class="bx bx-plus me-1"></i>Tạo thùng mới
            </a>
        </div>
    </div>

    <input type="hidden" id="detailBoxId" value="<?php echo $box_id; ?>" />

    <!-- Thông tin thùng -->
    <div class="card mb-4 box-info-card">
        <div class="card-body p-0">
            <div class="d-flex flex-wrap">
                <!-- Cột trái: Mã thùng nổi bật -->
                <div class="box-info-code-col d-flex flex-column align-items-center justify-content-center">
                    <div class="box-info-code-label">MÃ THÙNG</div>
                    <div class="box-info-code-value" id="boxCode">-</div>
                    <div id="infoStatus" class="mt-2"><span class="badge bg-label-secondary">-</span></div>
                </div>
                <!-- Cột phải: Chi tiết -->
                <div class="flex-grow-1 p-3">
                    <div class="d-flex align-items-center mb-3">
                        <h5 class="fw-bold mb-0 me-2" id="infoTitle" style="font-size:16px;">-</h5>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="box-info-item">
                                <div class="box-info-item-icon"><i class="bx bx-category"></i></div>
                                <div>
                                    <div class="box-info-item-label">Loại thùng</div>
                                    <div class="box-info-item-value" id="infoType">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="box-info-item">
                                <div class="box-info-item-icon"><i class="bx bx-package"></i></div>
                                <div>
                                    <div class="box-info-item-label">SL / Sức chứa</div>
                                    <div class="box-info-item-value" id="infoQty">0 / ∞</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="box-info-item">
                                <div class="box-info-item-icon"><i class="bx bx-calendar"></i></div>
                                <div>
                                    <div class="box-info-item-label">Ngày tạo</div>
                                    <div class="box-info-item-value" id="infoDate">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="box-info-item">
                                <div class="box-info-item-icon"><i class="bx bx-store"></i></div>
                                <div>
                                    <div class="box-info-item-label">Shop</div>
                                    <div class="box-info-item-value" id="infoShop">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="box-info-item">
                                <div class="box-info-item-icon"><i class="bx bx-barcode"></i></div>
                                <div>
                                    <div class="box-info-item-label">SP chính</div>
                                    <div class="box-info-item-value" id="infoSku">-</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8 col-12">
                            <div class="box-info-item">
                                <div class="box-info-item-icon"><i class="bx bx-note"></i></div>
                                <div>
                                    <div class="box-info-item-label">Ghi chú</div>
                                    <div class="box-info-item-value text-muted" id="infoNote" style="font-style:italic;">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar: Trạng thái -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted fw-semibold" style="font-size:13px;"><i class="bx bx-transfer me-1"></i>Chuyển trạng thái:</span>
                <button class="btn btn-sm btn-outline-warning box-status-btn" data-status="1" title="Đang đóng gói">
                    <i class="bx bx-box me-1"></i>Đóng gói
                </button>
                <button class="btn btn-sm btn-outline-info box-status-btn" data-status="2" title="Niêm phong">
                    <i class="bx bx-lock me-1"></i>Niêm phong
                </button>
                <button class="btn btn-sm btn-outline-primary box-status-btn" data-status="3" title="Vận chuyển">
                    <i class="bx bx-car me-1"></i>Vận chuyển
                </button>
                <button class="btn btn-sm btn-outline-success box-status-btn" data-status="4" title="Đã giao">
                    <i class="bx bx-check-circle me-1"></i>Đã giao
                </button>
                <button class="btn btn-sm btn-outline-secondary box-status-btn" data-status="5" title="Đã mở">
                    <i class="bx bx-lock-open me-1"></i>Đã mở
                </button>
                <div class="vr"></div>
                <button class="btn btn-sm btn-info" id="btnPrintLabel">
                    <i class="bx bx-printer me-1"></i>In nhãn thùng
                </button>
                <button class="btn btn-sm btn-danger" id="btnDeleteBox">
                    <i class="bx bx-trash me-1"></i>Xóa thùng
                </button>
            </div>
        </div>
    </div>

    <!-- Cấu hình in nhãn -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="text-muted fw-semibold" style="font-size:13px;"><i class="bx bx-cog me-1"></i>Hiển thị trên nhãn in:</span>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optShowQty" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Số lượng
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optShowType" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Loại thùng
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle" id="optShowStatus" data-active="0">
                    <i class="bx bx-x me-1 toggle-icon"></i>Trạng thái
                </button>
                <span class="text-muted ms-2" style="font-size:11px;">Bấm để bật/tắt • Áp dụng khi in nhãn thùng</span>
            </div>
        </div>
    </div>

    <!-- Thêm mã vào thùng -->
    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-scan me-1"></i>Thêm mã định danh vào thùng</h6>
                <button class="btn btn-sm btn-primary" id="btnOpenCameraScan" type="button">
                    <i class="bx bx-camera me-1"></i>Quét camera
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <!-- Cách 1: Quét mã định danh -->
                <div class="col-md-5">
                    <label class="form-label">Quét mã định danh</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bx-barcode"></i></span>
                        <input type="text" id="scanBarcodeInput" class="form-control" placeholder="Quét mã định danh — tự động thêm…" autofocus />
                        <button class="btn btn-primary" id="btnScanAdd" type="button">
                            <i class="bx bx-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-1 text-center pt-4">
                    <span class="text-muted fw-semibold">hoặc</span>
                </div>
                <!-- Cách 2: Tìm & chọn nhanh -->
                <div class="col-md-5">
                    <label class="form-label">Tìm & chọn nhanh</label>
                    <div class="position-relative box-search-wrap">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <input type="text" id="searchLotsInput" class="form-control" placeholder="Gõ tên SP, SKU, barcode, mã lô…" />
                        </div>
                        <div id="searchLotsDropdown" class="box-search-dropdown" style="display:none;"></div>
                    </div>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-success w-100" id="btnAddSelected" disabled>
                        <i class="bx bx-plus-circle"></i>
                    </button>
                </div>
            </div>
            <!-- Danh sách đã chọn (chưa thêm) -->
            <div id="pendingItemsWrap" class="mt-3" style="display:none;">
                <div class="d-flex flex-wrap gap-1" id="pendingItemsChips"></div>
            </div>
        </div>
    </div>

    <!-- Nội dung thùng (danh sách mã bên trong) -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bx bx-list-check me-1"></i>Mã định danh trong thùng</h6>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-label-primary" id="itemCountBadge">0 mã</span>
                    <button class="btn btn-sm btn-info" id="btnPrintLots" disabled>
                        <i class="bx bx-printer me-1"></i>In mã định danh (<span id="printLotCount">0</span>)
                    </button>
                    <button class="btn btn-sm btn-outline-danger" id="btnRemoveSelected" disabled>
                        <i class="bx bx-minus-circle me-1"></i>Gỡ đã chọn (<span id="removeCount">0</span>)
                    </button>
                </div>
            </div>
            <!-- Cấu hình in mã định danh -->
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="text-muted fw-semibold" style="font-size:12px;"><i class="bx bx-cog me-1"></i>Nhãn in mã định danh:</span>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optLotShowName" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Tên SP
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optLotShowPrice" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Giá bán
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optLotShowVariant" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Biến thể
                </button>
                <button type="button" class="btn btn-sm print-opt-toggle active" id="optLotShowLot" data-active="1">
                    <i class="bx bx-check me-1 toggle-icon"></i>Lô / HSD
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"><input type="checkbox" class="form-check-input" id="checkAll" /></th>
                        <th>#</th>
                        <th>Mã định danh</th>
                        <th>Sản phẩm</th>
                        <th>SKU</th>
                        <th>Biến thể</th>
                        <th>Lô</th>
                        <th>HSD</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="lotsTableBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

<!-- ═══════════════════════════════════════════════════════════════════
     MODAL: Quét camera mã định danh (tối ưu mobile)
     ═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalBoxCameraScan" tabindex="-1" data-bs-backdrop="false" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-scrollable box-scan-modal-dialog">
        <div class="modal-content box-scan-modal-content">
            <!-- Header -->
            <div class="modal-header box-scan-header py-2">
                <h6 class="modal-title fw-bold mb-0">
                    <i class="bx bx-scan me-1"></i>Quét mã định danh mới vào
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0 box-scan-body">
                <!-- Box info badges -->
                <div class="box-scan-info px-3 py-2">
                    <span class="badge bg-label-primary me-1" id="scanBoxProduct">-</span>
                    <span class="badge bg-label-secondary" id="scanBoxSku">-</span>
                </div>
                <div class="box-scan-qty-bar px-3 pb-2">
                    <span class="text-muted" style="font-size:12px;">Hiện có:</span>
                    <strong id="scanCurrentQty">0</strong> / <strong id="scanCapacity">∞</strong>
                    <span class="ms-2 text-muted" style="font-size:12px;">Có thể thêm:</span>
                    <strong class="text-success" id="scanRemaining">∞</strong>
                </div>

                <!-- Camera preview -->
                <div id="boxScanCameraPreview" class="box-scan-camera-preview">
                    <div class="box-scan-camera-placeholder">
                        <i class="bx bx-camera" style="font-size:48px; opacity:0.3;"></i>
                        <div class="text-muted mt-2" style="font-size:13px;">Bấm "Chụp ảnh" hoặc mở camera bên dưới</div>
                    </div>
                </div>

                <!-- Camera status + toggle -->
                <div class="box-scan-cam-controls px-3 py-2 d-flex align-items-center gap-2">
                    <span id="boxScanCamStatus" class="text-muted" style="font-size:12px;">
                        <i class="bx bx-info-circle me-1"></i>Camera tắt
                    </span>
                    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnToggleCamera" type="button">
                        <i class="bx bx-video-off me-1"></i>Tắt camera
                    </button>
                </div>

                <!-- Scan cooldown status -->
                <div id="boxScanCooldown" class="box-scan-cooldown px-3" style="display:none;">
                    <div class="alert alert-success py-1 px-3 mb-0" style="font-size:12px;">
                        <i class="bx bx-check-circle me-1"></i>
                        <span id="boxScanCooldownText">Đã quét — tiếp tục sau 1.5s…</span>
                    </div>
                </div>

                <!-- Manual barcode input -->
                <div class="px-3 py-2">
                    <label class="form-label mb-1" style="font-size:12px;">Quét hoặc nhập mã định danh</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="boxScanManualInput" class="form-control" placeholder="Quét hoặc nhập mã định da…" autocomplete="off" />
                        <button class="btn btn-primary" id="btnBoxScanManualAdd" type="button">
                            <i class="bx bx-plus me-1"></i>Thêm
                        </button>
                        <button class="btn btn-outline-secondary" id="btnBoxScanOpenCam" type="button" title="Bật camera">
                            <i class="bx bx-camera"></i>
                        </button>
                    </div>
                </div>

                <!-- Error/Warning banner -->
                <div id="boxScanAlert" class="px-3" style="display:none;">
                    <div class="alert alert-danger py-1 px-3 mb-2" style="font-size:12px;">
                        <i class="bx bx-error-circle me-1"></i><span id="boxScanAlertMsg"></span>
                    </div>
                </div>

                <!-- Scanned list -->
                <div class="px-3 pt-1 pb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size:12px; font-weight:600;">
                            Đã quét: <span class="badge bg-primary" id="scanListCount">0</span> mã
                        </span>
                        <button class="btn btn-sm btn-outline-danger" id="btnScanClearAll" type="button" style="font-size:11px; display:none;">
                            <i class="bx bx-trash me-1"></i>Xóa hết
                        </button>
                    </div>
                    <div class="table-responsive box-scan-table-wrap">
                        <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30px;">#</th>
                                    <th>Barcode</th>
                                    <th style="width:70px;">Trạng thái</th>
                                    <th style="width:40px;">Xoá</th>
                                </tr>
                            </thead>
                            <tbody id="boxScanListBody">
                                <tr><td colspan="4" class="text-center text-muted py-3">Chưa quét mã nào</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Info note -->
                <div class="px-3 pb-2">
                    <div class="alert alert-info py-1 px-3 mb-0" style="font-size:11px;">
                        <i class="bx bx-info-circle me-1"></i>
                        Chỉ chấp nhận mã có trạng thái hợp lệ (không phải mã trống 22).<br>
                        Mã đã thuộc thùng khác sẽ bị từ chối.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer box-scan-footer py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success" id="btnScanConfirm" disabled>
                    <i class="bx bx-check me-1"></i>Xác nhận gán <span id="scanConfirmCount">0</span> mã
                </button>
            </div>
        </div>
    </div>
</div>
