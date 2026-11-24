<?php

require_once "includes/inc_all.php";

if (empty($session_is_admin) && (empty($session_user_role) || $session_user_role < 2)) {
    $_SESSION['alert_type'] = "danger";
    $_SESSION['alert_message'] = WORDING_ROLECHECK_FAILED;
    exit(WORDING_ROLECHECK_FAILED . "<br>Tell your admin: Access to Inventory is restricted to Technicians and Administrators.");
}

?>

<div class="row">
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">In Stock</span>
                <span class="info-box-number text-lg" id="stat-in-stock-units">0</span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-dollar-sign"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Cost Value</span>
                <span class="info-box-number text-lg" id="stat-inventory-cost">$0.00</span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chart-line"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Potential Retail Value</span>
                <span class="info-box-number text-lg" id="stat-retail-value">$0.00</span>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Low / Out of Stock</span>
                <span class="info-box-number text-lg" id="stat-low-stock">0</span>
            </div>
        </div>
    </div>
</div>

<div class="card card-dark card-outline shadow-sm">
    <div class="card-header py-2">
        <h3 class="card-title mt-2 font-weight-bold">
            <i class="fas fa-warehouse mr-2 text-primary"></i>Inventory
        </h3>
        <div class="card-tools d-flex align-items-center">
            <span id="inventory-loading-spinner" class="spinner-border spinner-border-sm text-primary mr-2" style="display: none;" role="status"></span>
            <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold mr-2" id="btn-bulk-delete" style="display: none;">
                <i class="fas fa-trash mr-1"></i>Delete Selected (<span id="bulk-selected-count">0</span>)
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold mr-2" id="btn-import-inventory">
                <i class="fas fa-file-import mr-1"></i>Import
            </button>
            <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btn-add-product">
                <i class="fas fa-plus mr-1"></i>New Product
            </button>
        </div>
    </div>

    <div class="card-body pb-2">
        <div class="row align-items-center">
            <div class="col-lg-4 col-md-5 col-sm-12 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                    </div>
                    <input type="text" class="form-control" id="search-input" placeholder="Search product, SKU, serial, MAC, location...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" id="btn-clear-search" title="Clear Search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
                <select class="form-control" id="category-filter">
                    <option value="">- All Categories -</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-3 col-sm-6 mb-2">
                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                    <label class="btn btn-outline-secondary active btn-sm">
                        <input type="radio" name="tracking_type" value="all" checked> All
                    </label>
                    <label class="btn btn-outline-secondary btn-sm">
                        <input type="radio" name="tracking_type" value="serialized"> Serialized
                    </label>
                    <label class="btn btn-outline-secondary btn-sm">
                        <input type="radio" name="tracking_type" value="bulk"> Bulk
                    </label>
                </div>
            </div>

            <div class="col-lg-2 col-md-12 col-sm-12 mb-2 text-right">
                <select class="form-control form-control-sm" id="status-filter">
                    <option value="all">Status: All</option>
                    <option value="in_stock">In Stock Only</option>
                    <option value="low_stock">Low / Out of Stock</option>
                    <option value="out_of_stock">Out of Stock Only</option>
                </select>
            </div>
        </div>
    </div>

    <div id="bulk-actions-banner" class="alert alert-warning py-2 px-3 mb-2 mx-3 d-flex justify-content-between align-items-center" style="display: none;">
        <div>
            <i class="fas fa-check-square mr-1"></i>
            <span class="font-weight-bold"><span id="banner-selected-count">0</span> product(s) selected</span>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-secondary mr-2" id="btn-deselect-all">
                <i class="fas fa-times mr-1"></i>Clear Selection
            </button>
            <button type="button" class="btn btn-sm btn-danger font-weight-bold" id="btn-bulk-delete-banner">
                <i class="fas fa-trash mr-1"></i>Delete Selected
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="inventory-table">
            <thead class="thead-light">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input type="checkbox" id="select-all-products" title="Select All">
                    </th>
                    <th style="width: 25%;">Product / SKU</th>
                    <th style="width: 13%;">Category</th>
                    <th style="width: 11%;">Tracking</th>
                    <th style="width: 15%;">On Hand</th>
                    <th style="width: 12%;" class="text-right">Pricing & Cost</th>
                    <th style="width: 10%;">Location</th>
                    <th style="width: 11%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="inventory-tbody">
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i>
                        Loading inventory...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modal-product" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold" id="modal-product-title">
                    <i class="fas fa-box mr-2"></i>New Product
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-product" autocomplete="off">
                <input type="hidden" name="product_id" id="prod-id" value="0">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="prod-name">Product Name <strong class="text-danger">*</strong></label>
                                <input type="text" class="form-control" id="prod-name" name="name" placeholder="Product name" required maxlength="200">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="prod-code">Product Code / SKU</label>
                                <input type="text" class="form-control font-weight-bold" id="prod-code" name="code" placeholder="SKU / Model #" maxlength="200">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="prod-category">Category</label>
                                <select class="form-control" id="prod-category" name="category"></select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="d-block">Tracking Type <strong class="text-danger">*</strong></label>
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-outline-primary active" id="label-track-serialized">
                                        <input type="radio" name="tracking_type" id="prod-track-serialized" value="serialized" checked>
                                        <i class="fas fa-barcode mr-1"></i> Serialized (Units)
                                    </label>
                                    <label class="btn btn-outline-primary" id="label-track-bulk">
                                        <input type="radio" name="tracking_type" id="prod-track-bulk" value="bulk">
                                        <i class="fas fa-boxes mr-1"></i> Bulk (Quantity)
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1" id="tracking-help-text">
                                    Serialized: Each hardware unit has its own unique serial number and status.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="prod-cost">Purchase Cost ($)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                    <input type="number" step="0.01" min="0" class="form-control" id="prod-cost" name="cost" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="prod-price">Sale Price / MSRP ($)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                    <input type="number" step="0.01" min="0" class="form-control" id="prod-price" name="price" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="prod-min-stock">Low Stock Alert Level</label>
                                <input type="number" step="1" min="0" class="form-control" id="prod-min-stock" name="min_stock" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="prod-location">Default Storage Location</label>
                                <input type="text" class="form-control" id="prod-location" name="location" placeholder="Location">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="prod-description">Description</label>
                                <input type="text" class="form-control" id="prod-description" name="description" placeholder="Description">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold" id="btn-save-product">
                        <i class="fas fa-check mr-1"></i>Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-manage-serialized" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-barcode mr-2 text-info"></i>Serialized Units: <span id="ser-product-name" class="text-white"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded bg-light border">
                    <div>
                        <span class="font-weight-bold mr-3" id="ser-product-code"></span>
                        <span class="badge badge-success px-2 py-1 mr-2" id="ser-badge-in-stock">0 In Stock</span>
                        <span class="badge badge-warning px-2 py-1 mr-2" id="ser-badge-reserved">0 Reserved</span>
                        <span class="badge badge-secondary px-2 py-1" id="ser-badge-sold">0 Sold</span>
                    </div>
                    <button class="btn btn-sm btn-primary font-weight-bold" type="button" data-toggle="collapse" data-target="#collapse-add-serial">
                        <i class="fas fa-plus mr-1"></i>Add Serialized Item(s)
                    </button>
                </div>

                <div class="collapse mb-3" id="collapse-add-serial">
                    <div class="card card-body bg-light border-primary p-3">
                        <h6 class="font-weight-bold text-primary mb-2">
                            <i class="fas fa-plus-circle mr-1"></i>Add Units to Inventory
                        </h6>
                        <form id="form-add-serials" autocomplete="off">
                            <input type="hidden" name="product_id" id="ser-add-product-id" value="0">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group mb-2">
                                        <label for="ser-add-serials">Serial Number(s) <strong class="text-danger">*</strong> <small class="text-muted">(Paste multiple serials separated by lines or commas)</small></label>
                                        <textarea class="form-control font-weight-bold font-monospace" id="ser-add-serials" name="serials" rows="3" placeholder="Enter or paste serial number(s)..." required></textarea>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group mb-2">
                                        <label for="ser-add-status">Status</label>
                                        <select class="form-control form-control-sm" id="ser-add-status" name="status">
                                            <option value="In Stock" selected>In Stock (Available to Sell)</option>
                                            <option value="Reserved">Reserved for Client</option>
                                            <option value="Sold">Sold / Delivered</option>
                                            <option value="RMA">RMA / Defective</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label for="ser-add-client">Assign Client (Optional)</label>
                                        <select class="form-control form-control-sm" id="ser-add-client" name="client_id">
                                            <option value="0">- None (Unallocated) -</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group mb-2">
                                        <label for="ser-add-location">Location</label>
                                        <input type="text" class="form-control form-control-sm" id="ser-add-location" name="location" placeholder="Location">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-2">
                                        <label for="ser-add-mac">MAC Address (Optional)</label>
                                        <input type="text" class="form-control form-control-sm" id="ser-add-mac" name="mac" placeholder="MAC Address">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-2">
                                        <label for="ser-add-cost">Cost ($)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ser-add-cost" name="cost" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-2">
                                        <label for="ser-add-price">Price ($)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ser-add-price" name="price" placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-2">
                                <button type="button" class="btn btn-sm btn-secondary mr-2" data-toggle="collapse" data-target="#collapse-add-serial">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-primary font-weight-bold" id="btn-save-serials">
                                    <i class="fas fa-check mr-1"></i>Save Items
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Serial Number</th>
                                <th>Status</th>
                                <th>Assigned Client</th>
                                <th>Location</th>
                                <th>MAC</th>
                                <th class="text-right">Cost</th>
                                <th class="text-right">Price</th>
                                <th class="text-center" style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="serialized-items-tbody">
                            <tr><td colspan="8" class="text-center py-3 text-muted">Loading items...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-edit-item" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-edit mr-2"></i>Edit Serialized Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-edit-item" autocomplete="off">
                <input type="hidden" name="item_id" id="edit-item-id" value="0">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-item-serial">Serial Number <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control font-weight-bold font-monospace" id="edit-item-serial" name="serial" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-item-status">Status</label>
                                <select class="form-control" id="edit-item-status" name="status">
                                    <option value="In Stock">In Stock (Available)</option>
                                    <option value="Reserved">Reserved</option>
                                    <option value="Sold">Sold / Delivered</option>
                                    <option value="RMA">RMA / Defective</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-item-client">Assigned Client</label>
                                <select class="form-control" id="edit-item-client" name="client_id">
                                    <option value="0">- None (Unassigned) -</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-item-location">Location</label>
                                <input type="text" class="form-control" id="edit-item-location" name="location">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-item-mac">MAC Address</label>
                                <input type="text" class="form-control" id="edit-item-mac" name="mac">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-item-cost">Cost ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="edit-item-cost" name="cost">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit-item-price">Sale Price ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="edit-item-price" name="price">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit-item-notes">Notes</label>
                        <textarea class="form-control" id="edit-item-notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold" id="btn-save-edit-item">
                        <i class="fas fa-check mr-1"></i>Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-bulk-stock" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-boxes mr-2 text-warning"></i>Bulk Stock: <span id="bulk-product-name" class="text-white"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4 text-center">
                        <div class="p-3 border rounded bg-light">
                            <span class="text-muted d-block small uppercase font-weight-bold">Current Stock</span>
                            <span class="display-4 font-weight-bold text-dark" id="bulk-cur-qty">0</span>
                            <span class="d-block text-muted small" id="bulk-min-stock-label">Min Alert: 0</span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <form id="form-adjust-bulk" autocomplete="off">
                            <input type="hidden" name="product_id" id="bulk-product-id" value="0">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                            <div class="form-group mb-2">
                                <label class="d-block font-weight-bold">Stock Adjustment</label>
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-outline-success active btn-sm">
                                        <input type="radio" name="adjustment_type" value="add" checked>
                                        <i class="fas fa-plus mr-1"></i> Add (Received)
                                    </label>
                                    <label class="btn btn-outline-danger btn-sm">
                                        <input type="radio" name="adjustment_type" value="deduct">
                                        <i class="fas fa-minus mr-1"></i> Deduct (Sold/Used)
                                    </label>
                                    <label class="btn btn-outline-secondary btn-sm">
                                        <input type="radio" name="adjustment_type" value="set">
                                        <i class="fas fa-equals mr-1"></i> Set Count (Audit)
                                    </label>
                                </div>
                            </div>

                            <div class="form-group mb-2">
                                <label for="bulk-qty">Quantity</label>
                                <input type="number" min="1" step="1" class="form-control" id="bulk-qty" name="qty" required placeholder="Enter number of units">
                            </div>

                            <div class="form-group mb-2">
                                <label for="bulk-note">Reason / Reference</label>
                                <input type="text" class="form-control form-control-sm" id="bulk-note" name="note" placeholder="e.g. PO# 1042, Sold to Client X, Physical count audit">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block font-weight-bold" id="btn-save-bulk">
                                <i class="fas fa-save mr-1"></i>Save Stock Adjustment
                            </button>
                        </form>
                    </div>
                </div>

                <h6 class="font-weight-bold border-bottom pb-2 mt-4 text-secondary">
                    <i class="fas fa-history mr-1"></i>Stock Movement History
                </h6>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Change</th>
                                <th>Notes / Reference</th>
                            </tr>
                        </thead>
                        <tbody id="bulk-history-tbody">
                            <tr><td colspan="3" class="text-center py-2 text-muted">No history found</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-import-inventory" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-file-import mr-2 text-primary"></i>Import Inventory (Excel / CSV)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="import-step-upload">
                    <div class="border rounded p-5 text-center bg-light mb-3" id="import-dropzone" style="border: 2px dashed #007bff !important; cursor: pointer;">
                        <i class="fas fa-file-excel fa-4x text-success mb-3"></i>
                        <h4 class="font-weight-bold">Choose a spreadsheet file to upload</h4>
                        <p class="text-muted mb-3">Supports .xlsx, .xls, .csv, and .tsv files</p>
                        <input type="file" id="import-file-input" accept=".xlsx, .xls, .csv, .tsv" style="display: none;">
                        <button type="button" class="btn btn-primary font-weight-bold px-4" id="btn-browse-file">
                            <i class="fas fa-folder-open mr-1"></i>Browse File
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">You can map your file columns to inventory fields in the next step.</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-download-template">
                            <i class="fas fa-download mr-1"></i>Download Template (.xlsx)
                        </button>
                    </div>
                </div>

                <div id="import-step-mapping" style="display: none;">
                    <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle mr-1"></i>
                            File: <strong id="import-filename"></strong> (<span id="import-row-count">0</span> data rows detected)
                        </div>
                        <div id="import-sheet-selector-wrapper" style="display: none;">
                            <label class="mb-0 mr-1 small font-weight-bold">Sheet:</label>
                            <select id="import-sheet-select" class="form-control form-control-sm d-inline-block" style="width: auto;"></select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-7 col-md-12">
                            <div class="card card-outline card-secondary mb-3 shadow-none">
                                <div class="card-header py-2">
                                    <h6 class="card-title font-weight-bold mb-0">
                                        <i class="fas fa-columns mr-1 text-primary"></i>Map Columns
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                                        <table class="table table-sm table-striped mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width: 35%;">Inventory Field</th>
                                                    <th style="width: 40%;">File Column</th>
                                                    <th style="width: 25%;">Sample Value</th>
                                                </tr>
                                            </thead>
                                            <tbody id="import-mapping-tbody"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 col-md-12">
                            <div class="card card-outline card-secondary mb-3 shadow-none">
                                <div class="card-header py-2">
                                    <h6 class="card-title font-weight-bold mb-0">
                                        <i class="fas fa-cog mr-1 text-secondary"></i>Default Values & Options
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="form-group mb-3">
                                        <label for="import-default-category" class="small font-weight-bold">Default Category</label>
                                        <select class="form-control form-control-sm" id="import-default-category"></select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="import-default-tracking" class="small font-weight-bold">Default Tracking Type</label>
                                        <select class="form-control form-control-sm" id="import-default-tracking">
                                            <option value="serialized">Serialized (Firewalls, Switches, etc.)</option>
                                            <option value="bulk">Bulk (Cables, Accessories, etc.)</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="import-default-status" class="small font-weight-bold">Default Unit Status</label>
                                        <select class="form-control form-control-sm" id="import-default-status">
                                            <option value="In Stock" selected>In Stock</option>
                                            <option value="Reserved">Reserved</option>
                                            <option value="Sold">Sold</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-outline card-info shadow-none mb-0">
                        <div class="card-header py-2">
                            <h6 class="card-title font-weight-bold mb-0">
                                <i class="fas fa-eye mr-1 text-info"></i>Data Preview (First 3 Mapped Rows)
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0" id="import-preview-table">
                                    <thead class="thead-light" id="import-preview-thead"></thead>
                                    <tbody id="import-preview-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="import-step-progress" style="display: none;" class="py-4 text-center">
                    <h5 class="font-weight-bold mb-3" id="import-progress-status">Importing Inventory...</h5>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="import-progress-bar" role="progressbar" style="width: 100%;">
                            Processing...
                        </div>
                    </div>
                    <div id="import-summary-result" class="mt-3" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-import-back" style="display: none;">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm mr-1" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btn-start-import" style="display: none;">
                        <i class="fas fa-check mr-1"></i>Start Import
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/custom_legacy_js.php'; ?>
<script src="/libs/xlsx/xlsx.full.min.js"></script>
<script>
$(document).ready(function () {
    let clientsList = [];
    let categoriesList = [];
    let activeProductId = 0;
    let searchTimer = null;

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatCurrency(amount) {
        return '$' + parseFloat(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function loadClientsAndCategories() {
        $.getJSON('ajax_inventory.php?action=get_clients_and_categories', function (data) {
            if (data.success) {
                clientsList = data.clients || [];
                categoriesList = data.categories || [];

                const $catFilter = $('#category-filter');
                const $prodCat = $('#prod-category');
                $catFilter.find('option:not(:first)').remove();
                $prodCat.empty();

                categoriesList.forEach(function (cat) {
                    $catFilter.append(`<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`);
                    $prodCat.append(`<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`);
                });

                const $serAddClient = $('#ser-add-client');
                const $editItemClient = $('#edit-item-client');
                $serAddClient.find('option:not(:first)').remove();
                $editItemClient.find('option:not(:first)').remove();

                clientsList.forEach(function (c) {
                    $serAddClient.append(`<option value="${c.client_id}">${escapeHtml(c.client_name)}</option>`);
                    $editItemClient.append(`<option value="${c.client_id}">${escapeHtml(c.client_name)}</option>`);
                });
            }
        });
    }

    function loadInventory() {
        $('#select-all-products').prop('checked', false).prop('indeterminate', false);
        $('#btn-bulk-delete').hide();
        $('#bulk-actions-banner').hide();

        const query = $('#search-input').val().trim();
        const categoryId = $('#category-filter').val();
        const trackingType = $('input[name="tracking_type"]:checked').val();
        const status = $('#status-filter').val();

        $('#inventory-loading-spinner').show();

        $.ajax({
            url: 'ajax_inventory.php',
            type: 'GET',
            data: {
                action: 'get_inventory',
                q: query,
                category: categoryId,
                tracking_type: trackingType,
                status: status
            },
            dataType: 'json',
            success: function (res) {
                $('#inventory-loading-spinner').hide();
                if (!res.success) {
                    toastr.error('Failed to load inventory');
                    return;
                }

                $('#stat-in-stock-units').text(res.stats.total_in_stock_units.toLocaleString());
                $('#stat-inventory-cost').text(formatCurrency(res.stats.total_inventory_cost));
                $('#stat-retail-value').text(formatCurrency(res.stats.total_retail_value));
                $('#stat-low-stock').text(res.stats.low_stock_count.toLocaleString());

                renderInventoryTable(res.products);
            },
            error: function (xhr) {
                $('#inventory-loading-spinner').hide();
                let err = 'Error loading inventory data';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                }
                toastr.error(err);
            }
        });
    }

    function renderInventoryTable(products) {
        const $tbody = $('#inventory-tbody');
        $tbody.empty();

        if (!products || products.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted mb-3">
                            <i class="fas fa-boxes fa-3x d-block mb-2 text-secondary"></i>
                            No products found matching the criteria.
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-empty-add-product">
                            <i class="fas fa-plus mr-1"></i>Add New Product
                        </button>
                    </td>
                </tr>
            `);
            $('#btn-empty-add-product').on('click', function () {
                $('#btn-add-product').trigger('click');
            });
            updateSelectionUI();
            return;
        }

        products.forEach(function (p) {
            let trackingBadge = '';
            let stockDisplay = '';

            if (p.product_tracking_type === 'serialized') {
                trackingBadge = `<span class="badge badge-info"><i class="fas fa-barcode mr-1"></i>Serialized</span>`;
                const inStockBadge = p.in_stock_qty > 0
                    ? `<span class="badge badge-success mr-1 font-weight-bold px-2 py-1">${p.in_stock_qty} In Stock</span>`
                    : `<span class="badge badge-danger mr-1 font-weight-bold px-2 py-1">0 In Stock</span>`;
                const reservedBadge = p.reserved_qty > 0
                    ? `<span class="badge badge-warning mr-1 px-2 py-1">${p.reserved_qty} Reserved</span>`
                    : '';
                const soldBadge = p.sold_qty > 0
                    ? `<span class="badge badge-secondary px-2 py-1">${p.sold_qty} Sold</span>`
                    : '';

                stockDisplay = `<div>${inStockBadge}${reservedBadge}${soldBadge}</div>`;
                if (p.product_min_stock > 0 && p.in_stock_qty <= p.product_min_stock) {
                    stockDisplay += `<small class="text-danger font-weight-bold d-block mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Low Stock (Min: ${p.product_min_stock})</small>`;
                }
            } else {
                trackingBadge = `<span class="badge badge-warning text-dark"><i class="fas fa-boxes mr-1"></i>Bulk</span>`;
                const qtyBadge = p.in_stock_qty > 0
                    ? `<span class="badge badge-success font-weight-bold px-2 py-1">${p.in_stock_qty} units</span>`
                    : `<span class="badge badge-danger font-weight-bold px-2 py-1">0 units</span>`;

                stockDisplay = `<div>${qtyBadge}</div>`;
                if (p.product_min_stock > 0 && p.in_stock_qty <= p.product_min_stock) {
                    stockDisplay += `<small class="text-danger font-weight-bold d-block mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Low Stock (Min: ${p.product_min_stock})</small>`;
                }
            }

            const codeHtml = p.product_code
                ? `<span class="badge badge-light border font-monospace text-dark mr-1">${escapeHtml(p.product_code)}</span>`
                : '';

            const marginBadge = p.margin_percent > 0
                ? `<span class="badge badge-light text-success border border-success">${p.margin_percent}% margin</span>`
                : '';

            const row = `
                <tr data-product-id="${p.product_id}" data-tracking-type="${p.product_tracking_type}">
                    <td class="text-center align-middle">
                        <input type="checkbox" class="product-select-checkbox" value="${p.product_id}" data-name="${escapeHtml(p.product_name)}">
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark text-md">
                            ${escapeHtml(p.product_name)}
                        </div>
                        <div class="small mt-1">
                            ${codeHtml}
                            <span class="text-muted">${escapeHtml(p.product_description || '')}</span>
                        </div>
                    </td>
                    <td class="align-middle">
                        <span class="text-secondary font-weight-500">${escapeHtml(p.category_name)}</span>
                    </td>
                    <td class="align-middle">
                        ${trackingBadge}
                    </td>
                    <td class="align-middle">
                        ${stockDisplay}
                    </td>
                    <td class="text-right align-middle">
                        <div class="font-weight-bold text-dark">${formatCurrency(p.product_price)}</div>
                        <small class="text-muted d-block">Cost: ${formatCurrency(p.product_cost)}</small>
                        <div class="mt-1">${marginBadge}</div>
                    </td>
                    <td class="align-middle text-muted">
                        ${escapeHtml(p.product_location || '-')}
                    </td>
                    <td class="text-center align-middle">
                        <div class="btn-group">
                            ${p.product_tracking_type === 'serialized'
                                ? `<button type="button" class="btn btn-sm btn-outline-primary btn-manage-stock font-weight-bold" data-id="${p.product_id}" title="Manage Serialized Units">
                                     <i class="fas fa-barcode mr-1"></i>Units
                                   </button>`
                                : `<button type="button" class="btn btn-sm btn-outline-warning btn-manage-stock font-weight-bold text-dark" data-id="${p.product_id}" title="Adjust Bulk Stock">
                                     <i class="fas fa-boxes mr-1"></i>Stock
                                   </button>`
                            }
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-product" data-id="${p.product_id}" title="Edit Product">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-product" data-id="${p.product_id}" data-name="${escapeHtml(p.product_name)}" title="Delete Product">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;

            $tbody.append(row);
        });

        updateSelectionUI();
    }

    function updateSelectionUI() {
        const selectedCheckboxes = $('.product-select-checkbox:checked');
        const count = selectedCheckboxes.length;
        const total = $('.product-select-checkbox').length;

        $('#bulk-selected-count').text(count);
        $('#banner-selected-count').text(count);

        if (count > 0) {
            $('#btn-bulk-delete').show();
            $('#bulk-actions-banner').show();
        } else {
            $('#btn-bulk-delete').hide();
            $('#bulk-actions-banner').hide();
        }

        if (total > 0 && count === total) {
            $('#select-all-products').prop('checked', true).prop('indeterminate', false);
        } else if (count > 0 && count < total) {
            $('#select-all-products').prop('checked', false).prop('indeterminate', true);
        } else {
            $('#select-all-products').prop('checked', false).prop('indeterminate', false);
        }
    }

    $(document).on('change', '#select-all-products', function () {
        const isChecked = $(this).is(':checked');
        $('.product-select-checkbox').prop('checked', isChecked);
        updateSelectionUI();
    });

    $(document).on('change', '.product-select-checkbox', function () {
        updateSelectionUI();
    });

    $('#btn-deselect-all').on('click', function () {
        $('.product-select-checkbox').prop('checked', false);
        $('#select-all-products').prop('checked', false).prop('indeterminate', false);
        updateSelectionUI();
    });

    $(document).on('click', '.btn-delete-product', function () {
        const productId = $(this).data('id');
        const productName = $(this).data('name') || 'this product';

        if (confirm(`Are you sure you want to delete "${productName}"?\n\nThis will also remove any associated serial numbers and inventory stock records. This action cannot be undone.`)) {
            const $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: 'ajax_inventory.php',
                type: 'POST',
                data: {
                    action: 'delete_product',
                    product_id: productId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        loadInventory();
                    } else {
                        toastr.error(res.error || 'Failed to delete product');
                        $btn.prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    let err = 'Error deleting product';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        err = xhr.responseJSON.error;
                    }
                    toastr.error(err);
                    $btn.prop('disabled', false);
                }
            });
        }
    });

    function performBulkDelete() {
        const selectedIds = [];
        $('.product-select-checkbox:checked').each(function () {
            const id = parseInt($(this).val(), 10);
            if (id > 0) {
                selectedIds.push(id);
            }
        });

        if (selectedIds.length === 0) {
            toastr.warning('Please select at least one product to delete');
            return;
        }

        if (confirm(`Are you sure you want to delete the ${selectedIds.length} selected product(s)?\n\nThis will also remove any associated serial numbers and inventory stock records. This action cannot be undone.`)) {
            $('#btn-bulk-delete').prop('disabled', true);
            $('#btn-bulk-delete-banner').prop('disabled', true);

            $.ajax({
                url: 'ajax_inventory.php',
                type: 'POST',
                data: {
                    action: 'bulk_delete_products',
                    product_ids: selectedIds,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                },
                dataType: 'json',
                success: function (res) {
                    $('#btn-bulk-delete').prop('disabled', false);
                    $('#btn-bulk-delete-banner').prop('disabled', false);
                    if (res.success) {
                        toastr.success(res.message);
                        loadInventory();
                    } else {
                        toastr.error(res.error || 'Failed to delete products');
                    }
                },
                error: function (xhr) {
                    $('#btn-bulk-delete').prop('disabled', false);
                    $('#btn-bulk-delete-banner').prop('disabled', false);
                    let err = 'Error performing bulk delete';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        err = xhr.responseJSON.error;
                    }
                    toastr.error(err);
                }
            });
        }
    }

    $('#btn-bulk-delete').on('click', performBulkDelete);
    $('#btn-bulk-delete-banner').on('click', performBulkDelete);

    $('#btn-add-product').on('click', function () {
        $('#modal-product-title').html('<i class="fas fa-plus mr-2"></i>New Product');
        $('#form-product')[0].reset();
        $('#prod-id').val('0');
        $('#prod-cost').val('');
        $('#prod-price').val('');
        $('#prod-min-stock').val('');
        $('#label-track-serialized').trigger('click');
        $('#modal-product').modal('show');
    });

    $('input[name="tracking_type"]').on('change', function () {
        if ($(this).val() === 'bulk') {
            $('#tracking-help-text').text('Bulk: Tracked by quantity on hand (e.g. cables, keystones, accessories).');
        } else {
            $('#tracking-help-text').text('Serialized: Each hardware unit has its own unique serial number and status.');
        }
    });

    $('#form-product').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-save-product');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

        const formData = $(this).serialize() + '&action=save_product';

        $.ajax({
            url: 'ajax_inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save Product');
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal-product').modal('hide');
                    loadInventory();

                    if ($('#prod-id').val() === '0') {
                        if (res.tracking_type === 'serialized') {
                            setTimeout(function () {
                                openSerializedModal(res.product_id, true);
                            }, 400);
                        } else {
                            setTimeout(function () {
                                openBulkModal(res.product_id);
                            }, 400);
                        }
                    }
                } else {
                    toastr.error(res.error || 'Failed to save product');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save Product');
                let err = 'Error saving product';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                }
                toastr.error(err);
            }
        });
    });

    $(document).on('click', '.btn-edit-product', function () {
        const productId = $(this).data('id');
        $.getJSON('ajax_inventory.php', { action: 'get_product', product_id: productId }, function (res) {
            if (res.success && res.product) {
                const p = res.product;
                $('#modal-product-title').html('<i class="fas fa-edit mr-2"></i>Edit Product: ' + escapeHtml(p.product_name));
                $('#prod-id').val(p.product_id);
                $('#prod-name').val(p.product_name);
                $('#prod-code').val(p.product_code);
                $('#prod-category').val(p.product_category_id);
                $('#prod-cost').val(p.product_cost);
                $('#prod-price').val(p.product_price);
                $('#prod-min-stock').val(p.product_min_stock);
                $('#prod-location').val(p.product_location);
                $('#prod-description').val(p.product_description);

                if (p.product_tracking_type === 'bulk') {
                    $('#label-track-bulk').trigger('click');
                } else {
                    $('#label-track-serialized').trigger('click');
                }

                $('#modal-product').modal('show');
            }
        });
    });

    $(document).on('click', '.btn-manage-stock', function () {
        const productId = $(this).data('id');
        const $row = $(this).closest('tr');
        const trackingType = $row.data('tracking-type');

        if (trackingType === 'serialized') {
            openSerializedModal(productId, false);
        } else {
            openBulkModal(productId);
        }
    });

    function openSerializedModal(productId, expandAddForm) {
        activeProductId = productId;
        $('#ser-add-product-id').val(productId);
        $('#serialized-items-tbody').html('<tr><td colspan="8" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Loading items...</td></tr>');

        if (expandAddForm) {
            $('#collapse-add-serial').addClass('show');
        } else {
            $('#collapse-add-serial').removeClass('show');
        }

        $.getJSON('ajax_inventory.php', { action: 'get_product', product_id: productId }, function (res) {
            if (res.success) {
                const p = res.product;
                $('#ser-product-name').text(p.product_name);
                $('#ser-product-code').text(p.product_code ? '[' + p.product_code + ']' : '');
                $('#ser-add-cost').val(p.product_cost);
                $('#ser-add-price').val(p.product_price);
                $('#ser-add-location').val(p.product_location);
                $('#ser-add-serials').val('');

                renderSerializedItemsTable(res.items);
                $('#modal-manage-serialized').modal('show');
            }
        });
    }

    function renderSerializedItemsTable(items) {
        const $tbody = $('#serialized-items-tbody');
        $tbody.empty();

        let inStockCount = 0;
        let reservedCount = 0;
        let soldCount = 0;

        if (!items || items.length === 0) {
            $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No serialized units in inventory yet. Click <strong>"Add Serialized Item(s)"</strong> above to add units.</td></tr>');
            $('#ser-badge-in-stock').text('0 In Stock');
            $('#ser-badge-reserved').text('0 Reserved');
            $('#ser-badge-sold').text('0 Sold');
            return;
        }

        items.forEach(function (item) {
            let statusBadge = '';
            if (item.item_status === 'In Stock') {
                statusBadge = '<span class="badge badge-success px-2 py-1">In Stock</span>';
                inStockCount++;
            } else if (item.item_status === 'Reserved') {
                statusBadge = '<span class="badge badge-warning px-2 py-1">Reserved</span>';
                reservedCount++;
            } else if (item.item_status === 'Sold') {
                statusBadge = '<span class="badge badge-secondary px-2 py-1">Sold</span>';
                soldCount++;
            } else {
                statusBadge = `<span class="badge badge-danger px-2 py-1">${escapeHtml(item.item_status)}</span>`;
            }

            const clientText = item.client_name
                ? `<span class="text-primary font-weight-bold"><i class="fas fa-building mr-1"></i>${escapeHtml(item.client_name)}</span>`
                : '<span class="text-muted">-</span>';

            const row = `
                <tr data-item-id="${item.item_id}">
                    <td class="font-weight-bold font-monospace align-middle">${escapeHtml(item.item_serial)}</td>
                    <td class="align-middle">${statusBadge}</td>
                    <td class="align-middle">${clientText}</td>
                    <td class="align-middle text-muted">${escapeHtml(item.item_location || '-')}</td>
                    <td class="align-middle font-monospace small text-muted">${escapeHtml(item.item_mac || '-')}</td>
                    <td class="text-right align-middle text-muted">${formatCurrency(item.item_cost)}</td>
                    <td class="text-right align-middle font-weight-bold">${formatCurrency(item.item_price)}</td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-xs btn-outline-secondary btn-edit-item mr-1"
                            data-item='${JSON.stringify(item).replace(/'/g, "&apos;")}' title="Edit / Assign Client">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-danger btn-delete-item"
                            data-id="${item.item_id}" data-serial="${escapeHtml(item.item_serial)}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });

        $('#ser-badge-in-stock').text(inStockCount + ' In Stock');
        $('#ser-badge-reserved').text(reservedCount + ' Reserved');
        $('#ser-badge-sold').text(soldCount + ' Sold');
    }

    $('#form-add-serials').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-save-serials');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Adding...');

        const formData = $(this).serialize() + '&action=add_serialized_items';

        $.ajax({
            url: 'ajax_inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save Items');
                if (res.success) {
                    toastr.success(res.message);
                    $('#ser-add-serials').val('');
                    $('#collapse-add-serial').removeClass('show');

                    openSerializedModal(activeProductId, false);
                    loadInventory();
                } else {
                    toastr.error(res.error || 'Failed to add items');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save Items');
                let err = 'Error adding items';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                }
                toastr.error(err);
            }
        });
    });

    $(document).on('click', '.btn-edit-item', function () {
        const item = $(this).data('item');
        $('#edit-item-id').val(item.item_id);
        $('#edit-item-serial').val(item.item_serial);
        $('#edit-item-status').val(item.item_status);
        $('#edit-item-client').val(item.item_client_id);
        $('#edit-item-location').val(item.item_location);
        $('#edit-item-mac').val(item.item_mac);
        $('#edit-item-cost').val(item.item_cost);
        $('#edit-item-price').val(item.item_price);
        $('#edit-item-notes').val(item.item_notes);
        $('#modal-edit-item').modal('show');
    });

    $('#form-edit-item').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-save-edit-item');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Updating...');

        const formData = $(this).serialize() + '&action=update_serialized_item';

        $.ajax({
            url: 'ajax_inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Update Item');
                if (res.success) {
                    toastr.success(res.message);
                    $('#modal-edit-item').modal('hide');
                    openSerializedModal(activeProductId, false);
                    loadInventory();
                } else {
                    toastr.error(res.error || 'Failed to update item');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Update Item');
                let err = 'Error updating item';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                }
                toastr.error(err);
            }
        });
    });

    $(document).on('click', '.btn-delete-item', function () {
        const itemId = $(this).data('id');
        const serial = $(this).data('serial');

        if (confirm(`Remove item ${serial} from inventory?`)) {
            $.ajax({
                url: 'ajax_inventory.php',
                type: 'POST',
                data: {
                    action: 'delete_serialized_item',
                    item_id: itemId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        toastr.success(res.message);
                        openSerializedModal(activeProductId, false);
                        loadInventory();
                    } else {
                        toastr.error(res.error || 'Failed to remove item');
                    }
                }
            });
        }
    });

    function openBulkModal(productId) {
        activeProductId = productId;
        $('#bulk-product-id').val(productId);
        $('#bulk-qty').val('');
        $('#bulk-note').val('');
        $('#bulk-history-tbody').html('<tr><td colspan="3" class="text-center py-2 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Loading...</td></tr>');

        $.getJSON('ajax_inventory.php', { action: 'get_product', product_id: productId }, function (res) {
            if (res.success) {
                const p = res.product;
                $('#bulk-product-name').text(p.product_name);
                $('#bulk-cur-qty').text(p.bulk_qty.toLocaleString());
                $('#bulk-min-stock-label').text('Min Alert Level: ' + p.product_min_stock);

                renderBulkHistoryTable(res.stock_history);
                $('#modal-bulk-stock').modal('show');
            }
        });
    }

    function renderBulkHistoryTable(history) {
        const $tbody = $('#bulk-history-tbody');
        $tbody.empty();

        if (!history || history.length === 0) {
            $tbody.html('<tr><td colspan="3" class="text-center py-3 text-muted">No stock adjustments recorded yet.</td></tr>');
            return;
        }

        history.forEach(function (h) {
            const qtyChange = h.stock_qty > 0
                ? `<span class="badge badge-success">+${h.stock_qty}</span>`
                : `<span class="badge badge-danger">${h.stock_qty}</span>`;

            const row = `
                <tr>
                    <td class="text-muted small">${escapeHtml(h.stock_created_at)}</td>
                    <td>${qtyChange}</td>
                    <td>${escapeHtml(h.stock_note || '-')}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    $('#form-adjust-bulk').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-save-bulk');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

        const formData = $(this).serialize() + '&action=adjust_bulk_stock';

        $.ajax({
            url: 'ajax_inventory.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Stock Adjustment');
                if (res.success) {
                    toastr.success(res.message);
                    $('#bulk-qty').val('');
                    $('#bulk-note').val('');
                    openBulkModal(activeProductId);
                    loadInventory();
                } else {
                    toastr.error(res.error || 'Failed to adjust stock');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Stock Adjustment');
                let err = 'Error adjusting stock';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                }
                toastr.error(err);
            }
        });
    });

    $('#search-input').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            loadInventory();
        }, 300);
    });

    $('#btn-clear-search').on('click', function () {
        $('#search-input').val('');
        loadInventory();
    });

    $('#category-filter, #status-filter').on('change', function () {
        loadInventory();
    });

    $('input[name="tracking_type"]').on('change', function () {
        loadInventory();
    });

    let currentWorkbook = null;
    let currentRawData = [];
    let detectedColumns = [];

    const targetImportFields = [
        { key: 'name', label: 'Product Name', required: true, match: /^(product|product[_\s-]?name|item|model|device)$/i },
        { key: 'code', label: 'Product Code / SKU', required: false, match: /^(sku|product[_\s-]?code|code|part[_\s-]?number|part[_\s-]?#|model[_\s-]?#)$/i },
        { key: 'category', label: 'Category', required: false, match: /^(category|product[_\s-]?category|cat)$/i },
        { key: 'tracking_type', label: 'Tracking Type', required: false, match: /^(tracking|tracking[_\s-]?type|type)$/i },
        { key: 'serial', label: 'Serial Number', required: false, match: /^(serial|serial[_\s-]?number|sn|s\/n|serial[_\s-]?#|service[_\s-]?tag)$/i },
        { key: 'status', label: 'Status', required: false, match: /^(status|item[_\s-]?status|condition|state)$/i },
        { key: 'mac', label: 'MAC Address', required: false, match: /^(mac|mac[_\s-]?address|ethernet[_\s-]?mac|hardware[_\s-]?address)$/i },
        { key: 'location', label: 'Location / Shelf', required: false, match: /^(location|shelf|bin|warehouse|storage|room)$/i },
        { key: 'cost', label: 'Purchase Cost ($)', required: false, match: /^(cost|purchase[_\s-]?cost|unit[_\s-]?cost|buy[_\s-]?price|purchase[_\s-]?price)$/i },
        { key: 'price', label: 'Sale Price / MSRP ($)', required: false, match: /^(price|sale[_\s-]?price|selling[_\s-]?price|msrp|retail|unit[_\s-]?price)$/i },
        { key: 'min_stock', label: 'Min Stock Level', required: false, match: /^(min|min[_\s-]?stock|minimum|reorder|threshold|alert)$/i },
        { key: 'description', label: 'Description / Notes', required: false, match: /^(description|desc|specs|notes|comments)$/i },
        { key: 'client', label: 'Assigned Client', required: false, match: /^(client|customer|assigned[_\s-]?to|client[_\s-]?name)$/i },
        { key: 'qty', label: 'Bulk Quantity', required: false, match: /^(qty|quantity|count|stock|units|on[_\s-]?hand)$/i }
    ];

    $('#btn-import-inventory').on('click', function () {
        $('#import-step-upload').show();
        $('#import-step-mapping').hide();
        $('#import-step-progress').hide();
        $('#btn-import-back').hide();
        $('#btn-start-import').hide();
        $('#import-file-input').val('');
        $('#import-summary-result').hide().empty();

        const $defaultCat = $('#import-default-category');
        $defaultCat.empty();
        categoriesList.forEach(function (cat) {
            $defaultCat.append(`<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`);
        });

        $('#modal-import-inventory').modal('show');
    });

    $('#btn-browse-file, #import-dropzone').on('click', function (e) {
        if (e.target.id !== 'btn-download-template' && !$(e.target).closest('#btn-download-template').length) {
            $('#import-file-input').trigger('click');
        }
    });

    $('#import-dropzone').on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('bg-white border-primary');
    }).on('dragleave', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('bg-white border-primary');
    }).on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('bg-white border-primary');
        const files = e.originalEvent.dataTransfer.files;
        if (files && files.length > 0) {
            handleSelectedImportFile(files[0]);
        }
    });

    $('#import-file-input').on('change', function () {
        if (this.files && this.files.length > 0) {
            handleSelectedImportFile(this.files[0]);
        }
    });

    function handleSelectedImportFile(file) {
        $('#import-filename').text(file.name);
        const reader = new FileReader();

        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                currentWorkbook = XLSX.read(data, { type: 'array' });
                const sheetNames = currentWorkbook.SheetNames;

                if (!sheetNames || sheetNames.length === 0) {
                    toastr.error('No sheets found in spreadsheet file');
                    return;
                }

                if (sheetNames.length > 1) {
                    const $sheetSelect = $('#import-sheet-select');
                    $sheetSelect.empty();
                    sheetNames.forEach(function (s) {
                        $sheetSelect.append(`<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`);
                    });
                    $('#import-sheet-selector-wrapper').show();
                } else {
                    $('#import-sheet-selector-wrapper').hide();
                }

                loadSheetData(sheetNames[0]);
            } catch (err) {
                toastr.error('Error reading spreadsheet file: ' + err.message);
            }
        };

        reader.readAsArrayBuffer(file);
    }

    $('#import-sheet-select').on('change', function () {
        loadSheetData($(this).val());
    });

    function loadSheetData(sheetName) {
        const worksheet = currentWorkbook.Sheets[sheetName];
        const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

        if (!jsonData || jsonData.length === 0) {
            toastr.error('Selected sheet is empty');
            return;
        }

        let headerRowIndex = 0;
        for (let i = 0; i < jsonData.length; i++) {
            if (jsonData[i] && jsonData[i].some(cell => String(cell).trim() !== '')) {
                headerRowIndex = i;
                break;
            }
        }

        detectedColumns = (jsonData[headerRowIndex] || []).map(function (col, idx) {
            const clean = String(col).trim();
            return clean !== '' ? clean : 'Column ' + (idx + 1);
        });

        currentRawData = jsonData.slice(headerRowIndex + 1).filter(function (row) {
            return row && row.some(cell => String(cell).trim() !== '');
        });

        $('#import-row-count').text(currentRawData.length.toLocaleString());

        renderColumnMappingTable();
        renderPreviewTable();

        $('#import-step-upload').hide();
        $('#import-step-mapping').show();
        $('#btn-import-back').show();
        $('#btn-start-import').show();
    }

    function renderColumnMappingTable() {
        const $tbody = $('#import-mapping-tbody');
        $tbody.empty();

        targetImportFields.forEach(function (field) {
            let bestMatchIdx = -1;
            detectedColumns.forEach(function (colName, colIdx) {
                if (bestMatchIdx === -1) {
                    if (field.match.test(colName)) {
                        bestMatchIdx = colIdx;
                    }
                }
            });

            let optionsHtml = '<option value="">— Skip / Do not map —</option>';
            detectedColumns.forEach(function (colName, colIdx) {
                const selected = colIdx === bestMatchIdx ? 'selected' : '';
                optionsHtml += `<option value="${colIdx}" ${selected}>${escapeHtml(colName)}</option>`;
            });

            const sampleVal = bestMatchIdx >= 0 && currentRawData[0] && currentRawData[0][bestMatchIdx] !== undefined
                ? escapeHtml(currentRawData[0][bestMatchIdx])
                : '-';

            const reqBadge = field.required
                ? '<strong class="text-danger">*</strong>'
                : '';

            const row = `
                <tr>
                    <td class="align-middle">
                        <strong>${escapeHtml(field.label)}</strong> ${reqBadge}
                    </td>
                    <td class="align-middle">
                        <select class="form-control form-control-sm map-select" id="map-col-${field.key}" data-key="${field.key}">
                            ${optionsHtml}
                        </select>
                    </td>
                    <td class="align-middle text-muted small sample-preview" id="sample-val-${field.key}">
                        ${sampleVal}
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });

        $('.map-select').on('change', function () {
            const fieldKey = $(this).data('key');
            const colIdx = parseInt($(this).val());
            if (!isNaN(colIdx) && colIdx >= 0 && currentRawData[0] && currentRawData[0][colIdx] !== undefined) {
                $(`#sample-val-${fieldKey}`).text(String(currentRawData[0][colIdx]));
            } else {
                $(`#sample-val-${fieldKey}`).text('-');
            }
            renderPreviewTable();
        });
    }

    function renderPreviewTable() {
        const $thead = $('#import-preview-thead');
        const $tbody = $('#import-preview-tbody');
        $thead.empty();
        $tbody.empty();

        const activeMappedFields = [];
        targetImportFields.forEach(function (field) {
            const colIdx = parseInt($(`#map-col-${field.key}`).val());
            if (!isNaN(colIdx) && colIdx >= 0) {
                activeMappedFields.push({
                    key: field.key,
                    label: field.label,
                    colIdx: colIdx
                });
            }
        });

        if (activeMappedFields.length === 0) {
            $thead.html('<tr><th>No columns mapped yet</th></tr>');
            $tbody.html('<tr><td class="text-center py-2 text-muted">Select file columns in the mapping table above to see a live preview.</td></tr>');
            return;
        }

        let headerHtml = '<tr>';
        activeMappedFields.forEach(function (f) {
            headerHtml += `<th>${escapeHtml(f.label)}</th>`;
        });
        headerHtml += '</tr>';
        $thead.html(headerHtml);

        const previewRows = currentRawData.slice(0, 3);
        previewRows.forEach(function (row) {
            let rowHtml = '<tr>';
            activeMappedFields.forEach(function (f) {
                const val = row[f.colIdx] !== undefined ? String(row[f.colIdx]) : '';
                rowHtml += `<td>${escapeHtml(val)}</td>`;
            });
            rowHtml += '</tr>';
            $tbody.append(rowHtml);
        });
    }

    $('#btn-import-back').on('click', function () {
        $('#import-step-mapping').hide();
        $('#import-step-upload').show();
        $('#btn-import-back').hide();
        $('#btn-start-import').hide();
    });

    $('#btn-start-import').on('click', function () {
        const nameColIdx = parseInt($('#map-col-name').val());
        if (isNaN(nameColIdx) || nameColIdx < 0) {
            toastr.error('Product Name is required to be mapped.');
            return;
        }

        const mappedRows = [];
        currentRawData.forEach(function (rawRow) {
            const rowObj = {};
            targetImportFields.forEach(function (field) {
                const colIdx = parseInt($(`#map-col-${field.key}`).val());
                if (!isNaN(colIdx) && colIdx >= 0 && rawRow[colIdx] !== undefined) {
                    rowObj[field.key] = rawRow[colIdx];
                }
            });
            if (rowObj.name && String(rowObj.name).trim() !== '') {
                mappedRows.push(rowObj);
            }
        });

        if (mappedRows.length === 0) {
            toastr.error('No valid rows with Product Names found to import.');
            return;
        }

        $('#import-step-mapping').hide();
        $('#import-step-progress').show();
        $('#btn-import-back').hide();
        $('#btn-start-import').hide();
        $('#import-progress-status').text(`Importing ${mappedRows.length} inventory records...`);
        $('#import-summary-result').hide().empty();

        $.ajax({
            url: 'ajax_inventory.php',
            type: 'POST',
            data: {
                action: 'import_inventory',
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                default_category_id: $('#import-default-category').val(),
                default_tracking_type: $('#import-default-tracking').val(),
                default_status: $('#import-default-status').val(),
                rows: JSON.stringify(mappedRows)
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#import-progress-status').html('<i class="fas fa-check-circle text-success mr-1"></i>Import Completed Successfully!');
                    $('#import-progress-bar').removeClass('progress-bar-animated bg-primary').addClass('bg-success').text('Done (100%)');

                    $('#import-summary-result').html(`
                        <div class="alert alert-success text-left py-2 px-3">
                            <h6 class="font-weight-bold mb-1"><i class="fas fa-check mr-1"></i>Import Summary</h6>
                            <ul class="mb-0 pl-3">
                                <li><strong>${res.products_created}</strong> new product(s) created</li>
                                <li><strong>${res.products_updated}</strong> existing product(s) updated</li>
                                <li><strong>${res.items_created}</strong> serialized unit(s) added</li>
                                <li><strong>${res.bulk_units_added}</strong> bulk unit(s) stocked</li>
                                ${res.duplicates_skipped > 0 ? `<li><strong>${res.duplicates_skipped}</strong> duplicate serial numbers skipped</li>` : ''}
                            </ul>
                        </div>
                    `).show();

                    loadInventory();
                } else {
                    toastr.error(res.error || 'Import failed');
                    $('#import-progress-status').text('Import Failed');
                    $('#import-progress-bar').removeClass('bg-primary').addClass('bg-danger').text('Failed');
                }
            },
            error: function (xhr) {
                let err = 'Error processing import';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                }
                toastr.error(err);
                $('#import-progress-status').text('Import Error: ' + err);
                $('#import-progress-bar').removeClass('bg-primary').addClass('bg-danger').text('Error');
            }
        });
    });

    $('#btn-download-template').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const templateHeaders = [
            "Product Name",
            "SKU",
            "Category",
            "Tracking Type",
            "Serial Number",
            "Status",
            "MAC Address",
            "Cost",
            "Price",
            "Min Stock",
            "Location",
            "Description",
            "Client",
            "Quantity"
        ];

        const sampleRows = [
            [
                "FortiGate 60F",
                "FG-60F",
                "Hardware Sales",
                "Serialized",
                "FGT60FTK2100001",
                "In Stock",
                "00:09:0F:09:00:01",
                520.00,
                850.00,
                2,
                "Shelf A1",
                "Next-Gen Firewall",
                "",
                ""
            ],
            [
                "Cisco CBS350-24P",
                "CBS350-24P",
                "Hardware Sales",
                "Serialized",
                "FCZ2410L01A",
                "In Stock",
                "00:1E:13:22:33:44",
                420.00,
                690.00,
                2,
                "Shelf A2",
                "24-Port Gigabit PoE Managed Switch",
                "",
                ""
            ],
            [
                "Cat6 Patch Cable 10ft",
                "CBL-C6-10",
                "Hardware Sales",
                "Bulk",
                "",
                "",
                "",
                2.50,
                8.00,
                20,
                "Bin 4B",
                "Snagless RJ45 Patch Cord",
                "",
                50
            ]
        ];

        const wb = XLSX.utils.book_new();
        const wsData = [templateHeaders].concat(sampleRows);
        const ws = XLSX.utils.aoa_to_sheet(wsData);
        XLSX.utils.book_append_sheet(wb, ws, "Inventory Template");
        XLSX.writeFile(wb, "Inventory_Import_Template.xlsx");
    });

    loadClientsAndCategories();
    loadInventory();
});
</script>

<?php require_once "../includes/footer.php"; ?>
