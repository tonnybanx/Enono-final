<?php
// Dashboard/view-product.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'products';
$productId = (int)($_GET['id'] ?? 0);

if (!$productId) {
    header('Location: products.php');
    exit;
}

// ===================== HANDLE UPDATE PRODUCT =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $name              = trim($_POST['name'] ?? '');
    $category_id       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $spirit_id         = !empty($_POST['spirit_id']) ? (int)$_POST['spirit_id'] : null;
    $base_price        = (float)($_POST['base_price'] ?? 0);
    $short_description = trim($_POST['short_description'] ?? '');
    $long_description  = trim($_POST['long_description'] ?? '');
    $is_active         = isset($_POST['is_active']) ? 1 : 0;
    $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        // Update product
        $stmt = $pdo->prepare("
            UPDATE products SET
                name = ?, category_id = ?, spirit_id = ?, base_price = ?,
                short_description = ?, long_description = ?,
                is_active = ?, is_featured = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $category_id, $spirit_id, $base_price,
            $short_description, $long_description,
            $is_active, $is_featured, $productId
        ]);

        // Keep spirit in sync on all variants (matches your data style)
        $pdo->prepare("UPDATE product_variants SET spirit_id = ? WHERE product_id = ?")
            ->execute([$spirit_id, $productId]);

        $pdo->commit();
        header("Location: view-product.php?id=$productId&updated=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error updating product: ' . $e->getMessage());
    }
}

