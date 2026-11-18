<?php
session_start();

set_exception_handler(function ($e) {
    error_log($e->getMessage());
    die('<div style="font-family:sans-serif;max-width:500px;margin:80px auto;padding:30px;border:1px solid #7f1d1d;background:#450a0a;color:#fecaca;border-radius:12px;">
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

        .form-input {
            background-color: #141414;
            border: 1px solid #2A2A2A;
            color: #ffffff;
            transition: all 0.3s ease;
        }
        .form-input::placeholder {
            color: #6b7280;
        }
        .form-input:focus {
            border-color: #E8B923;
            box-shadow: 0 0 0 3px rgba(232, 185, 35, 0.15);
            outline: none;
        }
    </style>
</head>
<body class="bg-[#0B0B0B] min-h-screen flex items-center justify-center py-12">
    <div class="max-w-md w-full px-6">

        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center gap-3">
                <img src="../images/drum_logo.jpg" alt="" class="h-14 w-12 rounded">
                <div>
                    <div class="heading-font text-5xl font-bold tracking-tighter text-white">Enono</div>
                    <div class="pl-2 text-sm tracking-widest text-[#D4A017] -mt-1">MARKET</div>
                </div>
            </div>
        </div>

        <!-- Card -->
        <div class="bg-[#141414] rounded-2xl border border-[#2A2A2A] p-8">
            <h1 class="text-2xl font-semibold mb-2 text-white">Complete your account</h1>
            <p class="text-sm text-gray-400 mb-6">
                Verified email: <span class="font-medium text-[#D4A017]"><?= htmlspecialchars($email) ?></span>
            </p>

            <?php if ($error): ?>
                <div class="mb-5 p-4 bg-red-900/30 border border-red-700/50 text-red-300 rounded-lg text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-5">
                    <label class="block text-sm font-medium mb-1.5 text-gray-300">Your name</label>
                    <input type="text" name="full_name" required
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           placeholder="First and last name"
                           class="form-input w-full rounded-lg px-4 py-3">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium mb-1.5 text-gray-300">Password (at least 6 characters)</label>
                    <input type="password" name="password" required minlength="6"
                           class="form-input w-full rounded-lg px-4 py-3">
                    <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Passwords must be at least 6 characters.</span>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-medium mb-1.5 text-gray-300">Re-enter password</label>
                    <input type="password" name="confirm_password" required
                           class="form-input w-full rounded-lg px-4 py-3">
                </div>

                <button type="submit"
                        class="w-full bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] font-semibold py-3.5 rounded-xl text-lg transition-colors">
                    Create Account
                </button>
            </form>

            <div class="mt-8 text-[13px] text-gray-500 text-center">
                By creating an account, you agree to enono MARKET's<br>
                <a href="#" class="text-[#D4A017] hover:text-[#E8B923] hover:underline">Conditions of Use</a> and
                <a href="#" class="text-[#D4A017] hover:text-[#E8B923] hover:underline">Privacy Notice</a>
            </div>
        </div>

        <div class="text-center mt-10 text-xs text-gray-500">
            <p>© 2026 enono MARKET. All Rights Reserved.</p>
        </div>
    </div>
</body>
</html>