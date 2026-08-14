<?php
// Dashboard/products.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'products';

// ===================== HANDLE DELETE (AJAX) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {

    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (($input['action'] ?? '') === 'delete') {
        $ids = $input['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs provided']);
            exit;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $pdo->commit();
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ===================== FETCH PRODUCTS =====================
$sql = "
    SELECT 
        p.id, p.name, p.short_description, p.base_price, p.currency,
        p.is_active, p.is_featured, p.created_at,
        c.name AS category_name,
        (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variants_count,
        (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id = p.id OR pi.variant_id IN (
            SELECT id FROM product_variants WHERE product_id = p.id
        )) AS images_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.created_at DESC
";

try {
    $products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}

function formatMoney($amount, $currency = 'UGX') {
    return $currency . ' ' . number_format((float)$amount, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Products</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05); }
        tr { transition: background-color 0.2s; }
        tr:hover { background-color: #f8fafc; }
        .action-btn { opacity: 0; transform: translateX(10px); transition: all 0.25s ease; }
        tr:hover .action-btn { opacity: 1; transform: translateX(0); }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
        select {
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 1rem center; background-size: 1.1em;
            padding-right: 2.75rem !important;
        }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.open { transform: translateX(0); }
        @media (min-width: 1024px) { .sidebar { transform: none !important; } }
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
                    <h1 class="text-2xl font-semibold text-gray-900">Products</h1>
                    <p class="text-gray-500"><span id="totalCount"><?= count($products) ?></span> products</p>
                </div>
                <a href="add-product.php" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 font-medium">
                    <i class="fa fa-plus"></i> Add Product
                </a>
            </div>

            <!-- Search & Filter -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1 relative">
                    <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchInput" class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500"
                           placeholder="Search by product name or category..." onkeyup="applyFilters()">
                </div>
                <div class="w-full md:w-52">
                    <select id="statusFilter" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500" onchange="applyFilters()">
                        <option value="">All status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="w-full md:w-52">
                    <select id="featuredFilter" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:border-blue-500" onchange="applyFilters()">
                        <option value="">All products</option>
                        <option value="1">Featured only</option>
                    </select>
                </div>
            </div>

            <!-- Selection Bar -->
            <div id="selectionBar" class="hidden bg-white border border-gray-200 rounded-2xl p-4 flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="font-medium text-gray-700">1 selected</span>
                    <button onclick="clearSelection()" class="text-blue-600 hover:text-blue-700 font-medium">Clear</button>
                </div>
                <button onclick="deleteSelected()" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 font-medium">
                    <i class="fa fa-trash"></i> Delete selected
                </button>
            </div>

            <!-- Table -->
            <div class="card overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-left text-sm text-gray-600">
                            <th class="w-10 p-4"><input type="checkbox" id="selectAll" class="w-4 h-4"></th>
                            <th class="p-4 font-medium">Product</th>
                            <th class="p-4 font-medium">Category</th>
                            <th class="p-4 font-medium">Base Price</th>
                            <th class="p-4 font-medium text-center">Variants</th>
                            <th class="p-4 font-medium text-center">Images</th>
                            <th class="p-4 font-medium">Status</th>
                            <th class="w-40 p-4 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100" id="productsTable">
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="p-16">
                                    <div class="flex flex-col items-center justify-center text-center">
                                        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No products" class="w-36 h-36 object-contain mb-5 opacity-90">
                                        <h3 class="text-lg font-medium text-gray-700 mb-1">No products yet</h3>
                                        <p class="text-gray-400 text-sm max-w-xs">Add your first product to start selling authentic African crafts.</p>
                                        <a href="add-product.php" class="mt-5 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-medium">+ Add Product</a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr class="group product-row"
                                    data-id="<?= (int)$product['id'] ?>"
                                    data-name="<?= htmlspecialchars(strtolower($product['name'])) ?>"
                                    data-category="<?= htmlspecialchars(strtolower($product['category_name'] ?? '')) ?>"
                                    data-active="<?= (int)$product['is_active'] ?>"
                                    data-featured="<?= (int)$product['is_featured'] ?>">
                                    
                                    <td class="p-4">
                                        <input type="checkbox" class="w-4 h-4 product-checkbox" data-id="<?= (int)$product['id'] ?>">
                                    </td>
                                    
                                    <td class="p-4">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></span>
                                            <?php if ($product['is_featured']): ?>
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">
                                                    <i class="fa fa-star text-[10px]"></i> Featured
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500 line-clamp-1 max-w-xs mt-0.5">
                                            <?= htmlspecialchars($product['short_description'] ?? '') ?>
                                        </div>
                                    </td>
                                    
                                    <td class="p-4 text-gray-600"><?= htmlspecialchars($product['category_name'] ?? '—') ?></td>
                                    <td class="p-4 font-medium"><?= formatMoney($product['base_price'], $product['currency']) ?></td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                            <?= (int)$product['variants_count'] ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center text-gray-600"><?= (int)$product['images_count'] ?></td>
                                    <td class="p-4">
                                        <?php if ($product['is_active']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <span class="status-dot mr-1.5 bg-green-500"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                <span class="status-dot mr-1.5 bg-gray-400"></span> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <a href="view-product.php?id=<?= (int)$product['id'] ?>" 
                                               class="action-btn bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-1.5 whitespace-nowrap">
                                                <i class="fa fa-eye text-gray-500"></i> View
                                            </a>
                                            <button onclick="deleteSingle(<?= (int)$product['id'] ?>)"
                                                    class="action-btn bg-white border border-red-200 hover:border-red-300 text-red-600 px-3 py-2 rounded-xl text-sm font-medium flex items-center gap-1.5">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
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
                        <button id="prevBtn" onclick="changePage(-1)" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa fa-chevron-left mr-1"></i> Previous
                        </button>
                        <button id="nextBtn" onclick="changePage(1)" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
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
    let currentPage = 1, pageSize = 20, filteredRows = [];

    function getAllRows() { return Array.from(document.querySelectorAll('.product-row')); }

    function applyFilters() {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        const status = document.getElementById('statusFilter').value;
        const featured = document.getElementById('featuredFilter').value;

        const allRows = getAllRows();
        filteredRows = allRows.filter(row => {
            const name = row.dataset.name || '';
            const category = row.dataset.category || '';
            const active = row.dataset.active;
            const isFeat = row.dataset.featured;
            return (!search || name.includes(search) || category.includes(search)) &&
                   (status === '' || active === status) &&
                   (featured === '' || isFeat === featured);
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
        const end = start + pageSize;

        filteredRows.forEach((row, idx) => {
            row.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        document.getElementById('pageInfo').textContent = total === 0 ? 'No results' : `Page ${currentPage} of ${totalPages}`;
        document.getElementById('totalCount').textContent = total;
        document.getElementById('prevBtn').disabled = currentPage <= 1;
        document.getElementById('nextBtn').disabled = currentPage >= totalPages;
        document.getElementById('selectAll').checked = false;
        updateSelectionBar();
    }

    function changePage(delta) { currentPage += delta; renderPage(); }
    function changePageSize() { pageSize = parseInt(document.getElementById('pageSize').value, 10); currentPage = 1; renderPage(); }

    function updateSelectionBar() {
        const selected = document.querySelectorAll('.product-checkbox:checked').length;
        const bar = document.getElementById('selectionBar');
        bar.classList.toggle('hidden', selected === 0);
        if (selected > 0) bar.querySelector('span').textContent = `${selected} selected`;
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('product-checkbox')) updateSelectionBar();
    });

    function clearSelection() {
        document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        document.getElementById('selectionBar').classList.add('hidden');
    }

    function deleteSelected() {
        const ids = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.dataset.id);
        if (ids.length === 0) return;
        if (!confirm(`Delete ${ids.length} selected product(s)? This will also delete all their variants and images.`)) return;

        fetch('products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', ids })
        })
        .then(r => r.json())
        .then(data => data.success ? location.reload() : alert(data.message || 'Failed'))
        .catch(() => alert('Network error'));
    }

    function deleteSingle(id) {
        if (!confirm('Delete this product and all its variants & images?')) return;
        fetch('products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', ids: [id] })
        })
        .then(r => r.json())
        .then(data => data.success ? location.reload() : alert(data.message || 'Failed'))
        .catch(() => alert('Network error'));
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.toggle('open');
        if (window.innerWidth <= 1024) overlay.classList.toggle('hidden');
    }

    document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') cb.checked = this.checked;
        });
        updateSelectionBar();
    });

    document.addEventListener('DOMContentLoaded', applyFilters);
</script>
</body>
</html>