
<?php
// sales.php
session_start();

// Database credentials to fetch items for the sales page
$host = "localhost";
$user = "root";
$pass = "";
$db= "pharmaceutical_inventory";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed:" . $conn->connect_error);
}

// Fetch all items from the inventory to display on the sales page
$result = $conn->query("SELECT ItemID, ItemName, Quantity, CostPrice FROM inventory");
$medicines = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $medicines[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Point of Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Point of Sale</h1>
    
    <div id="statusMessage" class="alert d-none fade show" role="alert">
        <span id="statusText"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-4 shadow-sm">
                <h4>Available Medicines</h4>
                <div class="mb-3">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search for a medicine...">
                </div>
                <div id="medicineList" class="list-group" style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($medicines as $med): ?>
                        <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                data-id="<?= htmlspecialchars($med['ItemID']) ?>"
                                data-name="<?= htmlspecialchars($med['ItemName']) ?>"
                                data-price="<?= htmlspecialchars($med['CostPrice']) ?>"
                                data-stock="<?= htmlspecialchars($med['Quantity']) ?>">
                            <?= htmlspecialchars($med['ItemName']) ?>
                            <span class="badge bg-primary rounded-pill">Stock: <?= htmlspecialchars($med['Quantity']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-4 shadow-sm">
                <h4>Current Sale</h4>
                <form id="saleForm">
                    <div id="cartItems" class="list-group mb-3">
                        <div class="list-group-item text-center text-muted">Cart is empty.</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Total:</h5>
                        <h4 class="text-success" id="totalAmount">₹0.00</h4>
                    </div>
                    <button type="submit" class="btn btn-success w-100" id="confirmSaleBtn" disabled>
                        <i class="fas fa-check-circle"></i> Confirm Sale
                    </button>
                </form>
                
                <div id="invoiceButtonContainer" class="mt-3 d-grid gap-2 d-none">
                    <button id="printInvoiceBtn" class="btn btn-info w-100">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const medicineList = document.getElementById('medicineList');
    const cartItemsList = document.getElementById('cartItems');
    const totalAmountSpan = document.getElementById('totalAmount');
    const confirmSaleBtn = document.getElementById('confirmSaleBtn');
    const saleForm = document.getElementById('saleForm');
    const statusMessageDiv = document.getElementById('statusMessage');
    const statusTextSpan = document.getElementById('statusText');
    const invoiceBtnContainer = document.getElementById('invoiceButtonContainer');
    const printInvoiceBtn = document.getElementById('printInvoiceBtn');

    let cart = []; // The cart array to store items

    function showStatusMessage(type, message) {
        statusMessageDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
        statusMessageDiv.classList.add(`alert-${type}`);
        statusTextSpan.textContent = message;
        // The message will stay until the user closes it or a new action occurs.
        // You can add a setTimeout here to make it disappear automatically if you prefer.
    }

    // Function to render the cart UI
    function renderCart() {
        if (cart.length === 0) {
            cartItemsList.innerHTML = '<div class="list-group-item text-center text-muted">Cart is empty.</div>';
            totalAmountSpan.textContent = '₹0.00';
            confirmSaleBtn.disabled = true;
            return;
        }

        let total = 0;
        cartItemsList.innerHTML = '';
        cart.forEach(item => {
            const subtotal = item.quantity * item.price;
            total += subtotal;
            const itemElement = document.createElement('div');
            itemElement.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center');
            itemElement.innerHTML = `
                <div>
                    ${item.name} (${item.quantity})
                    <br><small class="text-muted">₹${item.price.toFixed(2)} each</small>
                </div>
                <div>
                    ₹${subtotal.toFixed(2)}
                    <button type="button" class="btn btn-sm btn-danger ms-2 remove-from-cart" data-id="${item.id}"><i class="fas fa-times"></i></button>
                </div>
            `;
            cartItemsList.appendChild(itemElement);
        });

        totalAmountSpan.textContent = `₹${total.toFixed(2)}`;
        confirmSaleBtn.disabled = false;
    }

    // Event listener for adding items to cart
    medicineList.addEventListener('click', (e) => {
        const button = e.target.closest('button');
        if (!button) return;

        const id = button.dataset.id;
        const name = button.dataset.name;
        const price = parseFloat(button.dataset.price);
        const stock = parseInt(button.dataset.stock);

        const existingItemIndex = cart.findIndex(item => item.id === id);

        if (existingItemIndex > -1) {
            if (cart[existingItemIndex].quantity < stock) {
                cart[existingItemIndex].quantity++;
            } else {
                showStatusMessage('danger', 'Cannot add more, maximum stock reached.');
            }
        } else {
            if (stock > 0) {
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    quantity: 1
                });
            } else {
                showStatusMessage('danger', 'This item is out of stock.');
            }
        }
        renderCart();
    });

    // Event listener for removing items from cart
    cartItemsList.addEventListener('click', (e) => {
        if (e.target.closest('.remove-from-cart')) {
            const idToRemove = e.target.closest('.remove-from-cart').dataset.id;
            const itemIndex = cart.findIndex(item => item.id === idToRemove);
            
            if (itemIndex > -1) {
                cart.splice(itemIndex, 1);
            }
            renderCart();
        }
    });

    // Event listener for confirming the sale
    saleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (cart.length === 0) {
            showStatusMessage('danger', 'Cart is empty. Add items to proceed.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'confirm_sale');
        formData.append('items_to_sell', JSON.stringify(cart));

        try {
            const response = await fetch('sales_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success') {
                showStatusMessage('success', result.message);
                
                // Set the link for the print invoice button
                printInvoiceBtn.onclick = () => {
                    window.open(`invoice.php?sale_id=${result.sale_id}`, '_blank');
                };

                // Show the print invoice button
                invoiceBtnContainer.classList.remove('d-none');
                
                // Clear the cart after a successful sale
                cart = []; 
                renderCart();
            } else {
                showStatusMessage('danger', result.message);
                invoiceBtnContainer.classList.add('d-none'); // Hide the button on error
            }
        } catch (error) {
            console.error('Error:', error);
            showStatusMessage('danger', 'An error occurred during the sale.');
            invoiceBtnContainer.classList.add('d-none'); // Hide the button on error
        }
    });

    renderCart();
});
</script>
</body>
</html>