<?php
/**
 * Danh sách thùng hàng
 *
 * @package tgs_box_manager
 */

if (!defined('ABSPATH')) exit;
?>

<!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bx bx-package me-2"></i>Danh sách thùng hàng
        </h4>
        <a href="<?php echo function_exists('tgs_url') ? tgs_url('box-create') : '#'; ?>" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i>Tạo thùng mới
        </a>
    </div>

    <!-- Bộ lọc -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-end g-2">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="Tìm mã thùng, tên thùng..." />
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select">
                        <option value="all">-- Tất cả trạng thái --</option>
                        <option value="0">Nháp</option>
                        <option value="1">Đang đóng gói</option>
                        <option value="2">Đã niêm phong</option>
                        <option value="3">Đang vận chuyển</option>
                        <option value="4">Đã giao</option>
                        <option value="5">Đã mở</option>
                        <option value="99">Lưu trữ</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="btnSearch" class="btn btn-primary">
                        <i class="bx bx-search me-1"></i>Tìm
                    </button>
                    <button type="button" id="btnRefresh" class="btn btn-outline-secondary ms-1">
                        <i class="bx bx-refresh"></i>
                    </button>
                </div>
                <div class="col-md-2 text-end">
                    <span class="text-muted" id="totalInfo">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Mã thùng</th>
                        <th>Tên thùng</th>
                        <th>Loại</th>
                        <th>SL / Sức chứa</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th style="width:80px;">Chi tiết</th>
                    </tr>
                </thead>
                <tbody id="boxTableBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="card-footer d-flex justify-content-between align-items-center" id="paginationWrap" style="display:none;">
            <span class="text-muted" id="pageInfo">Trang 1</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="btnPrev" disabled><i class="bx bx-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" id="btnNext" disabled><i class="bx bx-chevron-right"></i></button>
            </div>
        </div>
    </div>
