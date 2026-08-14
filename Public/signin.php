<?php
session_start();
require_once '../config/database-connection.php';

// Already logged in?
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, email, password_hash, first_name, last_name, role, email_verified, is_active
            FROM users 
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Invalid email or password.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['is_active'] == 0) {
            $error = 'Your account has been deactivated. Please contact support.';
        } elseif ($user['email_verified'] == 0) {
            $error = 'Please verify your email address before signing in. Check your inbox for the verification link.';
        } else {
            // Success
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['role']       = $user['role'];

            // Update last login
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
                ->execute([$user['id']]);

            // Redirect
            if ($user['role'] === 'admin') {
                header('Location: ../Dashboard/home.php');
            } else {
                header('Location: ../index.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - enono MARKET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap');
        .heading-font { font-family: 'Playfair Display', sans-serif; }
    </style>
</head>
<body class="bg-white min-h-screen flex items-center justify-center py-12">
    <div class="max-w-md w-full px-6">
        
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center gap-3">
                <img src="../images/drum_logo.jpg" alt="" class="h-14 w-12">
                <div class="flex flex-col">
                    <div class="heading-font text-5xl font-bold tracking-tighter text-black">Enono</div>
                    <div class="pl-2 text-sm tracking-widest text-[#8B4513] -mt-1">MARKET</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border border-gray-100 p-8">
            <h1 class="text-2xl font-semibold mb-6">Sign in</h1>

            <?php if ($error): ?>
                <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['verified']) && $_GET['verified'] == 1): ?>
                <div class="mb-5 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                    Email verified successfully! You can now sign in.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Enter your email address" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-amber-400">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" required
                           placeholder="Enter your password" 
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-amber-400">
                </div>

                <button type="submit" 
                        class="w-full bg-[#8B5000] hover:bg-[#8B4513] text-white font-semibold py-3.5 rounded-xl text-lg transition-colors">
                    Sign In
                </button>
            </form>

            <div class="text-center mt-6">
                <a href="forgot-password.php" class="text-amber-600 hover:underline text-sm">Forgot your password?</a>
            </div>

            <div class="mt-8 text-center border-t pt-6">
                <p class="text-sm">
                    New to enono MARKET? 
                    <a href="signup.php" class="text-amber-600 hover:underline font-medium">Create account</a>
                </p>
            </div>
        </div>

        <div class="text-center mt-10 text-xs text-gray-500">
            <div class="flex justify-center gap-6 mb-4">
                <a href="#" class="hover:text-gray-700">Conditions of Use</a>
                <a href="#" class="hover:text-gray-700">Privacy Notice</a>
                <a href="help.php" class="hover:text-gray-700">Help</a>
            </div>
            <p>© 2026 enono MARKET. All Rights Reserved.</p>
        </div>
    </div>
</body>
</html>