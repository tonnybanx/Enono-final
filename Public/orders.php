<?php
// Public/orders.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- Auth check ----------
if (empty($_SESSION['user_id'])) {
    $return = urlencode($_SERVER['REQUEST_URI'] ?? 'orders.php');
    header('Location: signin.php?return=' . $return);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ---------- Database connection ----------
require_once '../config/database-connection.php';   // must provide $pdo (PDO instance)

// ---------- Load company profile (needed by header) ----------
$company = [];
$logoUrl = null;
try {
    $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $logoUrl = $company['logo_url'] ?? null;
} catch (Exception $e) {
    // silent fallback
}

// ---------- Page meta ----------
$pageTitle  = 'My Orders';
$activeNav  = 'orders';

// ---------- Fetch orders + items ----------
$orders = [];
$errorMsg = null;

try {
    // Only show:
    // 1. Active orders (Pending / Packed / Shipped)
    // 2. Delivered orders that were updated within the last 14 days
    $stmt = $pdo->prepare("
        SELECT *
        FROM orders
        WHERE user_id = ?
          AND (
                status IN ('Pending', 'Packed', 'Shipped')
             OR (status = 'Delivered' AND updated_at >= DATE_SUB(NOW(), INTERVAL 14 DAY))
          )
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach items to each order
    if ($orders) {
        $orderIds = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $stmt = $pdo->prepare("
            SELECT oi.*,
                   pv.image_url AS variant_image,
                   pv.title     AS variant_title
            FROM order_items oi
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.id ASC
        ");
        $stmt->execute($orderIds);
        $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group items by order_id
        $itemsByOrder = [];
        foreach ($allItems as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }

        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
        unset($order);
    }
} catch (Exception $e) {
    $orders = [];
    $errorMsg = 'Unable to load orders. Please try again later.';
}

// ---------- Helpers ----------
function statusBadge(string $status): string
{
    $map = [
        'Pending'   => 'bg-[#E8B923]/15 text-[#E8B923] border-[#E8B923]/30',
        'Packed'    => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
        'Shipped'   => 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30',
        'Delivered' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
        'Cancelled' => 'bg-red-500/15 text-red-400 border-red-500/30',
    ];
    $class = $map[$status] ?? 'bg-[#1C1C1C] text-gray-400 border-[#2A2A2A]';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ' . $class . '">'
         . htmlspecialchars($status) . '</span>';
}

function progressSteps(string $status): array
{
    $steps = ['Pending', 'Packed', 'Shipped', 'Delivered'];
    if ($status === 'Cancelled') {
        return ['Cancelled'];
    }
    return $steps;
}

function stepIndex(string $status): int
{
    $map = [
        'Pending'   => 0,
        'Packed'    => 1,
        'Shipped'   => 2,
        'Delivered' => 3,
        'Cancelled' => -1,
    ];
    return $map[$status] ?? 0;
}

function formatMoney($amount, string $currency = 'UGX'): string
{
    return 'USh ' . number_format((float)$amount, 0);
}

function resolveImage(?string $url, string $rootPrefix = '../'): string
{
    if (empty($url)) {
        return $rootPrefix . 'images/drum_logo.jpg';
    }
    if (str_starts_with($url, 'http') || str_starts_with($url, '/') || str_starts_with($url, '../')) {
        return $url;
    }
    return $rootPrefix . ltrim($url, '/');
}

$rootPrefix = '../';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ===================== PAGE CONTENT ===================== -->
<main class="mx-auto bg-[#0B0B0B] min-h-screen">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 md:py-14">

        <!-- Header -->
        <div class="mb-10">
            <p class="uppercase tracking-[0.2em] text-sm text-[#D4A017] mb-2">ACCOUNT</p>
            <h1 class="heading-font text-3xl md:text-4xl font-semibold text-white mb-2">My Orders</h1>
            <p class="text-gray-400">
                Track your current orders. Delivered orders remain visible for 14 days after delivery.
            </p>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="mb-8 p-4 rounded-xl bg-red-900/30 border border-red-700/40 text-red-300">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <!-- Empty state -->
            <div class="bg-[#141414] rounded-2xl border border-[#2A2A2A] p-12 text-center">
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-[#1C1C1C] border border-[#2A2A2A] flex items-center justify-center">
                    <i class="fa-solid fa-box-open text-3xl text-[#D4A017]"></i>
                </div>
                <h2 class="text-xl font-semibold text-white mb-2">No active orders</h2>
                <p class="text-gray-400 mb-8 max-w-md mx-auto">
                    You currently have no orders in progress.  
                    Delivered orders stay visible here for 14 days after they are marked as delivered.
                </p>
                <a href="shopping.php"
                   class="inline-flex items-center gap-2 bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] px-6 py-3 rounded-xl font-medium transition-colors">
                    <i class="fa-solid fa-store"></i>
                    Browse the collection
                </a>
            </div>
        <?php else: ?>

            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $currentStep = stepIndex($order['status']);
                    $steps       = progressSteps($order['status']);
                    $isCancelled = $order['status'] === 'Cancelled';
                    ?>
                    <div class="bg-[#141414] rounded-2xl border border-[#2A2A2A] overflow-hidden"
                         x-data="{ open: false }">

                        <!-- Order summary header -->
                        <div class="p-5 sm:p-6 cursor-pointer hover:bg-[#1C1C1C]/50 transition"
                             @click="open = !open">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-1">
                                        <span class="font-semibold text-white">
                                            Order #<?= htmlspecialchars($order['order_number']) ?>
                                        </span>
                                        <?= statusBadge($order['status']) ?>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Placed on <?= date('d M Y, H:i', strtotime($order['created_at'])) ?>
                                        · <?= count($order['items']) ?> item<?= count($order['items']) !== 1 ? 's' : '' ?>
                                    </p>
                                </div>

                                <div class="flex items-center gap-4 sm:gap-6">
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500">Total</p>
                                        <p class="font-semibold text-[#D4A017]">
                                            <?= formatMoney($order['total'], $order['currency'] ?? 'UGX') ?>
                                        </p>
                                    </div>
                                    <i class="fa-solid fa-chevron-down text-gray-500 transition-transform duration-200"
                                       :class="{ 'rotate-180': open }"></i>
                                </div>
                            </div>

                            <!-- Progress tracker -->
                            <?php if (!$isCancelled): ?>
                                <div class="mt-5 pt-5 border-t border-[#2A2A2A]">
                                    <div class="flex items-center justify-between relative">
                                        <!-- Background line -->
                                        <div class="absolute top-4 left-0 right-0 h-0.5 bg-[#2A2A2A]"></div>
                                        <!-- Progress line -->
                                        <div class="absolute top-4 left-0 h-0.5 bg-[#E8B923] transition-all duration-500"
                                             style="width: <?= $currentStep <= 0 ? '0%' : ($currentStep / (count($steps) - 1) * 100) ?>%"></div>

                                        <?php foreach ($steps as $i => $step): ?>
                                            <?php
                                            $done    = $i <= $currentStep;
                                            $current = $i === $currentStep;
                                            ?>
                                            <div class="relative z-10 flex flex-col items-center flex-1">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium
                                                            <?= $done
                                                                ? 'bg-[#E8B923] text-[#0B0B0B]'
                                                                : 'bg-[#141414] border-2 border-[#2A2A2A] text-gray-500' ?>">
                                                    <?php if ($done && !$current): ?>
                                                        <i class="fa-solid fa-check text-xs"></i>
                                                    <?php else: ?>
                                                        <?= $i + 1 ?>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="mt-2 text-xs font-medium text-center
                                                             <?= $done ? 'text-[#E8B923]' : 'text-gray-500' ?>">
                                                    <?= $step ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-4 pt-4 border-t border-[#2A2A2A]">
                                    <p class="text-sm text-red-400 flex items-center gap-2">
                                        <i class="fa-solid fa-circle-xmark"></i>
                                        This order was cancelled.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Expandable details -->
                        <div x-show="open" x-collapse class="border-t border-[#2A2A2A] bg-[#0B0B0B]/60">
                            <div class="p-5 sm:p-6 space-y-5">

                                <!-- Items -->
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-400 mb-3 uppercase tracking-wide">Items</h3>
                                    <div class="space-y-4">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <?php $img = resolveImage($item['variant_image'] ?? null, $rootPrefix); ?>
                                            <div class="flex gap-4">
                                                <img src="<?= htmlspecialchars($img) ?>"
                                                     alt=""
                                                     class="w-16 h-16 object-cover rounded-lg bg-[#1C1C1C] border border-[#2A2A2A] flex-shrink-0"
                                                     onerror="this.src='<?= $rootPrefix ?>images/drum_logo.jpg'">
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-medium text-white truncate">
                                                        <?= htmlspecialchars($item['product_name']) ?>
                                                    </p>
                                                    <?php if (!empty($item['variant_title'])): ?>
                                                        <p class="text-sm text-gray-500">
                                                            <?= htmlspecialchars($item['variant_title']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-sm text-gray-400">
                                                        <span>Qty: <?= (int)$item['quantity'] ?></span>
                                                        <span>·</span>
                                                        <span><?= formatMoney($item['unit_price']) ?> each</span>
                                                        <span>·</span>
                                                        <?= statusBadge($item['status']) ?>
                                                    </div>
                                                </div>
                                                <div class="text-right font-medium text-[#D4A017] whitespace-nowrap">
                                                    <?= formatMoney($item['total_price']) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Order meta -->
                                <div class="grid sm:grid-cols-2 gap-4 pt-4 border-t border-[#2A2A2A] text-sm">
                                    <div>
                                        <p class="text-gray-500 mb-1">Shipping address</p>
                                        <p class="text-gray-300 whitespace-pre-line">
                                            <?= nl2br(htmlspecialchars($order['shipping_address'] ?? '—')) ?>
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Subtotal</span>
                                            <span class="text-white"><?= formatMoney($order['subtotal']) ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Shipping</span>
                                            <span class="text-white"><?= formatMoney($order['shipping_fee']) ?></span>
                                        </div>
                                        <div class="flex justify-between font-semibold text-base pt-1 border-t border-[#2A2A2A]">
                                            <span class="text-white">Total</span>
                                            <span class="text-[#D4A017]"><?= formatMoney($order['total']) ?></span>
                                        </div>
                                        <?php if (!empty($order['payment_method'])): ?>
                                            <p class="text-gray-500 pt-1">
                                                Paid via <?= htmlspecialchars($order['payment_method']) ?>
                                                (<?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>)
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($order['notes'])): ?>
                                    <div class="pt-3 border-t border-[#2A2A2A]">
                                        <p class="text-sm text-gray-500 mb-1">Order notes</p>
                                        <p class="text-sm text-gray-300"><?= htmlspecialchars($order['notes']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php
// Optional footer
// require_once __DIR__ . '/../includes/footer.php';
?>