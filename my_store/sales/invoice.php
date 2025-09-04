<?php
// invoice.php

// Start a session to handle status messages, if needed
session_start();

// Database credentials for the POS database
$host = "localhost";
$user = "root";
$pass = "";
$db   = "pharmacy_pos";

// Establish database connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get the sale ID from the URL
$sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

// Redirect or show an error if no sale ID is provided
if ($sale_id === 0) {
    die("Error: No sale ID provided.");
}

// Query to get the main sale details from the 'sales' table
$sale_sql = "SELECT created_at, total_amount FROM sales WHERE sale_id = ?";
$stmt = $conn->prepare($sale_sql);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale_result = $stmt->get_result();
$sale_details = $sale_result->fetch_assoc();
$stmt->close();

// Check if a sale with the given ID exists
if (!$sale_details) {
    die("Error: Sale not found.");
}

// Query to get the details of each item in the sale from the 'sales_items' table
// We join with the `pharmaceutical_inventory` database to get the ItemName
$items_sql = "
    SELECT 
        si.quantity, 
        si.price, 
        (si.quantity * si.price) AS subtotal, 
        inv.ItemName
    FROM sales_items si
    JOIN pharmaceutical_inventory.inventory inv ON si.item_id = inv.ItemID
    WHERE si.sale_id = ?
";
$stmt = $conn->prepare($items_sql);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$items_result = $stmt->get_result();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= htmlspecialchars($sale_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .invoice-container { max-width: 800px; margin: 50px auto; padding: 30px; background-color: #fff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .invoice-header h1 { font-weight: 700; color: #0d6efd; }
        .invoice-details strong { color: #343a40; }
        .table th, .table td { vertical-align: middle; }
        .footer-info { text-align: center; margin-top: 30px; font-size: 0.9em; color: #6c757d; }
        @media print {
            body { background-color: #fff; }
            .invoice-container { box-shadow: none; border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="invoice-header text-center mb-4">
        <h1>Invoice</h1>
        <p class="text-muted">Pharmacy Management System</p>
    </div>

    <div class="invoice-details mb-4">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Invoice ID:</strong> <?= htmlspecialchars($sale_id) ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p><strong>Date:</strong> <?= date("F j, Y, g:i a", strtotime($sale_details['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <hr>

    <div class="invoice-body">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['ItemName']) ?></td>
                        <td class="text-end"><?= htmlspecialchars($item['quantity']) ?></td>
                        <td class="text-end">₹<?= number_format($item['price'], 2) ?></td>
                        <td class="text-end">₹<?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-4">
        <div class="col-md-6 offset-md-6 text-end">
            <h4>Total: <span class="text-success">₹<?= number_format($sale_details['total_amount'], 2) ?></span></h4>
        </div>
    </div>

    <hr>

    <div class="footer-info">
        <p>Thank you for your business!</p>
        <button onclick="window.print()" class="btn btn-primary no-print"><i class="fas fa-print"></i> Print Invoice</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>