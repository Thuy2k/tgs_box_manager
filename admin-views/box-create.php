<?php
/**
 * Tạo thùng hàng mới
 *
 * Flow:
 * 1. Nhập thông tin (loại, sức chứa, tên, SP, ghi chú)
 * 2. Chọn số lượng thùng cần tạo
 * 3. Bấm "Tạo thùng" → sinh N mã thùng
 * 4. Bảng kết quả → click vào từng thùng để xem chi tiết
 *
 * @package tgs_box_manager
 */

if (!defined('ABSPATH')) exit;
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-package me-2"></i>Tạo thùng hàng mới
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Tạo mã thùng để nhóm các mã định danh sản phẩm. Scan thùng → xử lý tất cả SP bên trong.</p>
        </div>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('box-list') : '#'; ?>" class="btn btn-outline-secondary">
            <i class="bx bx-list-ul me-1"></i>DS Thùng
        </a>
    </div>

    <!-- Form Card -->
    <div class="card">
        <div class="card-body">
            <form id="boxCreateForm" autocomplete="off">

                <!-- ① Loại thùng & Sức chứa -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">1</span>
                            <label class="form-label fw-semibold mb-0">Loại thùng</label>
                        </div>
                        <select id="boxType" class="form-select">
                            <option value="1" selected>📦 Thùng carton</option>
                            <option value="2">🏗️ Pallet</option>
                            <option value="3">🗄️ Khay</option>
                            <option value="4">🛍️ Bao / Bì</option>
                            <option value="5">🧊 Thùng xốp</option>
                            <option value="6">🥡 Thùng nhựa</option>
                            <option value="7">🧰 Thùng gỗ</option>
                            <option value="8">📫 Hộp quà / Gift box</option>
                            <option value="9">🛒 Xe đẩy / Trolley</option>
                            <option value="10">📋 Khác</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">Sức chứa (tối đa)</label>
                            <span class="badge bg-label-secondary ms-2" style="font-size:10px;">0 = không giới hạn</span>
                        </div>
                        <input type="number" id="boxCapacity" class="form-control" value="0" min="0" placeholder="0" />
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">2</span>
                            <label class="form-label fw-semibold mb-0">Số lượng thùng tạo <span class="text-danger">*</span></label>
                        </div>
                        <input type="number" id="boxQuantity" class="form-control" value="1" min="1" max="500" />
                    </div>
                </div>

                <!-- ② Tên thùng -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-primary rounded-pill me-2" style="font-size:12px;">3</span>
                            <label class="form-label fw-semibold mb-0">Tên / mô tả thùng</label>
                        </div>
                        <input type="text" id="boxTitle" class="form-control" placeholder="VD: Thùng Sữa Ensure Gold 900g" />
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">Sản phẩm chính (tùy chọn)</label>
                        </div>
                        <div class="position-relative box-search-wrap">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bx bx-search text-muted"></i></span>
                                <input type="text" id="productSearch" class="form-control border-start-0" placeholder="Gõ tên SP hoặc SKU…" />
                            </div>
                            <input type="hidden" id="productId" name="product_id" value="" />
                            <input type="hidden" id="productSku" name="product_sku" value="" />
                            <div id="productDropdown" class="box-product-dropdown" style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <!-- ③ Ghi chú -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Ghi chú</label>
                        <textarea id="boxNote" class="form-control" rows="2" placeholder="Ghi chú thêm..."></textarea>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="btnCreate">
                        <i class="bx bx-package me-1"></i>Tạo thùng
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnReset">
                        <i class="bx bx-reset me-1"></i>Làm mới
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Kết quả -->
    <div class="card mt-4" id="resultCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bx bx-check-circle text-success me-1"></i>Thùng đã tạo</h5>
            <span class="badge bg-label-success" id="resultCount">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Mã thùng</th>
                        <th>Tên thùng</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="resultTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
