<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$inPublic   = (bool) preg_match('#/Public(/|$)#i', $scriptPath);

$publicPrefix = $inPublic ? '' : 'Public/';
$rootPrefix   = $inPublic ? '../' : '';
$homeHref     = $inPublic ? '../index.php' : 'index.php';

// Dashboard
$dashboardHref = $rootPrefix . 'Dashboard/home.php';

// Logo
if (!empty($logoUrl)) {
    if (
        str_starts_with($logoUrl, 'http') ||
        str_starts_with($logoUrl, '../') ||
        str_starts_with($logoUrl, '/')
    ) {
        $resolvedLogo = $logoUrl;
    } else {
        $resolvedLogo = $rootPrefix . ltrim($logoUrl, '/');
    }
} else {
    $resolvedLogo = $rootPrefix . 'images/drum_logo.jpg';
}

function navClass(string $page, string $activeNav): string
{
    return $page === $activeNav
        ? 'hover:text-[#8B4513] text-[#8B4513] font-semibold'
        : 'hover:text-[#8B4513]';
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Auth helpers
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && ($_SESSION['role'] ?? '') === 'admin';

$firstName = $_SESSION['first_name'] ?? '';
$lastName  = $_SESSION['last_name'] ?? '';
$email     = $_SESSION['email'] ?? '';
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
if ($initials === '') $initials = 'U';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e($company['company_name'] ?? 'Enono Market') ?></title>
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
    <div class="bg-[#8B4513] text-white text-center py-3 text-sm">
        Understand African Heritage and craft and get each at a 10% discount from the regular price
        <span class="font-mono bg-white/20 px-3 py-1 rounded ml-2"><?= e($company['company_name'] ?? 'Enono') ?></span>
    </div>

    <!-- NAVBAR -->
    <nav class="bg-white sticky top-0 z-50 border-b shadow-md">
        <div class="max-w-7xl  px-4 md:px-6 py-4 flex justify-between items-center gap-4">
            
            <!-- Logo -->
            <a href="<?= e($homeHref) ?>" class="flex items-center gap-2.5 flex-shrink-0">
                <img src="<?= e($resolvedLogo) ?>" class="w-9 h-11 object-cover rounded"
                     alt="<?= e($company['company_name'] ?? 'Enono') ?> Logo">
                <div class="hidden sm:block">
                    <span class="heading-font text-2xl lg:text-3xl font-bold leading-tight"><?= e($company['company_name'] ?? 'Enono') ?></span>
                    <p class="text-[10px] lg:text-xs tracking-widest text-gray-500"><?= e($company['tagline'] ?? 'Market') ?></p>
                </div>
            </a>

            <!-- Desktop Menu -->
            <div class="hidden lg:flex items-center gap-5 xl:gap-7 text-sm font-medium">
                <a href="<?= e($homeHref) ?>" class="<?= navClass('home', $activeNav) ?>">Home</a>
                <a href="<?= e($publicPrefix) ?>shopping.php" class="<?= navClass('shop', $activeNav) ?>">Shop</a>
                <a href="<?= e($publicPrefix) ?>aboutus.php" class="<?= navClass('aboutus', $activeNav) ?>">About</a>
                <a href="<?= e($publicPrefix) ?>blog.php" class="<?= navClass('blog', $activeNav) ?>">Blog</a>
                <a href="<?= e($publicPrefix) ?>heritage.php" class="<?= navClass('heritage', $activeNav) ?>">Heritage</a>
                <a href="<?= e($publicPrefix) ?>orders.php" class="<?= navClass('orders', $activeNav) ?>">Orders</a>
                <a href="<?= e($publicPrefix) ?>contactus.php" class="<?= navClass('contact', $activeNav) ?>">Contact</a>
            </div>

            <!-- Right side icons -->
            <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">

                <!-- Help -->
                <a href="<?= e($publicPrefix) ?>help.php" class="flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-[#8B4513]">
                    <i class="fa-solid fa-headset"></i>
                    <span class="hidden xl:inline">Help</span>
                </a>

                <!-- Dashboard – only for admins -->
                <?php if ($isAdmin): ?>
                    <a href="<?= e($dashboardHref) ?>" class="flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-[#8B4513]">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span class="hidden xl:inline">Dashboard</span>
                    </a>
                <?php endif; ?>

                <!-- ACCOUNT / SIGN IN -->
                <?php if ($isLoggedIn): ?>
                    <div class="relative" id="account-menu">
                        <!-- Avatar button -->
                        <button id="account-btn" type="button"
                                class="flex items-center gap-2 rounded-full focus:outline-none focus:ring-2 focus:ring-[#8B4513]/40 transition">
                            <div class="w-9 h-9 rounded-full bg-[#8B4513] text-white flex items-center justify-center text-sm font-semibold tracking-wide shadow-sm">
                                <?= e($initials) ?>
                            </div>
                        </button>

                        <!-- Dropdown card -->
                        <div id="account-dropdown"
                             class="hidden absolute right-0 mt-3 w-72 bg-white rounded-2xl shadow-xl border border-gray-100 z-[130] overflow-hidden">
                            
                            <!-- User info -->
                            <div class="px-5 py-4 border-b border-gray-100 bg-[#faf8f4]">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-full bg-[#8B4513] text-white flex items-center justify-center text-base font-semibold flex-shrink-0">
                                        <?= e($initials) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 truncate text-[15px]">
                                            <?= e(trim("$firstName $lastName") ?: 'User') ?>
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            <?= e($email) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Menu links -->
                            <div class="py-1.5">
                                <a href="<?= e($publicPrefix) ?>orders.php"
                                   class="flex items-center gap-3 px-5 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                    <i class="fa-solid fa-box-open w-5 text-center text-gray-400"></i>
                                    My Orders
                                </a>

                                <?php if ($isAdmin): ?>
                                    <a href="<?= e($dashboardHref) ?>"
                                       class="flex items-center gap-3 px-5 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
                                        <i class="fa-solid fa-gauge-high w-5 text-center text-gray-400"></i>
                                        Dashboard
                                    </a>
                                <?php endif; ?>

                                <a href="<?= e($publicPrefix) ?>logout.php"
                                   class="flex items-center gap-3 px-5 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
                                    <i class="fa-solid fa-right-from-bracket w-5 text-center"></i>
                                    Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= e($publicPrefix) ?>signin.php" class="flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-[#8B4513]">
                        <i class="fa-solid fa-user"></i>
                        <span class="hidden xl:inline">Sign In</span>
                    </a>
                <?php endif; ?>

                <!-- Cart -->
                <button onclick="toggleCart()" class="flex items-center gap-2 relative">
                    <i class="fa-solid fa-cart-shopping text-xl"></i>
                    <span id="cart-count" class="absolute -top-1.5 -right-1.5 bg-[#8B4513] text-white text-xs w-5 h-5 flex items-center justify-center rounded-full">0</span>
                </button>

                <!-- Hamburger -->
                <button id="menu-btn" class="lg:hidden text-2xl ml-1">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mobile / Tablet Menu -->
    <div id="mobile-overlay" class="hidden fixed inset-0 bg-black/60 z-[100] lg:hidden"></div>
    <div id="mobile-menu" class="hidden fixed top-0 right-0 h-full w-72 bg-white shadow-2xl z-[110] translate-x-full transition-transform duration-300 lg:hidden">
        <div class="p-6">
            <button id="close-menu" class="text-4xl text-gray-400 hover:text-gray-600 float-right">×</button>
            
            <div class="flex flex-col gap-5 mt-16 text-lg font-medium">
                <a href="<?= e($homeHref) ?>" class="<?= navClass('home', $activeNav) ?>">Home</a>
                <a href="<?= e($publicPrefix) ?>shopping.php" class="<?= navClass('shop', $activeNav) ?>">Shop</a>
                <a href="<?= e($publicPrefix) ?>aboutus.php" class="<?= navClass('aboutus', $activeNav) ?>">About Us</a>
                <a href="<?= e($publicPrefix) ?>heritage.php" class="<?= navClass('heritage', $activeNav) ?>">Heritage</a>
                <a href="<?= e($publicPrefix) ?>blog.php" class="<?= navClass('blog', $activeNav) ?>">Blog</a>
                <a href="<?= e($publicPrefix) ?>orders.php" class="<?= navClass('orders', $activeNav) ?>">Orders</a>
                <a href="<?= e($publicPrefix) ?>contactus.php" class="<?= navClass('contact', $activeNav) ?>">Contact Us</a>

                <?php if ($isLoggedIn): ?>
                    <div class="pt-5 mt-2 border-t border-gray-100">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-[#8B4513] text-white flex items-center justify-center text-sm font-semibold">
                                <?= e($initials) ?>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900"><?= e(trim("$firstName $lastName") ?: 'User') ?></p>
                                <p class="text-xs text-gray-500"><?= e($email) ?></p>
                            </div>
                        </div>

                        <a href="<?= e($publicPrefix) ?>orders.php" class="block py-2 text-base hover:text-[#8B4513]">My Orders</a>

                        <?php if ($isAdmin): ?>
                            <a href="<?= e($dashboardHref) ?>" class="block py-2 text-base hover:text-[#8B4513]">Dashboard</a>
                        <?php endif; ?>

                        <a href="<?= e($publicPrefix) ?>logout.php" class="block py-2 text-base text-red-600 hover:text-red-700">Sign out</a>
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
        <div class="p-6 border-b flex justify-between items-center">
            <h2 class="text-2xl font-semibold">Shopping Cart</h2>
            <button onclick="toggleCart()" class="text-3xl leading-none">×</button>
        </div>
        <div id="cart-items" class="flex-1 p-6 overflow-auto"></div>
        <div class="p-6 border-t">
            <div class="flex justify-between text-xl font-medium mb-6">
                <span>Total</span>
                <span id="cart-total">USH 0</span>
            </div>
            <button onclick="checkout()" class="w-full bg-[#8B4513] text-white py-4 rounded-2xl font-medium">
                Proceed to Checkout
            </button>
        </div>
    </div>

    <script>
        const API_BASE     = <?= json_encode($publicPrefix . 'api/') ?>;
        const ROOT_PREFIX  = <?= json_encode($rootPrefix) ?>;
        const CHECKOUT_URL = <?= json_encode($publicPrefix . 'checkout.php') ?>;

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

        // ========== Cart functions ==========
        async function addToCart(variantId, quantity = 1) {
            try {
                const res = await fetch(API_BASE + 'add-to-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: variantId, quantity }),
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
            if (quantity < 1) {
                removeFromCart(variantId);
                return;
            }

            try {
                const res = await fetch(API_BASE + 'update-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: variantId, quantity }),
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
            try {
                const res = await fetch(API_BASE + 'remove-from-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ variant_id: variantId }),
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
                        <div class="flex flex-col items-center justify-center h-full py-20 text-center text-gray-400">
                            <i class="fa-solid fa-cart-shopping text-6xl mb-6"></i>
                            <p>Your cart is empty.</p>
                        </div>`;
                    totalEl.textContent = 'USH 0';
                    return;
                }

                let html = '';
                data.items.forEach(item => {
                    let img = item.image_url || '';
                    if (img && !img.startsWith('http') && !img.startsWith('/') && !img.startsWith('../')) {
                        img = ROOT_PREFIX + img.replace(/^\//, '');
                    }
                    if (!img) {
                        img = ROOT_PREFIX + 'images/drum_logo.jpg';
                    }

                    const variantId = item.variant_id;

                    html += `
                        <div class="flex gap-4 mb-8">
                            <img src="${img}"
                                 class="w-20 h-20 object-cover rounded-xl"
                                 onerror="this.onerror=null; this.src='${ROOT_PREFIX}images/drum_logo.jpg'">
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium">${item.product_name}</p>
                                        <p class="text-sm text-gray-500">${item.variant_title || ''}</p>
                                        <p class="text-sm text-gray-500">USh ${Number(item.price).toLocaleString()}</p>
                                    </div>
                                    <button onclick="removeFromCart(${variantId})"
                                            class="text-red-500 hover:text-red-700 text-sm ml-2" title="Remove">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>

                                <div class="flex justify-between mt-3 items-center">
                                    <div class="flex items-center gap-2">
                                        <button onclick="updateQuantity(${variantId}, ${item.quantity - 1})"
                                                class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100 text-lg leading-none">−</button>
                                        <span class="w-8 text-center font-medium">${item.quantity}</span>
                                        <button onclick="updateQuantity(${variantId}, ${item.quantity + 1})"
                                                class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100 text-lg leading-none">+</button>
                                    </div>
                                    <span class="font-medium">USh ${Number(item.subtotal).toLocaleString()}</span>
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
            toast.className = 'fixed bottom-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-full shadow-2xl z-[150]';
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }

        function checkout() {
            window.location.href = CHECKOUT_URL;
        }

        // Mobile menu
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