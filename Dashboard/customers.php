<?php
// Dashboard/customers.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'customers';

// -------------------------------------------------
// Fetch customers from database
// -------------------------------------------------
try {
    $sql = "
        SELECT 
            u.id,
            u.email,
            u.first_name,
            u.last_name,
            u.phone,
            u.role,
            u.email_verified,
            u.is_active,
            u.created_at,
            u.last_login_at,
            (SELECT COUNT(*) FROM addresses a WHERE a.user_id = u.id) AS address_count
        FROM users u
        WHERE u.role = 'customer' OR u.role IS NULL OR u.role = ''
        ORDER BY u.created_at DESC
    ";
    $customers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $customers = [];
}

function fullName($c) {
    $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    return $name !== '' ? $name : '—';
}

function formatDate($datetime) {
    if (empty($datetime)) return '—';
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Customers</title>

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
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .table-wrapper table {
            min-width: 900px;
        }
        .modal { animation: modalPop 0.3s ease; }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <!-- Main content – flex column so pagination stays at bottom -->
        <div class="flex-1 overflow-hidden px-6 pt-6 flex flex-col">
            <div class="max-w-7xl mx-auto w-full flex flex-col flex-1 min-h-0">

                <!-- Header -->
                <div class="flex justify-between items-center mb-6 flex-shrink-0">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Customers</h1>
                        <p class="text-gray-500">
                            <span id="totalCount"><?= count($customers) ?></span> customers
                        </p>
                    </div>
                </div>

                <!-- Search & Filter -->
                <div class="flex flex-col md:flex-row gap-4 mb-6 flex-shrink-0">
                    <div class="flex-1 relative">
                        <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInput"
                               class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                               placeholder="Search by name, email or phone"
                               onkeyup="applyFilters()">
                    </div>
                    <div class="w-full md:w-56">
                        <select id="statusFilter" 
                                class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                                onchange="applyFilters()">
                            <option value="">All statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Table Card (flex column – pagination stays at bottom) -->
                <div class="card overflow-hidden flex flex-col flex-1 min-h-0">
                    
                    <!-- Scrollable table area -->
                    <div class="flex-1 overflow-auto min-h-0 table-wrapper">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                <tr class="text-left text-sm text-gray-600">
                                    <th class="w-10 p-4"><input type="checkbox" id="selectAll" class="w-4 h-4"></th>
                                    <th class="p-4 font-medium">Customer</th>
                                    <th class="p-4 font-medium">Email</th>
                                    <th class="p-4 font-medium">Phone</th>
                                    <th class="p-4 font-medium">Addresses</th>
                                    <th class="p-4 font-medium">Joined</th>
                                    <th class="p-4 font-medium">Status</th>
                                    <th class="w-28 p-4 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100" id="customersTable">
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="8" class="p-16">
                                            <div class="flex flex-col items-center justify-center text-center">
                                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                    <i class="fa fa-users text-3xl text-gray-400"></i>
                                                </div>
                                                <h3 class="text-lg font-medium text-gray-700 mb-1">No customers yet</h3>
                                                <p class="text-gray-400 text-sm max-w-xs">
                                                    When customers register, they will appear here.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $c): ?>
                                        <tr class="group customer-row"
                                            data-name="<?= htmlspecialchars(strtolower(fullName($c))) ?>"
                                            data-email="<?= htmlspecialchars(strtolower($c['email'] ?? '')) ?>"
                                            data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>"
                                            data-status="<?= (int)$c['is_active'] ?>">
                                            
                                            <td class="p-4">
                                                <input type="checkbox" class="w-4 h-4 customer-checkbox" 
                                                       data-id="<?= (int)$c['id'] ?>">
                                            </td>
                                            
                                            <td class="p-4">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars(fullName($c)) ?></div>
                                                <?php if (!empty($c['email_verified'])): ?>
                                                    <span class="text-xs text-emerald-600"><i class="fa fa-check-circle"></i> Verified</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="p-4 text-gray-600">
                                                <?= htmlspecialchars($c['email'] ?? '—') ?>
                                            </td>
                                            
                                            <td class="p-4 text-gray-600">
                                                <?= htmlspecialchars($c['phone'] ?? '—') ?>
                                            </td>
                                            
                                            <td class="p-4 text-center">
                                                <?= (int)$c['address_count'] ?>
                                            </td>
                                            
                                            <td class="p-4 text-gray-600">
                                                <?= formatDate($c['created_at']) ?>
                                            </td>
                                            
                                            <td class="p-4">
                                                <?php if ($c['is_active']): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                        <span class="status-dot mr-1.5 bg-green-500"></span>
                                                        Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                        <span class="status-dot mr-1.5 bg-red-500"></span>
                                                        Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="p-4">
                                                <button onclick="viewCustomer(<?= (int)$c['id'] ?>)"
                                                        class="action-btn bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 whitespace-nowrap">
                                                    <i class="fa fa-eye text-gray-500"></i>
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination – always at the bottom -->
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 border-t bg-gray-50 flex-shrink-0">
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

    <!-- View Customer Modal -->
    <div id="viewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="modal bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800" id="modalName">Customer</h2>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-3xl leading-none">&times;</button>
                </div>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-500">Email</p>
                        <p class="font-medium" id="modalEmail">—</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Phone</p>
                        <p class="font-medium" id="modalPhone">—</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Status</p>
                        <p class="font-medium" id="modalStatus">—</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Email Verified</p>
                        <p class="font-medium" id="modalVerified">—</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Joined</p>
                        <p class="font-medium" id="modalJoined">—</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Last Login</p>
                        <p class="font-medium" id="modalLastLogin">—</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Addresses</p>
                        <p class="font-medium" id="modalAddresses">—</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end border-t">
                <button onclick="closeModal()" class="px-6 py-2.5 rounded-xl border hover:bg-gray-100 font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <script>
        const customersData = <?= json_encode(array_map(function($c) {
            return [
                'id'             => (int)$c['id'],
                'name'           => fullName($c),
                'email'          => $c['email'] ?? '—',
                'phone'          => $c['phone'] ?? '—',
                'is_active'      => (int)$c['is_active'],
                'email_verified' => (int)$c['email_verified'],
                'created_at'     => formatDate($c['created_at']),
                'last_login_at'  => formatDate($c['last_login_at']),
                'address_count'  => (int)$c['address_count']
            ];
        }, $customers), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        let currentPage = 1;
        let pageSize = 20;
        let filteredRows = [];

        function getAllRows() {
            return Array.from(document.querySelectorAll('.customer-row'));
        }

        function applyFilters() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            const status = document.getElementById('statusFilter').value;

            const allRows = getAllRows();
            filteredRows = allRows.filter(row => {
                const name  = row.dataset.name || '';
                const email = row.dataset.email || '';
                const phone = row.dataset.phone || '';
                const rowStatus = row.dataset.status || '';

                const matchesSearch = !search || 
                    name.includes(search) || 
                    email.includes(search) || 
                    phone.includes(search);

                const matchesStatus = status === '' || rowStatus === status;

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

        function viewCustomer(id) {
            const customer = customersData.find(c => c.id === id);
            if (!customer) return;

            document.getElementById('modalName').textContent = customer.name;
            document.getElementById('modalEmail').textContent = customer.email;
            document.getElementById('modalPhone').textContent = customer.phone;
            document.getElementById('modalStatus').textContent = customer.is_active ? 'Active' : 'Inactive';
            document.getElementById('modalVerified').textContent = customer.email_verified ? 'Yes' : 'No';
            document.getElementById('modalJoined').textContent = customer.created_at;
            document.getElementById('modalLastLogin').textContent = customer.last_login_at;
            document.getElementById('modalAddresses').textContent = customer.address_count;

            const modal = document.getElementById('viewModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        function closeModal() {
            const modal = document.getElementById('viewModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('open');
            if (window.innerWidth <= 1024) {
                overlay.classList.toggle('hidden');
            }
        }

        document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);

        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.customer-checkbox').forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = this.checked;
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            applyFilters();
        });
    </script>
</body>
</html>