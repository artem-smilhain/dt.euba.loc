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
            <div class="col-2"></div>
            <div class="col-8">
                <h2>Add new product</h2>
            </div>
            <div class="col-2"></div>
        </div>
        <div class="row">
            <div class="col-2"></div>
            <div class="col-8">
                <form action="app/add.php" method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="1">Protein Products</option>
                            <option value="2">Amino Acids & Recovery</option>
                            <option value="3">Vitamins & Wellness</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" id="brand" name="brand" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="weight_or_volume" class="form-label">Weight/Volume</label>
                        <input type="text" id="weight_or_volume" name="weight_or_volume" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (€)</label>
                        <input type="number" step="0.01" id="price" name="price" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">Stock Quantity</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success">Save new product</button>
                </form>
            </div>
            <div class="col-2"></div>
        </div>
    </div>
</main>
<footer>
    <?php include 'layouts/footer.php'; ?>
</footer>
</body>
</html>
