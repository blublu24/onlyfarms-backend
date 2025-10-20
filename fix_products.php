<?php

// Setup preorder testing environment
$host = 'localhost';
$dbname = 'onlyfarms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🌾 Setting Up Preorder Testing Environment</h2>";
    
    // Get first product
    $stmt = $pdo->prepare("SELECT product_id, product_name, seller_id, price_per_kg FROM products LIMIT 1");
    $stmt->execute();
    $product = $stmt->fetch();
    
    if ($product) {
        echo "<p><strong>Found product:</strong> " . htmlspecialchars($product['product_name']) . " (ID: " . $product['product_id'] . ")</p>";
        
        // Step 1: Set product stock to LOW (triggers preorder eligibility)
        // Set variation stock to 0 since we haven't harvested yet
        $stmt = $pdo->prepare("UPDATE products SET 
            stock_kg = 0,
            premium_stock_kg = 0,
            type_a_stock_kg = 0,
            type_b_stock_kg = 0,
            price_per_kg = 350.00,
            premium_price_per_kg = 450.00,
            type_a_price_per_kg = 400.00,
            type_b_price_per_kg = 380.00
            WHERE product_id = ?");
        $result = $stmt->execute([$product['product_id']]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Product stock set to 0 (pre-harvest state)</p>";
            echo "<ul>";
            echo "<li>Premium price: ₱450/kg</li>";
            echo "<li>Type A price: ₱400/kg</li>";
            echo "<li>Type B price: ₱380/kg</li>";
            echo "</ul>";
        }
        
        // Step 2: Create crop schedule with TOTAL estimated harvest only (no breakdown yet)
        // Delete existing schedules first
        $stmt = $pdo->prepare("DELETE FROM crop_schedules WHERE product_id = ?");
        $stmt->execute([$product['product_id']]);
        
        $stmt = $pdo->prepare("INSERT INTO crop_schedules 
            (seller_id, product_id, crop_name, planting_date, expected_harvest_start, expected_harvest_end, quantity_estimate, quantity_unit, status, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY), 150, 'kg', 'Planted', 1, NOW(), NOW())");
        $result = $stmt->execute([$product['seller_id'], $product['product_id'], $product['product_name'] . ' - Test Harvest']);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Crop schedule created!</p>";
            echo "<ul>";
            echo "<li><strong>Total estimated harvest:</strong> 150kg (no breakdown yet - farmer doesn't know)</li>";
            echo "<li><strong>Expected harvest:</strong> 5 days from now</li>";
            echo "<li><strong>Planting date:</strong> 10 days ago</li>";
            echo "</ul>";
        }
        
        echo "<hr>";
        echo "<h3>🎯 What You'll See in the Preorder Form:</h3>";
        echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 8px; border: 2px solid #4CAF50;'>";
        echo "<h4>✅ 3 Variations (ALWAYS shown):</h4>";
        echo "<ol>";
        echo "<li><strong>Premium</strong> (₱450/kg) - Available: ~50kg (150kg ÷ 3)</li>";
        echo "<li><strong>Type A</strong> (₱400/kg) - Available: ~50kg (150kg ÷ 3)</li>";
        echo "<li><strong>Type B</strong> (₱380/kg) - Available: ~50kg (150kg ÷ 3)</li>";
        echo "</ol>";
        
        echo "<h4>✅ Unit:</h4>";
        echo "<p><strong>Kilogram ONLY</strong> (dropdown with options 1-50kg based on available)</p>";
        
        echo "<h4>ℹ️ How It Works:</h4>";
        echo "<ul>";
        echo "<li>📊 <strong>Estimated harvest:</strong> 150kg total (set by farmer when planting)</li>";
        echo "<li>🎯 <strong>Equal split assumption:</strong> 50kg per variation (until actual harvest)</li>";
        echo "<li>📉 <strong>Available decreases:</strong> As consumers make preorders</li>";
        echo "<li>🌾 <strong>After harvest:</strong> Farmer records actual Premium (30kg), Type A (90kg), Type B (30kg)</li>";
        echo "<li>📦 <strong>Stock updates:</strong> Product stock auto-updates with harvest quantities</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<hr>";
        echo "<p><strong>📱 Next Step:</strong> Go to the product detail page and click 'Pre-Order Now'</p>";
        
    } else {
        echo "<p style='color: red;'>❌ No products found in database</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
