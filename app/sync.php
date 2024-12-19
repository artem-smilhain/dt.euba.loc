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

    // Выполнение запросов для синхронизации
    $localDeviceId = $config['local']['device_id'];
    $pendingQueriesStmt = $localPdo->prepare("SELECT * FROM pending_queries WHERE device_id = :device_id");
    $pendingQueriesStmt->execute([':device_id' => $localDeviceId]);
    $pendingQueries = $pendingQueriesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pendingQueries as $query) {
        try {
            // Выполняем запрос на устройстве
            $targetPdo = null;
            if ($query['device_id'] === $config['remote']['device_id']) {
                $targetPdo = $remotePdo;
            } elseif ($query['device_id'] === $config['remote_2']['device_id']) {
                $targetPdo = $remote2Pdo;
            }

            if ($targetPdo) {
                $targetPdo->exec($query['query_text']);

                // Удаляем выполненный запрос
                $deleteStmt = $localPdo->prepare("DELETE FROM pending_queries WHERE id = :id");
                $deleteStmt->execute([':id' => $query['id']]);
                $_SESSION['message'] .= " Query successfully synchronized for device ID {$query['device_id']}.";
            } else {
                $_SESSION['message'] .= " Target PDO not available for device ID {$query['device_id']}";
            }
        } catch (PDOException $e) {
            error_log("Failed to execute query for device ID {$query['device_id']}: " . $e->getMessage());
        }
    }

    // Перенаправление после синхронизации
    $_SESSION['message_type'] = "success";
    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['message'] = "An error occurred: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header('Location: ../index.php');
    exit;
}
