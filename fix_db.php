<?php
// fix_db.php — ONE-TIME upgrade. Uses the same connection as the app (config.php),
// so it changes exactly the database the app reads. DELETE THIS FILE afterwards.
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Connecting to " . DB_HOST . ":" . DB_PORT . "  database=" . DB_NAME . "\n\n";

try {
    $pdo = db();

    $hasCol = function (string $table, string $col) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([DB_NAME, $table, $col]);
        return (int) $st->fetchColumn() > 0;
    };
    $hasTable = function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $st->execute([DB_NAME, $table]);
        return (int) $st->fetchColumn() > 0;
    };

    // products.description
    if (!$hasCol('products', 'description')) {
        $pdo->exec('ALTER TABLE products ADD COLUMN description TEXT NULL AFTER product_name');
        echo "Added products.description\n";
    } else { echo "products.description already exists\n"; }

    // products.is_available
    if (!$hasCol('products', 'is_available')) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1');
        echo "Added products.is_available\n";
    } else { echo "products.is_available already exists\n"; }

    // orders.pickup_date
    if (!$hasCol('orders', 'pickup_date')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN pickup_date DATE NULL AFTER order_date');
        echo "Added orders.pickup_date\n";
    } else { echo "orders.pickup_date already exists\n"; }

    // ingredients
    if (!$hasTable('ingredients')) {
        $pdo->exec('CREATE TABLE ingredients (
            ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
            ingredient_name VARCHAR(100) NOT NULL,
            unit VARCHAR(20) NOT NULL,
            quantity_available DECIMAL(10,2) NOT NULL DEFAULT 0,
            INDEX idx_ingredients_name (ingredient_name)
        )');
        $pdo->exec("INSERT INTO ingredients (ingredient_name, unit, quantity_available) VALUES
            ('All-purpose flour','kg',40.00),('Granulated sugar','kg',25.00),('Butter','kg',12.50),
            ('Eggs','tray',8.00),('Cocoa powder','kg',5.00)");
        echo "Created ingredients (with sample rows)\n";
    } else { echo "ingredients already exists\n"; }

    // production_batches
    if (!$hasTable('production_batches')) {
        $pdo->exec('CREATE TABLE production_batches (
            batch_id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            quantity_produced INT NOT NULL,
            production_date DATE NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'Planned\',
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
            INDEX idx_batches_date (production_date)
        )');
        $first = $pdo->query('SELECT product_id FROM products ORDER BY product_id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
        $ins = $pdo->prepare('INSERT INTO production_batches (product_id, quantity_produced, production_date, status) VALUES (?, ?, CURDATE(), ?)');
        $statuses = ['Completed', 'In Production', 'Planned'];
        foreach ($first as $i => $pid) { $ins->execute([$pid, 20, $statuses[$i]]); }
        echo "Created production_batches (with sample rows)\n";
    } else { echo "production_batches already exists\n"; }

    // Seed corrections (only touch the known sample rows)
    $pdo->exec("UPDATE orders SET total_amount = 230.00 WHERE order_id = 2 AND total_amount = 240.00");
    $pdo->exec("UPDATE orders SET total_amount = 280.00 WHERE order_id = 5 AND total_amount = 300.00");
    $pdo->exec("UPDATE customers SET email = 'rea.rodriguez@email.com' WHERE email = 'rea.redriguez@email.com'");
    echo "Sample-data corrections applied\n";

    echo "\nProducts now: " . $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() . " rows\n";
    echo "\nDONE. Delete fix_db.php and test_db.php, set DEBUG back to false, then reload the app (Ctrl+F5).\n";
} catch (Throwable $e) {
    echo "FAILED:\n" . $e->getMessage() . "\n";
}