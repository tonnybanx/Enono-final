<?php
session_start();
require_once '../config/database-connection.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}

// Fetch latest user data
$stmt = $pdo->prepare("
    SELECT id, email, first_name, last_name, phone, role, created_at, last_login_at 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: signin.php');
    exit;
}

$pageTitle = 'My Account';
$activeNav = '';

// Company data for header
$company = [];
try {
    $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));

require_once '../includes/header.php';
?>

<style>
    .account-card {
        background: #141414;
        border: 1px solid #2A2A2A;
        border-radius: 1.25rem;
    }
    .info-label {
        font-size: 0.7rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: 0.25rem;
    }
    .info-value {
        font-size: 0.95rem;
        font-weight: 500;
        color: #ffffff;
    }
</style>

<div class="mx-auto bg-[#0B0B0B] min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-10 md:py-14">

        <!-- Page title -->
        <div class="mb-8">
            <p class="uppercase tracking-[0.2em] text-sm text-[#D4A017] mb-2">ACCOUNT</p>
            <h1 class="text-3xl md:text-4xl font-semibold heading-font text-white">My Account</h1>
            <p class="text-gray-400 text-sm mt-1.5">View and manage your personal information</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">

            <!-- LEFT: Profile card -->
            <div class="lg:col-span-1">
                <div class="account-card p-6 text-center">
                    <!-- Avatar -->
                    <div class="w-20 h-20 mx-auto rounded-full bg-[#1C1C1C] border-2 border-[#D4A017]/40 flex items-center justify-center text-[#E8B923] text-2xl font-semibold tracking-wide mb-4">
                        <?= htmlspecialchars($initials) ?>
                    </div>

                    <h2 class="text-lg font-semibold text-white">
                        <?= htmlspecialchars($fullName ?: 'User') ?>
                    </h2>
                    <p class="text-sm text-gray-400 mt-0.5"><?= htmlspecialchars($user['email']) ?></p>

                    <div class="mt-3">
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-[#E8B923]/10 text-[#E8B923] border border-[#E8B923]/30">
                                <i class="fa-solid fa-shield-halved text-[10px]"></i>
                                Administrator
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-[#1C1C1C] text-gray-300 border border-[#2A2A2A]">
                                Customer
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Quick actions -->
                    <div class="mt-6 pt-5 border-t border-[#2A2A2A] space-y-2">
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="../Dashboard/home.php"
                               class="flex items-center justify-center gap-2 w-full bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] text-sm font-medium py-2.5 rounded-xl transition-colors">
                                <i class="fa-solid fa-gauge-high text-xs"></i>
                                Dashboard
                            </a>
                        <?php endif; ?>

                        <a href="logout.php"
                           class="flex items-center justify-center gap-2 w-full border border-[#2A2A2A] hover:border-[#D4A017] hover:text-[#D4A017] text-gray-300 text-sm font-medium py-2.5 rounded-xl transition-colors">
                            <i class="fa-solid fa-right-from-bracket text-xs"></i>
                            Sign out
                        </a>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Personal information -->
                <div class="account-card p-6 sm:p-7">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-base font-semibold text-white">Personal Information</h3>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-x-8 gap-y-5">
                        <div>
                            <p class="info-label">First name</p>
                            <p class="info-value"><?= htmlspecialchars($user['first_name'] ?: '—') ?></p>
                        </div>
                        <div>
                            <p class="info-label">Last name</p>
                            <p class="info-value"><?= htmlspecialchars($user['last_name'] ?: '—') ?></p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="info-label">Email address</p>
                            <p class="info-value"><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="info-label">Phone number</p>
                            <p class="info-value"><?= htmlspecialchars($user['phone'] ?: 'Not provided') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Account activity -->
                <div class="account-card p-6 sm:p-7">
                    <h3 class="text-base font-semibold text-white mb-6">Account Activity</h3>

                    <div class="grid sm:grid-cols-2 gap-x-8 gap-y-5">
                        <div>
                            <p class="info-label">Member since</p>
                            <p class="info-value">
                                <?= $user['created_at'] ? date('d F Y', strtotime($user['created_at'])) : '—' ?>
                            </p>
                        </div>
                        <div>
                            <p class="info-label">Last login</p>
                            <p class="info-value">
                                <?= $user['last_login_at'] 
                                    ? date('d M Y \a\t H:i', strtotime($user['last_login_at'])) 
                                    : '—' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Security note -->
                <div class="flex gap-3 p-4 rounded-xl bg-[#141414] border border-[#2A2A2A]">
                    <i class="fa-solid fa-lock text-[#D4A017] mt-0.5 text-sm"></i>
                    <div>
                        <p class="text-sm font-medium text-white">Account security</p>
                        <p class="text-xs text-gray-400 mt-0.5 leading-relaxed">
                            Your password is encrypted and never shared. If you notice any unusual activity, please contact support immediately.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>