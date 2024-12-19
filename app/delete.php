<?php
// Включаем отображение всех ошибок
ini_set('log_errors', 1);
ini_set('error_log', '../log.txt'); // Устанавливаем файл для записи логов
ini_set('display_errors', 1); // Включаем отображение ошибок на экран

session_start(); // Для использования уведомлений через сессии

define('ACCESS_ALLOWED', true);
$config = include '../config/config.php';

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
    $remotePdo = connectRemote($config['remote']);
    $remote2Pdo = connectRemote($config['remote_2']);

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

    // Попытка удаления из удалённых баз данных
    foreach (['remote' => $remotePdo, 'remote_2' => $remote2Pdo] as $device => $pdo) {
        $device_id = $config[$device]['device_id'];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE global_id = :global_id");
                $stmt->execute([':global_id' => $global_id]);

                if ($stmt->rowCount() === 0) {
                    $_SESSION['message'] .= " Product not found in the $device database.";
                    $_SESSION['message_type'] = "warning";
                } else {
                    $_SESSION['message'] .= " Product successfully deleted from the $device database.";
                }
            } catch (PDOException $e) {
                $queryText = "DELETE FROM products WHERE global_id = '$global_id'";
                logQuery($localPdo, 'delete', $queryText, $global_id, $device_id, 'failed');
                echo "Error deleting product from $device DB: " . $e->getMessage();
                $_SESSION['message'] .= " However, the $device database update failed.";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $queryText = "DELETE FROM products WHERE global_id = '$global_id'";
            logQuery($localPdo, 'delete', $queryText, $global_id, $device_id, 'pending');
            echo "$device server is unavailable. Changes logged for future synchronization.";
            $_SESSION['message'] .= " $device server is unavailable. Changes logged for future synchronization.";
            $_SESSION['message_type'] = "warning";
        }
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

// Функция для подключения к удалённым базам данных
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
        echo "Remote DB connection failed: " . $e->getMessage();
        return null;
    }
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
        echo "Failed to log query: " . $e->getMessage();
    }
}
