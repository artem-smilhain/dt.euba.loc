<?php
session_start();
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

    // Подключение к удалённой базе данных
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
        $remotePdo = null; // Если подключение к удалённой БД не удалось
        error_log("Remote DB connection failed: " . $e->getMessage());
    }

    // Проверяем, передан ли global_id
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        header('Location: ../index.php');
        exit;
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

    // Получение категорий и устройств для выпадающих списков
    $categoriesStmt = $localPdo->query("SELECT id, name FROM categories");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $devicesStmt = $localPdo->query("SELECT id, name FROM devices");
    $devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);

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
            $stmt = $localPdo->prepare("
                UPDATE products
                SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price,
                    stock_quantity = :stock_quantity, category_id = :category_id
                WHERE global_id = :global_id
            ");
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

        // Обновление удалённой базы данных (если подключение удалось)
        if ($remotePdo) {
            try {
                $stmt = $remotePdo->prepare("
                    UPDATE products
                    SET name = :name, brand = :brand, weight_or_volume = :weight_or_volume, price = :price,
                        stock_quantity = :stock_quantity, category_id = :category_id
                    WHERE global_id = :global_id
                ");
                $stmt->execute([
                    ':name' => $name,
                    ':brand' => $brand,
                    ':weight_or_volume' => $weight_or_volume,
                    ':price' => $price,
                    ':stock_quantity' => $stock_quantity,
                    ':category_id' => $category_id,
                    ':global_id' => $global_id
                ]);
                $_SESSION['message'] .= " Product successfully updated in the remote database.";
            } catch (PDOException $e) {
                // Логирование запроса, если не удалось обновить данные в удалённой базе
                $queryText = "
                    UPDATE products
                    SET name = '$name', brand = '$brand', weight_or_volume = '$weight_or_volume', price = $price,
                        stock_quantity = $stock_quantity, category_id = $category_id
                    WHERE global_id = '$global_id'
                ";
                logQuery($localPdo, 'edit', $queryText, $global_id);
                error_log("Failed to update remote DB: " . $e->getMessage());
                $_SESSION['message'] .= " However, the remote database update failed.";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            // Логирование запроса, если удалённый сервер недоступен
            $queryText = "
                UPDATE products
                SET name = '$name', brand = '$brand', weight_or_volume = '$weight_or_volume', price = $price,
                    stock_quantity = $stock_quantity, category_id = $category_id
                WHERE global_id = '$global_id'
            ";
            logQuery($localPdo, 'edit', $queryText, $global_id);
            $_SESSION['message'] .= " Remote server is unavailable. Changes logged for future synchronization.";
            $_SESSION['message_type'] = "warning";
        }

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
function logQuery(PDO $localPdo, string $queryType, string $queryText, string $globalId): void {
    try {
        $stmt = $localPdo->prepare("
            INSERT INTO pending_queries (query_type, query_text, global_id)
            VALUES (:query_type, :query_text, :global_id)
        ");
        $stmt->execute([
            ':query_type' => $queryType,
            ':query_text' => $queryText,
            ':global_id' => $globalId
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log query: " . $e->getMessage());
    }
}
?>
