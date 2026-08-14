<?php
session_start();

set_exception_handler(function ($e) {
    error_log($e->getMessage());
    die('<div style="font-family:sans-serif;max-width:500px;margin:80px auto;padding:30px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:12px;">
            <h2>Something went wrong</h2>
            <p>We encountered an unexpected error. Please try again later.</p>
         </div>');
});

require_once '../config/database-connection.php';

if (!isset($_SESSION['verified_email'])) {
    header('Location: signup.php');
    exit;
}

$email   = $_SESSION['verified_email'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (empty($full_name) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $name_parts = explode(' ', $full_name, 2);
            $first_name = $name_parts[0];
            $last_name  = $name_parts[1] ?? '';

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (email, password_hash, first_name, last_name, role, email_verified, is_active)
                VALUES (?, ?, ?, ?, 'customer', 1, 1)
            ");
            $stmt->execute([$email, $password_hash, $first_name, $last_name]);

            // Clear verification data
            unset($_SESSION['verified_email'], $_SESSION['pending_email']);

            // Auto-login
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id']    = $user_id;
            $_SESSION['email']      = $email;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name']  = $last_name;
            $_SESSION['role']       = 'customer';

            header('Location: ../index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Database error in complete-signup.php: ' . $e->getMessage());

        // Handle duplicate email specially
        if ($e->getCode() == 23000) {
            $error = 'This email is already registered. Please sign in instead.';
        } else {
            $error = 'A database error occurred while creating your account. Please try again.';
        }
    } catch (Exception $e) {
        error_log('General error in complete-signup.php: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Account - enono MARKET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');
        .heading-font { font-family: 'Playfair Display', sans-serif; }
    </style>
</head>
<body class="bg-white min-h-screen flex items-center justify-center py-12">
    <div class="max-w-md w-full px-6">

        <div class="flex justify-center mb-8">
            <div class="flex items-center gap-3">
                <img src="../images/drum_logo.jpg" alt="" class="h-14 w-12">
                <div>
                    <div class="heading-font text-5xl font-bold tracking-tighter text-black">Enono</div>
                    <div class="pl-2 text-sm tracking-widest text-amber-600 -mt-1">MARKET</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow border border-gray-100 p-8">
            <h1 class="text-2xl font-semibold mb-2">Complete your account</h1>
            <p class="text-sm text-gray-500 mb-6">
                Verified email: <span class="font-medium text-gray-800"><?= htmlspecialchars($email) ?></span>
            </p>

            <?php if ($error): ?>
                <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-1">Your name</label>
                    <input type="text" name="full_name" required
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           placeholder="First and last name"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-amber-400">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium mb-1">Password (at least 6 characters)</label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-amber-400">
                    <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Passwords must be at least 6 characters.</span>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-medium mb-1">Re-enter password</label>
                    <input type="password" name="confirm_password" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-amber-400">
                </div>

                <button type="submit"
                        class="w-full bg-[#8B4513] hover:bg-amber-600 text-white font-semibold py-3.5 rounded-xl text-lg transition-colors">
                    Create Account
                </button>
            </form>

            <div class="mt-8 text-[13px] text-gray-500 text-center">
                By creating an account, you agree to enono MARKET's<br>
                <a href="#" class="text-amber-600 hover:underline">Conditions of Use</a> and
                <a href="#" class="text-amber-600 hover:underline">Privacy Notice</a>
            </div>
        </div>

        <div class="text-center mt-10 text-xs text-gray-500">
            <p>© 2026 enono MARKET. All Rights Reserved.</p>
        </div>
    </div>
</body>
</html>