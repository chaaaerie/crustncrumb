<?php
require __DIR__ . '/config.php';

$user = current_user();

// Categories for the product form come straight from the database
$categories = [];
try {
    $categories = db()->query('SELECT category_name FROM categories ORDER BY category_name')
                      ->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('[bakeshop] could not load categories: ' . $e->getMessage());
}

// Handed to script.js so the page knows who is signed in (no flash of the login box)
$boot = [
    'user' => $user ? ['username' => $user['username'], 'role' => $user['role']] : null,
    'csrf' => $_SESSION['csrf'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crust & Crumb Bakeshop System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bakeshop: {
                            primary: '#4A2E1B',
                            secondary: '#5C3A21',
                            accent: '#C88A3D',
                            light: '#FAF6F0',
                            cardBg: '#FFFFFF',
                            border: '#E6DCD0'
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #FAF6F0;
            color: #2D231E;
        }
        .fade-in {
            animation: fadeIn 0.25s ease-in-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased selection:bg-[#C88A3D] selection:text-white">

    <!-- LOGIN MODAL (3 Account Types) -->
    <div id="login-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm <?= $user ? 'hidden' : '' ?>">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in">
            <div class="bg-[#4A2E1B] text-white px-6 py-6 text-center">
                <div class="w-12 h-12 rounded-xl bg-[#C88A3D] flex items-center justify-center mx-auto mb-2 text-white text-xl shadow-inner">
                    <i class="fa-solid fa-bread-slice"></i>
                </div>
                <h3 class="font-bold text-xl">Crust & Crumb Bakeshop</h3>
                <p class="text-xs text-neutral-300 mt-1">Please sign in to access your dashboard</p>
            </div>
            <form id="login-form" onsubmit="handleLogin(event)" class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Select Role</label>
                    <select id="login-role" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                        <option value="admin">Administrator (Full Access)</option>
                        <option value="staff">Staff / Employee (Operations)</option>
                        <option value="customer">Customer (Pre-Orders)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Username</label>
                    <input type="text" id="login-username" required placeholder="admin / staff / customer" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Password</label>
                    <input type="password" id="login-password" required placeholder="••••••••" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full bg-[#5C3A21] hover:bg-[#4A2E1B] text-white py-3 rounded-xl font-semibold transition shadow-sm">Sign In</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MAIN APP HEADER -->
    <header class="bg-[#4A2E1B] text-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo & Title -->
                <div class="flex items-center space-x-3 cursor-pointer" onclick="switchTab('dashboard')">
                    <div class="w-10 h-10 rounded-xl bg-[#C88A3D] flex items-center justify-center shadow-inner text-white text-lg">
                        <i class="fa-solid fa-bread-slice"></i>
                    </div>
                    <span class="text-xl sm:text-2xl font-bold tracking-tight">Crust & Crumb Bakeshop System</span>
                </div>
                <!-- User Profile & Logout -->
                <div class="flex items-center space-x-3">
                    <div class="hidden sm:flex items-center space-x-2 text-xs bg-[#5C3A21] px-3 py-1.5 rounded-full border border-[#C88A3D]/40">
                        <i class="fa-regular fa-user text-[#C88A3D]"></i>
                        <span id="current-user-badge" class="font-semibold uppercase"><?= $user ? htmlspecialchars($user['role'] . ': ' . $user['username']) : '' ?></span>
                    </div>
                    <button onclick="logout()" class="text-xs bg-red-800/80 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg transition font-medium flex items-center space-x-1.5">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </button>
                </div>
            </div>
            <!-- Navigation Tabs -->
            <nav class="flex space-x-2 sm:space-x-6 overflow-x-auto pb-2 scrollbar-none">
                <button onclick="switchTab('dashboard')" id="nav-dashboard" class="tab-btn px-3.5 py-2 text-sm font-semibold rounded-t-lg transition-all border-b-2 border-[#C88A3D] text-white bg-[#5C3A21]/60">
                    <i class="fa-solid fa-chart-pie mr-1.5 text-[#C88A3D]"></i> Overview
                </button>
                <button onclick="switchTab('products')" id="nav-products" class="tab-btn px-3.5 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-[#E6DCD0] hover:text-white hover:bg-[#5C3A21]/30">
                    <i class="fa-solid fa-cookie-bite mr-1.5 text-[#C88A3D]"></i> Products
                </button>
                <button onclick="switchTab('preorders')" id="nav-preorders" class="tab-btn px-3.5 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-[#E6DCD0] hover:text-white hover:bg-[#5C3A21]/30">
                    <i class="fa-solid fa-receipt mr-1.5 text-[#C88A3D]"></i> Pre-Orders & Payments
                </button>
                <button onclick="switchTab('customers')" id="nav-customers" class="tab-btn px-3.5 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-[#E6DCD0] hover:text-white hover:bg-[#5C3A21]/30">
                    <i class="fa-solid fa-users mr-1.5 text-[#C88A3D]"></i> Customers
                </button>
                <button onclick="switchTab('ingredients')" id="nav-ingredients" class="tab-btn px-3.5 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-[#E6DCD0] hover:text-white hover:bg-[#5C3A21]/30">
                    <i class="fa-solid fa-wheat-awn mr-1.5 text-[#C88A3D]"></i> Ingredients
                </button>
                <button onclick="switchTab('production')" id="nav-production" class="tab-btn px-3.5 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-[#E6DCD0] hover:text-white hover:bg-[#5C3A21]/30">
                    <i class="fa-solid fa-industry mr-1.5 text-[#C88A3D]"></i> Production
                </button>
            </nav>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
        <!-- Notification Toast Box -->
        <div id="toast-container" class="fixed bottom-5 right-5 z-50 space-y-2 pointer-events-none"></div>

        <!-- DASHBOARD VIEW -->
        <div id="view-dashboard" class="space-y-8 fade-in">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6DCD0] flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Total Products</p>
                        <h4 id="stat-total-products" class="text-2xl font-bold text-[#4A2E1B] mt-1">0</h4>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#FAF6F0] text-[#C88A3D] flex items-center justify-center text-xl">
                        <i class="fa-solid fa-bread-slice"></i>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6DCD0] flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Active Pre-Orders</p>
                        <h4 id="stat-total-orders" class="text-2xl font-bold text-[#4A2E1B] mt-1">0</h4>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#FAF6F0] text-[#C88A3D] flex items-center justify-center text-xl">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6DCD0] flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Registered Customers</p>
                        <h4 id="stat-total-customers" class="text-2xl font-bold text-[#4A2E1B] mt-1">0</h4>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#FAF6F0] text-[#C88A3D] flex items-center justify-center text-xl">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#E6DCD0] flex items-center justify-between">
                    <div>
                        <p id="label-revenue" class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Total Revenue</p>
                        <h4 id="stat-total-revenue" class="text-2xl font-bold text-[#4A2E1B] mt-1">₱0.00</h4>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#FAF6F0] text-[#C88A3D] flex items-center justify-center text-xl">
                        <i class="fa-solid fa-peso-sign"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <!-- Product Catalog Card -->
                <section class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                    <div class="p-5 sm:p-6 border-b border-[#E6DCD0] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-[#4A2E1B]">Bakeshop Product Catalog</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">Manage daily fresh baked goods, stock counts, and pricing.</p>
                        </div>
                        <div id="admin-product-actions" class="flex items-center space-x-3">
                            <button onclick="openProductModal()" class="bg-[#5C3A21] hover:bg-[#4A2E1B] text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition shadow-sm flex items-center space-x-2">
                                <i class="fa-solid fa-plus text-xs"></i>
                                <span>Add New Product</span>
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                    <th class="py-3 px-4 font-semibold">ID</th>
                                    <th class="py-3 px-4 font-semibold">Product Name</th>
                                    <th class="py-3 px-4 font-semibold">Category</th>
                                    <th class="py-3 px-4 font-semibold">Price (₱)</th>
                                    <th class="py-3 px-4 font-semibold">Stock Quantity</th>
                                    <th class="py-3 px-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="catalog-table-body" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                        </table>
                    </div>
                </section>

                <!-- Recent Pre-Orders & Transactions -->
                <section class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                    <div class="p-5 sm:p-6 border-b border-[#E6DCD0] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-[#4A2E1B]">Recent Pre-Orders & Payment Status</h2>
                            <p class="text-xs text-neutral-500 mt-0.5">Track customer orders, preparation steps, and checkout payments.</p>
                        </div>
                        <button onclick="switchTab('preorders')" class="text-xs font-semibold text-[#5C3A21] hover:underline px-2 py-1">View All Pre-Orders</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                    <th class="py-3 px-4 font-semibold">Order ID</th>
                                    <th class="py-3 px-4 font-semibold">Customer</th>
                                    <th class="py-3 px-4 font-semibold">Total Amount (₱)</th>
                                    <th class="py-3 px-4 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody id="orders-table-body" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- PRODUCTS FULL VIEW -->
        <div id="view-products" class="space-y-6 hidden fade-in">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-[#E6DCD0]">
                <div>
                    <h2 class="text-2xl font-bold text-[#4A2E1B]">Product Catalog Management</h2>
                    <p class="text-sm text-neutral-600">Browse and inspect artisan breads, pastries, and cakes.</p>
                </div>
                <button id="btn-add-product-main" onclick="openProductModal()" class="bg-[#C88A3D] hover:bg-[#b07733] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-sm flex items-center space-x-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add New Product</span>
                </button>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                <div class="p-4 border-b border-[#E6DCD0] flex items-center justify-between bg-[#FAF6F0]">
                    <div class="relative w-full max-w-sm">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-neutral-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="product-search" oninput="filterProducts()" placeholder="Search products..." class="w-full pl-9 pr-4 py-2 text-sm bg-white border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                    </div>
                    <span id="product-count-badge" class="text-xs font-semibold text-neutral-500 px-3 py-1">0 items</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">ID</th>
                                <th class="py-3.5 px-4 font-semibold">Product Name</th>
                                <th class="py-3.5 px-4 font-semibold">Category</th>
                                <th class="py-3.5 px-4 font-semibold">Price (₱)</th>
                                <th class="py-3.5 px-4 font-semibold">Stock Quantity</th>
                                <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="full-products-tbody" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PRE-ORDERS & PAYMENTS FULL VIEW -->
        <div id="view-preorders" class="space-y-6 hidden fade-in">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-[#E6DCD0]">
                <div>
                    <h2 class="text-2xl font-bold text-[#4A2E1B]">Pre-Orders & Payment Processing</h2>
                    <p class="text-sm text-neutral-600">Advance orders from Pending -> Preparing -> Completed (triggers payment transaction).</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="openOrderModal()" class="bg-[#C88A3D] hover:bg-[#b07733] text-white text-sm font-semibold px-4 py-2 rounded-xl transition shadow-sm flex items-center space-x-2 mr-2">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>New Pre-Order</span>
                    </button>
                    <button onclick="filterOrderStatus('all')" class="order-filter-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#5C3A21] text-white">All</button>
                    <button onclick="filterOrderStatus('Pending')" class="order-filter-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#FAF6F0] text-neutral-700 hover:bg-[#E6DCD0]">Pending</button>
                    <button onclick="filterOrderStatus('Preparing')" class="order-filter-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#FAF6F0] text-neutral-700 hover:bg-[#E6DCD0]">Preparing</button>
                    <button onclick="filterOrderStatus('Completed')" class="order-filter-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#FAF6F0] text-neutral-700 hover:bg-[#E6DCD0]">Completed</button>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">Order ID</th>
                                <th class="py-3.5 px-4 font-semibold">Customer</th>
                                <th class="py-3.5 px-4 font-semibold">Items</th>
                                <th class="py-3.5 px-4 font-semibold">Pickup Date</th>
                                <th class="py-3.5 px-4 font-semibold">Total (₱)</th>
                                <th class="py-3.5 px-4 font-semibold">Status</th>
                                <th class="py-3.5 px-4 font-semibold text-right">Actions / Payment</th>
                            </tr>
                        </thead>
                        <tbody id="full-orders-tbody" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CUSTOMERS FULL VIEW -->
        <div id="view-customers" class="space-y-6 hidden fade-in">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-[#E6DCD0]">
                <div>
                    <h2 class="text-2xl font-bold text-[#4A2E1B]">Customer Directory</h2>
                    <p class="text-sm text-neutral-600">Registered bakeshop patrons.</p>
                </div>
                <button id="btn-add-customer" onclick="openCustomerModal()" class="bg-[#C88A3D] hover:bg-[#b07733] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-sm flex items-center space-x-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Customer</span>
                </button>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">Customer ID</th>
                                <th class="py-3.5 px-4 font-semibold">Name</th>
                                <th class="py-3.5 px-4 font-semibold">Email</th>
                                <th class="py-3.5 px-4 font-semibold">Phone</th>
                                <th id="customer-actions-th" class="py-3.5 px-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="full-customers-tbody" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- INGREDIENTS VIEW -->
        <div id="view-ingredients" class="space-y-6 hidden fade-in">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-[#E6DCD0]">
                <div>
                    <h2 class="text-2xl font-bold text-[#4A2E1B]">Ingredient Inventory</h2>
                    <p class="text-sm text-neutral-600">Track raw materials used for baking.</p>
                </div>
                <button onclick="openIngredientModal()" class="bg-[#C88A3D] hover:bg-[#b07733] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-sm flex items-center space-x-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Ingredient</span>
                </button>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">ID</th>
                                <th class="py-3.5 px-4 font-semibold">Ingredient</th>
                                <th class="py-3.5 px-4 font-semibold">Quantity Available</th>
                                <th class="py-3.5 px-4 font-semibold">Unit</th>
                                <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="full-ingredients-tbody" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PRODUCTION BATCHES VIEW -->
        <div id="view-production" class="space-y-6 hidden fade-in">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-[#E6DCD0]">
                <div>
                    <h2 class="text-2xl font-bold text-[#4A2E1B]">Production Batches</h2>
                    <p class="text-sm text-neutral-600">Plan and record what the kitchen bakes each day.</p>
                </div>
                <button onclick="openBatchModal()" class="bg-[#C88A3D] hover:bg-[#b07733] text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition shadow-sm flex items-center space-x-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>New Batch</span>
                </button>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-[#E6DCD0] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#5C3A21] text-white text-xs uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">Batch ID</th>
                                <th class="py-3.5 px-4 font-semibold">Product</th>
                                <th class="py-3.5 px-4 font-semibold">Quantity</th>
                                <th class="py-3.5 px-4 font-semibold">Production Date</th>
                                <th class="py-3.5 px-4 font-semibold">Status</th>
                                <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="full-batches-tbody" class="divide-y divide-[#E6DCD0] text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- PRODUCT MODAL -->
    <div id="product-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs hidden">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in">
            <div class="bg-[#4A2E1B] text-white px-6 py-4 flex items-center justify-between">
                <h3 id="modal-title" class="font-bold text-lg flex items-center space-x-2">
                    <i class="fa-solid fa-bread-slice text-[#C88A3D]"></i>
                    <span>Add New Product</span>
                </h3>
                <button onclick="closeProductModal()" class="text-neutral-300 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="product-form" onsubmit="handleProductSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="product-id">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Product Name</label>
                    <input type="text" id="input-name" required placeholder="e.g. Sourdough Boule" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Category</label>
                    <select id="input-category" required class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Description</label>
                    <textarea id="input-description" rows="2" maxlength="1000" placeholder="Short description of the product" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Price (₱)</label>
                        <input type="number" id="input-price" step="0.01" min="0" required placeholder="180.00" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Stock Quantity</label>
                        <input type="number" id="input-stock" min="0" required placeholder="25" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                    </div>
                </div>
                <label class="flex items-center space-x-2 text-sm text-neutral-700">
                    <input type="checkbox" id="input-available" checked class="rounded border-[#E6DCD0] text-[#C88A3D] focus:ring-[#C88A3D]">
                    <span>Available for ordering</span>
                </label>
                <div class="pt-4 flex items-center justify-end space-x-3 border-t border-[#E6DCD0]">
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-100 rounded-xl">Cancel</button>
                    <button type="submit" class="bg-[#C88A3D] hover:bg-[#b07733] text-white px-5 py-2 text-sm font-semibold rounded-xl transition shadow-sm">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD CUSTOMER MODAL -->
    <div id="customer-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs hidden">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in max-h-[92vh] overflow-y-auto">
            <div class="bg-[#4A2E1B] text-white px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-lg flex items-center space-x-2">
                    <i class="fa-solid fa-user-plus text-[#C88A3D]"></i>
                    <span id="customer-modal-title">Add Customer</span>
                </h3>
                <button onclick="closeCustomerModal()" class="text-neutral-300 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="customer-form" onsubmit="handleCustomerSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="customer-id">
                <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">First Name</label>
                    <input type="text" id="cust-first" required maxlength="50" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Last Name</label>
                    <input type="text" id="cust-last" required maxlength="50" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Email</label>
                    <input type="email" id="cust-email" required maxlength="100" placeholder="name@email.com" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Phone</label>
                    <input type="tel" id="cust-phone" required maxlength="20" placeholder="09171234567" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div class="pt-4 flex items-center justify-end space-x-3 border-t border-[#E6DCD0]">
                    <button type="button" onclick="closeCustomerModal()" class="px-4 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-100 rounded-xl">Cancel</button>
                    <button type="submit" class="bg-[#C88A3D] hover:bg-[#b07733] text-white px-5 py-2 text-sm font-semibold rounded-xl transition shadow-sm">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- NEW PRE-ORDER MODAL -->
    <div id="order-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs hidden">
        <div class="bg-white w-full max-w-xl rounded-2xl shadow-xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in max-h-[92vh] overflow-y-auto">
            <div class="bg-[#4A2E1B] text-white px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-lg flex items-center space-x-2">
                    <i class="fa-solid fa-receipt text-[#C88A3D]"></i>
                    <span id="order-modal-title">New Pre-Order</span>
                </h3>
                <button onclick="closeOrderModal()" class="text-neutral-300 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="order-form" onsubmit="handleOrderSubmit(event)" class="p-6 space-y-4">
                <div id="order-customer-wrap">
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Customer</label>
                    <select id="order-customer" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]"></select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Pickup Date (optional)</label>
                    <input type="date" id="order-pickup" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Items</label>
                    <div id="order-lines" class="space-y-2"></div>
                    <button type="button" onclick="addOrderLine()" class="mt-2 text-xs font-semibold text-[#5C3A21] hover:underline"><i class="fa-solid fa-plus text-[10px] mr-1"></i>Add another item</button>
                </div>
                <div class="flex items-center justify-between bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl px-4 py-3">
                    <span class="text-xs font-semibold uppercase text-neutral-600">Order Total</span>
                    <span id="order-total-display" class="font-bold text-[#5C3A21]">₱0.00</span>
                </div>
                <div class="pt-4 flex items-center justify-end space-x-3 border-t border-[#E6DCD0]">
                    <button type="button" onclick="closeOrderModal()" class="px-4 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-100 rounded-xl">Cancel</button>
                    <button type="submit" class="bg-[#C88A3D] hover:bg-[#b07733] text-white px-5 py-2 text-sm font-semibold rounded-xl transition shadow-sm">Place Pre-Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ADD INGREDIENT MODAL -->
    <div id="ingredient-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs hidden">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in max-h-[92vh] overflow-y-auto">
            <div class="bg-[#4A2E1B] text-white px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-lg flex items-center space-x-2">
                    <i class="fa-solid fa-wheat-awn text-[#C88A3D]"></i>
                    <span id="ingredient-modal-title">Add Ingredient</span>
                </h3>
                <button onclick="closeIngredientModal()" class="text-neutral-300 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="ingredient-form" onsubmit="handleIngredientSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="ingredient-id">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Ingredient Name</label>
                    <input type="text" id="ing-name" required maxlength="100" placeholder="e.g. Bread flour" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Quantity Available</label>
                    <input type="number" id="ing-qty" step="0.01" min="0" required class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Unit</label>
                    <select id="ing-unit" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]"><option>kg</option><option>g</option><option>L</option><option>mL</option><option>pcs</option><option>pack</option><option>tray</option></select>
                </div>
                </div>
                <div class="pt-4 flex items-center justify-end space-x-3 border-t border-[#E6DCD0]">
                    <button type="button" onclick="closeIngredientModal()" class="px-4 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-100 rounded-xl">Cancel</button>
                    <button type="submit" class="bg-[#C88A3D] hover:bg-[#b07733] text-white px-5 py-2 text-sm font-semibold rounded-xl transition shadow-sm">Save Ingredient</button>
                </div>
            </form>
        </div>
    </div>

    <!-- NEW PRODUCTION BATCH MODAL -->
    <div id="batch-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs hidden">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in max-h-[92vh] overflow-y-auto">
            <div class="bg-[#4A2E1B] text-white px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-lg flex items-center space-x-2">
                    <i class="fa-solid fa-industry text-[#C88A3D]"></i>
                    <span id="batch-modal-title">New Production Batch</span>
                </h3>
                <button onclick="closeBatchModal()" class="text-neutral-300 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="batch-form" onsubmit="handleBatchSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="batch-id">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Product</label>
                    <select id="batch-product" required class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]"></select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Quantity</label>
                    <input type="number" id="batch-qty" min="1" required class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Production Date</label>
                    <input type="date" id="batch-date" required class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Status</label>
                    <select id="batch-status" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]"><option>Planned</option><option>In Production</option><option>Completed</option><option>Cancelled</option></select>
                </div>
                <div class="pt-4 flex items-center justify-end space-x-3 border-t border-[#E6DCD0]">
                    <button type="button" onclick="closeBatchModal()" class="px-4 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-100 rounded-xl">Cancel</button>
                    <button type="submit" class="bg-[#C88A3D] hover:bg-[#b07733] text-white px-5 py-2 text-sm font-semibold rounded-xl transition shadow-sm">Save Batch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PAYMENT CHECKOUT MODAL -->
    <div id="payment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs hidden">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-[#E6DCD0] overflow-hidden mx-4 fade-in">
            <div class="bg-[#4A2E1B] text-white px-6 py-4 flex items-center justify-between">
                <h3 class="font-bold text-lg flex items-center space-x-2">
                    <i class="fa-solid fa-peso-sign text-[#C88A3D]"></i>
                    <span>Order Payment & Completion</span>
                </h3>
                <button onclick="closePaymentModal()" class="text-neutral-300 hover:text-white p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <form id="payment-form" onsubmit="handlePaymentSubmit(event)" class="p-6 space-y-4">
                <input type="hidden" id="pay-order-id">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Total Amount Due</label>
                    <input type="text" id="pay-total-display" readonly class="w-full px-3.5 py-2.5 text-sm bg-neutral-100 border border-[#E6DCD0] rounded-xl font-bold text-[#5C3A21]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Payment Method</label>
                    <select id="pay-method" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash / Digital Wallet</option>
                        <option value="Credit Card">Credit Card</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Amount Tendered (₱)</label>
                    <input type="number" id="pay-amount-tendered" step="0.01" min="0" required oninput="calculateChange()" placeholder="0.00" class="w-full px-3.5 py-2.5 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 uppercase mb-1">Change (₱)</label>
                    <input type="text" id="pay-change-display" readonly value="₱0.00" class="w-full px-3.5 py-2.5 text-sm bg-neutral-100 border border-[#E6DCD0] rounded-xl font-bold text-emerald-700">
                </div>
                <div class="pt-4 flex items-center justify-end space-x-3 border-t border-[#E6DCD0]">
                    <button type="button" onclick="closePaymentModal()" class="px-4 py-2 text-sm font-semibold text-neutral-600 hover:bg-neutral-100 rounded-xl">Cancel</button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 text-sm font-semibold rounded-xl transition shadow-sm">Confirm Payment & Complete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Session/boot data from PHP -->
    <script>window.BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <!-- External Separated Script File -->
    <script src="script.js"></script>
</body>
</html>