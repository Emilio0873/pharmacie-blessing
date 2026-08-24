<?php
require 'config/db.php';
$code = "TEST0".rand(1000,9999);
$name = "Test ".rand(100,999);
$description = "";
$category_id = null;
$brand = "";
$buy_price = 10;
$sell_price = 20;
$qty = 10;
$alert_threshold = 5;
$image_name = "";
$mfg_date = null;
$exp_date = null;

try {
    $stmt = $pdo->prepare("INSERT INTO products (code, name, description, category_id, brand, buy_price, sell_price, qty, alert_threshold, image, mfg_date, exp_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $name, $description, $category_id, $brand, $buy_price, $sell_price, $qty, $alert_threshold, $image_name, $mfg_date, $exp_date]);
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
