<?php
// Dashboard/includes/topbar.php
?>

<!-- Top Bar -->
<div class="h-[70px] bg-white border-b border-gray-100 flex items-center px-6 shadow-sm z-40">
    <button id="toggleSidebar" class="lg:hidden text-2xl text-gray-600 hover:text-gray-900 mr-4">
        <i class="fa fa-bars"></i>
    </button>

    <div class="flex-1 max-w-md">
        <div class="relative">
            <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" 
                   placeholder="Search by name or SKU" 
                   class="w-full bg-gray-100 border border-transparent focus:border-gray-300 rounded-2xl py-3 pl-12 pr-5 focus:outline-none text-sm">
        </div>
    </div>

    <div class="flex items-center gap-6 ml-auto">
        <button class="text-gray-600 hover:text-gray-900">
            <i class="fa fa-bell text-xl"></i>
        </button>

        <div class="flex items-center gap-3">
            <div class="text-right">
                <div class="text-sm font-semibold">Sarah</div>
                <div class="text-xs text-gray-500">Admin</div>
            </div>
            <img src="https://picsum.photos/id/64/128/128" 
                 alt="Profile" 
                 class="w-9 h-9 rounded-2xl object-cover">
        </div>
    </div>
</div>