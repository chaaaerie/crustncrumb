<?php
/**
 * api.php — JSON API for the Crust & Crumb Bakeshop System.
 *
 * Usage:  api.php?action=<name>
 *   GET  data             -> products, orders, customers, ingredients, batches (scoped to role)
 *   POST login            -> { role, username, password }
 *   POST logout
 *   POST product_save     -> { id?, name, category, description, price, stock, available }  (admin)
 *   POST product_delete   -> { id }                                                           (admin)
 *   POST customer_save    -> { id?, first_name, last_name, email, phone }                    (admin/staff)
 *   POST customer_delete  -> { id }                                                           (admin/staff)
 *   POST order_create     -> { customer_id?, pickup_date?, items:[{product_id,quantity}] }
 *   POST order_advance    -> { id }          Pending -> Preparing                             (admin/staff)
 *   POST order_pay        -> { id, method, amount_tendered }
 *                            Preparing -> Completed + payments row                            (admin/staff)
 *   POST ingredient_save  -> { id?, name, unit, quantity }                                   (admin/staff)
 *   POST ingredient_delete-> { id }                                                           (admin/staff)
 *   POST batch_save       -> { id?, product_id, quantity, production_date, status }          (admin/staff)
 *   POST batch_delete     -> { id }                                                           (admin/staff)
 */

require __DIR__ . '/config.php';
date_default_timezone_set('Asia/Manila');

const INGREDIENT_UNITS = ['kg', 'g', 'L', 'mL', 'pcs', 'pack', 'tray'];
const BATCH_STATUSES   = ['Planned', 'In Production', 'Completed', 'Cancelled'];

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input  = [];

if ($method === 'POST') {
    // CSRF check
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $sent)) {
        fail('Invalid or missing security token. Please refresh the page.', 403);
    }
    $raw   = file_get_contents('php://input');
    $input = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($input)) {
        fail('Invalid JSON body.', 400);
    }
}

try {
    switch ($action) {
        case 'data':
            need_method('GET', $method);
            action_data();
            break;
        case 'login':
            need_method('POST', $method);
            action_login($input);
            break;
        case 'logout':
            need_method('POST', $method);
            $_SESSION = [];
            session_destroy();
            respond();
            break;
        case 'product_save':
            need_method('POST', $method);
            action_product_save($input);
            break;
        case 'product_delete':
            need_method('POST', $method);
            action_product_delete($input);
            break;
        case 'customer_save':
            need_method('POST', $method);
            action_customer_save($input);
            break;
        case 'customer_delete':
            need_method('POST', $method);
            action_customer_delete($input);
            break;
        case 'order_create':
            need_method('POST', $method);
            action_order_create($input);
            break;
        case 'order_advance':
            need_method('POST', $method);
            action_order_advance($input);
            break;
        case 'order_pay':
            need_method('POST', $method);
            action_order_pay($input);
            break;
        case 'ingredient_save':
            need_method('POST', $method);
            action_ingredient_save($input);
            break;
        case 'ingredient_delete':
            need_method('POST', $method);
            action_ingredient_delete($input);
            break;
        case 'batch_save':
            need_method('POST', $method);
            action_batch_save($input);
            break;
        case 'batch_delete':
            need_method('POST', $method);
            action_batch_delete($input);
            break;
        default:
            fail('Unknown action.', 404);
    }
} catch (Throwable $e) {
    try {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Throwable $ignored) {
        // DB unreachable; nothing to roll back
    }
    error_log('[bakeshop api] ' . $e->getMessage());
    fail(DEBUG ? $e->getMessage() : 'Server error. Please try again.', 500);
}

// =====================================================================
// Helpers
// =====================================================================

function need_method(string $expected, string $actual): void
{
    if ($expected !== $actual) {
        fail("Use $expected for this action.", 405);
    }
}

function cents($amount): int
{
    return (int) round(((float) $amount) * 100);
}

