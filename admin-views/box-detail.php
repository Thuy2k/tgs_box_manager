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

<div class="container-xxl flex-grow-1 container-p-y">
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
            <h6 class="mb-0"><i class="bx bx-scan me-1"></i>Thêm mã định danh vào thùng</h6>
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
</div>
