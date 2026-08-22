<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';

// ---------- Safe output helper ----------
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}


if (!function_exists('resolveAssetUrl')) {
    function resolveAssetUrl(?string $path, string $rootPrefix, string $default): string
    {
        $path = trim((string)$path);
        if ($path === '') {
            return $rootPrefix . ltrim($default, '/');
        }

        // External URL
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Normalize
        $path = str_replace(['\\', "\0"], ['/', ''], $path);
        $path = ltrim($path, '/');

        // Block traversal
        if (str_contains($path, '..')) {
            return $rootPrefix . ltrim($default, '/');
        }

        // Already root-relative or parent-relative from caller
        if (str_starts_with($path, '../') || str_starts_with($path, '/')) {
            // Still block .. segments after normalize
            if (str_contains($path, '..')) {
                return $rootPrefix . ltrim($default, '/');
            }
            return $path;
        }

        return $rootPrefix . $path;
    }
}

// ---------- Path prefixes ----------
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$inPublic   = (bool) preg_match('#/Public(/|$)#i', $scriptPath);

$projectRoot = realpath(__DIR__ . '/..');
$scriptDir   = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));

$depth = 0;
if ($projectRoot && $scriptDir && str_starts_with($scriptDir, $projectRoot)) {
    $relative = trim(substr($scriptDir, strlen($projectRoot)), '/\\');
    $depth = $relative === '' ? 0 : substr_count($relative, DIRECTORY_SEPARATOR) + 1;
}

$publicPrefix  = $inPublic ? '' : 'Public/';
$rootPrefix    = str_repeat('../', $depth);
$homeHref      = $rootPrefix . 'index.php';
$dashboardHref = $rootPrefix . 'Dashboard/home.php';

// ---------- Company profile (for name, logo, favicon) ----------
if (!isset($company) || !is_array($company)) {
    $company = [];
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
            $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        $company = [];
    }
}

// Defaults
$defaultLogo    = 'images/drum_logo.jpg';
$defaultFavicon = 'images/drum_logo.jpg'; // change to images/favicon.png if you have one

// Logo: prefer $logoUrl if page set it, else company_profile
$rawLogo = !empty($logoUrl) ? $logoUrl : ($company['logo_url'] ?? '');
$resolvedLogo = resolveAssetUrl($rawLogo, $rootPrefix, $defaultLogo);

// Favicon from database
$rawFavicon = $company['favicon_url'] ?? '';
$resolvedFavicon = resolveAssetUrl($rawFavicon, $rootPrefix, $defaultFavicon);

