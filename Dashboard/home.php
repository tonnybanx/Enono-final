<?php
// Dashboard/home.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'home';

// -------------------------------------------------
// KPI Queries
// -------------------------------------------------
try {
    // Total active products
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

    // Featured products
    $featuredProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND is_featured = 1")->fetchColumn();

    // Low stock (variants with stock <= 5)
    $lowStock = (int)$pdo->query("
        SELECT COUNT(*) 
        FROM product_variants 
        WHERE is_active = 1 AND stock_quantity <= 5
    ")->fetchColumn();

    // Total customers
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' OR role IS NULL")->fetchColumn();

    // Total categories
    $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();

    // Recent products (for the table)
    $recentProducts = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.base_price,
            p.is_featured,
            p.created_at,
            c.name AS category_name,
            COALESCE(pv.stock_quantity, 0) AS stock
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN (
            SELECT product_id, MIN(id) AS first_variant_id
            FROM product_variants
            WHERE is_active = 1
            GROUP BY product_id
        ) fv ON fv.product_id = p.id
        LEFT JOIN product_variants pv ON pv.id = fv.first_variant_id
        WHERE p.is_active = 1
        ORDER BY p.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top products by stock (simple ranking until you have real sales data)
    $topProducts = $pdo->query("
        SELECT p.name, COALESCE(SUM(pv.stock_quantity), 0) AS total_stock
        FROM products p
        LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY total_stock DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

function formatMoney($amount) {
    return 'UGX ' . number_format((float)$amount, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <style>
        .sidebar { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
        .chart-container {
            position: relative;
            height: 340px;
            width: 100%;
        }
        .overlay { display: none; background: rgba(0, 0, 0, 0.5); }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.08); }
    </style>
</head>
<body class="w-full h-screen flex overflow-hidden bg-gray-50">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden">

        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <!-- Dashboard Content -->
        <div class="flex-1 overflow-auto p-6 space-y-8">

            <!-- Header -->
            <div class="flex flex-wrap justify-between items-end gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-gray-500">A snapshot of your business today</p>
                </div>
                <a href="add-product.html" 
                   class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl font-medium transition-colors">
                    <i class="fa fa-plus"></i>
                    <span>Add Product</span>
                </a>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Total Products -->
                <div class="bg-white rounded-3xl p-6 shadow-sm card-hover border border-gray-100">
                    <div class="flex justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Products</p>
                            <p class="text-4xl font-bold text-gray-800 mt-2"><?= $totalProducts ?></p>
                            <p class="text-emerald-500 text-sm flex items-center gap-1 mt-3">
                                <i class="fa fa-box"></i> <?= $featuredProducts ?> featured
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600">
                            <i class="fa fa-cube text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Low Stock -->
                <div class="bg-white rounded-3xl p-6 shadow-sm card-hover border border-gray-100">
                    <div class="flex justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Low Stock Items</p>
                            <p class="text-4xl font-bold text-gray-800 mt-2"><?= $lowStock ?></p>
                            <p class="text-orange-500 text-sm flex items-center gap-1 mt-3">
                                <i class="fa fa-exclamation-triangle"></i> Needs restock
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-2xl flex items-center justify-center text-orange-600">
                            <i class="fa fa-boxes-stacked text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Customers -->
                <div class="bg-white rounded-3xl p-6 shadow-sm card-hover border border-gray-100">
                    <div class="flex justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Customers</p>
                            <p class="text-4xl font-bold text-gray-800 mt-2"><?= $totalCustomers ?></p>
                            <p class="text-emerald-500 text-sm flex items-center gap-1 mt-3">
                                <i class="fa fa-users"></i> Registered
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center text-purple-600">
                            <i class="fa fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="bg-white rounded-3xl p-6 shadow-sm card-hover border border-gray-100">
                    <div class="flex justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Categories</p>
                            <p class="text-4xl font-bold text-gray-800 mt-2"><?= $totalCategories ?></p>
                            <p class="text-blue-500 text-sm flex items-center gap-1 mt-3">
                                <i class="fa fa-tags"></i> Active
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center text-emerald-600">
                            <i class="fa fa-folder text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Weekly Sales (placeholder until orders table exists) -->
                <div class="bg-white rounded-3xl p-6 shadow-sm">
                    <h3 class="font-semibold text-lg mb-2">Weekly Sales Trend</h3>
                    <p class="text-xs text-gray-400 mb-4">Placeholder – will use real data when orders table is added</p>
                    <div class="chart-container">
                        <canvas id="weeklySalesChart"></canvas>
                    </div>
                </div>

                <!-- Top Products by Stock -->
                <div class="bg-white rounded-3xl p-6 shadow-sm">
                    <h3 class="font-semibold text-lg mb-6">Top Products (by Stock)</h3>
                    <div class="chart-container">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Products Table -->
            <div class="bg-white rounded-3xl p-6 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-semibold text-lg">Recent Products</h3>
                    <a href="product.php" class="text-blue-600 hover:text-blue-700 text-sm flex items-center gap-1">
                        View all <i class="fa fa-arrow-right text-xs"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px]">
                        <thead>
                            <tr class="border-b text-left text-gray-500 text-sm">
                                <th class="pb-4 font-medium">Product</th>
                                <th class="pb-4 font-medium">Category</th>
                                <th class="pb-4 font-medium">Price</th>
                                <th class="pb-4 font-medium">Stock</th>
                                <th class="pb-4 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y">
                            <?php if (empty($recentProducts)): ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-gray-400">No products found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentProducts as $p): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="py-4">
                                            <div class="font-medium"><?= htmlspecialchars($p['name']) ?></div>
                                            <?php if ($p['is_featured']): ?>
                                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Featured</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 text-gray-600">
                                            <?= htmlspecialchars($p['category_name'] ?? '—') ?>
                                        </td>
                                        <td class="py-4 font-medium">
                                            <?= formatMoney($p['base_price']) ?>
                                        </td>
                                        <td class="py-4">
                                            <?php if ((int)$p['stock'] <= 5): ?>
                                                <span class="text-orange-600 font-medium"><?= (int)$p['stock'] ?></span>
                                            <?php else: ?>
                                                <?= (int)$p['stock'] ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                                <span class="status-dot bg-green-500"></span>Active
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <div id="overlay" onclick="toggleSidebar()" class="overlay fixed inset-0 z-40 lg:hidden"></div>

    <script>
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('open');
            if (window.innerWidth <= 1024) {
                overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            }
        }
        document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);

        // Charts
        const topProductLabels = <?= json_encode(array_column($topProducts, 'name')) ?>;
        const topProductData   = <?= json_encode(array_map('intval', array_column($topProducts, 'total_stock'))) ?>;

        // Weekly Sales (placeholder)
        new Chart(document.getElementById('weeklySalesChart'), {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales',
                    data: [420000, 680000, 520000, 910000, 1240000, 890000, 1120000],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.08)',
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } },
                    x: { grid: { color: '#f1f5f9' }, ticks: { color: '#64748b' } }
                }
            }
        });

        // Top Products (real stock data)
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: topProductLabels.length ? topProductLabels : ['No data'],
                datasets: [{
                    data: topProductData.length ? topProductData : [0],
                    backgroundColor: '#3b82f6',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: '#f1f5f9' } },
                    y: { grid: { color: '#f1f5f9' } }
                }
            }
        });
    </script>
</body>
</html>