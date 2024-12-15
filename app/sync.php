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
    // Подключение к локальной базе данных
    $localPdo = new PDO(
        "mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}",
        $config['local']['username'],
        $config['local']['password'],
        [
            PDO::ATTR_TIMEOUT => 5, // Таймаут подключения
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
                PDO::ATTR_TIMEOUT => 5, // Таймаут подключения
            ]
        );
        $remotePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Получение несработанных SQL-запросов с удалённого сервера
        $stmt = $remotePdo->prepare("SELECT * FROM pending_queries WHERE status = 'pending'");
        $stmt->execute();
        $pendingQueries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Выполнение запросов в локальной базе данных
        foreach ($pendingQueries as $query) {
            try {
                $localPdo->exec($query['query_text']); // Выполнение SQL-запроса

                // Пометка выполненных запросов как `executed` в удалённой базе данных
                $updateStmt = $remotePdo->prepare("UPDATE pending_queries SET status = 'executed' WHERE id = :id");
                $updateStmt->execute([':id' => $query['id']]);
            } catch (PDOException $e) {
                error_log("Failed to execute query locally: " . $e->getMessage());
            }
        }

        echo "<p>Synchronization completed successfully!</p>";

    } catch (PDOException $e) {
        error_log("Remote DB connection failed: " . $e->getMessage());
        echo "<p>Error: Unable to connect to remote database.</p>";
    }

} catch (PDOException $e) {
    die("Local DB Error: " . $e->getMessage());
}
?>