// Favicon MIME hint (optional)
$faviconExt = strtolower(pathinfo(parse_url($resolvedFavicon, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
$faviconType = match ($faviconExt) {
    'ico'         => 'image/x-icon',
    'svg'         => 'image/svg+xml',
    'webp'        => 'image/webp',
    'gif'         => 'image/gif',
    'jpg', 'jpeg' => 'image/jpeg',
    default       => 'image/png',
};

function navClass(string $page, string $activeNav): string
{
    return $page === $activeNav
        ? 'hover:text-[#8B4513] text-[#8B4513] font-semibold'
        : 'hover:text-[#8B4513]';
}

// ---------- Auth helpers (session values only; never trust client input) ----------
$isLoggedIn = !empty($_SESSION['user_id']);
$userRole   = (string)($_SESSION['role'] ?? '');
$isAdmin    = $isLoggedIn && $userRole === 'admin';
$isStaff    = $isLoggedIn && $userRole === 'staff';
$canAccessDashboard = $isAdmin || $isStaff;

$firstName = (string)($_SESSION['first_name'] ?? '');
$lastName  = (string)($_SESSION['last_name'] ?? '');
$email     = (string)($_SESSION['email'] ?? '');
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
if ($initials === '') {
    $initials = 'U';
}

$companyName = (string)($company['company_name'] ?? 'Enono Market');
$tagline     = (string)($company['tagline'] ?? 'Market');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' - ' . $companyName : $companyName) ?></title>

    <!-- Favicon from database (with default fallback) -->
    <link rel="icon" type="<?= e($faviconType) ?>" href="<?= e($resolvedFavicon) ?>">
    <link rel="shortcut icon" type="<?= e($faviconType) ?>" href="<?= e($resolvedFavicon) ?>">
    <link rel="apple-touch-icon" href="<?= e($resolvedFavicon) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        .heading-font { font-family: 'Playfair Display', serif; }
        .body-font { font-family: 'Inter', system-ui, sans-serif; }
        .story-image { border-radius: 0; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .story-image:hover { transform: scale(1.05); }
        @media (min-width: 768px) { .story-image { border-radius: 10px; } }
        .about-grid { align-items: start; }
    </style>
</head>
<body class="body-font bg-[#F9F5EB] text-gray-800 overflow-x-hidden">

    <!-- TOP BANNER -->
    <div class="bg-[#8B4513] text-white text-center py-2 text-xs">
        Understand African Heritage and craft and get each at a 10% discount from the regular price
    </div>

    <!-- NAVBAR -->
    <nav class="bg-white sticky top-0 z-50 border-b shadow-md">
        <div class="max-w-7xl px-4 md:px-6 py-2.5 flex justify-between items-center gap-4">

            <!-- Logo -->
            <a href="<?= e($homeHref) ?>" class="flex items-center gap-2 flex-shrink-0">
                <img src="<?= e($resolvedLogo) ?>"
                     class="w-8 h-9 object-cover rounded"
                     alt="<?= e($companyName) ?> Logo"
                     onerror="this.onerror=null;this.src='<?= e($rootPrefix . $defaultLogo) ?>';">
                <div class="hidden sm:block">
                    <span class="heading-font text-lg lg:text-xl font-bold leading-tight"><?= e($companyName) ?></span>
                    <p class="text-[9px] lg:text-[10px] tracking-widest text-gray-500"><?= e($tagline) ?></p>
                </div>
            </a>

            <!-- Desktop Menu -->
            <div class="hidden lg:flex items-center gap-4 xl:gap-6 text-[13px] font-medium">
                <a href="<?= e($homeHref) ?>" class="<?= navClass('home', $activeNav) ?>">Home</a>
                <a href="<?= e($publicPrefix) ?>shopping.php" class="<?= navClass('shop', $activeNav) ?>">Shop</a>
                <a href="<?= e($publicPrefix) ?>aboutus.php" class="<?= navClass('aboutus', $activeNav) ?>">About</a>
                <a href="<?= e($publicPrefix) ?>blog.php" class="<?= navClass('blog', $activeNav) ?>">Blog</a>
                <a href="<?= e($publicPrefix) ?>heritage.php" class="<?= navClass('heritage', $activeNav) ?>">Heritage</a>
                <a href="<?= e($publicPrefix) ?>orders.php" class="<?= navClass('orders', $activeNav) ?>">Orders</a>
                <a href="<?= e($publicPrefix) ?>contactus.php" class="<?= navClass('contact', $activeNav) ?>">Contact</a>
            </div>

            <!-- Right side icons -->
            <div class="flex items-center gap-2.5 sm:gap-3 flex-shrink-0">

                <a href="<?= e($publicPrefix) ?>help.php" class="flex items-center gap-1.5 text-[13px] font-medium text-gray-700 hover:text-[#8B4513]">
                    <i class="fa-solid fa-headset text-sm"></i>
                    <span class="hidden xl:inline">Help</span>
                </a>

                <?php if ($canAccessDashboard): ?>
                    <a href="<?= e($dashboardHref) ?>" class="flex items-center gap-1.5 text-[13px] font-medium text-gray-700 hover:text-[#8B4513]">
                        <i class="fa-solid fa-gauge-high text-sm"></i>
                        <span class="hidden xl:inline">Dashboard</span>
                    </a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <div class="relative" id="account-menu">
                        <button id="account-btn" type="button"
                                class="flex items-center gap-2 rounded-full focus:outline-none focus:ring-2 focus:ring-[#8B4513]/40 transition">
                            <div class="w-8 h-8 rounded-full bg-[#8B4513] text-white flex items-center justify-center text-xs font-semibold tracking-wide shadow-sm">
                                <?= e($initials) ?>
                            </div>
                        </button>

                        <div id="account-dropdown"
                             class="hidden absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-xl border border-gray-100 z-[130] overflow-hidden">

                            <div class="px-4 py-3.5 border-b border-gray-100 bg-[#faf8f4]">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-full bg-[#8B4513] text-white flex items-center justify-center text-sm font-semibold flex-shrink-0">
                                        <?= e($initials) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 truncate text-sm">
                                            <?= e(trim($firstName . ' ' . $lastName) ?: 'User') ?>
                                        </p>
                                        <p class="text-xs text-gray-500 truncate">
                                            <?= e($email) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="py-1">
                                <a href="<?= e($publicPrefix) ?>orders.php"
                                   class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-gray-700 hover:bg-gray-50 transition">
                                    <i class="fa-solid fa-box-open w-4 text-center text-gray-400 text-sm"></i>
                                    My Orders
                                </a>

                                <?php if ($canAccessDashboard): ?>
                                    <a href="<?= e($dashboardHref) ?>"
                                       class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-gray-700 hover:bg-gray-50 transition">
                                        <i class="fa-solid fa-gauge-high w-4 text-center text-gray-400 text-sm"></i>
                                        Dashboard
                                    </a>
                                <?php endif; ?>

                                <a href="<?= e($publicPrefix) ?>logout.php"
                                   class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-red-600 hover:bg-red-50 transition">
                                    <i class="fa-solid fa-right-from-bracket w-4 text-center text-sm"></i>
                                    Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= e($publicPrefix) ?>signin.php" class="flex items-center gap-1.5 text-[13px] font-medium text-gray-700 hover:text-[#8B4513]">
                        <i class="fa-solid fa-user text-sm"></i>
                        <span class="hidden xl:inline">Sign In</span>
                    </a>
                <?php endif; ?>

                <button type="button" onclick="toggleCart()" class="flex items-center gap-2 relative" aria-label="Open cart">
                    <i class="fa-solid fa-cart-shopping text-lg"></i>
                    <span id="cart-count" class="absolute -top-1.5 -right-1.5 bg-[#8B4513] text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full">0</span>
                </button>

                <button type="button" id="menu-btn" class="lg:hidden text-xl ml-1" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mobile / Tablet Menu -->
    <div id="mobile-overlay" class="hidden fixed inset-0 bg-black/60 z-[100] lg:hidden"></div>
    <div id="mobile-menu" class="hidden fixed top-0 right-0 h-full w-64 bg-white shadow-2xl z-[110] translate-x-full transition-transform duration-300 lg:hidden">
        <div class="p-5">
            <button type="button" id="close-menu" class="text-3xl text-gray-400 hover:text-gray-600 float-right" aria-label="Close menu">×</button>

            <div class="flex flex-col gap-4 mt-14 text-base font-medium">
                <a href="<?= e($homeHref) ?>" class="<?= navClass('home', $activeNav) ?>">Home</a>
                <a href="<?= e($publicPrefix) ?>shopping.php" class="<?= navClass('shop', $activeNav) ?>">Shop</a>
                <a href="<?= e($publicPrefix) ?>aboutus.php" class="<?= navClass('aboutus', $activeNav) ?>">About Us</a>
                <a href="<?= e($publicPrefix) ?>heritage.php" class="<?= navClass('heritage', $activeNav) ?>">Heritage</a>
                <a href="<?= e($publicPrefix) ?>blog.php" class="<?= navClass('blog', $activeNav) ?>">Blog</a>
                <a href="<?= e($publicPrefix) ?>orders.php" class="<?= navClass('orders', $activeNav) ?>">Orders</a>
                <a href="<?= e($publicPrefix) ?>contactus.php" class="<?= navClass('contact', $activeNav) ?>">Contact Us</a>

                <?php if ($isLoggedIn): ?>
                    <div class="pt-4 mt-1 border-t border-gray-100">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-9 h-9 rounded-full bg-[#8B4513] text-white flex items-center justify-center text-xs font-semibold">
                                <?= e($initials) ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900"><?= e(trim($firstName . ' ' . $lastName) ?: 'User') ?></p>
                                <p class="text-xs text-gray-500"><?= e($email) ?></p>
                            </div>
                        </div>

                        <a href="<?= e($publicPrefix) ?>orders.php" class="block py-1.5 text-sm hover:text-[#8B4513]">My Orders</a>

                        <?php if ($canAccessDashboard): ?>
                            <a href="<?= e($dashboardHref) ?>" class="block py-1.5 text-sm hover:text-[#8B4513]">Dashboard</a>
                        <?php endif; ?>

                        <a href="<?= e($publicPrefix) ?>logout.php" class="block py-1.5 text-sm text-red-600 hover:text-red-700">Sign out</a>
                    </div>
                <?php else: ?>
                    <a href="<?= e($publicPrefix) ?>signin.php" class="hover:text-[#8B4513]">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CART OVERLAY -->
    <div id="cart-overlay"
         class="hidden fixed inset-0 bg-black/50 z-[115] transition-opacity duration-300"
         onclick="toggleCart()"></div>

    <!-- CART DRAWER -->
    <div id="cart-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl translate-x-full transition-transform duration-300 z-[120] flex flex-col border-l border-gray-200">
        <div class="p-5 border-b flex justify-between items-center">
            <h2 class="text-lg font-semibold">Shopping Cart</h2>
            <button type="button" onclick="toggleCart()" class="text-2xl leading-none" aria-label="Close cart">×</button>
        </div>
        <div id="cart-items" class="flex-1 p-5 overflow-auto"></div>
        <div class="p-5 border-t">
            <div class="flex justify-between text-base font-medium mb-4">
                <span>Total</span>
                <span id="cart-total">USH 0</span>
            </div>
            <button type="button" onclick="checkout()" class="w-full bg-[#8B4513] text-white py-3.5 rounded-2xl font-medium text-sm">
                Proceed to Checkout
            </button>
        </div>
    </div>

    <script>
        const API_BASE     = <?= json_encode($publicPrefix . 'api/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const ROOT_PREFIX  = <?= json_encode($rootPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const CHECKOUT_URL = <?= json_encode($publicPrefix . 'checkout.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const DEFAULT_IMG  = <?= json_encode($rootPrefix . $defaultLogo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // ========== Account Dropdown ==========
        const accountBtn      = document.getElementById('account-btn');
        const accountDropdown = document.getElementById('account-dropdown');

        if (accountBtn && accountDropdown) {
            accountBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                accountDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!document.getElementById('account-menu')?.contains(e.target)) {
                    accountDropdown.classList.add('hidden');
                }
            });
        }

        // ========== Image URL helper (blocks .. traversal) ==========
        function resolveImageUrl(rawPath) {
            if (!rawPath) return DEFAULT_IMG;
            let path = String(rawPath).trim().replace(/\\/g, '/');
            if (/^https?:\/\//i.test(path) || path.startsWith('//')) return path;
            path = path.replace(/^\.?\/+/, '');
            if (path.includes('..')) return DEFAULT_IMG;
            return ROOT_PREFIX + path;
        }

        // ========== Cart functions ==========
        async function addToCart(variantId, quantity = 1) {
            const qty = Math.max(1, parseInt(quantity, 10) || 1);
            const id  = parseInt(variantId, 10);
            if (!id) return;

            try {
                const res = await fetch(API_BASE + 'add-to-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: id, quantity: qty }),
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('cart-count').textContent = data.cart_count;
                    loadCart();
                    showToast('Added to cart');
                } else {
                    showToast(data.message || 'Could not add to cart');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error');
            }
        }

        async function updateQuantity(variantId, quantity) {
            const id  = parseInt(variantId, 10);
            const qty = parseInt(quantity, 10);
            if (!id || qty < 1) return;

            try {
                const res = await fetch(API_BASE + 'update-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: id, quantity: qty }),
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.success) {
                    loadCart();
                } else {
                    showToast(data.message || 'Could not update quantity');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error');
            }
        }

        async function removeFromCart(variantId) {
            const id = parseInt(variantId, 10);
            if (!id) return;

            try {
                const res = await fetch(API_BASE + 'remove-from-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: id }),
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.success) {
                    loadCart();
                    showToast('Item removed');
                } else {
                    showToast(data.message || 'Could not remove item');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function loadCart() {
            try {
                const res = await fetch(API_BASE + 'get-cart.php', {
                    cache: 'no-store',
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!data.success) return;

                const container = document.getElementById('cart-items');
                const totalEl   = document.getElementById('cart-total');
                const countEl   = document.getElementById('cart-count');

                if (countEl) countEl.textContent = data.count;

                if (!data.items || data.items.length === 0) {
                    container.innerHTML = `
                        <div class="flex flex-col items-center justify-center h-full py-16 text-center text-gray-400">
                            <i class="fa-solid fa-cart-shopping text-5xl mb-5"></i>
                            <p class="text-sm">Your cart is empty.</p>
                        </div>`;
                    totalEl.textContent = 'USH 0';
                    return;
                }

                let html = '';
                data.items.forEach(item => {
                    const rawImg = item.image_url || item.img_url || item.image || item.thumbnail || '';
                    const img = resolveImageUrl(rawImg);
                    const variantId = parseInt(item.variant_id, 10);
                    const qty = parseInt(item.quantity, 10) || 1;

                    const minusBtn = qty <= 1
                        ? `<button type="button" disabled class="w-5 h-5 rounded-full border border-gray-200 flex items-center justify-center text-xs leading-none text-gray-300 cursor-not-allowed">−</button>`
                        : `<button type="button" onclick="updateQuantity(${variantId}, ${qty - 1})"
                                   class="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100 text-xs leading-none">−</button>`;

                    html += `
                        <div class="flex gap-3 mb-6">
                            <img src="${escapeHtml(img)}"
                                 class="w-12 h-12 object-cover rounded-lg flex-shrink-0"
                                 alt=""
                                 onerror="this.onerror=null; this.src='${escapeHtml(DEFAULT_IMG)}'">
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-sm">${escapeHtml(item.product_name || '')}</p>
                                        <p class="text-xs text-gray-500">${escapeHtml(item.variant_title || '')}</p>
                                        <p class="text-xs text-gray-500">USh ${Number(item.price).toLocaleString()}</p>
                                    </div>
                                    <button type="button" onclick="removeFromCart(${variantId})"
                                            class="text-red-500 hover:text-red-700 text-xs ml-2" title="Remove">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>

                                <div class="flex justify-between mt-2.5 items-center">
                                    <div class="flex items-center gap-1.5">
                                        ${minusBtn}
                                        <span class="w-5 text-center font-medium text-xs">${qty}</span>
                                        <button type="button" onclick="updateQuantity(${variantId}, ${qty + 1})"
                                                class="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100 text-xs leading-none">+</button>
                                    </div>
                                    <span class="font-medium text-sm">USh ${Number(item.subtotal).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>`;
                });

                container.innerHTML = html;
                totalEl.textContent = 'USH ' + Number(data.total).toLocaleString();
            } catch (err) {
                console.error('Failed to load cart', err);
            }
        }

        function toggleCart() {
            const drawer  = document.getElementById('cart-drawer');
            const overlay = document.getElementById('cart-overlay');

            if (drawer.classList.contains('translate-x-full')) {
                loadCart();
                overlay.classList.remove('hidden');
                drawer.classList.remove('translate-x-full');
            } else {
                drawer.classList.add('translate-x-full');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        function showToast(msg) {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-5 py-2.5 rounded-full shadow-2xl z-[150] text-sm';
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }

        function checkout() {
            window.location.href = CHECKOUT_URL;
        }

        document.getElementById('menu-btn')?.addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.remove('hidden');
            document.getElementById('mobile-overlay').classList.remove('hidden');
            setTimeout(() => document.getElementById('mobile-menu').classList.remove('translate-x-full'), 10);
        });

        document.getElementById('close-menu')?.addEventListener('click', closeMobileMenu);
        document.getElementById('mobile-overlay')?.addEventListener('click', closeMobileMenu);

        function closeMobileMenu() {
            document.getElementById('mobile-menu').classList.add('translate-x-full');
            setTimeout(() => {
                document.getElementById('mobile-menu').classList.add('hidden');
                document.getElementById('mobile-overlay').classList.add('hidden');
            }, 300);
        }

        document.addEventListener('DOMContentLoaded', loadCart);
    </script>