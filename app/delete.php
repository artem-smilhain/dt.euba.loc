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
        $config['local']['password']
    );
    $localPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Подключение к удалённой базе данных
    try {
        $remotePdo = new PDO(
            "mysql:host={$config['remote']['host']};dbname={$config['remote']['dbname']};charset={$config['remote']['charset']}",
            $config['remote']['username'],
            $config['remote']['password']
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

    $global_id = $_GET['id'];

    // Удаление из локальной базы данных
    try {
        $stmt = $localPdo->prepare("DELETE FROM products WHERE global_id = :global_id");
        $stmt->execute([':global_id' => $global_id]);

        if ($stmt->rowCount() === 0) {
            echo "<p class='alert alert-warning'>Product not found in the local database.</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='alert alert-danger'>Error deleting product from local DB: " . $e->getMessage() . "</p>";
    }

    // Удаление из удалённой базы данных (если подключение удалось)
    if ($remotePdo) {
        try {
            $stmt = $remotePdo->prepare("DELETE FROM products WHERE global_id = :global_id");
            $stmt->execute([':global_id' => $global_id]);

            if ($stmt->rowCount() === 0) {
                echo "<p class='alert alert-warning'>Product not found in the remote database.</p>";
            }
        } catch (PDOException $e) {
            error_log("Error deleting product from remote DB: " . $e->getMessage());
            echo "Error deleting product from remote DB: " . $e->getMessage();
        }
    }

    // Перенаправление после успешного удаления
    //header('Location: ../index.php');
    exit;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
