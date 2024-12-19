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
        [PDO::ATTR_TIMEOUT => 5]
    );
    $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $localDeviceId = $config['local']['device_id'];

    // Подключение к удалённым базам данных
    $remoteConnections = [];
    foreach (['remote', 'remote_2'] as $remoteKey) {
        if (!empty($config[$remoteKey]['host'])) {
            try {
                $pdo = new PDO(
                    "mysql:host={$config[$remoteKey]['host']};dbname={$config[$remoteKey]['dbname']};charset={$config[$remoteKey]['charset']}",
                    $config[$remoteKey]['username'],
                    $config[$remoteKey]['password'],
                    [PDO::ATTR_TIMEOUT => 5]
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $remoteConnections[] = ['pdo' => $pdo, 'device_id' => $config[$remoteKey]['device_id']];
            } catch (PDOException $e) {
                echo "Failed to connect to remote DB ({$remoteKey}): " . $e->getMessage();
            }
        }
    }

    // Выполнение SQL запросов для локального устройства
    foreach ($remoteConnections as $connection) {
        $remotePdo = $connection['pdo'];
        $remoteDeviceId = $connection['device_id'];

        // Получение записей для текущего устройства
        $stmt = $remotePdo->prepare("SELECT id, query_text, global_id FROM pending_queries WHERE device_id = :device_id AND status = 'pending'");
        $stmt->execute([':device_id' => $localDeviceId]);
        $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($queries as $query) {
            try {
                // Подготовка запроса для локальной базы данных
                $preparedStmt = $localPdo->prepare($query['query_text']);

                // Получение параметров (если они есть) для запроса
                $params = json_decode($query['global_id'], true) ?? [];
                $preparedStmt->execute($params);

                // Обновление статуса на удалённой базе данных
                $updateStmt = $remotePdo->prepare("UPDATE pending_queries SET status = 'completed' WHERE id = :id");
                $updateStmt->execute([':id' => $query['id']]);

                // Логирование успешного выполнения
                echo "Query ID {$query['id']} executed successfully for device ID $localDeviceId.";
            } catch (PDOException $e) {
                echo "Failed to execute query ID {$query['id']} on local DB: " . $e->getMessage();
            }
        }
    }

    $_SESSION['message'] = "SQL queries for device ID $localDeviceId executed successfully.";
    $_SESSION['message_type'] = "success";
    //header('Location: ../index.php');
} catch (PDOException $e) {
    $_SESSION['message'] = "An error occurred: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    //header('Location: ../index.php');
}
