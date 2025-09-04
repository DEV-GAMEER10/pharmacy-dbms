<?php

// sales_api.php
//
// This API handles the sales process, including stock deduction and invoice creation.
// It uses database transactions to ensure data integrity.

header('Content-Type: application/json');

// Database credentials for both databases
$inventory_host = "localhost";
$inventory_user = "root";
$inventory_pass = "";
$inventory_db   = "pharmaceutical_inventory";

$pos_host       = "localhost";
$pos_user       = "root";
$pos_pass       = "";
$pos_db         = "pharmacy_pos";

// Establish connections
$inventory_conn = new mysqli($inventory_host, $inventory_user, $inventory_pass, $inventory_db);
$pos_conn       = new mysqli($pos_host, $pos_user, $pos_pass, $pos_db);

if ($inventory_conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Inventory database connection failed: ' . $inventory_conn->connect_error]);
    exit;
}
if ($pos_conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'POS database connection failed: ' . $pos_conn->connect_error]);
    exit;
}

// Check for the "confirm_sale" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'confirm_sale') {
    
    // Decode the JSON-encoded list of items being sold
    $items_sold = json_decode($_POST['items_to_sell'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received.']);
        exit;
    }

    // Begin a database transaction for both databases
    $inventory_conn->begin_transaction();
    $pos_conn->begin_transaction();
    $transaction_successful = true;
    $total_bill_amount = 0;
    
    // Step 1: Check if sufficient stock is available
    foreach ($items_sold as $item) {
        $item_id = intval($item['id']);
        $quantity_sold = intval($item['quantity']);
        
        $sql = "SELECT Quantity FROM inventory WHERE ItemID = ?";
        $stmt = $inventory_conn->prepare($sql);
        if (!$stmt) { $transaction_successful = false; break; }
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stock = $result->fetch_assoc();
        
        if (!$stock || $stock['Quantity'] < $quantity_sold) {
            $transaction_successful = false;
            echo json_encode(['status' => 'error', 'message' => "Not enough stock for item ID {$item_id}."]);
            break;
        }
        
        $price_per_unit = floatval($item['price']);
        $subtotal = $quantity_sold * $price_per_unit;
        $total_bill_amount += $subtotal;
    }
    
    // Proceed only if all stock checks passed
    if ($transaction_successful) {
        
        // Step 2: Create a new sales record in the pharmacy_pos database
        $sql = "INSERT INTO sales (total_amount) VALUES (?)";
        $stmt = $pos_conn->prepare($sql);
        if (!$stmt) { $transaction_successful = false; }
        $stmt->bind_param("d", $total_bill_amount);
        if (!$stmt->execute()) { $transaction_successful = false; }
        
        $sale_id = $pos_conn->insert_id;
        
        // Step 3: Loop through each item to deduct stock and create sale items
        if ($transaction_successful) {
            foreach ($items_sold as $item) {
                $item_id = intval($item['id']);
                $quantity_sold = intval($item['quantity']);
                $item_name = $item['name'];
                $price_per_unit = floatval($item['price']);
                
                // Deduct quantity from the main inventory table
                $sql = "UPDATE pharmaceutical_inventory.inventory SET Quantity = Quantity - ? WHERE ItemID = ?";
                $stmt = $inventory_conn->prepare($sql);
                if (!$stmt) { $transaction_successful = false; break; }
                $stmt->bind_param("ii", $quantity_sold, $item_id);
                if (!$stmt->execute()) { $transaction_successful = false; break; }
                
                // Insert the item into the sales_items table
                $sql = "INSERT INTO sales_items (sale_id, item_id, quantity, price) VALUES (?, ?, ?, ?)";
                $stmt = $pos_conn->prepare($sql);
                if (!$stmt) { $transaction_successful = false; break; }
                $stmt->bind_param("iiid", $sale_id, $item_id, $quantity_sold, $price_per_unit);
                if (!$stmt->execute()) { $transaction_successful = false; break; }
            }
        }
    }
    
    // Step 4: Commit or rollback transactions
    if ($transaction_successful) {
        $inventory_conn->commit();
        $pos_conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Sale confirmed and stock deducted successfully!', 
            'sale_id' => $sale_id,
            'total_amount' => $total_bill_amount
        ]);
    } else {
        $inventory_conn->rollback();
        $pos_conn->rollback();
        // An error message was already echoed inside the loop
    }
    
} else {
    // Invalid action
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action or request method.']);
}

$inventory_conn->close();
$pos_conn->close();

?>