<?php
// Dashboard/process-order.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'orders';

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: order.php');
    exit;
}

// ---------- Fetch order ----------
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: order.php');
    exit;
}

// ---------- Handle POST actions ----------
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update single item status
    if ($action === 'update_item_status') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $allowed = ['Pending', 'Packed', 'Shipped', 'Delivered'];

        if ($itemId > 0 && in_array($newStatus, $allowed)) {
            $stmt = $pdo->prepare("
                UPDATE order_items 
                SET status = ? 
                WHERE id = ? AND order_id = ?
            ");
            $stmt->execute([$newStatus, $itemId, $orderId]);
            $message = 'Item status updated.';
            $messageType = 'success';
        }
    }

    // Bulk update all items
    if ($action === 'bulk_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowed = ['Pending', 'Packed', 'Shipped', 'Delivered'];

        if (in_array($newStatus, $allowed)) {
            $stmt = $pdo->prepare("
                UPDATE order_items 
                SET status = ? 
                WHERE order_id = ?
            ");
            $stmt->execute([$newStatus, $orderId]);
            $message = "All items marked as $newStatus.";
            $messageType = 'success';
        }
    }
}

// ---------- Fetch items + product info (correct image) ----------
$stmt = $pdo->prepare("
    SELECT 
        oi.*,
        p.short_description,
        p.long_description,
        p.is_active AS product_active,
        c.name AS category_name,
        COALESCE(
            NULLIF(pv.image_url, ''),
            (
                SELECT pi.url 
                FROM product_images pi 
                WHERE pi.variant_id = oi.variant_id 
                ORDER BY pi.is_primary DESC, pi.sort_order ASC 
                LIMIT 1
            ),
            (
                SELECT pi.url 
                FROM product_images pi 
                WHERE pi.product_id = oi.product_id 
                ORDER BY pi.is_primary DESC, pi.sort_order ASC 
                LIMIT 1
            )
        ) AS image_url,
        pv.stock_quantity AS stock
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_variants pv ON pv.id = oi.variant_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Calculate progress from item statuses ----------
$statusWeight = ['Pending' => 0, 'Packed' => 1, 'Shipped' => 2, 'Delivered' => 3];
$maxWeight = 3;
$totalItems = count($items);
$totalWeight = 0;
$counts = ['Pending' => 0, 'Packed' => 0, 'Shipped' => 0, 'Delivered' => 0];

foreach ($items as $item) {
    $s = $item['status'] ?? 'Pending';
    $totalWeight += $statusWeight[$s] ?? 0;
    $counts[$s] = ($counts[$s] ?? 0) + 1;
}

$progress = $totalItems > 0 ? (int) round(($totalWeight / ($totalItems * $maxWeight)) * 100) : 0;

// Derive overall order status from items
if ($totalItems > 0) {
    if ($counts['Delivered'] === $totalItems) {
        $overallStatus = 'Delivered';
    } elseif (($counts['Shipped'] + $counts['Delivered']) === $totalItems) {
        $overallStatus = 'Shipped';
    } elseif (($counts['Packed'] + $counts['Shipped'] + $counts['Delivered']) === $totalItems) {
        $overallStatus = 'Packed';
    } else {
        $overallStatus = 'Pending';
    }

    // Sync main order status
    if ($order['status'] !== $overallStatus && $order['status'] !== 'Cancelled') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$overallStatus, $orderId]);
        $order['status'] = $overallStatus;
    }
} else {
    $overallStatus = $order['status'];
}

// Helpers
function formatMoney($amount, $currency = 'UGX') {
    return $currency . ' ' . number_format((float)$amount, 0);
}
function formatDate($datetime) {
    return $datetime ? date('M j, Y \a\t g:i A', strtotime($datetime)) : '—';
}
function statusBadge($status) {
    return match($status) {
        'Delivered'  => 'bg-green-100 text-green-700',
        'Shipped'    => 'bg-purple-100 text-purple-700',
        'Packed'     => 'bg-blue-100 text-blue-700',
        'Pending'    => 'bg-yellow-100 text-yellow-700',
        'Cancelled'  => 'bg-red-100 text-red-700',
        default      => 'bg-gray-100 text-gray-700'
    };
}
function paymentBadge($status) {
    return match($status) {
        'Paid'     => 'bg-green-100 text-green-700',
        'Pending'  => 'bg-yellow-100 text-yellow-700',
        'Failed'   => 'bg-red-100 text-red-700',
        'Refunded' => 'bg-gray-100 text-gray-700',
        default    => 'bg-gray-100 text-gray-700'
    };
}
function productImage($url) {
    if (empty($url)) return '../images/drum_logo.jpg';
    if (str_starts_with($url, 'http')) return $url;
    return '../' . ltrim($url, '/');
}

