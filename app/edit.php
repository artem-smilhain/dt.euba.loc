<?php
session_start();

define('ACCESS_ALLOWED', true);
if (file_exists('config/config.php')) {
    $config = include 'config/config.php';
} elseif (file_exists('../config/config.php')) {
    $config = include '../config/config.php';
} else {
    die('Configuration file not found in both paths.');
}

try {
    // Подключение к локальной базе данных
    $localPdo = new PDO(
        "mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}",
        $config['local']['username'],
        $config['local']['password'],
        [PDO::ATTR_TIMEOUT => 5]
    );
    $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Подключения к удалённым базам данных
    $remoteConnections = [];
    foreach (['remote', 'remote_2'] as $key) {
        try {
            $pdo = new PDO(
                "mysql:host={$config[$key]['host']};dbname={$config[$key]['dbname']};charset={$config[$key]['charset']}",
                $config[$key]['username'],
                $config[$key]['password'],
                [PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $remoteConnections[$key] = $pdo;
        } catch (PDOException $e) {
            $remoteConnections[$key] = null;
            error_log("Connection to {$key} database failed: " . $e->getMessage());
        }
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $_SESSION['message'] = "Product ID is missing.";
        $_SESSION['message_type'] = "warning";
        header('Location: ../index.php');
        exit;
    }

    $global_id = $_GET['id'];

    $stmt = $localPdo->prepare("SELECT * FROM products WHERE global_id = :global_id");
    $stmt->execute([':global_id' => $global_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['message'] = "Product not found in the local database.";
        $_SESSION['message_type'] = "warning";
        header('Location: ../index.php');
        exit;
    }

    $categories = $localPdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    $devices = $localPdo->query("SELECT id, name FROM devices")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $brand = $_POST['brand'];
        $weight_or_volume = $_POST['weight_or_volume'];
        $price = $_POST['price'];
        $stock_quantity = $_POST['stock_quantity'];
        $category_id = $_POST['category_id'];

        try {
            $stmt = $localPdo->prepare(
                "UPDATE products SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price,
                stock_quantity = :stock_quantity, category_id = :category_id WHERE global_id = :global_id"
            );
            $stmt->execute([
                ':name' => $name,
                ':brand' => $brand,
                ':weight_or_volume' => $weight_or_volume,
                ':price' => $price,
                ':stock_quantity' => $stock_quantity,
                ':category_id' => $category_id,
                ':global_id' => $global_id
            ]);
            $_SESSION['message'] = "Product successfully updated in the local database.";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error updating product in the local database: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }

        foreach ($remoteConnections as $key => $pdo) {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare(
                        "UPDATE products SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price,
                        stock_quantity = :stock_quantity, category_id = :category_id WHERE global_id = :global_id"
                    );
                    $stmt->execute([
                        ':name' => $name,
                        ':brand' => $brand,
                        ':weight_or_volume' => $weight_or_volume,
                        ':price' => $price,
                        ':stock_quantity' => $stock_quantity,
                        ':category_id' => $category_id,
                        ':global_id' => $global_id
                    ]);
                    $_SESSION['message'] .= " Product successfully updated in {$key} database.";
                } catch (PDOException $e) {
                    logQuery($localPdo, 'edit', $stmt->queryString, $global_id, $config[$key]['device_id'], [
                        ':name' => $name,
                        ':brand' => $brand,
                        ':weight_or_volume' => $weight_or_volume,
                        ':price' => $price,
                        ':stock_quantity' => $stock_quantity,
                        ':category_id' => $category_id,
                        ':global_id' => $global_id
                    ]);
                    error_log("Failed to update {$key} DB: " . $e->getMessage());
                    $_SESSION['message'] .= " However, the {$key} database update failed.";
                    $_SESSION['message_type'] = "warning";
                }
            } else {
                logQuery($localPdo, 'edit', "UPDATE products SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price, stock_quantity = :stock_quantity, category_id = :category_id WHERE global_id = :global_id", $global_id, $config[$key]['device_id'], [
                    ':name' => $name,
                    ':brand' => $brand,
                    ':weight_or_volume' => $weight_or_volume,
                    ':price' => $price,
                    ':stock_quantity' => $stock_quantity,
                    ':category_id' => $category_id,
                    ':global_id' => $global_id
                ]);
                $_SESSION['message'] .= " {$key} server is unavailable. Changes logged for future synchronization.";
                $_SESSION['message_type'] = "warning";
            }
        }

        header('Location: ../index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "An error occurred: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

function logQuery(PDO $localPdo, string $queryType, string $queryText, string $globalId, int $deviceId, array $params): void {
    // Заменяем параметры на их значения в строке запроса
    foreach ($params as $key => $value) {
        $value = $localPdo->quote($value); // Экранируем значения
        $queryText = str_replace($key, $value, $queryText);
    }

    try {
        $stmt = $localPdo->prepare(
            "INSERT INTO pending_queries (query_type, query_text, global_id, device_id) VALUES (:query_type, :query_text, :global_id, :device_id)"
        );
        $stmt->execute([
            ':query_type' => $queryType,
            ':query_text' => $queryText,
            ':global_id' => $globalId,
            ':device_id' => $deviceId
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log query: " . $e->getMessage());
    }
}
