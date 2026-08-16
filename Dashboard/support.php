<?php
// Dashboard/support.php

require_once __DIR__ . '/../config/database-connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../Public/login.php');
    exit;
}

$currentPage = 'support';
$success = $error = '';

// ---------- Handle actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add new category
    if (isset($_POST['add_category'])) {
        header('Content-Type: application/json');
        $name = trim($_POST['name'] ?? '');
        $display = trim($_POST['display_name'] ?? $name);

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Category name is required']);
            exit;
        }

        try {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
            $stmt = $pdo->prepare("INSERT INTO faq_categories (name, display_name, sort_order, is_active) VALUES (?, ?, 99, 1)");
            $stmt->execute([$slug, $display]);
            $newId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'id' => $newId,
                'display_name' => $display
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Category already exists or error occurred']);
        }
        exit;
    }

    // Save FAQ (Add or Edit)
    if (isset($_POST['save_faq'])) {
        $id         = (int) ($_POST['id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $question   = trim($_POST['question'] ?? '');
        $answer     = trim($_POST['answer'] ?? '');
        $sortOrder  = (int) ($_POST['sort_order'] ?? 0);
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        if ($question === '' || $answer === '' || $categoryId <= 0) {
            $error = 'Please fill all required fields.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE faqs 
                    SET category_id = ?, question = ?, answer = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$categoryId, $question, $answer, $sortOrder, $isActive, $id]);
                $success = 'FAQ updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO faqs (category_id, question, answer, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$categoryId, $question, $answer, $sortOrder, $isActive]);
                $success = 'FAQ added successfully.';
            }
        }
    }

    // Delete FAQ
    if (isset($_POST['delete_faq'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM faqs WHERE id = ?")->execute([$id]);
        $success = 'FAQ deleted successfully.';
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM faq_categories ORDER BY sort_order, display_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch FAQs grouped by category
$faqs = $pdo->query("
    SELECT f.*, c.display_name as category_name, c.id as cat_id
    FROM faqs f 
    LEFT JOIN faq_categories c ON c.id = f.category_id 
    ORDER BY c.sort_order, f.sort_order, f.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Group FAQs by category
$groupedFaqs = [];
foreach ($faqs as $faq) {
    $catName = $faq['category_name'] ?? 'Uncategorized';
    if (!isset($groupedFaqs[$catName])) {
        $groupedFaqs[$catName] = [];
    }
    $groupedFaqs[$catName][] = $faq;
}

$pageTitle = 'User Support';
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

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">User Support</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage frequently asked questions shown to customers</p>
                </div>

                <button onclick="openModal()"
                        class="bg-[#8B4513] hover:bg-[#6B3410] text-white px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition">
                    <i class="fa-solid fa-plus text-xs"></i>
                    Add New Question
                </button>
            </div>

            <!-- Alerts -->
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

            <!-- Grouped FAQ List -->
            <?php if (empty($groupedFaqs)): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center text-gray-400">
                    <i class="fa-regular fa-circle-question text-5xl mb-4 opacity-30"></i>
                    <p class="text-sm">No questions yet. Click “Add New Question” to get started.</p>
                </div>
            <?php else: ?>

                <?php foreach ($groupedFaqs as $categoryName => $items): ?>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
                        
                        <!-- Category Header -->
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-[#8B4513]/10 flex items-center justify-center text-[#8B4513]">
                                    <i class="fa-solid fa-folder text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($categoryName) ?></h2>
                                    <p class="text-xs text-gray-500"><?= count($items) ?> question<?= count($items) !== 1 ? 's' : '' ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Questions in this category -->
                        <div class="divide-y divide-gray-50">
                            <?php foreach ($items as $faq): ?>
                                <div class="px-6 py-5 hover:bg-gray-50/80 transition group">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                                <?php if (!$faq['is_active']): ?>
                                                    <span class="text-[11px] px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactive</span>
                                                <?php endif; ?>
                                                <span class="text-[11px] text-gray-400">
                                                    Order: <?= $faq['sort_order'] ?>
                                                </span>
                                            </div>

                                            <h3 class="font-medium text-gray-900 text-[15px] leading-snug">
                                                <?= htmlspecialchars($faq['question']) ?>
                                            </h3>

                                            <p class="text-sm text-gray-600 mt-2 leading-relaxed whitespace-pre-wrap">
                                                <?= htmlspecialchars($faq['answer']) ?>
                                            </p>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                                            <button onclick='editFaq(<?= json_encode($faq, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                    class="p-2 text-gray-400 hover:text-[#8B4513] hover:bg-amber-50 rounded-lg transition"
                                                    title="Edit">
                                                <i class="fa-solid fa-pen text-sm"></i>
                                            </button>

                                            <form method="POST" onsubmit="return confirm('Delete this question?')" class="inline">
                                                <input type="hidden" name="id" value="<?= $faq['id'] ?>">
                                                <button type="submit" name="delete_faq"
                                                        class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                                        title="Delete">
                                                    <i class="fa-solid fa-trash text-sm"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>

        </main>
    </div>
</div>

<!-- ==================== MODAL OVERLAY ==================== -->
<div id="faqModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">

            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Add New Question</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-1">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form method="POST" id="faqForm" class="flex-1 overflow-y-auto p-6 space-y-5">
                <input type="hidden" name="id" id="faqId" value="0">

                <!-- Category -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                        Category <span class="text-[#8B4513]">*</span>
                    </label>
                    <div class="flex gap-2">
                        <select name="category_id" id="categorySelect" required
                                class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513]">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="showNewCategory()"
                                class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm hover:bg-gray-50 transition whitespace-nowrap">
                            + New
                        </button>
                    </div>

                    <div id="newCategoryBox" class="hidden mt-3 flex gap-2">
                        <input type="text" id="newCategoryName" placeholder="New category name"
                               class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513]">
                        <button type="button" onclick="addCategory()"
                                class="bg-[#8B4513] text-white px-4 py-2.5 rounded-xl text-sm hover:bg-[#6B3410] transition">
                            Add
                        </button>
                        <button type="button" onclick="hideNewCategory()"
                                class="px-3 py-2.5 text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Question -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                        Question <span class="text-[#8B4513]">*</span>
                    </label>
                    <input type="text" name="question" id="faqQuestion" required
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513]"
                           placeholder="e.g. How can I track my order?">
                </div>

                <!-- Answer -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                        Answer <span class="text-[#8B4513]">*</span>
                    </label>
                    <textarea name="answer" id="faqAnswer" rows="5" required
                              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513] resize-none"
                              placeholder="Write the full answer here..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                            Sort Order
                        </label>
                        <input type="number" name="sort_order" id="faqSortOrder" value="0"
                               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-[#8B4513]">
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="is_active" id="faqIsActive" value="1" checked
                                   class="rounded border-gray-300 text-[#8B4513] focus:ring-[#8B4513]">
                            Active (visible to customers)
                        </label>
                    </div>
                </div>
            </form>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeModal()"
                        class="px-5 py-2.5 rounded-xl text-sm border border-gray-200 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" form="faqForm" name="save_faq"
                        class="bg-[#8B4513] hover:bg-[#6B3410] text-white px-6 py-2.5 rounded-xl text-sm font-medium transition">
                    Save Question
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('faqModal');

function openModal() {
    document.getElementById('modalTitle').textContent = 'Add New Question';
    document.getElementById('faqId').value = 0;
    document.getElementById('faqQuestion').value = '';
    document.getElementById('faqAnswer').value = '';
    document.getElementById('faqSortOrder').value = 0;
    document.getElementById('faqIsActive').checked = true;
    document.getElementById('categorySelect').value = '';
    hideNewCategory();

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editFaq(faq) {
    document.getElementById('modalTitle').textContent = 'Edit Question';
    document.getElementById('faqId').value = faq.id;
    document.getElementById('faqQuestion').value = faq.question;
    document.getElementById('faqAnswer').value = faq.answer;
    document.getElementById('faqSortOrder').value = faq.sort_order;
    document.getElementById('faqIsActive').checked = faq.is_active == 1;
    document.getElementById('categorySelect').value = faq.category_id;
    hideNewCategory();

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function showNewCategory() {
    document.getElementById('newCategoryBox').classList.remove('hidden');
    document.getElementById('newCategoryName').focus();
}

function hideNewCategory() {
    document.getElementById('newCategoryBox').classList.add('hidden');
    document.getElementById('newCategoryName').value = '';
}

async function addCategory() {
    const name = document.getElementById('newCategoryName').value.trim();
    if (!name) {
        alert('Please enter a category name');
        return;
    }

    const formData = new FormData();
    formData.append('add_category', '1');
    formData.append('name', name);
    formData.append('display_name', name);

    try {
        const res = await fetch('support.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            const select = document.getElementById('categorySelect');
            const option = document.createElement('option');
            option.value = data.id;
            option.textContent = data.display_name;
            option.selected = true;
            select.appendChild(option);
            hideNewCategory();
        } else {
            alert(data.message || 'Could not add category');
        }
    } catch (err) {
        alert('Network error');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>