// Data for product modal
$productsForModal = [];
foreach ($items as $item) {
    $pid = (int)($item['product_id'] ?? 0);
    if ($pid <= 0) continue;
    $productsForModal[$pid] = [
        'id'       => $pid,
        'name'     => $item['product_name'],
        'short'    => $item['short_description'] ?? '',
        'long'     => $item['long_description'] ?? '',
        'sku'      => $item['sku'] ?? '—',
        'category' => $item['category_name'] ?? '—',
        'price'    => formatMoney($item['unit_price'], $order['currency']),
        'stock'    => (int)($item['stock'] ?? 0),
        'status'   => !empty($item['product_active']) ? 'Active' : 'Inactive',
        'image'    => productImage($item['image_url'] ?? ''),
        'qty'      => (int)$item['quantity'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enono Market - Order #<?= htmlspecialchars($order['order_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgb(0 0 0 / 0.06); }
        select {
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 0.75rem center;
            background-size: 1em; padding-right: 2.25rem !important;
        }
        .label { font-size: 11px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: #9ca3af; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .modal-overlay { animation: fadeIn 0.2s ease; }
        .modal-panel { animation: slideUp 0.25s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(16px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="flex-1 overflow-auto p-4 md:p-6">
            <div class="max-w-6xl mx-auto">

                <a href="order.php" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-5">
                    <i class="fa fa-arrow-left"></i> Back to Orders
                </a>

                <?php if ($message): ?>
                    <div class="mb-5 px-4 py-3 rounded-xl text-sm <?= $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Header + Progress -->
                <div class="card p-5 md:p-6 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                                    #<?= htmlspecialchars($order['order_number']) ?>
                                </h1>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= statusBadge($overallStatus) ?>">
                                    <?= htmlspecialchars($overallStatus) ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                Placed on <?= formatDate($order['created_at']) ?>
                            </p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                            <span>Fulfillment progress</span>
                            <span class="font-semibold text-gray-800"><?= $progress ?>%</span>
                        </div>
                        <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600 rounded-full transition-all duration-500" style="width:<?= $progress ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            <?= $counts['Delivered'] ?> of <?= $totalItems ?> delivered
                            · <?= $counts['Packed'] ?> packed
                            · <?= $counts['Shipped'] ?> shipped
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">

                    <!-- LEFT -->
                    <div class="lg:col-span-2 flex flex-col space-y-6">

                        <!-- Items -->
                        <div class="card overflow-hidden flex-1">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h2 class="font-semibold text-gray-900">
                                    Order Items
                                    <span class="text-gray-400 font-normal text-sm ml-1">(<?= $totalItems ?>)</span>
                                </h2>
                            </div>

                            <?php if (empty($items)): ?>
                                <div class="p-10 text-center text-gray-400">
                                    <i class="fa fa-box-open text-3xl mb-3"></i>
                                    <p class="text-sm">No items in this order</p>
                                </div>
                            <?php else: ?>
                                <div class="divide-y divide-gray-50">
                                    <?php foreach ($items as $item): ?>
                                        <?php $pid = (int)($item['product_id'] ?? 0); ?>
                                        <div class="px-5 py-4 hover:bg-gray-50/60 transition">
                                            <div class="flex gap-4">
                                                <img src="<?= htmlspecialchars(productImage($item['image_url'] ?? '')) ?>"
                                                     class="w-16 h-16 rounded-xl object-cover border border-gray-100 shrink-0"
                                                     onerror="this.src='../images/drum_logo.jpg'" alt="">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></p>
                                                            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1 text-xs text-gray-500">
                                                                <?php if ($item['sku']): ?>
                                                                    <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($item['sku']) ?></span>
                                                                <?php endif; ?>
                                                                <span>Qty <?= (int)$item['quantity'] ?></span>
                                                                <span><?= formatMoney($item['unit_price'], $order['currency']) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-2 shrink-0">
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="action" value="update_item_status">
                                                                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                                                <select name="status" onchange="this.form.submit()"
                                                                        class="border border-gray-200 rounded-xl px-3 py-2 text-xs bg-white">
                                                                    <?php foreach (['Pending','Packed','Shipped','Delivered'] as $s): ?>
                                                                        <option value="<?= $s ?>" <?= ($item['status'] ?? 'Pending') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </form>

                                                            <?php if ($pid > 0): ?>
                                                                <button type="button" onclick="openProductModal(<?= $pid ?>)"
                                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg">
                                                                    <i class="fa fa-eye"></i> View
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($order['notes'])): ?>
                            <div class="card p-5">
                                <p class="label mb-2">Order Notes</p>
                                <p class="text-sm text-gray-700 whitespace-pre-line"><?= htmlspecialchars($order['notes']) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Bulk actions -->
                        <div class="card p-5">
                            <p class="label mb-3">Bulk actions</p>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="bulk_status">
                                    <input type="hidden" name="status" value="Packed">
                                    <button type="submit" class="px-4 py-2 text-sm border border-gray-200 rounded-xl hover:bg-gray-50">Mark all Packed</button>
                                </form>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="bulk_status">
                                    <input type="hidden" name="status" value="Shipped">
                                    <button type="submit" class="px-4 py-2 text-sm border border-gray-200 rounded-xl hover:bg-gray-50">Mark all Shipped</button>
                                </form>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="bulk_status">
                                    <input type="hidden" name="status" value="Delivered">
                                    <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-xl hover:bg-blue-700">Mark all Delivered</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT – fills remaining height -->
                    <div class="flex flex-col gap-5 h-full">
                        <div class="card p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fa fa-user text-gray-400"></i>
                                <h3 class="font-semibold text-gray-900">Customer</h3>
                            </div>
                            <div class="space-y-3 text-sm">
                                <p class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></p>
                                <?php if ($order['customer_email']): ?>
                                    <div class="flex items-start gap-2 text-gray-600">
                                        <i class="fa fa-envelope text-gray-400 mt-0.5 w-4"></i>
                                        <span class="break-all"><?= htmlspecialchars($order['customer_email']) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['customer_phone']): ?>
                                    <div class="flex items-center gap-2 text-gray-600">
                                        <i class="fa fa-phone text-gray-400 w-4"></i>
                                        <span><?= htmlspecialchars($order['customer_phone']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fa fa-map-marker-alt text-gray-400"></i>
                                <h3 class="font-semibold text-gray-900">Shipping</h3>
                            </div>
                            <?php if ($order['shipping_address']): ?>
                                <p class="text-sm text-gray-700 whitespace-pre-line"><?= htmlspecialchars($order['shipping_address']) ?></p>
                            <?php else: ?>
                                <p class="text-sm text-gray-400">No address</p>
                            <?php endif; ?>
                        </div>

                        <div class="card p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fa fa-credit-card text-gray-400"></i>
                                <h3 class="font-semibold text-gray-900">Payment</h3>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Method</span>
                                    <span class="font-medium"><?= htmlspecialchars($order['payment_method'] ?? '—') ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Status</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?= paymentBadge($order['payment_status'] ?? 'Pending') ?>">
                                        <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Summary grows to fill remaining space -->
                        <div class="card p-5 flex-1 flex flex-col">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fa fa-receipt text-gray-400"></i>
                                <h3 class="font-semibold text-gray-900">Summary</h3>
                            </div>
                            <div class="space-y-2.5 text-sm flex-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Subtotal</span>
                                    <span><?= formatMoney($order['subtotal'], $order['currency']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Shipping</span>
                                    <span class="<?= (float)$order['shipping_fee'] <= 0 ? 'text-green-600' : '' ?>">
                                        <?= (float)$order['shipping_fee'] <= 0 ? 'Free' : formatMoney($order['shipping_fee'], $order['currency']) ?>
                                    </span>
                                </div>
                                <div class="flex justify-between pt-3 border-t font-semibold text-base mt-auto">
                                    <span>Total</span>
                                    <span><?= formatMoney($order['total'], $order['currency']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="card p-5">
                            <p class="label mb-3">Item status breakdown</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-gray-500">Pending</span><span class="font-medium"><?= $counts['Pending'] ?></span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Packed</span><span class="font-medium"><?= $counts['Packed'] ?></span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Shipped</span><span class="font-medium"><?= $counts['Shipped'] ?></span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Delivered</span><span class="font-medium text-green-600"><?= $counts['Delivered'] ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0 bg-black/50" onclick="closeProductModal()"></div>
        <div class="modal-panel relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="text-lg font-semibold">Product Details</h2>
                <button onclick="closeProductModal()" class="w-9 h-9 rounded-full hover:bg-gray-100 text-xl text-gray-500">&times;</button>
            </div>
            <div class="overflow-y-auto flex-1 p-6">
                <div class="flex flex-col sm:flex-row gap-6">
                    <img id="modalImage" src="" class="w-full sm:w-48 h-48 object-cover rounded-2xl border shrink-0" alt="">
                    <div class="flex-1 space-y-4">
                        <div>
                            <h3 id="modalName" class="text-xl font-bold"></h3>
                            <p id="modalShort" class="text-sm text-gray-500 mt-1"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="bg-gray-50 rounded-xl p-3"><p class="label mb-0.5">Product ID</p><p id="modalId" class="font-mono font-medium"></p></div>
                            <div class="bg-gray-50 rounded-xl p-3"><p class="label mb-0.5">SKU</p><p id="modalSku" class="font-mono font-medium"></p></div>
                            <div class="bg-gray-50 rounded-xl p-3"><p class="label mb-0.5">Category</p><p id="modalCategory" class="font-medium"></p></div>
                            <div class="bg-gray-50 rounded-xl p-3"><p class="label mb-0.5">Price</p><p id="modalPrice" class="font-medium"></p></div>
                            <div class="bg-gray-50 rounded-xl p-3"><p class="label mb-0.5">Stock</p><p id="modalStock" class="font-medium"></p></div>
                            <div class="bg-gray-50 rounded-xl p-3"><p class="label mb-0.5">Status</p><p id="modalStatus"></p></div>
                        </div>
                    </div>
                </div>
                <div class="mt-6">
                    <p class="label mb-2">Description</p>
                    <p id="modalLong" class="text-sm text-gray-600 leading-relaxed"></p>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded-2xl flex items-center gap-3">
                    <i class="fa fa-shopping-bag text-blue-500"></i>
                    <div class="text-sm">
                        <span class="text-gray-600">Ordered quantity:</span>
                        <span id="modalQty" class="font-semibold text-blue-700 ml-1"></span>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t bg-gray-50 flex justify-end">
                <button onclick="closeProductModal()" class="px-5 py-2.5 rounded-xl border text-sm font-medium hover:bg-white">Close</button>
            </div>
        </div>
    </div>

    <script>
        const productsData = <?= json_encode($productsForModal) ?>;

        function openProductModal(id) {
            const p = productsData[id];
            if (!p) return;
            document.getElementById('modalImage').src = p.image;
            document.getElementById('modalName').textContent = p.name;
            document.getElementById('modalShort').textContent = p.short || '';
            document.getElementById('modalLong').textContent = p.long || 'No description.';
            document.getElementById('modalId').textContent = p.id;
            document.getElementById('modalSku').textContent = p.sku;
            document.getElementById('modalCategory').textContent = p.category;
            document.getElementById('modalPrice').textContent = p.price;
            document.getElementById('modalStock').textContent = p.stock;
            document.getElementById('modalQty').textContent = p.qty;
            document.getElementById('modalStatus').innerHTML = p.status === 'Active'
                ? '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>'
                : '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>';
            document.getElementById('productModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeProductModal();
        });
    </script>
</body>
</html>