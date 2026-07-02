<?php
// ============================================
// modules/inventory/transfer.php
// Inter-branch Stock Transfer Functionality (Multi-item)
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$page_title = "Stock Transfer Request";
$error = '';
$success = '';

// Determine User's Center
$user_role = $_SESSION['role'];
$user_prov = $_SESSION['province'] ?? 'None';
$my_center_id = null;

if ($user_role === 'rdc_staff') {
    $c_stmt = mysqli_query($conn, "SELECT id FROM distribution_centers WHERE province='$user_prov' AND status='active'");
    if ($c_row = mysqli_fetch_assoc($c_stmt)) {
        $my_center_id = $c_row['id'];
    }
}

// Handle Transfer Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transfer'])) {
    $op_type = $_POST['op_type'] ?? '';
    
    // For RDC staff, one center is ALREADY known ($my_center_id)
    if ($my_center_id) {
        if ($op_type === 'send') {
            $source_id = $my_center_id;
            $dest_id   = (int)($_POST['dest_id'] ?? 0);
        } else {
            // Default to 'request'
            $source_id = (int)($_POST['source_id'] ?? 0);
            $dest_id   = $my_center_id;
        }
    } else {
        // Admin or HO Manager
        $source_id = (int)($_POST['source_id'] ?? 0);
        $dest_id   = (int)($_POST['dest_id'] ?? 0);
    }
    $items     = $_POST['items'] ?? [];
    $notes     = trim($_POST['notes']);

    if ($source_id === $dest_id) {
        $error = "Source and Destination centers must be different.";
    } elseif (empty($items)) {
        $error = "Please add at least one product to the transfer request.";
    } elseif ($source_id <= 0 || $dest_id <= 0) {
        $error = "Invalid center selection.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            $transfer_num = 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = mysqli_prepare($conn, "INSERT INTO stock_transfers (transfer_number, source_center_id, dest_center_id, created_by, notes) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "siiss", $transfer_num, $source_id, $dest_id, $_SESSION['user_id'], $notes);
            mysqli_stmt_execute($stmt);
            $transfer_id = mysqli_insert_id($conn);

            foreach ($items as $item) {
                $prod_id = (int)$item['product_id'];
                $qty = (int)$item['qty'];
                
                if ($qty <= 0) continue;

                $istmt = mysqli_prepare($conn, "INSERT INTO stock_transfer_items (transfer_id, product_id, quantity) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($istmt, "iii", $transfer_id, $prod_id, $qty);
                mysqli_stmt_execute($istmt);
            }

            mysqli_commit($conn);
            $success = "Stock transfer request $transfer_num submitted successfully.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Centers
$centers_query = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE status='active' ORDER BY name");
$centers_arr = [];
while ($c = mysqli_fetch_assoc($centers_query)) {
    $centers_arr[] = $c;
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3">
    <div class="col-xl-10 offset-xl-1">
        <div class="page-card">
            <div class="page-card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-arrow-left-right me-2"></i>New Stock Transfer Request</h5>
                <a href="transfer_list.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-list me-1"></i>View Requests</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small m-3"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success py-2 small m-3"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="page-card-body p-4">
                <form method="POST" id="transferForm">
                    <div class="row mb-4">
                        <?php if ($my_center_id): ?>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-semibold">Operation Type</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="op_type" id="op_request" value="request" checked>
                                        <label class="form-check-label" for="op_request">Request Stock (Receive into my branch)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="op_type" id="op_send" value="send">
                                        <label class="form-check-label" for="op_send">Send Stock (Transfer out of my branch)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dynamic Center Selection for RDC Staff -->
                            <div class="col-md-6" id="source_col">
                                <label class="form-label fw-semibold" id="source_label">Source Center (From)</label>
                                <select name="source_id" id="source_id" class="form-select form-select-sm" required>
                                    <option value="">Select Source...</option>
                                    <?php foreach ($centers_arr as $c): ?>
                                        <?php if ($c['id'] != $my_center_id): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div id="source_fixed" class="form-control form-control-sm bg-light text-muted d-none">
                                    <?php foreach($centers_arr as $c) if($c['id'] == $my_center_id) echo htmlspecialchars($c['name']); ?> (My Branch)
                                </div>
                            </div>
                            
                            <div class="col-md-6" id="dest_col">
                                <label class="form-label fw-semibold" id="dest_label">Destination Center (To)</label>
                                <div id="dest_fixed" class="form-control form-control-sm bg-light text-muted">
                                    <?php foreach($centers_arr as $c) if($c['id'] == $my_center_id) echo htmlspecialchars($c['name']); ?> (My Branch)
                                </div>
                                <select name="dest_id" id="dest_id" class="form-select form-select-sm d-none">
                                    <option value="">Select Destination...</option>
                                    <?php foreach ($centers_arr as $c): ?>
                                        <?php if ($c['id'] != $my_center_id): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="my_center_id" id="my_center_hidden" value="<?= $my_center_id ?>">
                            </div>

                            <script>
                                document.querySelectorAll('input[name="op_type"]').forEach(radio => {
                                    radio.addEventListener('change', function() {
                                        const isRequest = this.value === 'request';
                                        
                                        // Toggle Source
                                        document.getElementById('source_id').classList.toggle('d-none', !isRequest);
                                        document.getElementById('source_fixed').classList.toggle('d-none', isRequest);
                                        document.getElementById('source_id').required = isRequest;
                                        
                                        // Toggle Destination
                                        document.getElementById('dest_id').classList.toggle('d-none', isRequest);
                                        document.getElementById('dest_fixed').classList.toggle('d-none', !isRequest);
                                        document.getElementById('dest_id').required = !isRequest;
                                    });
                                });
                            </script>

                        <?php else: ?>
                            <!-- Admin/HO View -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Source Center (From)</label>
                                <select name="source_id" class="form-select form-select-sm" required>
                                    <option value="">Select Source...</option>
                                    <?php foreach ($centers_arr as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Destination Center (To)</label>
                                <select name="dest_id" class="form-select form-select-sm" required>
                                    <option value="">Select Destination...</option>
                                    <?php foreach ($centers_arr as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>


                    <div class="mb-3">
                        <label class="form-label fw-semibold">Search & Add Products</label>
                        <div class="position-relative">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="productSearch" class="form-control" placeholder="Search by SKU or Name...">
                            </div>
                            <div id="searchResults" class="list-group position-absolute w-100 mt-1 shadow-sm d-none" style="z-index: 1000;">
                                <!-- Results will appear here -->
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm align-middle" id="transferItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th style="width: 150px;">Quantity</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="transferItemsBody">
                                <tr id="noItemsRow">
                                    <td colspan="3" class="text-center text-muted py-3">No products added yet. Use search above.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Reason for transfer..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="submit_transfer" class="btn btn-primary fw-bold">
                            <i class="bi bi-send me-2"></i>Submit Transfer Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const productSearch = document.getElementById('productSearch');
const searchResults = document.getElementById('searchResults');
const transferItemsBody = document.getElementById('transferItemsBody');
const noItemsRow = document.getElementById('noItemsRow');
let addedProducts = new Set();

productSearch.addEventListener('input', function() {
    const q = this.value.trim();
    if (q.length < 2) {
        searchResults.classList.add('d-none');
        return;
    }

    fetch(`product_search.php?q=${encodeURIComponent(q)}`)
        .then(response => response.json())
        .then(data => {
            searchResults.innerHTML = '';
            if (data.length > 0) {
                data.forEach(p => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-2';
                    btn.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold small">${p.name}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">${p.sku}</div>
                            </div>
                            <i class="bi bi-plus-circle text-primary"></i>
                        </div>
                    `;
                    btn.onclick = () => addProduct(p);
                    searchResults.appendChild(btn);
                });
                searchResults.classList.remove('d-none');
            } else {
                searchResults.innerHTML = '<div class="list-group-item disabled small text-muted">No products found</div>';
                searchResults.classList.remove('d-none');
            }
        });
});

document.addEventListener('click', function(e) {
    if (!productSearch.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.classList.add('d-none');
    }
});

function addProduct(p) {
    if (addedProducts.has(p.id)) {
        alert('Product already added');
        return;
    }

    if (noItemsRow) noItemsRow.classList.add('d-none');

    const row = document.createElement('tr');
    row.id = `row-${p.id}`;
    row.innerHTML = `
        <td>
            <div class="fw-semibold small">${p.name}</div>
            <div class="text-muted small">${p.sku}</div>
            <input type="hidden" name="items[${p.id}][product_id]" value="${p.id}">
        </td>
        <td>
            <input type="number" name="items[${p.id}][qty]" class="form-control form-control-sm" min="1" value="1" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeProduct(${p.id})">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    transferItemsBody.appendChild(row);
    addedProducts.add(p.id);
    searchResults.classList.add('d-none');
    productSearch.value = '';
}

function removeProduct(id) {
    document.getElementById(`row-${id}`).remove();
    addedProducts.delete(id);
    if (addedProducts.size === 0) {
        noItemsRow.classList.remove('d-none');
    }
}
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
