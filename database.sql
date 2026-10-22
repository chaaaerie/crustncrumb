-- =====================================================================
-- Crust & Crumb Bakeshop System - database script (fixed version)
-- Run this whole file in phpMyAdmin (Import) or the MySQL console.
-- Changes from the old script:
--   * products: added description + is_available
--   * orders: added pickup_date
--   * NEW tables: ingredients, production_batches
--   * indexes on commonly searched columns
--   * seed order totals corrected (order 2 = 230.00, order 5 = 280.00)
--   * typo fixed in customer email (rea.rodriguez)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS bakeshop_db;
USE bakeshop_db;

-- Temporarily disable checks so old tables can be dropped cleanly
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS production_batches;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Categories Table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT
);

-- 2. Products Table
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE,
    INDEX idx_products_name (product_name)
);

-- 3. Customers Table
CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL
);

-- 4. Staff Table
CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role VARCHAR(50) NOT NULL,
    hire_date DATE NOT NULL
);

-- 5. Users Table (logins for Admin, Staff, and Customers)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer') NOT NULL,
    reference_id INT DEFAULT NULL -- staff_id or customer_id when applicable
);

-- 6. Orders Table
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    staff_id INT,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    pickup_date DATE NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_date (order_date)
);

-- 7. Order Items Table (junction table)
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- 8. Payments Table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    amount_paid DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- 9. Ingredients Table (NEW)
CREATE TABLE ingredients (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_name VARCHAR(100) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    quantity_available DECIMAL(10,2) NOT NULL DEFAULT 0,
    INDEX idx_ingredients_name (ingredient_name)
);

-- 10. Production Batches Table (NEW)
CREATE TABLE production_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity_produced INT NOT NULL,
    production_date DATE NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Planned',
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_batches_date (production_date)
);

-- ==========================================
-- SAMPLE INSERT DATA
-- ==========================================

INSERT INTO categories (category_name, description) VALUES
('Cakes', 'Custom celebration and layered cakes'),
('Cupcakes', 'Freshly baked individual cupcakes with frosting'),
('Pastries', 'Flaky croissants, danishes, and puffs'),
('Breads', 'Artisan sourdough and sweet breads'),
('Cookies', 'Chewy chocolate chip and specialty cookies');

INSERT INTO products (product_name, description, category_id, price, stock_quantity, is_available) VALUES
('Classic Chocolate Cake', 'Moist chocolate layer cake with fudge frosting', 1, 650.00, 10, 1),
('Vanilla Bean Cupcake', 'Vanilla sponge topped with buttercream', 2, 75.00, 30, 1),
('Butter Croissant', 'Flaky, buttery croissant baked fresh daily', 3, 90.00, 25, 1),
('Sourdough Loaf', 'Slow-fermented artisan sourdough', 4, 150.00, 15, 1),
('Choco Chip Cookie', 'Chewy cookie loaded with chocolate chips', 5, 50.00, 50, 1);

INSERT INTO customers (first_name, last_name, email, phone) VALUES
('Chassica', 'Flores', 'cha.flores@email.com', '09171234567'),
('Marychrist', 'Novicio', 'mary.nov@email.com', '09189876543'),
('Rea', 'Rodriguez', 'rea.rodriguez@email.com', '09191112233'),
('Anna', 'Garcia', 'anna.garcia@email.com', '09204445566'),
('Kristel', 'Alonzo', 'thel.alonzo@email.com', '09217778899');

INSERT INTO staff (first_name, last_name, role, hire_date) VALUES
('Elena', 'Santiago', 'Head Baker', '2023-01-15'),
('Marco', 'Dela cruz', 'Assistant Baker', '2023-06-10'),
('Sofia', 'Reyes', 'Cashier', '2024-02-01'),
('Lucas', 'Garcia', 'Order Processor', '2024-05-12'),
('Clara', 'Bautista', 'Pastry Chef', '2025-01-10');

-- Default login accounts. Plain-text seed passwords are converted to
-- bcrypt hashes automatically the first time each account signs in.
INSERT INTO users (username, password, role, reference_id) VALUES
('admin', 'admin123', 'admin', NULL),
('staff', 'staff123', 'staff', 3),
('customer', 'customer123', 'customer', 1);

-- Totals now match their order_items (725, 230, 150, 650, 280)
INSERT INTO orders (customer_id, staff_id, pickup_date, total_amount, status) VALUES
(1, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 725.00, 'Completed'),
(2, 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 230.00, 'Preparing'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 150.00, 'Pending'),
(4, 4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 650.00, 'Completed'),
(5, 3, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 280.00, 'Pending');

INSERT INTO order_items (order_id, product_id, quantity, subtotal) VALUES
(1, 1, 1, 650.00),
(1, 2, 1, 75.00),
(2, 3, 2, 180.00),
(2, 5, 1, 50.00),
(3, 4, 1, 150.00),
(4, 1, 1, 650.00),
(5, 3, 2, 180.00),
(5, 5, 2, 100.00);

INSERT INTO payments (order_id, amount_paid, change_amount, payment_method) VALUES
(1, 800.00, 75.00, 'Cash'),
(4, 700.00, 50.00, 'Cash');

INSERT INTO ingredients (ingredient_name, unit, quantity_available) VALUES
('All-purpose flour', 'kg', 40.00),
('Granulated sugar', 'kg', 25.00),
('Butter', 'kg', 12.50),
('Eggs', 'tray', 8.00),
('Cocoa powder', 'kg', 5.00);

INSERT INTO production_batches (product_id, quantity_produced, production_date, status) VALUES
(1, 10, CURDATE(), 'Completed'),
(2, 30, CURDATE(), 'Completed'),
(3, 25, CURDATE(), 'In Production'),
(4, 15, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Planned'),
(5, 50, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Planned');