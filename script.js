// ---------------------------------------------------------------
// Crust & Crumb Bakeshop — front end
// ---------------------------------------------------------------

const BOOT = window.BOOT || {};
const CSRF = BOOT.csrf || '';

let currentUser = BOOT.user || null;
let products = [];
let orders = [];
let customers = [];
let ingredients = [];
let batches = [];
let orderLines = [];
let currentFilterStatus = 'all';

const ALL_TABS = ['dashboard', 'products', 'preorders', 'customers', 'ingredients', 'production'];
const STAFF_ONLY_TABS = ['ingredients', 'production'];

// ---------- Helpers ----------
function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
}

function peso(n) {
    return '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function isAdmin() {
    return !!currentUser && currentUser.role === 'admin';
}

function canManage() {
    return !!currentUser && currentUser.role !== 'customer';
}

function todayStr() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function formatDate(s) {
    if (!s) return '—';
    const d = new Date(s + 'T00:00:00');
    return isNaN(d) ? esc(s) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showModal(id) { document.getElementById(id).classList.remove('hidden'); }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); }

async function api(action, { method = 'GET', body = null } = {}) {
    const opts = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (method !== 'GET') {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-Token'] = CSRF;
        opts.body = JSON.stringify(body || {});
    }

    let res, data;
    try {
        res = await fetch('api.php?action=' + encodeURIComponent(action), opts);
        data = await res.json();
    } catch (err) {
        throw new Error('Cannot reach the server. Is Apache/MySQL running?');
    }

    if (!res.ok || data.ok === false) {
        const error = new Error(data.error || 'Request failed.');
        error.status = res.status;
        // Session expired: bring the login box back
        if (res.status === 401 && currentUser && action !== 'login') {
            currentUser = null;
            document.getElementById('login-modal').classList.remove('hidden');
        }
        throw error;
    }
    return data;
}