/** Returns a valid Y-m-d string, or null if the value is not a real date. */
function clean_date($value): ?string
{
    $value = trim((string) $value);
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

// =====================================================================
// Actions
// =====================================================================

function action_login(array $in): void
{
    $role     = (string) ($in['role'] ?? '');
    $username = trim((string) ($in['username'] ?? ''));
    $password = (string) ($in['password'] ?? '');

    if (!in_array($role, ['admin', 'staff', 'customer'], true) || $username === '' || $password === '') {
        fail('Please enter your role, username and password.', 422);
    }

    $st = db()->prepare('SELECT user_id, username, password, role, reference_id
                         FROM users WHERE username = ? AND role = ? LIMIT 1');
    $st->execute([$username, $role]);
    $u = $st->fetch();

    $valid = false;
    if ($u) {
        $stored = $u['password'];
        if (password_get_info($stored)['algo'] !== null) {
            // Already hashed
            $valid = password_verify($password, $stored);
        } else {
            // Plain-text seed password from database.sql: compare, then upgrade to a hash
            $valid = hash_equals($stored, $password);
            if ($valid) {
                $up = db()->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $up->execute([password_hash($password, PASSWORD_DEFAULT), $u['user_id']]);
            }
        }
    }

    if (!$valid) {
        fail('Invalid username, password, or role.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'           => (int) $u['user_id'],
        'username'     => $u['username'],
        'role'         => $u['role'],
        'reference_id' => $u['reference_id'] !== null ? (int) $u['reference_id'] : null,
    ];

    respond(['user' => ['username' => $u['username'], 'role' => $u['role']]]);
}

function action_data(): void
{
    $user = require_login();
    $pdo  = db();
    $isCustomer = $user['role'] === 'customer';
    $myCustomerId = $user['reference_id'] ?? 0;

    // Products
    $products = [];
    $rows = $pdo->query(
        'SELECT p.product_id, p.product_name, p.description, c.category_name, p.price,
                p.stock_quantity, p.is_available
         FROM products p LEFT JOIN categories c ON c.category_id = p.category_id
         ORDER BY p.product_id'
    )->fetchAll();
    foreach ($rows as $r) {
        $products[] = [
            'id'          => (int) $r['product_id'],
            'code'        => sprintf('PRD-%03d', $r['product_id']),
            'name'        => $r['product_name'],
            'description' => $r['description'] ?? '',
            'category'    => $r['category_name'] ?? 'Uncategorized',
            'price'       => (float) $r['price'],
            'stock'       => (int) $r['stock_quantity'],
            'available'   => (bool) $r['is_available'],
        ];
    }

    // Orders (customers only see their own)
    $sql = "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) AS customer,
                   o.order_date, o.pickup_date, o.total_amount, o.status,
                   (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.product_name)
                                        ORDER BY oi.order_item_id SEPARATOR ', ')
                    FROM order_items oi
                    JOIN products p ON p.product_id = oi.product_id
                    WHERE oi.order_id = o.order_id) AS items
            FROM orders o
            LEFT JOIN customers c ON c.customer_id = o.customer_id";
    if ($isCustomer) {
        $st = $pdo->prepare($sql . ' WHERE o.customer_id = ? ORDER BY o.order_id DESC');
        $st->execute([$myCustomerId]);
    } else {
        $st = $pdo->query($sql . ' ORDER BY o.order_id DESC');
    }
    $orders = [];
    foreach ($st->fetchAll() as $r) {
        $orders[] = [
            'id'       => (int) $r['order_id'],
            'code'     => 'ORD-' . (100 + (int) $r['order_id']),
            'customer' => $r['customer'] ?? 'Walk-in',
            'items'    => $r['items'] ?? '',
            'ordered'  => $r['order_date'] ? substr($r['order_date'], 0, 10) : '',
            'pickup'   => $r['pickup_date'] ?? '',
            'total'    => (float) $r['total_amount'],
            'status'   => $r['status'],
        ];
    }

    // Customers (customers only see themselves)
    $csql = 'SELECT customer_id, first_name, last_name, email, phone FROM customers';
    if ($isCustomer) {
        $st = $pdo->prepare($csql . ' WHERE customer_id = ?');
        $st->execute([$myCustomerId]);
    } else {
        $st = $pdo->query($csql . ' ORDER BY customer_id');
    }
    $customers = [];
    foreach ($st->fetchAll() as $r) {
        $customers[] = [
            'id'         => (int) $r['customer_id'],
            'code'       => sprintf('CUST-%03d', $r['customer_id']),
            'first_name' => $r['first_name'],
            'last_name'  => $r['last_name'],
            'name'       => $r['first_name'] . ' ' . $r['last_name'],
            'email'      => $r['email'],
            'phone'      => $r['phone'],
        ];
    }

    // Ingredients and production batches (admin/staff only)
    $ingredients = [];
    $batches = [];
    if (!$isCustomer) {
        foreach ($pdo->query('SELECT ingredient_id, ingredient_name, unit, quantity_available
                              FROM ingredients ORDER BY ingredient_id')->fetchAll() as $r) {
            $ingredients[] = [
                'id'       => (int) $r['ingredient_id'],
                'code'     => sprintf('ING-%03d', $r['ingredient_id']),
                'name'     => $r['ingredient_name'],
                'unit'     => $r['unit'],
                'quantity' => (float) $r['quantity_available'],
            ];
        }
        foreach ($pdo->query('SELECT b.batch_id, b.product_id, p.product_name, b.quantity_produced,
                                     b.production_date, b.status
                              FROM production_batches b
                              JOIN products p ON p.product_id = b.product_id
                              ORDER BY b.production_date DESC, b.batch_id DESC')->fetchAll() as $r) {
            $batches[] = [
                'id'         => (int) $r['batch_id'],
                'code'       => sprintf('BATCH-%03d', $r['batch_id']),
                'product_id' => (int) $r['product_id'],
                'product'    => $r['product_name'],
                'quantity'   => (int) $r['quantity_produced'],
                'date'       => $r['production_date'],
                'status'     => $r['status'],
            ];
        }
    }

    respond(compact('products', 'orders', 'customers', 'ingredients', 'batches'));
}

// ---------------------------------------------------------------------
// Products (administrator only)
// ---------------------------------------------------------------------

function action_product_save(array $in): void
{
    require_role(['admin']);
    $pdo = db();

    $id       = (isset($in['id']) && $in['id'] !== '') ? (int) $in['id'] : 0;
    $name     = trim((string) ($in['name'] ?? ''));
    $category = trim((string) ($in['category'] ?? ''));
    $desc     = trim((string) ($in['description'] ?? ''));
    $price    = filter_var($in['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $stock    = filter_var($in['stock'] ?? null, FILTER_VALIDATE_INT);
    $avail    = !empty($in['available']) ? 1 : 0;

    if ($name === '' || mb_strlen($name) > 150) {
        fail('Product name is required (max 150 characters).', 422);
    }
    if (mb_strlen($desc) > 1000) {
        fail('Description is too long (max 1000 characters).', 422);
    }
    if ($price === false || $price < 0 || $price > 99999999.99) {
        fail('Enter a valid price.', 422);
    }
    if ($stock === false || $stock < 0) {
        fail('Enter a valid stock quantity.', 422);
    }

    $st = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
    $st->execute([$category]);
    $categoryId = $st->fetchColumn();
    if ($categoryId === false) {
        fail('Unknown category.', 422);
    }

    if ($id > 0) {
        $chk = $pdo->prepare('SELECT 1 FROM products WHERE product_id = ?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            fail('Product not found.', 404);
        }
        $pdo->prepare('UPDATE products SET product_name = ?, description = ?, category_id = ?, price = ?,
                              stock_quantity = ?, is_available = ?
                       WHERE product_id = ?')
            ->execute([$name, $desc, $categoryId, $price, $stock, $avail, $id]);
        respond(['id' => $id, 'created' => false]);
    }

    $pdo->prepare('INSERT INTO products (product_name, description, category_id, price, stock_quantity, is_available)
                   VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$name, $desc, $categoryId, $price, $stock, $avail]);
    respond(['id' => (int) $pdo->lastInsertId(), 'created' => true], 201);
}

function action_product_delete(array $in): void
{
    require_role(['admin']);
    $pdo = db();
    $id  = (int) ($in['id'] ?? 0);
    if ($id <= 0) {
        fail('Invalid product.', 422);
    }

    // order_items has ON DELETE CASCADE, so deleting a product that was ordered
    // would silently erase part of past orders. Block that.
    $st = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        fail('This product appears in existing orders and cannot be deleted. Mark it unavailable instead.', 409);
    }

    $pdo->prepare('DELETE FROM products WHERE product_id = ?')->execute([$id]);
    respond();
}

// ---------------------------------------------------------------------
// Customers (admin/staff)
// ---------------------------------------------------------------------

function action_customer_save(array $in): void
{
    require_role(['admin', 'staff']);
    $pdo = db();

    $id    = (isset($in['id']) && $in['id'] !== '') ? (int) $in['id'] : 0;
    $first = trim((string) ($in['first_name'] ?? ''));
    $last  = trim((string) ($in['last_name'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $phone = trim((string) ($in['phone'] ?? ''));

    if ($first === '' || mb_strlen($first) > 50 || $last === '' || mb_strlen($last) > 50) {
        fail('First and last name are required (max 50 characters each).', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        fail('Enter a valid email address.', 422);
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        fail('Enter a valid phone number (7-20 digits).', 422);
    }

    $dup = $pdo->prepare('SELECT 1 FROM customers WHERE email = ? AND customer_id <> ?');
    $dup->execute([$email, $id]);
    if ($dup->fetchColumn()) {
        fail('That email address is already registered.', 409);
    }

    if ($id > 0) {
        $chk = $pdo->prepare('SELECT 1 FROM customers WHERE customer_id = ?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            fail('Customer not found.', 404);
        }
        $pdo->prepare('UPDATE customers SET first_name = ?, last_name = ?, email = ?, phone = ?
                       WHERE customer_id = ?')
            ->execute([$first, $last, $email, $phone, $id]);
        respond(['id' => $id, 'created' => false]);
    }

    $pdo->prepare('INSERT INTO customers (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)')
        ->execute([$first, $last, $email, $phone]);
    respond(['id' => (int) $pdo->lastInsertId(), 'created' => true], 201);
}

function action_customer_delete(array $in): void
{
    require_role(['admin', 'staff']);
    $pdo = db();
    $id  = (int) ($in['id'] ?? 0);
    if ($id <= 0) {
        fail('Invalid customer.', 422);
    }

    // orders has ON DELETE CASCADE: block deletion so order history is never erased
    $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        fail('This customer has orders on record and cannot be deleted.', 409);
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'customer' AND reference_id = ?");
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        fail('This customer has a login account and cannot be deleted.', 409);
    }

    $pdo->prepare('DELETE FROM customers WHERE customer_id = ?')->execute([$id]);
    respond();
}

// ---------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------

function action_order_create(array $in): void
{
    $user = require_login();
    $pdo  = db();

    // Who is the order for?
    if ($user['role'] === 'customer') {
        $customerId = (int) ($user['reference_id'] ?? 0);
    } else {
        $customerId = (int) ($in['customer_id'] ?? 0);
    }
    $chk = $pdo->prepare('SELECT 1 FROM customers WHERE customer_id = ?');
    $chk->execute([$customerId]);
    if (!$chk->fetchColumn()) {
        fail('A valid customer is required.', 422);
    }

    // Optional pickup date (must be a real date, today or later)
    $pickup = null;
    $rawPickup = trim((string) ($in['pickup_date'] ?? ''));
    if ($rawPickup !== '') {
        $pickup = clean_date($rawPickup);
        if ($pickup === null) {
            fail('Enter a valid pickup date.', 422);
        }
        if ($pickup < date('Y-m-d')) {
            fail('Pickup date cannot be in the past.', 422);
        }
    }

    // Merge duplicate lines and validate quantities
    $wanted = [];
    foreach ((array) ($in['items'] ?? []) as $line) {
        $pid = (int) ($line['product_id'] ?? 0);
        $qty = (int) ($line['quantity'] ?? 0);
        if ($pid <= 0 || $qty < 1 || $qty > 1000) {
            fail('Each item needs a valid product and quantity.', 422);
        }
        $wanted[$pid] = ($wanted[$pid] ?? 0) + $qty;
    }
    if (!$wanted) {
        fail('Add at least one item to the order.', 422);
    }

    $staffId = $user['role'] === 'staff' ? $user['reference_id'] : null;

    $pdo->beginTransaction();

    $ids = array_keys($wanted);
    $in_ = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT product_id, product_name, price, stock_quantity, is_available
                          FROM products WHERE product_id IN ($in_) FOR UPDATE");
    $st->execute($ids);
    $found = [];
    foreach ($st->fetchAll() as $p) {
        $found[(int) $p['product_id']] = $p;
    }

    $totalCents = 0;
    $lines = [];
    foreach ($wanted as $pid => $qty) {
        if (!isset($found[$pid])) {
            $pdo->rollBack();
            fail('One of the products no longer exists.', 422);
        }
        $p = $found[$pid];
        if (!(int) $p['is_available']) {
            $pdo->rollBack();
            fail("{$p['product_name']} is currently unavailable.", 409);
        }
        if ($p['stock_quantity'] < $qty) {
            $pdo->rollBack();
            fail("Not enough stock for {$p['product_name']} (only {$p['stock_quantity']} left).", 409);
        }
        $subCents    = cents($p['price']) * $qty;
        $totalCents += $subCents;
        $lines[]     = [$pid, $qty, $subCents / 100];
    }

    $pdo->prepare('INSERT INTO orders (customer_id, staff_id, pickup_date, total_amount, status)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$customerId, $staffId, $pickup, $totalCents / 100, 'Pending']);
    $orderId = (int) $pdo->lastInsertId();

    $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, subtotal) VALUES (?, ?, ?, ?)');
    $updStock = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?');
    foreach ($lines as [$pid, $qty, $sub]) {
        $insItem->execute([$orderId, $pid, $qty, $sub]);
        $updStock->execute([$qty, $pid]);
    }

    $pdo->commit();
    respond(['id' => $orderId, 'total' => $totalCents / 100], 201);
}

function action_order_advance(array $in): void
{
    require_role(['admin', 'staff']);
    $id = (int) ($in['id'] ?? 0);

    $st = db()->prepare("UPDATE orders SET status = 'Preparing' WHERE order_id = ? AND status = 'Pending'");
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        fail('Only Pending orders can be moved to Preparing.', 409);
    }
    respond();
}

function action_order_pay(array $in): void
{
    require_role(['admin', 'staff']);
    $pdo = db();

    $id       = (int) ($in['id'] ?? 0);
    $method   = (string) ($in['method'] ?? 'Cash');
    $tendered = filter_var($in['amount_tendered'] ?? null, FILTER_VALIDATE_FLOAT);

    if (!in_array($method, ['Cash', 'GCash', 'Credit Card'], true)) {
        fail('Invalid payment method.', 422);
    }
    if ($tendered === false || $tendered < 0) {
        fail('Enter a valid amount tendered.', 422);
    }

    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT total_amount, status FROM orders WHERE order_id = ? FOR UPDATE');
    $st->execute([$id]);
    $order = $st->fetch();
    if (!$order) {
        $pdo->rollBack();
        fail('Order not found.', 404);
    }
    if ($order['status'] !== 'Preparing') {
        $pdo->rollBack();
        fail('Only orders that are Preparing can be paid and completed.', 409);
    }

    $totalCents    = cents($order['total_amount']);
    $tenderedCents = cents($tendered);
    if ($tenderedCents < $totalCents) {
        $pdo->rollBack();
        fail('Amount tendered is less than the total amount due.', 422);
    }
    $change = ($tenderedCents - $totalCents) / 100;

    $pdo->prepare('INSERT INTO payments (order_id, amount_paid, change_amount, payment_method) VALUES (?, ?, ?, ?)')
        ->execute([$id, $tenderedCents / 100, $change, $method]);
    $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE order_id = ?")->execute([$id]);

    $pdo->commit();
    respond(['change' => $change]);
}

// ---------------------------------------------------------------------
// Ingredients (admin/staff)
// ---------------------------------------------------------------------

function action_ingredient_save(array $in): void
{
    require_role(['admin', 'staff']);
    $pdo = db();

    $id   = (isset($in['id']) && $in['id'] !== '') ? (int) $in['id'] : 0;
    $name = trim((string) ($in['name'] ?? ''));
    $unit = (string) ($in['unit'] ?? '');
    $qty  = filter_var($in['quantity'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($name === '' || mb_strlen($name) > 100) {
        fail('Ingredient name is required (max 100 characters).', 422);
    }
    if (!in_array($unit, INGREDIENT_UNITS, true)) {
        fail('Choose a valid unit.', 422);
    }
    if ($qty === false || $qty < 0 || $qty > 99999999.99) {
        fail('Enter a valid quantity.', 422);
    }

    if ($id > 0) {
        $chk = $pdo->prepare('SELECT 1 FROM ingredients WHERE ingredient_id = ?');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            fail('Ingredient not found.', 404);
        }
        $pdo->prepare('UPDATE ingredients SET ingredient_name = ?, unit = ?, quantity_available = ?
                       WHERE ingredient_id = ?')
            ->execute([$name, $unit, $qty, $id]);
        respond(['id' => $id, 'created' => false]);
    }

    $pdo->prepare('INSERT INTO ingredients (ingredient_name, unit, quantity_available) VALUES (?, ?, ?)')
        ->execute([$name, $unit, $qty]);
    respond(['id' => (int) $pdo->lastInsertId(), 'created' => true], 201);
}

function action_ingredient_delete(array $in): void
{
    require_role(['admin', 'staff']);
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) {
        fail('Invalid ingredient.', 422);
    }
    db()->prepare('DELETE FROM ingredients WHERE ingredient_id = ?')->execute([$id]);
    respond();
}

// ---------------------------------------------------------------------
// Production batches (admin/staff)
// ---------------------------------------------------------------------

function action_batch_save(array $in): void
{
    require_role(['admin', 'staff']);
    $pdo = db();

    $id     = (isset($in['id']) && $in['id'] !== '') ? (int) $in['id'] : 0;
    $pid    = (int) ($in['product_id'] ?? 0);
    $qty    = filter_var($in['quantity'] ?? null, FILTER_VALIDATE_INT);
    $date   = clean_date($in['production_date'] ?? '');
    $status = (string) ($in['status'] ?? '');

    $chk = $pdo->prepare('SELECT 1 FROM products WHERE product_id = ?');
    $chk->execute([$pid]);
    if (!$chk->fetchColumn()) {
        fail('Choose a valid product.', 422);
    }
    if ($qty === false || $qty < 1 || $qty > 100000) {
        fail('Enter a valid quantity (1 to 100000).', 422);
    }
    if ($date === null) {
        fail('Enter a valid production date.', 422);
    }
    if (!in_array($status, BATCH_STATUSES, true)) {
        fail('Choose a valid status.', 422);
    }

    if ($id > 0) {
        $exists = $pdo->prepare('SELECT 1 FROM production_batches WHERE batch_id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            fail('Batch not found.', 404);
        }
        $pdo->prepare('UPDATE production_batches SET product_id = ?, quantity_produced = ?,
                              production_date = ?, status = ?
                       WHERE batch_id = ?')
            ->execute([$pid, $qty, $date, $status, $id]);
        respond(['id' => $id, 'created' => false]);
    }

    $pdo->prepare('INSERT INTO production_batches (product_id, quantity_produced, production_date, status)
                   VALUES (?, ?, ?, ?)')
        ->execute([$pid, $qty, $date, $status]);
    respond(['id' => (int) $pdo->lastInsertId(), 'created' => true], 201);
}

function action_batch_delete(array $in): void
{
    require_role(['admin', 'staff']);
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) {
        fail('Invalid batch.', 422);
    }
    db()->prepare('DELETE FROM production_batches WHERE batch_id = ?')->execute([$id]);
    respond();
}