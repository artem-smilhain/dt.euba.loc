<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>DT EUBA PROJECT</title>
    <?php include 'layouts/head.php'; // подключение head ?>
</head>
<body>
    <header>
        <?php include 'layouts/header.php'; ?>
    </header>
    <main class="pt-5">
        <div class="container">
            <div class="row pb-2">
                <div class="col-1"></div>
                <div class="col-5">
                    <h2>List of products</h2>
                </div>
                <div class="col-5 text-end">
                    <a href="/add.php" class="btn btn-primary">Add product</a>
                </div>
                <div class="col-1"></div>
            </div>
            <div class="row">
                <div class="col-1"></div>
                <div class="col-10">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th scope="col" class="text-center">ID</th>
                                <th scope="col">Product</th>
                                <th scope="col">Category</th>
                                <th scope="col">Weight/Volume</th>
                                <th scope="col">Price / Stock</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php include 'app/show.php'; ?>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <th scope="row" class="text-center">P<?= htmlspecialchars($product['id']) ?></th>
                                        <td>
                                            <?= htmlspecialchars($product['product_name']) ?>
                                            <br>
                                            <span>
                                                <small class="text-secondary">
                                                    <?= htmlspecialchars($product['device_name']).' / '.htmlspecialchars($product['device_type'])?>
                                                </small>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge text-white <?= htmlspecialchars($product['category_class']) ?>">
                                                <?= htmlspecialchars($product['category_name']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($product['weight_or_volume']) ?></td>
                                        <td><?= number_format($product['price'], 2) ?>€ / <?= htmlspecialchars($product['stock_quantity']) ?></td>
                                        <td>
                                            <?php if ($product['device_id'] === $device_id): ?>
                                                <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-outline-warning btn-sm">Edit</a>
                                                <a href="app/delete.php?id=<?= $product['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No products found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="col-1"></div>
            </div>
        </div>
    </main>
    <footer>
        <?php include 'layouts/footer.php'; ?>
    </footer>
</body>
</html>
