<?php
// Включаем отображение всех ошибок
ini_set('log_errors', 1);
ini_set('error_log', '../log.txt'); // Устанавливаем файл для записи логов
ini_set('display_errors', 1); // Включаем отображение ошибок на экран

session_start(); // Для использования уведомлений через сессии

define('ACCESS_ALLOWED', true);
// Определяем путь к конфигурационному файлу в зависимости от операции
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = include '../config/config.php'; // Для операций сохранения
} else {
    $config = include 'config/config.php'; // Для операций вывода данных
}

// Функция для генерации UUID
function generateUUID(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Подключение к базам данных
try {
    // Подключение к локальной базе данных
    $localPdo = new PDO(
        "mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}",
        $config['local']['username'],
        $config['local']['password'],
        [
            PDO::ATTR_TIMEOUT => 5, // Таймаут подключения (5 секунд)
        ]
    );
    $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Подключение к удалённым базам данных
    $remotePdo = null;
    $remote2Pdo = null;

    try {
        $remotePdo = new PDO(
            "mysql:host={$config['remote']['host']};dbname={$config['remote']['dbname']};charset={$config['remote']['charset']}",
            $config['remote']['username'],
            $config['remote']['password'],
            [
                PDO::ATTR_TIMEOUT => 5, // Таймаут подключения (5 секунд)
            ]
        );
        $remotePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("Remote DB connection failed: " . $e->getMessage());
    }

    try {
        $remote2Pdo = new PDO(
            "mysql:host={$config['remote_2']['host']};dbname={$config['remote_2']['dbname']};charset={$config['remote_2']['charset']}",
            $config['remote_2']['username'],
            $config['remote_2']['password'],
            [
                PDO::ATTR_TIMEOUT => 5, // Таймаут подключения (5 секунд)
            ]
        );
        $remote2Pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log("Remote_2 DB connection failed: " . $e->getMessage());
    }

    // Проверяем, передан ли global_id
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $_SESSION['message'] = "Product ID is missing.";
        $_SESSION['message_type'] = "warning";
        header('Location: ../index.php');
        exit;
    }

    $global_id = $_GET['id'];

    // Получаем текущие данные товара из локальной базы
    $stmt = $localPdo->prepare("SELECT * FROM products WHERE global_id = :global_id");
    $stmt->execute([':global_id' => $global_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['message'] = "Product not found in the local database.";
        $_SESSION['message_type'] = "warning";
        header('Location: ../index.php');
        exit;
    }

    // Получаем категории из базы данных
    try {
        $stmtCategories = $localPdo->query("SELECT id, name FROM categories ORDER BY name ASC");
        $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching categories: " . $e->getMessage());
    }

    // Обработка отправки формы
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $brand = $_POST['brand'];
        $weight_or_volume = $_POST['weight_or_volume'];
        $price = $_POST['price'];
        $stock_quantity = $_POST['stock_quantity'];
        $category_id = $_POST['category_id'];

        // Обновление локальной базы данных
        try {
            $stmt = $localPdo->prepare("UPDATE products SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price, stock_quantity = :stock_quantity, category_id = :category_id WHERE global_id = :global_id");
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

        // Функция для обновления удалённых баз данных
        function updateRemoteDatabase($pdo, $queryText, $params, $global_id, $logFallback = false, $targetDeviceId = '') {
            global $localPdo;
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare($queryText);
                    $stmt->execute($params);
                    $_SESSION['message'] .= " Product successfully updated in the remote database.";
                } catch (PDOException $e) {
                    if ($logFallback) {
                        logQuery($localPdo, 'edit', $queryText, $global_id, $targetDeviceId);
                    }
                    error_log("Failed to update remote DB: " . $e->getMessage());
                    $_SESSION['message'] .= " However, the remote database update failed.";
                    $_SESSION['message_type'] = "warning";
                }
            } else {
                if ($logFallback) {
                    logQuery($localPdo, 'edit', $queryText, $global_id, $targetDeviceId);
                }
                $_SESSION['message'] .= " Remote server is unavailable. Changes logged for future synchronization.";
                $_SESSION['message_type'] = "warning";
            }
        }

        $queryText = "UPDATE products SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price, stock_quantity = :stock_quantity, category_id = :category_id WHERE global_id = :global_id";
        $params = [
            ':name' => $name,
            ':brand' => $brand,
            ':weight_or_volume' => $weight_or_volume,
            ':price' => $price,
            ':stock_quantity' => $stock_quantity,
            ':category_id' => $category_id,
            ':global_id' => $global_id
        ];

        // Обновление удалённых баз данных
        updateRemoteDatabase($remotePdo, $queryText, $params, $global_id, true, $config['remote']['device_id']);
        updateRemoteDatabase($remote2Pdo, $queryText, $params, $global_id, true, $config['remote_2']['device_id']);

        // Перенаправление после успешного обновления
        header('Location: ../index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "An error occurred: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

// Функция для логирования запросов в таблицу pending_queries
function logQuery(PDO $localPdo, string $queryType, string $queryText, string $globalId, string $deviceId): void {
    try {
        $stmt = $localPdo->prepare("INSERT INTO pending_queries (query_type, query_text, global_id, device_id) VALUES (:query_type, :query_text, :global_id, :device_id)");
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