// ===================== HANDLE DELETE VARIANT (AJAX) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (($input['action'] ?? '') === 'delete_variant') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'No ID']);
            exit;
        }
        try {
            $pdo->beginTransaction();
            // Clean related data first
            $pdo->prepare("DELETE FROM product_images WHERE variant_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM product_variant_attributes WHERE variant_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ===================== FETCH DATA =====================
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, s.name AS spirit_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN spirits s ON s.id = p.spirit_id
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Variants – also fetch image_url from the variant itself
$variantsStmt = $pdo->prepare("
    SELECT 
        pv.*,
        GROUP_CONCAT(DISTINCT av.display_value ORDER BY at.sort_order SEPARATOR ' • ') AS attributes_text
    FROM product_variants pv
    LEFT JOIN product_variant_attributes pva ON pva.variant_id = pv.id
    LEFT JOIN attribute_values av ON av.id = pva.attribute_value_id
    LEFT JOIN attribute_types at ON at.id = av.attribute_type_id
    WHERE pv.product_id = ?
    GROUP BY pv.id
    ORDER BY pv.id
");
$variantsStmt->execute([$productId]);
$variants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

// All images from product_images table
$imagesStmt = $pdo->prepare("
    SELECT * FROM product_images 
    WHERE product_id = ? 
       OR variant_id IN (SELECT id FROM product_variants WHERE product_id = ?)
    ORDER BY is_primary DESC, sort_order ASC
");
$imagesStmt->execute([$productId, $productId]);
$allImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Group by variant_id (or 'product' for product-level images)
$imagesByVariant = [];
foreach ($allImages as $img) {
    $key = $img['variant_id'] ?: 'product';
    $imagesByVariant[$key][] = $img;
}

$categories = $pdo->query("
    SELECT id, name 
    FROM categories 
    WHERE is_active = 1 AND deleted_at IS NULL 
    ORDER BY sort_order, name
")->fetchAll(PDO::FETCH_ASSOC);

$spirits = $pdo->query("
    SELECT id, name 
    FROM spirits 
    WHERE is_active = 1 
    ORDER BY sort_order, name
")->fetchAll(PDO::FETCH_ASSOC);

function formatMoney($amount, $currency = 'UGX') {
    return 'USh ' . number_format((float)$amount, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product - <?= htmlspecialchars($product['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        .variant-card:hover { border-color: #3b82f6; }
        .img-thumb { width: 72px; height: 72px; object-fit: cover; border-radius: 10px; background: #f1f5f9; }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.open { transform: translateX(0); }
        @media (min-width: 1024px) { .sidebar { transform: none !important; } }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-hidden">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <div class="flex-1 overflow-auto p-6">
        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <a href="products.php" class="text-gray-500 hover:text-gray-800"><i class="fa fa-arrow-left"></i></a>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl font-semibold text-gray-900"><?= htmlspecialchars($product['name']) ?></h1>
                            <?php if ($product['is_featured']): ?>
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-100 px-2.5 py-0.5 rounded-full">
                                    <i class="fa fa-star text-[10px]"></i> Featured
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-500 text-sm">
                            ID #<?= $product['id'] ?> • <?= count($variants) ?> variants
                            <?php if ($product['spirit_name']): ?>
                                • Spirit: <span class="font-medium text-gray-700"><?= htmlspecialchars($product['spirit_name']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <button onclick="toggleEditMode()" id="editBtn"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-medium flex items-center gap-2">
                    <i class="fa fa-pen"></i> Edit Product
                </button>
            </div>

            <!-- Product Info -->
            <div class="card p-6 mb-6">
                <form method="POST">
                    <input type="hidden" name="action" value="update">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 edit-field" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 edit-field" disabled>
                                <option value="">— None —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Base Price (UGX)</label>
                            <input type="number" name="base_price" value="<?= $product['base_price'] ?>"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 edit-field" disabled>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Spirit <span class="text-gray-400 text-xs">(for all variants)</span>
                            </label>
                            <select name="spirit_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 edit-field" disabled>
                                <option value="">— None —</option>
                                <?php foreach ($spirits as $sp): ?>
                                    <option value="<?= $sp['id'] ?>" <?= $product['spirit_id'] == $sp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                            <textarea name="short_description" rows="2"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 edit-field" disabled><?= htmlspecialchars($product['short_description'] ?? '') ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Long Description</label>
                            <textarea name="long_description" rows="4"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 edit-field" disabled><?= htmlspecialchars($product['long_description'] ?? '') ?></textarea>
                        </div>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>
                                       class="w-4 h-4 edit-field" disabled>
                                <span class="text-sm font-medium">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_featured" value="1" <?= $product['is_featured'] ? 'checked' : '' ?>
                                       class="w-4 h-4 edit-field" disabled>
                                <span class="text-sm font-medium">Featured</span>
                            </label>
                        </div>
                    </div>

                    <div id="saveBar" class="hidden mt-6 pt-5 border-t flex justify-end gap-3">
                        <button type="button" onclick="toggleEditMode()" class="px-5 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-medium">Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- Variants -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Product Variants (<?= count($variants) ?>)</h2>
                <a href="edit-variant.php?product_id=<?= $productId ?>" 
                   class="text-blue-600 hover:text-blue-700 font-medium text-sm flex items-center gap-1">
                    <i class="fa fa-plus"></i> Add Variant
                </a>
            </div>

            <?php if (empty($variants)): ?>
                <div class="card p-12 text-center text-gray-500">
                    No variants yet. <a href="edit-variant.php?product_id=<?= $productId ?>" class="text-blue-600 hover:underline">Add the first one</a>.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($variants as $v): ?>
                        <?php
                        // Prefer images from product_images table
                        $vImages = $imagesByVariant[$v['id']] ?? [];

                        // Fallback: use the image_url stored directly on the variant
                        // (this is how most of your current data is stored)
                        if (empty($vImages) && !empty($v['image_url'])) {
                            $vImages = [[
                                'url'        => $v['image_url'],
                                'is_primary' => 1
                            ]];
                        }
                        ?>
                        <div class="card p-5 variant-card border border-transparent transition">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 mb-1">
                                        <h3 class="font-medium text-gray-900">
                                            <?= htmlspecialchars($v['title'] ?: $v['name'] ?: 'Untitled') ?>
                                        </h3>
                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-mono">
                                            <?= htmlspecialchars($v['sku']) ?>
                                        </span>
                                        <?php if (!$v['is_active']): ?>
                                            <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-500 mb-2">
                                        <?= htmlspecialchars($v['attributes_text'] ?? 'No attributes') ?>
                                    </p>
                                    <div class="flex flex-wrap gap-4 text-sm">
                                        <span class="font-medium text-gray-900"><?= formatMoney($v['price']) ?></span>
                                        <span class="text-gray-500">Stock: <strong><?= (int)$v['stock_quantity'] ?></strong></span>
                                        <?php if (!empty($v['low_stock_threshold'])): ?>
                                            <span class="text-gray-400 text-xs">(low stock ≤ <?= (int)$v['low_stock_threshold'] ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a href="edit-variant.php?id=<?= $v['id'] ?>"
                                       class="bg-white border border-gray-300 hover:border-gray-400 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2">
                                        <i class="fa fa-pen text-gray-500"></i> Edit
                                    </a>
                                    <button onclick="deleteVariant(<?= $v['id'] ?>)"
                                            class="bg-white border border-red-200 hover:border-red-300 text-red-600 px-3 py-2 rounded-xl text-sm">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($vImages)): ?>
                                <div class="mt-4 pt-4 border-t">
                                    <p class="text-xs font-medium text-gray-500 mb-2">Images</p>
                                    <div class="flex flex-wrap gap-3">
                                        <?php foreach ($vImages as $img): ?>
                                            <div class="relative">
                                                <img src="../<?= htmlspecialchars($img['url']) ?>" 
                                                     class="img-thumb border <?= !empty($img['is_primary']) ? 'ring-2 ring-blue-500' : '' ?>"
                                                     onerror="this.src='../images/drum_logo.jpg'">
                                                <?php if (!empty($img['is_primary'])): ?>
                                                    <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-[10px] px-1.5 py-0.5 rounded-full">Primary</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-3 text-xs text-gray-400">No images for this variant</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<script>
    let editMode = false;

    function toggleEditMode() {
        editMode = !editMode;
        document.querySelectorAll('.edit-field').forEach(el => el.disabled = !editMode);
        document.getElementById('saveBar').classList.toggle('hidden', !editMode);

        const btn = document.getElementById('editBtn');
        if (editMode) {
            btn.innerHTML = '<i class="fa fa-times"></i> Cancel Edit';
            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            btn.classList.add('bg-gray-600', 'hover:bg-gray-700');
        } else {
            btn.innerHTML = '<i class="fa fa-pen"></i> Edit Product';
            btn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
            btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        }
    }

    function deleteVariant(id) {
        if (!confirm('Delete this variant and its images?')) return;
        fetch('view-product.php?id=<?= $productId ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_variant', id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.message || 'Failed');
        });
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        if (window.innerWidth <= 1024) document.getElementById('overlay').classList.toggle('hidden');
    }
    document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);
</script>
</body>
</html>