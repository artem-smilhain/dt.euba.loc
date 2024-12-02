<?php
    // Подключаем конфигурацию
    define('ACCESS_ALLOWED', true);
    $config = include '../config/config.php';

    try {
        $pdo = new PDO("mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}", $config['local']['username'], $config['local']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Проверяем, передан ли ID для удаления
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = (int) $_GET['id'];

            // Удаляем товар по ID
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);

            // Перенаправляем на главную страницу после удаления
            header('Location: ../index.php');
            exit;
        } else {
            // Если ID не передан или некорректный, перенаправляем на главную страницу
            header('Location: ../index.php');
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }