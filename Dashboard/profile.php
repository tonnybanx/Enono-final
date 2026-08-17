<?php
// Dashboard/profile.php

require_once __DIR__ . '/../config/database-connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access control is handled by includes/topbar.php
// (only admin and staff can reach this page)

$currentPage = 'profile';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$success = $error = '';

// Fetch current user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Safety fallback – should not happen if topbar is working
    header('Location: ../../Public/signin.php');
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if ($firstName === '' || $email === '') {
        $error = 'First name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $userId]);
            if ($check->fetch()) {
                $error = 'Email is already used by another account.';
            } else {
                if ($password !== '') {
                    if ($password !== $password2) {
                        $error = 'Passwords do not match.';
                    } elseif (strlen($password) < 6) {
                        $error = 'Password must be at least 6 characters.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$firstName, $lastName, $email, $phone, $hash, $userId]);
                    }
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$firstName, $lastName, $email, $phone, $userId]);
                }

                if (!$error) {
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name']  = $lastName;
                    $_SESSION['email']      = $email;

                    $success = 'Profile updated successfully.';

                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> – Enono Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { overflow: hidden; }
        .main-scroll {
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        .main-scroll::-webkit-scrollbar { width: 6px; }
        .main-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

<div class="flex h-screen overflow-hidden">

    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">

        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <!-- Scrollable main content -->
        <main class="main-scroll p-6">

            <div class="w-full max-w-7xl mx-auto">

                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">My Profile</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage your account information</p>
                </div>

                <?php if ($success): ?>
                    <div class="mb-5 p-4 bg-emerald-50 border border-emerald-100 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="mb-5 p-4 bg-red-50 border border-red-100 text-red-800 rounded-xl text-sm flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8">

                    <!-- Profile header -->
                    <div class="flex items-center gap-5 mb-8 pb-6 border-b border-gray-100">
                        <div class="w-20 h-20 rounded-2xl bg-[#8B4513]/10 flex items-center justify-center text-[#8B4513] text-3xl font-semibold flex-shrink-0">
                            <?= strtoupper(substr($user['first_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-semibold text-xl text-gray-900">
                                <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($user['email']) ?></p>
                            <p class="text-xs text-gray-400 mt-1">
                                Role: <span class="font-medium text-gray-600"><?= ucfirst($user['role']) ?></span>
                            </p>
                        </div>
                    </div>

                    <!-- Form -->
                    <form method="POST" class="space-y-6">

                        <div class="grid sm:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                    First Name
                                </label>
                                <input type="text" name="first_name" required
                                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#8B4513] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                    Last Name
                                </label>
                                <input type="text" name="last_name"
                                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#8B4513] transition">
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                    Email
                                </label>
                                <input type="email" name="email" required
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#8B4513] transition">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                    Phone
                                </label>
                                <input type="text" name="phone"
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#8B4513] transition">
                            </div>
                        </div>

                        <!-- Password section -->
                        <div class="border-t border-gray-100 pt-6">
                            <p class="text-sm font-medium text-gray-700 mb-4">
                                Change Password
                                <span class="text-gray-400 font-normal">(leave blank to keep current)</span>
                            </p>

                            <div class="grid sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                        New Password
                                    </label>
                                    <input type="password" name="password"
                                           class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#8B4513] transition"
                                           placeholder="••••••••">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                                        Confirm Password
                                    </label>
                                    <input type="password" name="password_confirm"
                                           class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#8B4513] transition"
                                           placeholder="••••••••">
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" name="update_profile"
                                    class="bg-[#8B4513] hover:bg-[#6B3410] text-white px-8 py-3 rounded-xl text-sm font-medium transition">
                                Save Changes
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

</body>
</html>