<?php
// Dashboard/about.php

require_once __DIR__ . '/../config/database-connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Access control is handled by includes/topbar.php
// (only admin and staff can reach this page)

$currentPage = 'about';
$success = $error = '';

// Check if current user is admin (only admin can edit)
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Fetch current company profile
$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Handle update – only allow if admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {

    if (!$isAdmin) {
        $error = 'You do not have permission to edit company information.';
    } else {

        $fields = [
            'company_name', 'tagline', 'short_description', 'full_history',
            'mission', 'vision', 'email', 'phone', 'whatsapp',
            'address_line1', 'address_line2', 'city', 'country',
            'facebook_url', 'instagram_url', 'twitter_url', 'youtube_url', 'tiktok_url',
            'working_hours'
        ];

        $data = [];
        foreach ($fields as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
        }

        // Keep existing paths by default
        $logoUrl    = $company['logo_url'] ?? '';
        $faviconUrl = $company['favicon_url'] ?? '';

        // ---------- Handle Logo Upload ----------
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newName = 'logo_' . time() . '.' . $ext;
                $destination = $uploadDir . $newName;

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
                    // Delete old logo
                    if (!empty($company['logo_url'])) {
                        $oldPath = __DIR__ . '/../' . ltrim($company['logo_url'], '/');
                        if (file_exists($oldPath) && strpos($company['logo_url'], 'images/') === 0) {
                            @unlink($oldPath);
                        }
                    }
                    $logoUrl = 'images/' . $newName;
                } else {
                    $error = 'Failed to upload logo.';
                }
            } else {
                $error = 'Logo must be an image (jpg, png, gif, webp, svg).';
            }
        }

        // ---------- Handle Favicon Upload ----------
        if (!$error && !empty($_FILES['favicon']['name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg'];
            $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newName = 'favicon_' . time() . '.' . $ext;
                $destination = $uploadDir . $newName;

                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $destination)) {
                    // Delete old favicon
                    if (!empty($company['favicon_url'])) {
                        $oldPath = __DIR__ . '/../' . ltrim($company['favicon_url'], '/');
                        if (file_exists($oldPath) && strpos($company['favicon_url'], 'images/') === 0) {
                            @unlink($oldPath);
                        }
                    }
                    $faviconUrl = 'images/' . $newName;
                } else {
                    $error = 'Failed to upload favicon.';
                }
            } else {
                $error = 'Favicon must be an image (ico, png, jpg, gif, svg).';
            }
        }

        if ($data['company_name'] === '') {
            $error = 'Company name is required.';
        }

        if (!$error) {
            try {
                $sql = "UPDATE company_profile SET 
                    company_name = ?, tagline = ?, short_description = ?, full_history = ?,
                    mission = ?, vision = ?, email = ?, phone = ?, whatsapp = ?,
                    address_line1 = ?, address_line2 = ?, city = ?, country = ?,
                    facebook_url = ?, instagram_url = ?, twitter_url = ?, youtube_url = ?, tiktok_url = ?,
                    working_hours = ?, logo_url = ?, favicon_url = ?,
                    updated_at = NOW()
                    WHERE id = 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['company_name'], $data['tagline'], $data['short_description'], $data['full_history'],
                    $data['mission'], $data['vision'], $data['email'], $data['phone'], $data['whatsapp'],
                    $data['address_line1'], $data['address_line2'], $data['city'], $data['country'],
                    $data['facebook_url'], $data['instagram_url'], $data['twitter_url'], $data['youtube_url'], $data['tiktok_url'],
                    $data['working_hours'], $logoUrl, $faviconUrl
                ]);

                $success = 'Company information updated successfully.';

                // Refresh data
                $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
                $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e) {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

$pageTitle = 'About Us / Company';
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

        <main class="main-scroll p-6">

            <div class="w-full max-w-6xl mx-auto">

                <div class="mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Company Profile</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $isAdmin ? 'Edit all company information shown on the website' : 'View company information (only admins can edit)' ?>
                    </p>
                </div>

                <?php if (!$isAdmin): ?>
                    <div class="mb-5 p-4 bg-amber-50 border border-amber-100 text-amber-800 rounded-xl text-sm flex items-center gap-2">
                        <i class="fa-solid fa-lock"></i>
                        You are viewing this page as staff. Only administrators can edit company information.
                    </div>
                <?php endif; ?>

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

                <form method="POST" enctype="multipart/form-data" class="space-y-6">

                    <!-- Basic Info -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="font-semibold text-lg mb-5">Basic Information</h2>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Company Name *</label>
                                <input type="text" name="company_name" required
                                       value="<?= htmlspecialchars($company['company_name'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Tagline</label>
                                <input type="text" name="tagline"
                                       value="<?= htmlspecialchars($company['tagline'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                        </div>

                        <div class="mt-5">
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Short Description</label>
                            <textarea name="short_description" rows="3"
                                      <?= $isAdmin ? '' : 'disabled' ?>
                                      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] resize-none <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>"><?= htmlspecialchars($company['short_description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Story & Mission -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="font-semibold text-lg mb-5">Story & Mission</h2>
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Full History</label>
                                <textarea name="full_history" rows="4"
                                          <?= $isAdmin ? '' : 'disabled' ?>
                                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] resize-none <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>"><?= htmlspecialchars($company['full_history'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Mission</label>
                                <textarea name="mission" rows="3"
                                          <?= $isAdmin ? '' : 'disabled' ?>
                                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] resize-none <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>"><?= htmlspecialchars($company['mission'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Vision / Values</label>
                                <textarea name="vision" rows="3"
                                          <?= $isAdmin ? '' : 'disabled' ?>
                                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] resize-none <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>"><?= htmlspecialchars($company['vision'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="font-semibold text-lg mb-5">Contact Information</h2>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Email</label>
                                <input type="email" name="email"
                                       value="<?= htmlspecialchars($company['email'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Phone</label>
                                <input type="text" name="phone"
                                       value="<?= htmlspecialchars($company['phone'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">WhatsApp</label>
                                <input type="text" name="whatsapp"
                                       value="<?= htmlspecialchars($company['whatsapp'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Working Hours</label>
                                <input type="text" name="working_hours"
                                       value="<?= htmlspecialchars($company['working_hours'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-5 mt-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Address Line 1</label>
                                <input type="text" name="address_line1"
                                       value="<?= htmlspecialchars($company['address_line1'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Address Line 2</label>
                                <input type="text" name="address_line2"
                                       value="<?= htmlspecialchars($company['address_line2'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">City</label>
                                <input type="text" name="city"
                                       value="<?= htmlspecialchars($company['city'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Country</label>
                                <input type="text" name="country"
                                       value="<?= htmlspecialchars($company['country'] ?? 'Uganda') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="font-semibold text-lg mb-5">Social Media</h2>
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Facebook URL</label>
                                <input type="url" name="facebook_url"
                                       value="<?= htmlspecialchars($company['facebook_url'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Instagram URL</label>
                                <input type="url" name="instagram_url"
                                       value="<?= htmlspecialchars($company['instagram_url'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">Twitter / X URL</label>
                                <input type="url" name="twitter_url"
                                       value="<?= htmlspecialchars($company['twitter_url'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">YouTube URL</label>
                                <input type="url" name="youtube_url"
                                       value="<?= htmlspecialchars($company['youtube_url'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">TikTok URL</label>
                                <input type="url" name="tiktok_url"
                                       value="<?= htmlspecialchars($company['tiktok_url'] ?? '') ?>"
                                       <?= $isAdmin ? '' : 'disabled' ?>
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] <?= $isAdmin ? '' : 'bg-gray-50 text-gray-500 cursor-not-allowed' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Logo & Favicon -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                        <h2 class="font-semibold text-lg mb-5">Logo & Favicon</h2>

                        <div class="grid sm:grid-cols-2 gap-10">

                            <!-- LOGO -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">
                                    Company Logo
                                </label>

                                <div class="relative inline-block group">
                                    <div class="w-40 h-40 rounded-2xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                        <img id="logoPreviewImg"
                                             src="<?= !empty($company['logo_url']) ? '../' . htmlspecialchars($company['logo_url']) : '' ?>"
                                             alt="Logo"
                                             class="max-w-full max-h-full object-contain <?= empty($company['logo_url']) ? 'hidden' : '' ?>"
                                             onerror="this.classList.add('hidden'); document.getElementById('logoPlaceholder').classList.remove('hidden');">

                                        <div id="logoPlaceholder" class="<?= !empty($company['logo_url']) ? 'hidden' : '' ?> text-center text-gray-400">
                                            <i class="fa-regular fa-image text-3xl mb-2"></i>
                                            <p class="text-xs">No logo</p>
                                        </div>
                                    </div>

                                    <?php if ($isAdmin): ?>
                                        <label class="absolute -bottom-2 -right-2 w-10 h-10 bg-[#8B4513] hover:bg-[#6B3410] text-white rounded-full flex items-center justify-center cursor-pointer shadow-lg transition group-hover:scale-105"
                                               title="Change logo">
                                            <i class="fa-solid fa-pen text-sm"></i>
                                            <input type="file" name="logo" accept="image/*" class="hidden"
                                                   onchange="previewAndShow(this, 'logoPreviewImg', 'logoPlaceholder')">
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isAdmin): ?>
                                    <p class="text-xs text-gray-400 mt-3">Click the pen icon to upload a new logo</p>
                                <?php endif; ?>
                            </div>

                            <!-- FAVICON -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">
                                    Favicon
                                </label>

                                <div class="relative inline-block group">
                                    <div class="w-24 h-24 rounded-2xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                        <img id="faviconPreviewImg"
                                             src="<?= !empty($company['favicon_url']) ? '../' . htmlspecialchars($company['favicon_url']) : '' ?>"
                                             alt="Favicon"
                                             class="max-w-full max-h-full object-contain <?= empty($company['favicon_url']) ? 'hidden' : '' ?>"
                                             onerror="this.classList.add('hidden'); document.getElementById('faviconPlaceholder').classList.remove('hidden');">

                                        <div id="faviconPlaceholder" class="<?= !empty($company['favicon_url']) ? 'hidden' : '' ?> text-center text-gray-400">
                                            <i class="fa-regular fa-image text-2xl mb-1"></i>
                                            <p class="text-[10px]">No icon</p>
                                        </div>
                                    </div>

                                    <?php if ($isAdmin): ?>
                                        <label class="absolute -bottom-2 -right-2 w-9 h-9 bg-[#8B4513] hover:bg-[#6B3410] text-white rounded-full flex items-center justify-center cursor-pointer shadow-lg transition group-hover:scale-105"
                                               title="Change favicon">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                            <input type="file" name="favicon" accept="image/*,.ico" class="hidden"
                                                   onchange="previewAndShow(this, 'faviconPreviewImg', 'faviconPlaceholder')">
                                        </label>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isAdmin): ?>
                                    <p class="text-xs text-gray-400 mt-3">Click the pen icon to upload a new favicon</p>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <?php if ($isAdmin): ?>
                        <div class="pt-2 pb-8">
                            <button type="submit" name="update_company"
                                    class="bg-[#8B4513] hover:bg-[#6B3410] text-white px-8 py-3 rounded-xl text-sm font-medium transition">
                                Save Company Information
                            </button>
                        </div>
                    <?php endif; ?>
                </form>

            </div>
        </main>
    </div>
</div>

<script>
function previewAndShow(input, imgId, placeholderId) {
    const img = document.getElementById(imgId);
    const placeholder = document.getElementById(placeholderId);

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>