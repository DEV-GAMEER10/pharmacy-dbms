<?php

// inventory/index.php

//
// This file manages the pharmacy inventory. It has been refactored to use
// AJAX for dynamic updates, preventing full page refreshes.

// Start a session to handle status messages
session_start();

// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$db= "pharmaceutical_inventory";

// Establish database connection and handle errors
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed:" . $conn->connect_error);
}

// Function to safely sanitize and display output
function h($s){
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Fetch categories & suppliers for filter dropdowns
$categories = $conn->query("SELECT DISTINCT Category FROM inventory ORDER BY Category ASC");
$suppliers  = $conn->query("SELECT DISTINCT SupplierName FROM inventory ORDER BY SupplierName ASC");

// Close the database connection as it will be handled by the API calls
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pharmacy Inventory Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
body { background: #e9ecef; padding: 2rem; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
.container { max-width: 1200px; }
.card { border-radius: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s, box-shadow 0.3s; }
.card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.card-title { font-size: 1.5rem; font-weight: 700; color: #343a40; }
.card-text strong { color: #555; }
.expired { border: 2px solid #dc3545; background-color: #f8d7da; color: #721c24; }
.expiring-soon { border: 2px solid #ffc107; background-color: #fff3cd; color: #664d03; }
.low-stock { border: 2px solid #17a2b8; background-color: #d1ecf1; color: #0c5460; }
.btn-primary, .btn-success, .btn-warning, .btn-danger { border-radius: 0.5rem; }
.badge { font-size: 0.8em; font-weight: 600; padding: 0.4em 0.8em; }
.modal-body { max-height: 70vh; overflow-y: auto; }
</style>
</head>
<body>
<div class="container">

<h1 class="mb-5 text-center">
    <i class="fas fa-prescription-bottle-alt text-primary"></i> Pharmacy Inventory
</h1>

<div id="statusMessage" class="alert d-none fade show" role="alert">
    <span id="statusText"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<div class="card mb-5 p-4">
    <h4 class="card-title">Add New Medicine</h4>
    <form id="addForm" class="row g-3">
        <input type="hidden" name="action" value="add_medicine">
        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
        <div class="col-md-2"><input type="text" name="category" class="form-control" placeholder="Category" required></div>
        <div class="col-md-2"><input type="text" name="form" class="form-control" placeholder="Form" required></div>
        <div class="col-md-2"><input type="text" name="batch" class="form-control" placeholder="Batch No" required></div>
        <div class="col-md-1"><input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required></div>
        <div class="col-md-2"><input type="text" name="supplier" class="form-control" placeholder="Supplier" required></div>
        <div class="col-md-2"><input type="date" name="expiry" class="form-control"></div>
        <div class="col-md-2"><input type="number" name="quantity" class="form-control" placeholder="Quantity" required></div>
        <div class="col-md-1"><button class="btn btn-success w-100" type="submit"><i class="fas fa-plus"></i> Add</button></div>
    </form>
</div>

<div class="card mb-5 p-4">
    <h4 class="card-title">Filter & Search</h4>
    <form id="filterForm" class="row g-3 align-items-center">
        <div class="col-md-3"><input type="text" name="filter_name" class="form-control" placeholder="Search by Name" value="<?=h($_GET['filter_name'] ?? '')?>"></div>
        <div class="col-md-3">
            <select name="filter_category" class="form-select">
                <option value="">All Categories</option>
                <?php while($c=$categories->fetch_assoc()): ?>
                    <option value="<?=h($c['Category'])?>" <?=(($_GET['filter_category'] ?? '') == $c['Category']?'selected':'')?>><?=h($c['Category'])?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="filter_supplier" class="form-select">
                <option value="">All Suppliers</option>
                <?php while($s=$suppliers->fetch_assoc()): ?>
                    <option value="<?=h($s['SupplierName'])?>" <?=(($_GET['filter_supplier'] ?? '') == $s['SupplierName']?'selected':'')?>><?=h($s['SupplierName'])?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="sort_expiry" class="form-select">
                <option value="">Default Sort</option>
                <option value="1" <?=(isset($_GET['sort_expiry'])?'selected':'')?>>Soonest Expiry First</option>
            </select>
        </div>
        <div class="col-md-1"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button></div>
    </form>
</div>

<div class="card mb-4 p-3 bg-light">
    <h5 class="card-title mb-0">Total Stock Value: <span id="totalStockValueDisplay" class="text-success"></span></h5>
</div>

<div id="inventoryCards" class="row g-4">
    <div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading inventory...</div>
</div>

<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="updateForm" class="modal-content">
            <input type="hidden" name="action" value="update_medicine">
            <input type="hidden" name="update_id" id="update_id">
            <div class="modal-header">
                <h5 class="modal-title">Update Medicine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label>Name</label><input type="text" name="name" id="update_name" class="form-control" required></div>
                <div class="mb-3"><label>Category</label><input type="text" name="category" id="update_category" class="form-control" required></div>
                <div class="mb-3"><label>Form</label><input type="text" name="form" id="update_form" class="form-control" required></div>
                <div class="mb-3"><label>Batch No</label><input type="text" name="batch" id="update_batch" class="form-control" required></div>
                <div class="mb-3"><label>Quantity</label><input type="number" name="quantity" id="update_quantity" class="form-control" required></div>
                <div class="mb-3"><label>Price</label><input type="number" step="0.01" name="price" id="update_price" class="form-control" required></div>
                <div class="mb-3"><label>Supplier</label><input type="text" name="supplier" id="update_supplier" class="form-control" required></div>
                <div class="mb-3"><label>Expiry Date</label><input type="date" name="expiry" id="update_expiry" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    const inventoryCardsContainer = document.getElementById('inventoryCards');
    const totalStockValueDisplay = document.getElementById('totalStockValueDisplay');
    const addForm = document.getElementById('addForm');
    const updateForm = document.getElementById('updateForm');
    const filterForm = document.getElementById('filterForm');
    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
    const statusMessageDiv = document.getElementById('statusMessage');
    const statusTextSpan = document.getElementById('statusText');

    // Constants
    const LOW_STOCK_THRESHOLD = 20;

    // Function to show a status message
    function showStatusMessage(type, message) {
        statusMessageDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
        statusMessageDiv.classList.add(`alert-${type}`);
        statusTextSpan.textContent = message;
        // Hide the message after 5 seconds
        setTimeout(() => {
            statusMessageDiv.classList.add('d-none');
        }, 5000);
    }

    // Function to fetch and render the inventory cards
    async function fetchAndRenderInventory() {
        inventoryCardsContainer.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading...</div>';
        totalStockValueDisplay.textContent = 'Calculating...';
        const formData = new FormData(filterForm);
        const queryString = new URLSearchParams(formData).toString();
        
        try {
            const response = await fetch(`api.php?action=get_inventory&${queryString}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const result = await response.json();
            
            if (result.status === 'success') {
                let html = '';
                let totalStockValue = 0;
                
                if (result.data.length > 0) {
                    result.data.forEach(item => {
                        let cardClass = '';
                        let statusBadge = '';
                        
                        // Calculate total stock value
                        totalStockValue += parseFloat(item.CostPrice) * parseInt(item.Quantity);
                        
                        // Check expiry status
                        if (item.ExpiryDate) {
                            const diffDays = (new Date(item.ExpiryDate).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24);
                            if (diffDays <= 0) {
                                cardClass = 'expired';
                                statusBadge = '<span class="badge bg-danger">EXPIRED</span>';
                            } else if (diffDays <= 30) {
                                cardClass = 'expiring-soon';
                                statusBadge = '<span class="badge bg-warning text-dark">EXPIRING SOON</span>';
                            }
                        }

                        // Check stock status (prioritized if no expiry issue)
                        if (!cardClass && item.Quantity <= LOW_STOCK_THRESHOLD) {
                            cardClass = 'low-stock';
                            statusBadge = '<span class="badge bg-info">LOW STOCK</span>';
                        }
                        
                        html += `
                        <div class="col-md-4">
                            <div class="card h-100 ${cardClass}">
                                <div class="card-body">
                                    <h5 class="card-title">${item.ItemName}</h5>
                                    <p class="card-text">
                                        <strong>Category:</strong> ${item.Category}<br>
                                        <strong>Form:</strong> ${item.Type_FormTablet}<br>
                                        <strong>Batch No:</strong> ${item.BatchNumber}<br>
                                        <strong>Supplier:</strong> ${item.SupplierName}<br>
                                        <strong>Quantity:</strong> ${item.Quantity}<br>
                                        <strong>Price:</strong> ₹${parseFloat(item.CostPrice).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}<br>
                                        <strong>Expiry:</strong> ${item.ExpiryDate || 'N/A'}
                                        ${statusBadge ? `<br>${statusBadge}` : ''}
                                    </p>
                                    <a href="#" class="btn btn-warning btn-sm w-100 mb-1"
                                        onclick='openUpdateModal(${JSON.stringify(item)}); return false;'>
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                    <a href="#" class="btn btn-danger btn-sm w-100" onclick="deleteMedicine(${item.ItemID}); return false;">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                        `;
                    });
                } else {
                    html = '<div class="col-12"><p class="text-center">No medicines found.</p></div>';
                }
                inventoryCardsContainer.innerHTML = html;
                totalStockValueDisplay.textContent = `₹${totalStockValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            } else {
                showStatusMessage('danger', result.message || 'Failed to fetch inventory.');
            }
        } catch (error) {
            console.error('Error fetching inventory:', error);
            showStatusMessage('danger', 'An error occurred while fetching inventory.');
        }
    }

    // Function to handle delete requests
    window.deleteMedicine = async (id) => {
        if (!confirm('Are you sure you want to delete this medicine?')) {
            return;
        }
        
        try {
            const response = await fetch(`api.php?action=delete_medicine&id=${id}`);
            const result = await response.json();

            if (result.status === 'success') {
                showStatusMessage('success', result.message);
                fetchAndRenderInventory(); // Refresh the list
            } else {
                showStatusMessage('danger', result.message);
            }
        } catch (error) {
            showStatusMessage('danger', 'An error occurred while deleting.');
        }
    };
    
    // Open the update modal and populate it with data
    window.openUpdateModal = (item) => {
        document.getElementById('update_id').value = item.ItemID;
        document.getElementById('update_name').value = item.ItemName;
        document.getElementById('update_category').value = item.Category;
        document.getElementById('update_form').value = item.Type_FormTablet;
        document.getElementById('update_batch').value = item.BatchNumber;
        document.getElementById('update_price').value = item.CostPrice;
        document.getElementById('update_supplier').value = item.SupplierName;
        document.getElementById('update_expiry').value = item.ExpiryDate;
        document.getElementById('update_quantity').value = item.Quantity;
        updateModal.show();
    };

    // Event listener for adding a new medicine
    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(addForm);
        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                showStatusMessage('success', result.message);
                addForm.reset();
                fetchAndRenderInventory(); // Refresh the list
            } else {
                showStatusMessage('danger', result.message);
            }
        } catch (error) {
            showStatusMessage('danger', 'An error occurred while adding.');
        }
    });
    
    // Event listener for updating a medicine
    updateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(updateForm);
        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status === 'success') {
                showStatusMessage('success', result.message);
                updateModal.hide();
                fetchAndRenderInventory(); // Refresh the list
            } else {
                showStatusMessage('danger', result.message);
            }
        } catch (error) {
            showStatusMessage('danger', 'An error occurred while updating.');
        }
    });

    // Event listener for filter form
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchAndRenderInventory();
    });

    // Initial fetch on page load
    fetchAndRenderInventory();
});
</script>
</div>
</body>
</html>