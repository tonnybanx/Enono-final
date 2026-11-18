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
        ? 'text-[#E8A92B] font-semibold border-b-2 border-[#E8A92B] pb-1'
        : 'text-gray-100 border-b-2 border-transparent pb-1 hover:text-[#E8A92B]';
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
$tagline     = (string)($company['tagline'] ?? 'Obuwanga Nennono Kuntiko');

// Company name on two lines in the header (first word / rest)
$nameParts = explode(' ', trim($companyName), 2);
$nameLine1 = $nameParts[0];
$nameLine2 = $nameParts[1] ?? '';

// Keep the search term in the box after searching
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="color-scheme" content="dark">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' - ' . $companyName : $companyName) ?></title>

    <!-- Favicon from database (with default fallback) -->
    <link rel="icon" type="<?= e($faviconType) ?>" href="<?= e($resolvedFavicon) ?>">
    <link rel="shortcut icon" type="<?= e($faviconType) ?>" href="<?= e($resolvedFavicon) ?>">
    <link rel="apple-touch-icon" href="<?= e($resolvedFavicon) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* Theme colours (black + gold) */
        :root {
            --bg:        #050505;
            --surface:   #0D0D0D;
            --surface-2: #141008;
            --gold:      #E8A92B;
            --gold-soft: #F2B84B;
            --line:      rgba(232, 169, 43, 0.30);
        }

        .heading-font { font-family: 'Playfair Display', serif; }
        .body-font { font-family: 'Inter', system-ui, sans-serif; }
        .story-image { border-radius: 0; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .story-image:hover { transform: scale(1.05); }
        @media (min-width: 768px) { .story-image { border-radius: 10px; } }
        .about-grid { align-items: start; }
    </style>
</head>
<body class="body-font bg-[#050505] text-gray-100 overflow-x-hidden">

    <!-- HEADER -->
    <header class="bg-black sticky top-0 z-50 border-b border-[#E8A92B]/30 shadow-md shadow-black/60">
        <div class="max-w-7xl mx-auto px-4 md:px-6 pt-3 pb-2">

            <!-- Row 1: logo | search | actions -->
            <div class="flex flex-wrap md:flex-nowrap items-center justify-between gap-x-4 gap-y-3 md:gap-x-8">

                <!-- Logo -->
                <a href="<?= e($homeHref) ?>" class="flex items-center gap-3 flex-shrink-0">
                    <img src="<?= e($resolvedLogo) ?>"
                         class="w-10 h-12 md:w-12 md:h-14 object-cover rounded"
                         alt="<?= e($companyName) ?> Logo"
                         onerror="this.onerror=null;this.src='<?= e($rootPrefix . $defaultLogo) ?>';">
                    <div class="hidden sm:block">
                        <span class="heading-font block text-xl lg:text-2xl font-bold leading-[1.05] text-[#F2B84B]">
                            <?= e($nameLine1) ?><?php if ($nameLine2 !== ''): ?><br><?= e($nameLine2) ?><?php endif; ?>
                        </span>
                        <p class="heading-font mt-1 text-[11px] italic text-white"><?= e($tagline) ?></p>
                    </div>
                </a>

                <!-- Search bar (full width row on mobile, centred pill on desktop) -->
                <form action="<?= e($publicPrefix) ?>shopping.php" method="get" role="search"
                      class="order-last md:order-none w-full md:w-auto md:flex-1 md:max-w-xl">
                    <div class="relative">
                        <input type="search" name="q" value="<?= e($searchQuery) ?>"
                               placeholder="Search for products, communities, or culture..."
                               aria-label="Search"
                               class="w-full bg-black border border-white/40 focus:border-[#E8A92B] rounded-full pl-5 pr-12 py-2.5 text-xs text-white placeholder-gray-300 focus:outline-none transition">
                        <button type="submit" aria-label="Search"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-white hover:text-[#E8A92B]">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </button>
                    </div>
                </form>

                <!-- Right side icons -->
                <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0 text-gray-100">

                    <a href="<?= e($publicPrefix) ?>help.php" class="flex items-center gap-1.5 text-[13px] font-medium text-gray-100 hover:text-[#E8A92B]">
                        <i class="fa-solid fa-headset text-sm"></i>
                        <span class="hidden md:inline">Help</span>
                    </a>

                    <?php if ($canAccessDashboard): ?>
                        <a href="<?= e($dashboardHref) ?>" class="flex items-center gap-1.5 text-[13px] font-medium text-gray-100 hover:text-[#E8A92B]">
                            <i class="fa-solid fa-gauge-high text-sm"></i>
                            <span class="hidden md:inline">Dashboard</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <div class="relative" id="account-menu">
                            <button id="account-btn" type="button"
                                    class="flex items-center gap-2 rounded-full focus:outline-none focus:ring-2 focus:ring-[#E8A92B]/50 transition">
                                <div class="w-8 h-8 rounded-full bg-[#E8A92B] text-black flex items-center justify-center text-xs font-semibold tracking-wide shadow-sm">
                                    <?= e($initials) ?>
                                </div>
                            </button>

                            <div id="account-dropdown"
                                 class="hidden absolute right-0 mt-3 w-64 bg-[#0D0D0D] rounded-2xl shadow-xl shadow-black/70 border border-[#E8A92B]/30 z-[130] overflow-hidden">

                                <div class="px-4 py-3.5 border-b border-[#E8A92B]/20 bg-[#141008]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-full bg-[#E8A92B] text-black flex items-center justify-center text-sm font-semibold flex-shrink-0">
                                            <?= e($initials) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-white truncate text-sm">
                                                <?= e(trim($firstName . ' ' . $lastName) ?: 'User') ?>
                                            </p>
                                            <p class="text-xs text-gray-400 truncate">
                                                <?= e($email) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="py-1">
                                    <a href="<?= e($publicPrefix) ?>orders.php"
                                       class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-gray-200 hover:bg-white/5 hover:text-[#E8A92B] transition">
                                        <i class="fa-solid fa-box-open w-4 text-center text-[#E8A92B] text-sm"></i>
                                        My Orders
                                    </a>

                                    <?php if ($canAccessDashboard): ?>
                                        <a href="<?= e($dashboardHref) ?>"
                                           class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-gray-200 hover:bg-white/5 hover:text-[#E8A92B] transition">
                                            <i class="fa-solid fa-gauge-high w-4 text-center text-[#E8A92B] text-sm"></i>
                                            Dashboard
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?= e($publicPrefix) ?>logout.php"
                                       class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-red-400 hover:bg-red-500/10 transition">
                                        <i class="fa-solid fa-right-from-bracket w-4 text-center text-sm"></i>
                                        Sign out
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?= e($publicPrefix) ?>signin.php" class="flex items-center gap-1.5 text-[13px] font-medium text-gray-100 hover:text-[#E8A92B]">
                            <i class="fa-regular fa-user text-sm"></i>
                            <span class="hidden md:inline">Sign In</span>
                        </a>
                    <?php endif; ?>

                    <button type="button" onclick="toggleCart()" class="flex items-center relative text-gray-100 hover:text-[#E8A92B]" aria-label="Open cart">
                        <i class="fa-solid fa-cart-shopping text-lg"></i>
                        <span id="cart-count" class="absolute -top-2 -right-2 bg-[#E8A92B] text-black font-semibold text-[10px] w-4 h-4 flex items-center justify-center rounded-full">0</span>
                    </button>

                    <button type="button" id="menu-btn" class="lg:hidden text-xl ml-1 text-gray-100 hover:text-[#E8A92B]" aria-label="Open menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Row 2: main navigation -->
            <nav class="hidden lg:flex items-center justify-center gap-6 xl:gap-8 mt-3 text-[13px] font-medium" aria-label="Main">
                <a href="<?= e($homeHref) ?>" class="<?= navClass('home', $activeNav) ?>">Home</a>
                <a href="<?= e($publicPrefix) ?>shopping.php" class="<?= navClass('shop', $activeNav) ?>">Shop</a>
                <a href="<?= e($publicPrefix) ?>aboutus.php" class="<?= navClass('aboutus', $activeNav) ?>">About</a>
                <a href="<?= e($publicPrefix) ?>blog.php" class="<?= navClass('blog', $activeNav) ?>">Blog</a>
                <a href="<?= e($publicPrefix) ?>heritage.php" class="<?= navClass('heritage', $activeNav) ?>">Heritage</a>
                <a href="<?= e($publicPrefix) ?>orders.php" class="<?= navClass('orders', $activeNav) ?>">Orders</a>
                <a href="<?= e($publicPrefix) ?>contactus.php" class="<?= navClass('contact', $activeNav) ?>">Contact</a>
            </nav>
        </div>
    </header>

    <!-- Mobile / Tablet Menu -->
    <div id="mobile-overlay" class="hidden fixed inset-0 bg-black/70 z-[100] lg:hidden"></div>
    <div id="mobile-menu" class="hidden fixed top-0 right-0 h-full w-64 bg-[#0D0D0D] border-l border-[#E8A92B]/30 shadow-2xl z-[110] translate-x-full transition-transform duration-300 lg:hidden">
        <div class="p-5">
            <button type="button" id="close-menu" class="text-3xl text-gray-400 hover:text-[#E8A92B] float-right" aria-label="Close menu">×</button>

            <div class="flex flex-col gap-4 mt-14 text-base font-medium">
                <a href="<?= e($homeHref) ?>" class="<?= navClass('home', $activeNav) ?>">Home</a>
                <a href="<?= e($publicPrefix) ?>shopping.php" class="<?= navClass('shop', $activeNav) ?>">Shop</a>
                <a href="<?= e($publicPrefix) ?>aboutus.php" class="<?= navClass('aboutus', $activeNav) ?>">About Us</a>
                <a href="<?= e($publicPrefix) ?>heritage.php" class="<?= navClass('heritage', $activeNav) ?>">Heritage</a>
                <a href="<?= e($publicPrefix) ?>blog.php" class="<?= navClass('blog', $activeNav) ?>">Blog</a>
                <a href="<?= e($publicPrefix) ?>orders.php" class="<?= navClass('orders', $activeNav) ?>">Orders</a>
                <a href="<?= e($publicPrefix) ?>contactus.php" class="<?= navClass('contact', $activeNav) ?>">Contact Us</a>

                <?php if ($isLoggedIn): ?>
                    <div class="pt-4 mt-1 border-t border-[#E8A92B]/20">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-9 h-9 rounded-full bg-[#E8A92B] text-black flex items-center justify-center text-xs font-semibold">
                                <?= e($initials) ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white"><?= e(trim($firstName . ' ' . $lastName) ?: 'User') ?></p>
                                <p class="text-xs text-gray-400"><?= e($email) ?></p>
                            </div>
                        </div>

                        <a href="<?= e($publicPrefix) ?>orders.php" class="block py-1.5 text-sm text-gray-200 hover:text-[#E8A92B]">My Orders</a>

                        <?php if ($canAccessDashboard): ?>
                            <a href="<?= e($dashboardHref) ?>" class="block py-1.5 text-sm text-gray-200 hover:text-[#E8A92B]">Dashboard</a>
                        <?php endif; ?>

                        <a href="<?= e($publicPrefix) ?>logout.php" class="block py-1.5 text-sm text-red-400 hover:text-red-300">Sign out</a>
                    </div>
                <?php else: ?>
                    <a href="<?= e($publicPrefix) ?>signin.php" class="text-gray-100 hover:text-[#E8A92B]">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CART OVERLAY -->
    <div id="cart-overlay"
         class="hidden fixed inset-0 bg-black/70 z-[115] transition-opacity duration-300"
         onclick="toggleCart()"></div>

    <!-- CART DRAWER -->
    <div id="cart-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-[#0D0D0D] text-gray-100 shadow-2xl translate-x-full transition-transform duration-300 z-[120] flex flex-col border-l border-[#E8A92B]/30">
        <div class="p-5 border-b border-[#E8A92B]/20 flex justify-between items-center">
            <h2 class="heading-font text-lg font-semibold text-[#F2B84B]">Shopping Cart</h2>
            <button type="button" onclick="toggleCart()" class="text-2xl leading-none text-gray-300 hover:text-[#E8A92B]" aria-label="Close cart">×</button>
        </div>
        <div id="cart-items" class="flex-1 p-5 overflow-auto"></div>
        <div class="p-5 border-t border-[#E8A92B]/20">
            <div class="flex justify-between text-base font-medium mb-4">
                <span>Total</span>
                <span id="cart-total" class="text-[#F2B84B]">USH 0</span>
            </div>
            <button type="button" onclick="checkout()" class="w-full bg-[#E8A92B] hover:bg-[#F2B84B] text-black py-3.5 rounded-2xl font-semibold text-sm transition">
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
                        <div class="flex flex-col items-center justify-center h-full py-16 text-center text-gray-500">
                            <i class="fa-solid fa-cart-shopping text-5xl mb-5 text-[#E8A92B]/60"></i>
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
                        ? `<button type="button" disabled class="w-5 h-5 rounded-full border border-gray-700 flex items-center justify-center text-xs leading-none text-gray-600 cursor-not-allowed">−</button>`
                        : `<button type="button" onclick="updateQuantity(${variantId}, ${qty - 1})"
                                   class="w-5 h-5 rounded-full border border-[#E8A92B]/60 text-[#E8A92B] flex items-center justify-center hover:bg-[#E8A92B]/10 text-xs leading-none">−</button>`;

                    html += `
                        <div class="flex gap-3 mb-6">
                            <img src="${escapeHtml(img)}"
                                 class="w-12 h-12 object-cover rounded-lg flex-shrink-0 border border-[#E8A92B]/20"
                                 alt=""
                                 onerror="this.onerror=null; this.src='${escapeHtml(DEFAULT_IMG)}'">
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-sm text-white">${escapeHtml(item.product_name || '')}</p>
                                        <p class="text-xs text-gray-400">${escapeHtml(item.variant_title || '')}</p>
                                        <p class="text-xs text-gray-400">USh ${Number(item.price).toLocaleString()}</p>
                                    </div>
                                    <button type="button" onclick="removeFromCart(${variantId})"
                                            class="text-red-400 hover:text-red-300 text-xs ml-2" title="Remove">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>

                                <div class="flex justify-between mt-2.5 items-center">
                                    <div class="flex items-center gap-1.5">
                                        ${minusBtn}
                                        <span class="w-5 text-center font-medium text-xs text-white">${qty}</span>
                                        <button type="button" onclick="updateQuantity(${variantId}, ${qty + 1})"
                                                class="w-5 h-5 rounded-full border border-[#E8A92B]/60 text-[#E8A92B] flex items-center justify-center hover:bg-[#E8A92B]/10 text-xs leading-none">+</button>
                                    </div>
                                    <span class="font-medium text-sm text-[#F2B84B]">USh ${Number(item.subtotal).toLocaleString()}</span>
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
            toast.className = 'fixed bottom-8 left-1/2 -translate-x-1/2 bg-[#E8A92B] text-black font-medium px-5 py-2.5 rounded-full shadow-2xl z-[150] text-sm';
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
</document_content>