<?php
    // Подключаем конфигурацию
    define('ACCESS_ALLOWED', true);
    if (file_exists('config/config.php')) {
        $config = include 'config/config.php';
    } elseif (file_exists('../config/config.php')) {
        $config = include '../config/config.php';
    } else {
        die('Configuration file not found in both paths.');
    }

    try {
        $pdo = new PDO("mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}", $config['local']['username'], $config['local']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Проверяем, передан ли ID
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            header('Location: ../index.php');
            exit;
        }

        $id = (int) $_GET['id'];

        // Получаем текущие данные товара
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            header('Location: ../index.php');
            exit;
        }

        // Получение категорий и устройств для выпадающих списков
        $categoriesStmt = $pdo->query("SELECT id, name FROM categories");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

        $devicesStmt = $pdo->query("SELECT id, name FROM devices");
        $devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Обработка отправки формы
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'];
            $brand = $_POST['brand'];
            $weight_or_volume = $_POST['weight_or_volume'];
            $price = $_POST['price'];
            $stock_quantity = $_POST['stock_quantity'];
            $category_id = $_POST['category_id'];

            try {
                $stmt = $pdo->prepare("
                    UPDATE products
                    SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price,
                        stock_quantity = :stock_quantity, category_id = :category_id
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':brand' => $brand,
                    ':weight_or_volume' => $weight_or_volume,
                    ':price' => $price,
                    ':stock_quantity' => $stock_quantity,
                    ':category_id' => $category_id,
                    ':id' => $id
                ]);

                // Перенаправление после успешного обновления
                header('Location: ../index.php');
                exit;
            } catch (PDOException $e) {
                echo "<p class='alert alert-danger'>Error: " . $e->getMessage() . "</p>";
            }
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }