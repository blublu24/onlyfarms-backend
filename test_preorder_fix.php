<?php

// Simple test to update a product for preorder testing
$host = 'localhost';
$dbname = 'onlyfarms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== FIXING PRODUCT FOR PREORDER TESTING ===\n";
    
    // Get first product
    $stmt = $pdo->prepare("SELECT product_id, product_name, stock_kg FROM products LIMIT 1");
    $stmt->execute();
    $product = $stmt->fetch();
    
    if ($product) {
        echo "Found product: " . $product['product_name'] . " (ID: " . $product['product_id'] . ")\n";
        echo "Current stock: " . $product['stock_kg'] . "kg\n";
        
        // Update product to have low stock and variation stock
        $stmt = $pdo->prepare("UPDATE products SET 
            stock_kg = 1.5,
            premium_stock_kg = 0.8,
            type_a_stock_kg = 1.2,
            type_b_stock_kg = 0.5,
            premium_price_per_kg = price_per_kg * 1.2,
            type_a_price_per_kg = price_per_kg * 1.1,
            type_b_price_per_kg = price_per_kg * 0.9,
            available_units = '[\"kg\", \"sack\", \"small_sack\", \"packet\", \"tali\", \"pieces\"]'
            WHERE product_id = ?");
        $result = $stmt->execute([$product['product_id']]);
        
        if ($result) {
            echo "✅ Product updated with variation stock!\n";
            echo "  - Regular stock: 1.5kg\n";
            echo "  - Premium stock: 0.8kg\n";
            echo "  - Type A stock: 1.2kg\n";
            echo "  - Type B stock: 0.5kg\n";
            echo "  - Available units: kg, sack, small_sack, packet, tali, pieces\n";
        } else {
            echo "❌ Failed to update product\n";
        }
        
        // Create a crop schedule for this product
        $stmt = $pdo->prepare("INSERT INTO crop_schedules (seller_id, product_id, crop_name, planting_date, expected_harvest_start, expected_harvest_end, quantity_estimate, quantity_unit, status, is_active) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 45 DAY), 50, 'kg', 'Planted', 1)");
        $result = $stmt->execute([1, $product['product_id'], 'Test Crop Schedule']);
        
        if ($result) {
            echo "✅ Crop schedule created!\n";
            echo "  - Harvest date: 30 days from now\n";
            echo "  - Expected quantity: 50kg\n";
        } else {
            echo "❌ Failed to create crop schedule\n";
        }
        
    } else {
        echo "❌ No products found in database\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
