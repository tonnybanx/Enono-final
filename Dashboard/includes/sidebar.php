<?php
// Dashboard/includes/sidebar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$sidebarRole = $_SESSION['role'] ?? '';

// Not logged in → sign in
if (empty($_SESSION['user_id'])) {
    header('Location: ../Public/signin.php');
    exit;
}

// Customer or any non admin/staff → public index
if (!in_array($sidebarRole, ['admin', 'staff'], true)) {
    header('Location: ../index.php');
    exit;
}


$currentPage = $currentPage ?? '';
$isAdminUser = $sidebarRole === 'admin';
?>

<!-- Overlay (mobile only) -->
<div id="sidebarOverlay" 
     class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"
     onclick="closeSidebar()"></div>

<!-- Sidebar -->
<div id="sidebar" 
     class="sidebar w-[260px] h-full bg-white border-r border-gray-100 flex flex-col 
            fixed top-0 left-0 z-50 
            transform -translate-x-full lg:translate-x-0 lg:relative
            transition-transform duration-300 ease-in-out">

    <!-- Header -->
    <div class="w-full h-[70px] border-b border-gray-100 flex items-center justify-between px-6 bg-[#faf5ef]">
        <div>
            <div class="heading-font text-2xl font-bold tracking-tight text-[#8B4513]">Enono</div>
            <div class="text-[10px] font-medium tracking-widest text-amber-800/70 -mt-1">MARKET</div>
        </div>

        <!-- Close button (mobile only) -->
        <button onclick="closeSidebar()" class="lg:hidden text-gray-400 hover:text-gray-700 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <!-- Navigation -->
    <div class="body-font text-[13px] flex flex-col gap-0.5 mt-4 px-3 flex-1 overflow-y-auto">

        <!-- Main -->
        <a href="home.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'home' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-home w-5 text-center"></i><span>Dashboard</span>
        </a>

        <a href="products.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'products' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-cube w-5 text-center"></i><span>Products</span>
        </a>

        <a href="add-product.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'add-product' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-plus-square w-5 text-center"></i><span>Add Product</span>
        </a>

        <a href="order.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'orders' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-shopping-cart w-5 text-center"></i><span>Orders</span>
        </a>

        <a href="customers.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'customers' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-users w-5 text-center"></i><span>Customers</span>
        </a>

        <a href="heritage.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'heritage' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-dove w-5 text-center"></i><span>Heritage & Spirits</span>
        </a>

        <!-- Divider -->
        <div class="border-t border-gray-100 my-2 mx-2"></div>

        <!-- Communication & Support -->
        <a href="messages.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'messages' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-envelope w-5 text-center"></i><span>Messages</span>
        </a>

        <a href="support.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'support' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-circle-question w-5 text-center"></i><span>User Support</span>
        </a>

        <!-- Divider -->
        <div class="border-t border-gray-100 my-2 mx-2"></div>

        <!-- Account & Settings -->
        <?php if ($isAdminUser): ?>
            <a href="users.php" 
               class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
               <?= $currentPage === 'users' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
                <i class="fa fa-user-gear w-5 text-center"></i><span>Users</span>
            </a>
        <?php endif; ?>

        <a href="profile.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'profile' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-user w-5 text-center"></i><span>My Profile</span>
        </a>

        <a href="about.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl transition-colors
           <?= $currentPage === 'about' ? 'bg-[#8B4513] text-white font-medium' : 'text-gray-600 hover:bg-[#faf5ef] hover:text-[#8B4513]' ?>">
            <i class="fa fa-building w-5 text-center"></i><span>About Us</span>
        </a>

        <!-- Divider -->
        <div class="border-t border-gray-100 my-2 mx-2"></div>

        <!-- Back to public storefront -->
        <a href="../index.php" 
           class="nav-item flex items-center gap-3 px-3.5 py-2 rounded-xl text-[#8B4513] hover:bg-[#faf5ef] font-medium transition-colors mb-3">
            <i class="fa fa-store w-5 text-center"></i><span>View Storefront</span>
        </a>

    </div>
</div>

<style>
    .heading-font { font-family: 'Playfair Display', serif; }
    .body-font { font-family: 'Inter', system-ui, sans-serif; }
</style>

<script>
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleSidebar');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
    }
});
</script>