async function loadData() {
    try {
        const data = await api('data');
        products = data.products;
        orders = data.orders;
        customers = data.customers;
        ingredients = data.ingredients || [];
        batches = data.batches || [];
        renderAll();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---------- Toast notifications ----------
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const isOk = type === 'success';
    toast.className = `px-4 py-3 rounded-xl shadow-lg text-white text-sm font-medium flex items-center space-x-3 pointer-events-auto transition-all fade-in ${isOk ? 'bg-[#5C3A21] border border-[#C88A3D]' : 'bg-red-700'}`;
    toast.innerHTML = `<i class="fa-solid ${isOk ? 'fa-circle-check text-[#C88A3D]' : 'fa-circle-exclamation'}"></i><span>${esc(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ---------- Login / Logout ----------
async function handleLogin(e) {
    e.preventDefault();
    const role = document.getElementById('login-role').value;
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;

    try {
        const data = await api('login', { method: 'POST', body: { role, username, password } });
        currentUser = data.user;
        document.getElementById('login-password').value = '';
        document.getElementById('login-modal').classList.add('hidden');
        document.getElementById('current-user-badge').innerText = `${currentUser.role}: ${currentUser.username}`;
        applyRolePermissions();
        switchTab('dashboard');
        await loadData();
        showToast(`Welcome back, ${currentUser.username} (${currentUser.role.toUpperCase()})!`);
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function logout() {
    try { await api('logout', { method: 'POST' }); } catch (err) { /* ignore */ }
    window.location.reload();
}

function applyRolePermissions() {
    if (!currentUser) return;
    const setShow = (id, show, display = 'flex') => {
        const el = document.getElementById(id);
        if (el) el.style.display = show ? display : 'none';
    };

    // Product management: administrator only
    setShow('admin-product-actions', isAdmin());
    setShow('btn-add-product-main', isAdmin());

    // Customer management and production modules: admin + staff
    setShow('btn-add-customer', canManage());
    setShow('customer-actions-th', canManage(), 'table-cell');
    STAFF_ONLY_TABS.forEach(t => setShow(`nav-${t}`, canManage(), 'block'));

    // Dashboard wording for customers
    const rev = document.getElementById('label-revenue');
    if (rev) rev.innerText = canManage() ? 'Total Revenue' : 'My Completed Spending';
}

// ---------- Tab navigation ----------
function switchTab(tabName) {
    if (STAFF_ONLY_TABS.includes(tabName) && !canManage()) return;

    ALL_TABS.forEach(t => {
        document.getElementById(`view-${t}`).classList.add('hidden');
        const navBtn = document.getElementById(`nav-${t}`);
        if (navBtn) {
            navBtn.className = "tab-btn px-3.5 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 border-transparent text-[#E6DCD0] hover:text-white hover:bg-[#5C3A21]/30";
        }
    });

    document.getElementById(`view-${tabName}`).classList.remove('hidden');
    const activeNav = document.getElementById(`nav-${tabName}`);
    if (activeNav) {
        activeNav.className = "tab-btn px-3.5 py-2 text-sm font-semibold rounded-t-lg transition-all border-b-2 border-[#C88A3D] text-white bg-[#5C3A21]/60";
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ---------- Rendering ----------
function renderAll() {
    renderDashboardStats();
    renderCatalogTable(getFilteredProducts());
    renderOrdersTable();
    renderCustomersTable();
    renderIngredientsTable();
    renderBatchesTable();
}

function renderDashboardStats() {
    document.getElementById('stat-total-products').innerText = products.length;
    document.getElementById('stat-total-orders').innerText = orders.filter(o => o.status !== 'Completed').length;
    document.getElementById('stat-total-customers').innerText = customers.length;

    const totalRev = orders.reduce((acc, o) => acc + (o.status === 'Completed' ? o.total : 0), 0);
    document.getElementById('stat-total-revenue').innerText = peso(totalRev);
}

function stockBadge(p) {
    const cls = p.stock < 15 ? 'bg-red-100 text-red-700 font-bold' : 'bg-green-100 text-green-700';
    const unavailable = p.available ? '' : ' <span class="ml-1 px-2 py-0.5 rounded-md bg-neutral-200 text-neutral-600 text-xs">Unavailable</span>';
    return `<span class="px-2 py-0.5 rounded-md ${cls}">${p.stock} units</span>${unavailable}`;
}

function productNameCell(p) {
    const desc = p.description ? `<div class="text-xs text-neutral-500 font-normal mt-0.5 max-w-xs truncate" title="${esc(p.description)}">${esc(p.description)}</div>` : '';
    return `<div>${esc(p.name)}</div>${desc}`;
}

function getFilteredProducts() {
    const input = document.getElementById('product-search');
    const query = input ? input.value.trim().toLowerCase() : '';
    if (!query) return products;
    return products.filter(p =>
        p.name.toLowerCase().includes(query) || p.category.toLowerCase().includes(query)
    );
}

function renderCatalogTable(filteredList = products) {
    const dashTbody = document.getElementById('catalog-table-body');
    const fullTbody = document.getElementById('full-products-tbody');
    const canEdit = isAdmin();

    document.getElementById('product-count-badge').innerText = `${filteredList.length} items`;

    const empty = `<tr><td colspan="6" class="text-center py-6 text-neutral-400">No products found.</td></tr>`;

    if (products.length === 0) {
        dashTbody.innerHTML = empty;
        fullTbody.innerHTML = empty;
        return;
    }

    dashTbody.innerHTML = products.slice(0, 5).map(p => `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3 px-4 font-semibold text-[#5C3A21]">${esc(p.code)}</td>
            <td class="py-3 px-4 font-medium">${productNameCell(p)}</td>
            <td class="py-3 px-4"><span class="px-2.5 py-1 text-xs rounded-full bg-[#FAF6F0] text-[#5C3A21] border border-[#E6DCD0]">${esc(p.category)}</span></td>
            <td class="py-3 px-4 font-semibold">₱${Number(p.price).toFixed(2)}</td>
            <td class="py-3 px-4">${stockBadge(p)}</td>
            <td class="py-3 px-4 text-right">
                ${canEdit ? `<button onclick="editProduct(${p.id})" class="text-[#5C3A21] hover:text-[#C88A3D] p-1"><i class="fa-solid fa-pen-to-square"></i></button>` : '<span class="text-xs text-neutral-400">View Only</span>'}
            </td>
        </tr>
    `).join('');

    fullTbody.innerHTML = filteredList.length === 0 ? empty : filteredList.map(p => `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3.5 px-4 font-semibold text-[#5C3A21]">${esc(p.code)}</td>
            <td class="py-3.5 px-4 font-medium">${productNameCell(p)}</td>
            <td class="py-3.5 px-4"><span class="px-2.5 py-1 text-xs rounded-full bg-[#FAF6F0] text-[#5C3A21] border border-[#E6DCD0]">${esc(p.category)}</span></td>
            <td class="py-3.5 px-4 font-semibold">₱${Number(p.price).toFixed(2)}</td>
            <td class="py-3.5 px-4">${stockBadge(p)}</td>
            <td class="py-3.5 px-4 text-right space-x-2">
                ${canEdit ? `
                    <button onclick="editProduct(${p.id})" class="text-neutral-600 hover:text-[#5C3A21] p-1.5" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                    <button onclick="deleteProduct(${p.id})" class="text-red-500 hover:text-red-700 p-1.5" title="Delete"><i class="fa-solid fa-trash"></i></button>
                ` : '<span class="text-xs text-neutral-400">Restricted</span>'}
            </td>
        </tr>
    `).join('');
}

function getStatusBadge(status) {
    if (status === 'Pending') return `<span class="px-2.5 py-1 text-xs rounded-full bg-amber-100 text-amber-800 font-medium">Pending</span>`;
    if (status === 'Preparing') return `<span class="px-2.5 py-1 text-xs rounded-full bg-blue-100 text-blue-800 font-medium">Preparing</span>`;
    if (status === 'Completed') return `<span class="px-2.5 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800 font-medium">Completed</span>`;
    if (status === 'In Production') return `<span class="px-2.5 py-1 text-xs rounded-full bg-blue-100 text-blue-800 font-medium">In Production</span>`;
    if (status === 'Planned') return `<span class="px-2.5 py-1 text-xs rounded-full bg-amber-100 text-amber-800 font-medium">Planned</span>`;
    if (status === 'Cancelled') return `<span class="px-2.5 py-1 text-xs rounded-full bg-red-100 text-red-700 font-medium">Cancelled</span>`;
    return `<span class="px-2.5 py-1 text-xs rounded-full bg-neutral-100 text-neutral-800 font-medium">${esc(status)}</span>`;
}

function renderOrdersTable() {
    const dashTbody = document.getElementById('orders-table-body');
    const fullTbody = document.getElementById('full-orders-tbody');
    const filteredOrders = currentFilterStatus === 'all' ? orders : orders.filter(o => o.status === currentFilterStatus);

    dashTbody.innerHTML = orders.length === 0
        ? `<tr><td colspan="4" class="text-center py-6 text-neutral-400">No pre-orders yet.</td></tr>`
        : orders.slice(0, 5).map(o => `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3 px-4 font-semibold text-[#5C3A21]">${esc(o.code)}</td>
            <td class="py-3 px-4 font-medium">${esc(o.customer)}</td>
            <td class="py-3 px-4 font-semibold">₱${Number(o.total).toFixed(2)}</td>
            <td class="py-3 px-4">${getStatusBadge(o.status)}</td>
        </tr>
    `).join('');

    fullTbody.innerHTML = filteredOrders.length === 0
        ? `<tr><td colspan="7" class="text-center py-6 text-neutral-400">No pre-orders found.</td></tr>`
        : filteredOrders.map(o => {
        let action;
        if (o.status === 'Completed') {
            action = '<span class="text-xs text-emerald-700 font-semibold"><i class="fa-solid fa-check-circle"></i> Paid &amp; Completed</span>';
        } else if (canManage()) {
            const label = o.status === 'Pending' ? 'Start Preparing' : 'Take Payment';
            action = `<button onclick="advanceOrderStatus(${o.id})" class="text-xs bg-[#5C3A21] text-white px-3 py-1.5 rounded-lg hover:bg-[#4A2E1B]">${label}</button>`;
        } else {
            action = '<span class="text-xs text-neutral-400">In progress</span>';
        }
        return `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3.5 px-4 font-semibold text-[#5C3A21]">${esc(o.code)}</td>
            <td class="py-3.5 px-4 font-medium">${esc(o.customer)}</td>
            <td class="py-3.5 px-4 text-xs text-neutral-500 max-w-xs truncate" title="${esc(o.items)}">${esc(o.items)}</td>
            <td class="py-3.5 px-4 text-xs text-neutral-600">${formatDate(o.pickup)}</td>
            <td class="py-3.5 px-4 font-semibold">₱${Number(o.total).toFixed(2)}</td>
            <td class="py-3.5 px-4">${getStatusBadge(o.status)}</td>
            <td class="py-3.5 px-4 text-right">${action}</td>
        </tr>`;
    }).join('');
}

function renderCustomersTable() {
    const fullTbody = document.getElementById('full-customers-tbody');
    const manage = canManage();
    fullTbody.innerHTML = customers.length === 0
        ? `<tr><td colspan="5" class="text-center py-6 text-neutral-400">No customers found.</td></tr>`
        : customers.map(c => `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3.5 px-4 font-semibold text-[#5C3A21]">${esc(c.code)}</td>
            <td class="py-3.5 px-4 font-medium">${esc(c.name)}</td>
            <td class="py-3.5 px-4 text-neutral-600 text-xs">${esc(c.email)}</td>
            <td class="py-3.5 px-4 text-neutral-600 text-xs">${esc(c.phone)}</td>
            ${manage ? `<td class="py-3.5 px-4 text-right space-x-2">
                <button onclick="openCustomerModal(${c.id})" class="text-neutral-600 hover:text-[#5C3A21] p-1.5" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick="deleteCustomer(${c.id})" class="text-red-500 hover:text-red-700 p-1.5" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>` : ''}
        </tr>
    `).join('');
}

function renderIngredientsTable() {
    const tbody = document.getElementById('full-ingredients-tbody');
    if (!tbody) return;
    tbody.innerHTML = ingredients.length === 0
        ? `<tr><td colspan="5" class="text-center py-6 text-neutral-400">No ingredients recorded.</td></tr>`
        : ingredients.map(i => `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3.5 px-4 font-semibold text-[#5C3A21]">${esc(i.code)}</td>
            <td class="py-3.5 px-4 font-medium">${esc(i.name)}</td>
            <td class="py-3.5 px-4"><span class="px-2 py-0.5 rounded-md ${i.quantity < 5 ? 'bg-red-100 text-red-700 font-bold' : 'bg-green-100 text-green-700'}">${Number(i.quantity).toLocaleString('en-US', { maximumFractionDigits: 2 })}</span></td>
            <td class="py-3.5 px-4 text-neutral-600">${esc(i.unit)}</td>
            <td class="py-3.5 px-4 text-right space-x-2">
                <button onclick="openIngredientModal(${i.id})" class="text-neutral-600 hover:text-[#5C3A21] p-1.5" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick="deleteIngredient(${i.id})" class="text-red-500 hover:text-red-700 p-1.5" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function renderBatchesTable() {
    const tbody = document.getElementById('full-batches-tbody');
    if (!tbody) return;
    tbody.innerHTML = batches.length === 0
        ? `<tr><td colspan="6" class="text-center py-6 text-neutral-400">No production batches recorded.</td></tr>`
        : batches.map(b => `
        <tr class="hover:bg-[#FAF6F0] transition">
            <td class="py-3.5 px-4 font-semibold text-[#5C3A21]">${esc(b.code)}</td>
            <td class="py-3.5 px-4 font-medium">${esc(b.product)}</td>
            <td class="py-3.5 px-4">${b.quantity} pcs</td>
            <td class="py-3.5 px-4 text-xs text-neutral-600">${formatDate(b.date)}</td>
            <td class="py-3.5 px-4">${getStatusBadge(b.status)}</td>
            <td class="py-3.5 px-4 text-right space-x-2">
                <button onclick="openBatchModal(${b.id})" class="text-neutral-600 hover:text-[#5C3A21] p-1.5" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                <button onclick="deleteBatch(${b.id})" class="text-red-500 hover:text-red-700 p-1.5" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

// ---------- Order status & payment flow ----------
async function advanceOrderStatus(id) {
    const order = orders.find(o => o.id === Number(id));
    if (!order) return;

    if (order.status === 'Pending') {
        try {
            await api('order_advance', { method: 'POST', body: { id: order.id } });
            showToast(`Order ${order.code} is now Preparing.`);
            await loadData();
        } catch (err) {
            showToast(err.message, 'error');
        }
    } else if (order.status === 'Preparing') {
        openPaymentModal(order.id, order.total);
    }
}

function openPaymentModal(orderId, totalAmount) {
    document.getElementById('pay-order-id').value = orderId;
    document.getElementById('pay-total-display').value = '₱' + Number(totalAmount).toFixed(2);
    document.getElementById('pay-amount-tendered').value = '';
    document.getElementById('pay-change-display').value = '₱0.00';
    showModal('payment-modal');
}

function closePaymentModal() {
    hideModal('payment-modal');
}

function calculateChange() {
    const orderId = Number(document.getElementById('pay-order-id').value);
    const order = orders.find(o => o.id === orderId);
    if (!order) return;
    const tendered = parseFloat(document.getElementById('pay-amount-tendered').value) || 0;
    const change = Math.round(tendered * 100) - Math.round(order.total * 100);

    document.getElementById('pay-change-display').value = change >= 0 ? '₱' + (change / 100).toFixed(2) : 'Insufficient Amount';
}

async function handlePaymentSubmit(e) {
    e.preventDefault();
    const orderId = Number(document.getElementById('pay-order-id').value);
    const tendered = parseFloat(document.getElementById('pay-amount-tendered').value);
    const method = document.getElementById('pay-method').value;
    const order = orders.find(o => o.id === orderId);
    if (!order) return;

    if (!(tendered >= order.total)) {
        showToast('Amount tendered is less than the total amount due!', 'error');
        return;
    }

    try {
        await api('order_pay', { method: 'POST', body: { id: orderId, method, amount_tendered: tendered } });
        closePaymentModal();
        showToast(`Payment successful! Order ${order.code} marked as Completed.`);
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---------- New pre-order ----------
function orderableProducts() {
    return products.filter(p => p.available && p.stock > 0);
}

function openOrderModal() {
    if (!currentUser) return;
    orderLines = [{ product_id: '', quantity: 1 }];

    const wrap = document.getElementById('order-customer-wrap');
    const select = document.getElementById('order-customer');
    if (canManage()) {
        wrap.style.display = 'block';
        select.innerHTML = '<option value="">Select a customer…</option>' +
            customers.map(c => `<option value="${c.id}">${esc(c.code)} — ${esc(c.name)}</option>`).join('');
    } else {
        wrap.style.display = 'none';
    }

    const pickup = document.getElementById('order-pickup');
    pickup.value = '';
    pickup.min = todayStr();

    renderOrderLines();
    showModal('order-modal');
}

function closeOrderModal() {
    hideModal('order-modal');
}

function renderOrderLines() {
    const list = orderableProducts();
    document.getElementById('order-lines').innerHTML = orderLines.map((line, i) => `
        <div class="flex items-center gap-2">
            <select onchange="updateOrderLine(${i}, 'product_id', this.value)" required
                    class="flex-1 min-w-0 px-3 py-2 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
                <option value="">Select a product…</option>
                ${list.map(p => `<option value="${p.id}" ${String(p.id) === String(line.product_id) ? 'selected' : ''}>${esc(p.name)} — ₱${Number(p.price).toFixed(2)} (${p.stock} left)</option>`).join('')}
            </select>
            <input type="number" min="1" max="1000" value="${line.quantity}" oninput="updateOrderLine(${i}, 'quantity', this.value)"
                   class="w-20 px-3 py-2 text-sm bg-[#FAF6F0] border border-[#E6DCD0] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#C88A3D]">
            <button type="button" onclick="removeOrderLine(${i})" class="text-red-500 hover:text-red-700 p-1.5" title="Remove"><i class="fa-solid fa-xmark"></i></button>
        </div>
    `).join('');
    updateOrderTotal();
}

function addOrderLine() {
    orderLines.push({ product_id: '', quantity: 1 });
    renderOrderLines();
}

function removeOrderLine(i) {
    orderLines.splice(i, 1);
    if (orderLines.length === 0) orderLines.push({ product_id: '', quantity: 1 });
    renderOrderLines();
}

function updateOrderLine(i, field, value) {
    orderLines[i][field] = value;
    updateOrderTotal();
}

function updateOrderTotal() {
    let cents = 0;
    orderLines.forEach(l => {
        const p = products.find(x => String(x.id) === String(l.product_id));
        const qty = parseInt(l.quantity, 10);
        if (p && qty > 0) cents += Math.round(p.price * 100) * qty;
    });
    document.getElementById('order-total-display').innerText = peso(cents / 100);
}

async function handleOrderSubmit(e) {
    e.preventDefault();

    const items = orderLines
        .filter(l => l.product_id)
        .map(l => ({ product_id: Number(l.product_id), quantity: parseInt(l.quantity, 10) }));

    if (items.length === 0) {
        showToast('Add at least one item to the order.', 'error');
        return;
    }
    if (items.some(i => !(i.quantity >= 1))) {
        showToast('Each item needs a quantity of at least 1.', 'error');
        return;
    }

    const body = { items, pickup_date: document.getElementById('order-pickup').value || '' };
    if (canManage()) {
        body.customer_id = Number(document.getElementById('order-customer').value) || 0;
        if (!body.customer_id) {
            showToast('Please select a customer.', 'error');
            return;
        }
    }

    try {
        const data = await api('order_create', { method: 'POST', body });
        closeOrderModal();
        showToast(`Pre-order placed! Total: ${peso(data.total)}`);
        await loadData();
        switchTab('preorders');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---------- Product management (administrator) ----------
function openProductModal(productId = null) {
    if (!isAdmin()) return;
    document.getElementById('product-form').reset();

    if (productId) {
        document.getElementById('modal-title').innerHTML = `<i class="fa-solid fa-pen-to-square text-[#C88A3D]"></i><span>Edit Product</span>`;
        const prod = products.find(p => p.id === Number(productId));
        if (prod) {
            document.getElementById('product-id').value = prod.id;
            document.getElementById('input-name').value = prod.name;
            document.getElementById('input-category').value = prod.category;
            document.getElementById('input-description').value = prod.description || '';
            document.getElementById('input-price').value = prod.price;
            document.getElementById('input-stock').value = prod.stock;
            document.getElementById('input-available').checked = !!prod.available;
        }
    } else {
        document.getElementById('modal-title').innerHTML = `<i class="fa-solid fa-bread-slice text-[#C88A3D]"></i><span>Add New Product</span>`;
        document.getElementById('product-id').value = '';
        document.getElementById('input-available').checked = true;
    }
    showModal('product-modal');
}

function closeProductModal() {
    hideModal('product-modal');
}

async function handleProductSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('product-id').value;
    const name = document.getElementById('input-name').value.trim();
    const category = document.getElementById('input-category').value;
    const description = document.getElementById('input-description').value.trim();
    const price = parseFloat(document.getElementById('input-price').value);
    const stock = parseInt(document.getElementById('input-stock').value, 10);
    const available = document.getElementById('input-available').checked;

    try {
        const data = await api('product_save', {
            method: 'POST',
            body: { id: id || null, name, category, description, price, stock, available }
        });
        closeProductModal();
        showToast(data.created ? `New product "${name}" added to catalog!` : `Product "${name}" successfully updated!`);
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function editProduct(id) {
    openProductModal(id);
}

async function deleteProduct(id) {
    const prod = products.find(p => p.id === Number(id));
    if (!confirm(`Are you sure you want to remove ${prod ? prod.name : 'this product'}?`)) return;

    try {
        await api('product_delete', { method: 'POST', body: { id: Number(id) } });
        showToast('Product removed successfully.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function filterProducts() {
    renderCatalogTable(getFilteredProducts());
}

function filterOrderStatus(status) {
    currentFilterStatus = status;
    document.querySelectorAll('.order-filter-btn').forEach(btn => {
        btn.className = "order-filter-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#FAF6F0] text-neutral-700 hover:bg-[#E6DCD0]";
        if (btn.innerText.toLowerCase() === status.toLowerCase() || (status === 'all' && btn.innerText === 'All')) {
            btn.className = "order-filter-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-[#5C3A21] text-white";
        }
    });
    renderOrdersTable();
}

// ---------- Customer management (admin/staff) ----------
function openCustomerModal(customerId = null) {
    if (!canManage()) return;
    document.getElementById('customer-form').reset();
    const title = document.getElementById('customer-modal-title');

    if (customerId) {
        title.innerText = 'Edit Customer';
        const c = customers.find(x => x.id === Number(customerId));
        if (c) {
            document.getElementById('customer-id').value = c.id;
            document.getElementById('cust-first').value = c.first_name;
            document.getElementById('cust-last').value = c.last_name;
            document.getElementById('cust-email').value = c.email;
            document.getElementById('cust-phone').value = c.phone;
        }
    } else {
        title.innerText = 'Add Customer';
        document.getElementById('customer-id').value = '';
    }
    showModal('customer-modal');
}

function closeCustomerModal() {
    hideModal('customer-modal');
}

async function handleCustomerSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('customer-id').value;
    const body = {
        id: id || null,
        first_name: document.getElementById('cust-first').value.trim(),
        last_name: document.getElementById('cust-last').value.trim(),
        email: document.getElementById('cust-email').value.trim(),
        phone: document.getElementById('cust-phone').value.trim()
    };

    try {
        const data = await api('customer_save', { method: 'POST', body });
        closeCustomerModal();
        showToast(data.created ? 'Customer added successfully.' : 'Customer updated successfully.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteCustomer(id) {
    const c = customers.find(x => x.id === Number(id));
    if (!confirm(`Delete ${c ? c.name : 'this customer'}? This cannot be undone.`)) return;

    try {
        await api('customer_delete', { method: 'POST', body: { id: Number(id) } });
        showToast('Customer deleted.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---------- Ingredient management (admin/staff) ----------
function openIngredientModal(ingredientId = null) {
    if (!canManage()) return;
    document.getElementById('ingredient-form').reset();
    const title = document.getElementById('ingredient-modal-title');

    if (ingredientId) {
        title.innerText = 'Edit Ingredient';
        const i = ingredients.find(x => x.id === Number(ingredientId));
        if (i) {
            document.getElementById('ingredient-id').value = i.id;
            document.getElementById('ing-name').value = i.name;
            document.getElementById('ing-qty').value = i.quantity;
            document.getElementById('ing-unit').value = i.unit;
        }
    } else {
        title.innerText = 'Add Ingredient';
        document.getElementById('ingredient-id').value = '';
    }
    showModal('ingredient-modal');
}

function closeIngredientModal() {
    hideModal('ingredient-modal');
}

async function handleIngredientSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('ingredient-id').value;
    const body = {
        id: id || null,
        name: document.getElementById('ing-name').value.trim(),
        unit: document.getElementById('ing-unit').value,
        quantity: parseFloat(document.getElementById('ing-qty').value)
    };

    try {
        const data = await api('ingredient_save', { method: 'POST', body });
        closeIngredientModal();
        showToast(data.created ? 'Ingredient added.' : 'Ingredient updated.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteIngredient(id) {
    const i = ingredients.find(x => x.id === Number(id));
    if (!confirm(`Remove ${i ? i.name : 'this ingredient'} from inventory?`)) return;

    try {
        await api('ingredient_delete', { method: 'POST', body: { id: Number(id) } });
        showToast('Ingredient removed.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---------- Production batch management (admin/staff) ----------
function openBatchModal(batchId = null) {
    if (!canManage()) return;
    document.getElementById('batch-form').reset();
    const title = document.getElementById('batch-modal-title');

    document.getElementById('batch-product').innerHTML = '<option value="">Select a product…</option>' +
        products.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');

    if (batchId) {
        title.innerText = 'Edit Production Batch';
        const b = batches.find(x => x.id === Number(batchId));
        if (b) {
            document.getElementById('batch-id').value = b.id;
            document.getElementById('batch-product').value = b.product_id;
            document.getElementById('batch-qty').value = b.quantity;
            document.getElementById('batch-date').value = b.date;
            document.getElementById('batch-status').value = b.status;
        }
    } else {
        title.innerText = 'New Production Batch';
        document.getElementById('batch-id').value = '';
        document.getElementById('batch-date').value = todayStr();
    }
    showModal('batch-modal');
}

function closeBatchModal() {
    hideModal('batch-modal');
}

async function handleBatchSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('batch-id').value;
    const body = {
        id: id || null,
        product_id: Number(document.getElementById('batch-product').value),
        quantity: parseInt(document.getElementById('batch-qty').value, 10),
        production_date: document.getElementById('batch-date').value,
        status: document.getElementById('batch-status').value
    };

    try {
        const data = await api('batch_save', { method: 'POST', body });
        closeBatchModal();
        showToast(data.created ? 'Production batch recorded.' : 'Production batch updated.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function deleteBatch(id) {
    const b = batches.find(x => x.id === Number(id));
    if (!confirm(`Delete batch ${b ? b.code : ''}?`)) return;

    try {
        await api('batch_delete', { method: 'POST', body: { id: Number(id) } });
        showToast('Production batch deleted.');
        await loadData();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ---------- Initialize ----------
window.onload = function () {
    if (currentUser) {
        document.getElementById('login-modal').classList.add('hidden');
        document.getElementById('current-user-badge').innerText = `${currentUser.role}: ${currentUser.username}`;
        applyRolePermissions();
        loadData();
    }
};