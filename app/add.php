<?php
    define('ACCESS_ALLOWED', true);
    $config = include '../config/config.php';

    try {
        $pdo = new PDO("mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}", $config['local']['username'], $config['local']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Получение категорий для выпадающего списка
        $categoriesStmt = $pdo->query("SELECT id, name FROM categories");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Получение устройств для выпадающего списка
        $devicesStmt = $pdo->query("SELECT id, name FROM devices");
        $devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

    // Обработка отправки формы
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $brand = $_POST['brand'];
        $weight_or_volume = $_POST['weight_or_volume'];
        $price = $_POST['price'];
        $stock_quantity = $_POST['stock_quantity'];
        $category_id = $_POST['category_id'];
        $device_id = $config['local']['device_id'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, brand, weight_or_volume, price, stock_quantity, category_id, device_id)
                VALUES (:name, :brand, :weight_or_volume, :price, :stock_quantity, :category_id, :device_id)
            ");
            $stmt->execute([
                ':name' => $name,
                ':brand' => $brand,
                ':weight_or_volume' => $weight_or_volume,
                ':price' => $price,
                ':stock_quantity' => $stock_quantity,
                ':category_id' => $category_id,
                ':device_id' => $device_id,
            ]);
            header('Location: ../index.php');
            exit;
        } catch (PDOException $e) {
            echo "<p class='alert alert-danger'>Error: " . $e->getMessage() . "</p>";
        }
    }