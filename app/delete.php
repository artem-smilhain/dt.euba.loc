<?php
// Подключаем конфигурацию
session_start(); // Для использования уведомлений через сессии

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
        $_SESSION['message'] = "Product ID is missing.";
        $_SESSION['message_type'] = "warning";
        header('Location: ../index.php');
        exit;
    }

    $global_id = $_GET['id'];

    // Удаление из локальной базы данных
    try {
        $stmt = $localPdo->prepare("DELETE FROM products WHERE global_id = :global_id");
        $stmt->execute([':global_id' => $global_id]);

        if ($stmt->rowCount() === 0) {
            $_SESSION['message'] = "Product not found in the local database.";
            $_SESSION['message_type'] = "warning";
        } else {
            $_SESSION['message'] = "Product successfully deleted from the local database.";
            $_SESSION['message_type'] = "success";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error deleting product from local database: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    // Удаление из удалённой базы данных (если подключение удалось)
    if ($remotePdo) {
        try {
            $stmt = $remotePdo->prepare("DELETE FROM products WHERE global_id = :global_id");
            $stmt->execute([':global_id' => $global_id]);

            if ($stmt->rowCount() === 0) {
                $_SESSION['message'] .= " Product not found in the remote database.";
                $_SESSION['message_type'] = "warning";
            } else {
                $_SESSION['message'] .= " Product successfully deleted from the remote database.";
            }
        } catch (PDOException $e) {
            // Логирование запроса, если не удалось удалить данные в удалённой базе
            $queryText = "DELETE FROM products WHERE global_id = '$global_id'";
            logQuery($localPdo, 'delete', $queryText, $global_id);
            error_log("Error deleting product from remote DB: " . $e->getMessage());
            $_SESSION['message'] .= " However, the remote database update failed.";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        // Логирование запроса, если удалённый сервер недоступен
        $queryText = "DELETE FROM products WHERE global_id = '$global_id'";
        logQuery($localPdo, 'delete', $queryText, $global_id);
        $_SESSION['message'] .= " Remote server is unavailable. Changes logged for future synchronization.";
        $_SESSION['message_type'] = "warning";
    }

    // Перенаправление после завершения
    header('Location: ../index.php');
    exit;
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
