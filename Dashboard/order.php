<?php
// Dashboard/order.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'orders';

// ---------- Fetch all orders with item count ----------
$sql = "
    SELECT 
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_email,
        o.status,
        o.payment_method,
        o.payment_status,
        o.total,
        o.currency,
        o.created_at,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
    FROM orders o
    ORDER BY o.created_at DESC
";

try {
    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $orders = [];
}

function formatMoney($amount, $currency = 'UGX') {
    return $currency . ' ' . number_format((float)$amount, 0);
}

function formatDate($datetime) {
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Orders</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        body { background-color: #f8fafc; }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        }
        tr { transition: background-color 0.2s; }
        tr:hover { background-color: #f8fafc; }
        .action-btn {
            opacity: 0;
            transform: translateX(10px);
            transition: all 0.25s ease;
        }
        tr:hover .action-btn {
            opacity: 1;
            transform: translateX(0);
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.1em;
            padding-right: 2.75rem !important;
        }

        /* ===== Sidebar mobile collapse ===== */
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        @media (min-width: 1024px) {
            .sidebar {
                transform: none !important;
            }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Orders</h1>
                        <p class="text-gray-500"><span id="totalCount"><?= count($orders) ?></span> orders</p>
                    </div>
                </div>

                <!-- Search & Filter -->
                <div class="flex flex-col md:flex-row gap-4 mb-6">
                    <div class="flex-1 relative">
                        <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInput"
                               class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                               placeholder="Search by customer or order #"
                               onkeyup="applyFilters()">
                    </div>
                    <div class="w-full md:w-64">
                        <select id="statusFilter" 
                                class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                                onchange="applyFilters()">
                            <option value="">All statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Packed">Packed</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- Selection Bar -->
                <div id="selectionBar" class="hidden bg-white border border-gray-200 rounded-2xl p-4 flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-gray-700">1 selected</span>
                        <button onclick="clearSelection()" class="text-blue-600 hover:text-blue-700 font-medium">Clear</button>
                    </div>
                    <button onclick="deleteSelected()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 font-medium">
                        <i class="fa fa-trash"></i>
                        Delete selected
                    </button>
                </div>

                <!-- Orders Table -->
                <div class="card overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr class="text-left text-sm text-gray-600">
                                <th class="w-10 p-4"><input type="checkbox" id="selectAll" class="w-4 h-4"></th>
                                <th class="p-4 font-medium">Order</th>
                                <th class="p-4 font-medium">Customer</th>
                                <th class="p-4 font-medium">Date</th>
                                <th class="p-4 font-medium">Items</th>
                                <th class="p-4 font-medium">Total</th>
                                <th class="p-4 font-medium">Payment</th>
                                <th class="p-4 font-medium">Status</th>
                                <th class="w-32 p-4 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100" id="ordersTable">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="9" class="p-16">
                                        <div class="flex flex-col items-center justify-center text-center">
                                            <img 
                                                src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" 
                                                alt="No orders" 
                                                class="w-36 h-36 object-contain mb-5 opacity-90"
                                            >
                                            <h3 class="text-lg font-medium text-gray-700 mb-1">No orders yet</h3>
                                            <p class="text-gray-400 text-sm max-w-xs">
                                                When customers place orders, they will appear here.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="group order-row"
                                        data-id="<?= htmlspecialchars($order['order_number']) ?>"
                                        data-customer="<?= htmlspecialchars(strtolower($order['customer_name'])) ?>"
                                        data-email="<?= htmlspecialchars(strtolower($order['customer_email'] ?? '')) ?>"
                                        data-status="<?= htmlspecialchars($order['status']) ?>">
                                        
                                        <td class="p-4">
                                            <input type="checkbox" class="w-4 h-4 order-checkbox" 
                                                   data-id="<?= (int)$order['id'] ?>">
                                        </td>
                                        
                                        <td class="p-4 font-medium">
                                            <a href="process-order.php?id=<?= (int)$order['id'] ?>" 
                                               class="text-blue-600 hover:underline">
                                                #<?= htmlspecialchars($order['order_number']) ?>
                                            </a>
                                        </td>
                                        
                                        <td class="p-4">
                                            <div class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?= htmlspecialchars($order['customer_email'] ?? '—') ?>
                                            </div>
                                        </td>
                                        
                                        <td class="p-4 text-gray-600">
                                            <?= formatDate($order['created_at']) ?>
                                        </td>
                                        
                                        <td class="p-4 text-center font-medium">
                                            <?= (int)$order['items_count'] ?>
                                        </td>
                                        
                                        <td class="p-4 font-medium">
                                            <?= formatMoney($order['total'], $order['currency']) ?>
                                        </td>
                                        
                                        <td class="p-4 text-gray-600">
                                            <?= htmlspecialchars($order['payment_method'] ?? '—') ?>
                                        </td>
                                        
                                        <td class="p-4">
                                            <?php
                                            $status = $order['status'];
                                            $badge = match($status) {
                                                'Delivered'  => 'bg-green-100 text-green-700',
                                                'Shipped'   => 'bg-purple-100 text-purple-700',
                                                'Packed'    => 'bg-blue-100 text-blue-700',
                                                'Pending'   => 'bg-yellow-100 text-yellow-700',
                                                'Cancelled' => 'bg-red-100 text-red-700',
                                                default     => 'bg-gray-100 text-gray-700'
                                            };
                                            $dot = match($status) {
                                                'Delivered'  => 'bg-green-500',
                                                'Shipped'   => 'bg-purple-500',
                                                'Packed'    => 'bg-blue-500',
                                                'Pending'   => 'bg-yellow-500',
                                                'Cancelled' => 'bg-red-500',
                                                default     => 'bg-gray-500'
                                            };
                                            ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $badge ?>">
                                                <span class="status-dot mr-1.5 <?= $dot ?>"></span>
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                        
                                        <td class="p-4">
                                            <a href="process-order.php?id=<?= (int)$order['id'] ?>" 
                                               class="action-btn bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 whitespace-nowrap">
                                                <i class="fa fa-sliders-h text-gray-500"></i>
                                                Process
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination bar -->
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-t bg-gray-50">
                        <div class="flex items-center gap-3 text-sm text-gray-600">
                            <span>Show</span>
                            <select id="pageSize" class="bg-white border border-gray-300 rounded-xl py-2 px-4 focus:outline-none" onchange="changePageSize()">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                            </select>
                            <span>per page</span>
                        </div>

                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <span id="pageInfo">Page 1 of 1</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button id="prevBtn" onclick="changePage(-1)" 
                                    class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="fa fa-chevron-left mr-1"></i> Previous
                            </button>
                            <button id="nextBtn" onclick="changePage(1)" 
                                    class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
                                Next <i class="fa fa-chevron-right ml-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <script>
        let currentPage = 1;
        let pageSize = 20;
        let filteredRows = [];

        function getAllRows() {
            return Array.from(document.querySelectorAll('.order-row'));
        }

        function applyFilters() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            const status = document.getElementById('statusFilter').value;

            const allRows = getAllRows();
            filteredRows = allRows.filter(row => {
                const id        = row.dataset.id || '';
                const customer  = row.dataset.customer || '';
                const email     = row.dataset.email || '';
                const rowStatus = row.dataset.status || '';

                const matchesSearch = !search || 
                    id.toLowerCase().includes(search) || 
                    customer.includes(search) ||
                    email.includes(search);

                const matchesStatus = !status || rowStatus === status;

                return matchesSearch && matchesStatus;
            });

            allRows.forEach(r => r.style.display = 'none');
            currentPage = 1;
            renderPage();
        }

        function renderPage() {
            const total = filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));

            if (currentPage > totalPages) currentPage = totalPages;

            const start = (currentPage - 1) * pageSize;
            const end   = start + pageSize;

            filteredRows.forEach((row, idx) => {
                row.style.display = (idx >= start && idx < end) ? '' : 'none';
            });

            document.getElementById('pageInfo').textContent = 
                total === 0 ? 'No results' : `Page ${currentPage} of ${totalPages}`;
            document.getElementById('totalCount').textContent = total;

            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;

            document.getElementById('selectAll').checked = false;
            updateSelectionBar();
        }

        function changePage(delta) {
            currentPage += delta;
            renderPage();
        }

        function changePageSize() {
            pageSize = parseInt(document.getElementById('pageSize').value, 10);
            currentPage = 1;
            renderPage();
        }

        function updateSelectionBar() {
            const selected = document.querySelectorAll('.order-checkbox:checked').length;
            const selectionBar = document.getElementById('selectionBar');
            selectionBar.classList.toggle('hidden', selected === 0);
            if (selected > 0) {
                selectionBar.querySelector('span').textContent = `${selected} selected`;
            }
        }

        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('order-checkbox')) updateSelectionBar();
        });

        function clearSelection() {
            document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            document.getElementById('selectionBar').classList.add('hidden');
        }

        function deleteSelected() {
            const ids = Array.from(document.querySelectorAll('.order-checkbox:checked'))
                             .map(cb => cb.dataset.id);

            if (ids.length === 0) return;

            if (!confirm(`Delete ${ids.length} selected order(s)? This cannot be undone.`)) return;

            fetch('delete-orders.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete orders');
                }
            })
            .catch(() => alert('Network error'));
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('open');
            if (window.innerWidth <= 1024) {
                overlay.classList.toggle('hidden');
            }
        }

        // Connect the hamburger button
        document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);

        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.order-checkbox').forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = this.checked;
                }
            });
            updateSelectionBar();
        });

        document.addEventListener('DOMContentLoaded', () => {
            applyFilters();
        });
    </script>
</body>
</html>