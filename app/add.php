<?php
// Включаем отображение всех ошибок
ini_set('log_errors', 1);
ini_set('error_log', '../log.txt'); // Устанавливаем файл для записи логов
ini_set('display_errors', 1); // Включаем отображение ошибок на экран

session_start(); // Для использования уведомлений через сессии

define('ACCESS_ALLOWED', true);
$config = include '../config/config.php';

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

    // Подключение к удалённой базе данных remote
    $remotePdo = connectRemote($config['remote']);

    // Подключение к удалённой базе данных remote_2
    $remote2Pdo = connectRemote($config['remote_2']);

    // Получение категорий и устройств
    $categoriesStmt = $localPdo->query("SELECT id, name FROM categories");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $devicesStmt = $localPdo->query("SELECT id, name FROM devices");
    $devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Local DB Error: " . $e->getMessage());
    $_SESSION['message'] = "Local database error: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header('Location: ../index.php');
    exit;
}

function connectRemote(array $config): ?PDO {
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_TIMEOUT => 5, // Таймаут подключения (5 секунд)
            ]
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Remote DB connection failed: " . $e->getMessage());
        return null;
    }
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $brand = $_POST['brand'];
    $weight_or_volume = $_POST['weight_or_volume'];
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $category_id = $_POST['category_id'];

    // Локальный ID устройства
    $local_device_id = $config['local']['device_id'];

    // Генерация уникального идентификатора
    $global_id = generateUUID();

    try {
        // Подготовка SQL-запроса для локальной базы данных
        $stmt = $localPdo->prepare("INSERT INTO products (global_id, name, brand, weight_or_volume, price, stock_quantity, category_id, device_id) VALUES (:global_id, :name, :brand, :weight_or_volume, :price, :stock_quantity, :category_id, :device_id)");
        $stmt->execute([
            ':global_id' => $global_id,
            ':name' => $name,
            ':brand' => $brand,
            ':weight_or_volume' => $weight_or_volume,
            ':price' => $price,
            ':stock_quantity' => $stock_quantity,
            ':category_id' => $category_id,
            ':device_id' => $local_device_id,
        ]);

        $_SESSION['message'] = "Product added successfully to the local database.";
        $_SESSION['message_type'] = "success";

    } catch (PDOException $e) {
        error_log("Local DB Error (Insert): " . $e->getMessage());
        $_SESSION['message'] = "Error adding product to the local database: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    // Попытка записи в удалённые базы данных
    foreach (['remote' => $remotePdo, 'remote_2' => $remote2Pdo] as $device => $pdo) {
        $device_id = $config[$device]['device_id'];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO products (global_id, name, brand, weight_or_volume, price, stock_quantity, category_id, device_id) VALUES (:global_id, :name, :brand, :weight_or_volume, :price, :stock_quantity, :category_id, :device_id)");
                $stmt->execute([
                    ':global_id' => $global_id,
                    ':name' => $name,
                    ':brand' => $brand,
                    ':weight_or_volume' => $weight_or_volume,
                    ':price' => $price,
                    ':stock_quantity' => $stock_quantity,
                    ':category_id' => $category_id,
                    ':device_id' => $local_device_id,
                ]);
                $_SESSION['message'] .= " Product also added to the $device database.";
            } catch (PDOException $e) {
                $queryText = "INSERT INTO products (global_id, name, brand, weight_or_volume, price, stock_quantity, category_id, device_id) VALUES ('$global_id', '$name', '$brand', '$weight_or_volume', $price, $stock_quantity, $category_id, $local_device_id)";
                logQuery($localPdo, 'add', $queryText, $global_id, $device_id, 'failed');
                error_log("$device DB Error (Insert): " . $e->getMessage());
                $_SESSION['message'] .= " However, the $device database update failed.";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $queryText = "INSERT INTO products (global_id, name, brand, weight_or_volume, price, stock_quantity, category_id, device_id) VALUES ('$global_id', '$name', '$brand', '$weight_or_volume', $price, $stock_quantity, $category_id, $local_device_id)";
            logQuery($localPdo, 'add', $queryText, $global_id, $device_id, 'pending');
            $_SESSION['message'] .= " $device server is unavailable. Changes logged for future synchronization.";
            $_SESSION['message_type'] = "warning";
        }
    }

    // Перенаправление после обработки
    header('Location: ../index.php');
    exit;
}

// Функция для логирования запросов в таблицу pending_queries
function logQuery(PDO $localPdo, string $queryType, string $queryText, string $globalId, int $deviceId, string $status): void {
    try {
        $stmt = $localPdo->prepare("INSERT INTO pending_queries (query_type, query_text, global_id, device_id, status) VALUES (:query_type, :query_text, :global_id, :device_id, :status)");
        $stmt->execute([
            ':query_type' => $queryType,
            ':query_text' => $queryText,
            ':global_id' => $globalId,
            ':device_id' => $deviceId,
            ':status' => $status
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log query: " . $e->getMessage());
    }
}
