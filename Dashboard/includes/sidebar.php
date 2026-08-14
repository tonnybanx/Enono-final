<?php
// Dashboard/includes/sidebar.php

$currentPage = $currentPage ?? '';
?>

<!-- Sidebar -->
<div id="sidebar" class="sidebar w-[280px] h-full bg-[#0f172a] shadow-xl flex flex-col fixed lg:relative z-50">
    <div class="w-full h-[70px] border-b border-gray-700 flex items-center pl-6 text-white">
        <div>
            <div class="text-3xl font-bold tracking-tight">Enono</div>
            <div class="text-xs font-light text-yellow-400 -mt-1">MARKET</div>
        </div>
    </div>

    <div class="text-white text-sm flex flex-col gap-1 mt-8 px-3 flex-1">

        <a href="home.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl <?= $currentPage === 'home' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
            <i class="fa fa-home w-5"></i><span>Dashboard</span>
        </a>

        <a href="products.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl <?= $currentPage === 'products' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
            <i class="fa fa-cube w-5"></i><span>Products</span>
        </a>

        <a href="add-product.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl <?= $currentPage === 'add-product' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
            <i class="fa fa-plus-square w-5"></i><span>Add Product</span>
        </a>

        <a href="order.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl <?= $currentPage === 'orders' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
            <i class="fa fa-shopping-cart w-5"></i><span>Orders</span>
        </a>

        <a href="customers.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl <?= $currentPage === 'customers' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
            <i class="fa fa-users w-5"></i><span>Customers</span>
        </a>

        <a href="heritage.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl <?= $currentPage === 'heritage' ? 'bg-gray-700' : 'hover:bg-gray-700' ?>">
            <i class="fa fa-dove w-5"></i><span>Heritage & Spirits</span>
        </a>

        <!-- Divider -->
        <div class="border-t border-gray-700 my-4 mx-2"></div>

        <!-- Back to public storefront -->
        <a href="../index.php" 
           class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-700 text-yellow-400">
            <i class="fa fa-store w-5"></i><span>View Storefront</span>
        </a>

    </div>
</div>