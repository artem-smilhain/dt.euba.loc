<?php
    define('ACCESS_ALLOWED', true);
    $config = include 'config/config.php';

    try {
        $pdo = new PDO(
            "mysql:host={$config['local']['host']};dbname={$config['local']['dbname']};charset={$config['local']['charset']}",
            $config['local']['username'],
            $config['local']['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch products with category and device information
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.name AS product_name, 
                c.name AS category_name, 
                c.elem_class AS category_class,
                p.weight_or_volume, 
                p.price, 
                p.stock_quantity,
                d.id AS device_id,
                d.name AS device_name,
                d.type AS device_type
            FROM 
                products p
            INNER JOIN 
                categories c
            ON 
                p.category_id = c.id
            INNER JOIN
                devices d
            ON
                p.device_id = d.id
        ");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $device_id = $config['local']['device_id'];
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }