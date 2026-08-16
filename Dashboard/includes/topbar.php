<?php
// Dashboard/includes/topbar.php

// 1. Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Protect the dashboard – only logged-in admin or staff users can access.
//    Non-qualifying sessions are sent back to the storefront home, never to a
//    login/signin page.
$allowedDashboardRoles = ['admin', 'staff'];
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || !in_array($_SESSION['role'], $allowedDashboardRoles, true)) {
    header('Location: ../../index.php');
    exit;
}

// 3. Get logged-in user details from session (set these on login)
$adminFirstName = trim($_SESSION['first_name'] ?? '');
$adminLastName  = trim($_SESSION['last_name'] ?? '');
$adminName      = trim($adminFirstName . ' ' . $adminLastName);
$adminName      = $adminName !== '' ? $adminName : ($_SESSION['email'] ?? 'User');
$adminEmail     = $_SESSION['email'] ?? '';
$adminRole      = ucfirst($_SESSION['role'] ?? '');

// 4. Build initials for the avatar (no profile image stored in DB)
if ($adminFirstName !== '' || $adminLastName !== '') {
    $initials = strtoupper(substr($adminFirstName, 0, 1) . substr($adminLastName, 0, 1));
} else {
    // Fall back to the first two letters of whatever name/email we have
    $fallback = preg_replace('/[^A-Za-z]/', '', $adminName);
    $initials = strtoupper(substr($fallback, 0, 2));
}
if ($initials === '') {
    $initials = 'U';
}

// 5. Count new / unread messages
$newMessagesCount = 0;
try {
    // $pdo should already be available (included before topbar)
    if (isset($pdo)) {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
        $newMessagesCount = (int) $countStmt->fetchColumn();
    }
} catch (Exception $e) {
    // silently fail – don’t break the topbar
    $newMessagesCount = 0;
}
?>

<!-- Top Bar -->
<div class="h-[70px] bg-white border-b border-gray-100 flex items-center px-6 shadow-sm z-40 relative">
    
    <!-- Mobile sidebar toggle -->
    <button id="toggleSidebar" class="lg:hidden text-2xl text-gray-600 hover:text-gray-900 mr-4">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Search -->
    <div class="flex-1 max-w-md">
        <div class="relative">
            <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" 
                   placeholder="Search by name or SKU" 
                   class="w-full bg-gray-100 border border-transparent focus:border-gray-300 rounded-2xl py-3 pl-12 pr-5 focus:outline-none text-sm">
        </div>
    </div>

    <!-- Right side -->
    <div class="flex items-center gap-6 ml-auto">

        <!-- Messages / Notifications bell -->
        <a href="messages.php" 
           class="relative text-gray-600 hover:text-gray-900 transition"
           title="Messages">
            <i class="fa fa-bell text-xl"></i>

            <?php if ($newMessagesCount > 0): ?>
                <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] font-bold 
                             min-w-[18px] h-[18px] rounded-full flex items-center justify-center px-1">
                    <?= $newMessagesCount > 99 ? '99+' : $newMessagesCount ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Logged-in user -->
        <div class="relative">
            <button id="accountTrigger" onclick="toggleAccountCard(event)"
                    class="flex items-center focus:outline-none">
                <div class="w-9 h-9 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-sm font-semibold border border-gray-200 select-none">
                    <?= htmlspecialchars($initials) ?>
                </div>
            </button>

            <!-- Account card popover -->
            <div id="accountCard"
                 class="hidden absolute right-0 top-[calc(100%+10px)] w-72 bg-white border border-gray-100 rounded-2xl shadow-lg z-50 overflow-hidden">
                <div class="p-5 flex items-center gap-3 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-base font-semibold select-none flex-shrink-0">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($adminName) ?></div>
                        <?php if ($adminEmail !== ''): ?>
                            <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($adminEmail) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Role</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?= $_SESSION['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                            <?= htmlspecialchars($adminRole) ?>
                        </span>
                    </div>
                </div>
                <div class="border-t border-gray-100 p-2">
                    <a href="account.php" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-50 font-medium">
                        <i class="fa fa-user"></i> My account
                    </a>
                    <a href="logout.php" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm text-red-600 hover:bg-red-50 font-medium">
                        <i class="fa fa-sign-out-alt"></i> Log out
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleAccountCard(e) {
        e.stopPropagation();
        document.getElementById('accountCard').classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        const card = document.getElementById('accountCard');
        const trigger = document.getElementById('accountTrigger');
        if (!card.contains(e.target) && !trigger.contains(e.target)) {
            card.classList.add('hidden');
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') document.getElementById('accountCard').classList.add('hidden');
    });
</script>