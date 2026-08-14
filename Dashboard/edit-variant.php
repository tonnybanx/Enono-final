<?php
// Dashboard/edit-variant.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'products';

$variantId = (int)($_GET['id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$isEdit = $variantId > 0;

// ===================== HANDLE SAVE =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variantId      = (int)($_POST['variant_id'] ?? 0);
    $productId      = (int)($_POST['product_id'] ?? 0);
    $title          = trim($_POST['title'] ?? '');
    $sku            = trim($_POST['sku'] ?? '');
    $price          = (float)($_POST['price'] ?? 0);
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $is_active      = isset($_POST['is_active']) ? 1 : 0;
    $attributes     = $_POST['attributes'] ?? [];

    if (!$productId || empty($title) || empty($sku) || $price <= 0) {
        die('Missing required fields.');
    }

    // Get product spirit (we still inherit it for consistency)
    $spiritStmt = $pdo->prepare("SELECT spirit_id FROM products WHERE id = ?");
    $spiritStmt->execute([$productId]);
    $spirit_id = $spiritStmt->fetchColumn() ?: null;

    try {
        $pdo->beginTransaction();

        if ($variantId > 0) {
            // ---------- UPDATE ----------
            $stmt = $pdo->prepare("
                UPDATE product_variants SET
                    title          = ?,
                    name           = ?,
                    sku            = ?,
                    price          = ?,
                    stock_quantity = ?,
                    spirit_id      = ?,
                    is_active      = ?,
                    updated_at     = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $title,               // keep name in sync with title
                $sku,
                $price,
                $stock_quantity,
                $spirit_id,
                $is_active,
                $variantId
            ]);
        } else {
            // ---------- INSERT ----------
            $stmt = $pdo->prepare("
                INSERT INTO product_variants 
                    (product_id, spirit_id, sku, name, title, price, 
                     stock_quantity, low_stock_threshold, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 5, ?)
            ");
            $stmt->execute([
                $productId,
                $spirit_id,
                $sku,
                $title,               // name
                $title,               // title
                $price,
                $stock_quantity,
                $is_active
            ]);
            $variantId = (int) $pdo->lastInsertId();
        }

        // ---------- ATTRIBUTES ----------
        $pdo->prepare("DELETE FROM product_variant_attributes WHERE variant_id = ?")
            ->execute([$variantId]);

        foreach ($attributes as $typeId => $value) {
            if ($value === '' || $value === null) continue;

            $attrValueId = null;

            if (str_starts_with((string)$value, 'new:')) {
                $newValue = trim(substr($value, 4));
                if ($newValue === '') continue;

                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $newValue));
                $slug = trim($slug, '-');

                // Avoid UNIQUE constraint violation
                $check = $pdo->prepare("
                    SELECT id FROM attribute_values 
                    WHERE attribute_type_id = ? AND value = ?
                ");
                $check->execute([(int)$typeId, $slug]);
                $existing = $check->fetchColumn();

                if ($existing) {
                    $attrValueId = (int)$existing;
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO attribute_values 
                            (attribute_type_id, value, display_value, sort_order)
                        VALUES (?, ?, ?, 99)
                    ");
                    $ins->execute([(int)$typeId, $slug, $newValue]);
                    $attrValueId = (int) $pdo->lastInsertId();
                }
            } else {
                $attrValueId = (int)$value;
            }

            if ($attrValueId > 0) {
                $pdo->prepare("
                    INSERT INTO product_variant_attributes (variant_id, attribute_value_id)
                    VALUES (?, ?)
                ")->execute([$variantId, $attrValueId]);
            }
        }

        // ---------- IMAGE HANDLING (single image) ----------
        if (!empty($_FILES['variant_image']['name']) && $_FILES['variant_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../images/variants/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['variant_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed)) {
                throw new Exception('Invalid image type.');
            }

            $filename = 'var_' . $variantId . '_' . time() . '.' . $ext;
            $target   = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['variant_image']['tmp_name'], $target)) {
                $url = 'images/variants/' . $filename;

                // 1. Remove old rows from product_images for this variant
                $pdo->prepare("DELETE FROM product_images WHERE variant_id = ?")
                    ->execute([$variantId]);

                // 2. Insert the new image as primary in product_images
                $pdo->prepare("
                    INSERT INTO product_images 
                        (product_id, variant_id, url, is_primary, sort_order)
                    VALUES (?, ?, ?, 1, 1)
                ")->execute([$productId, $variantId, $url]);

                // 3. Critical: also write it to product_variants.image_url
                //    (this is what the frontend cart & product pages use)
                $pdo->prepare("
                    UPDATE product_variants 
                    SET image_url = ? 
                    WHERE id = ?
                ")->execute([$url, $variantId]);
            }
        }

        $pdo->commit();
        header("Location: view-product.php?id=$productId&variant_saved=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error saving variant: ' . $e->getMessage());
    }
}

// ===================== LOAD DATA =====================
$variant = null;
$currentAttributes = [];
$currentImage = null;

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ?");
    $stmt->execute([$variantId]);
    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        header('Location: products.php');
        exit;
    }
    $productId = (int)$variant['product_id'];

    // Current attribute value IDs
    $attrStmt = $pdo->prepare("
        SELECT attribute_value_id 
        FROM product_variant_attributes 
        WHERE variant_id = ?
    ");
    $attrStmt->execute([$variantId]);
    $currentAttributes = $attrStmt->fetchAll(PDO::FETCH_COLUMN);

    // Prefer the image stored on the variant itself, fall back to product_images
    if (!empty($variant['image_url'])) {
        $currentImage = ['url' => $variant['image_url']];
    } else {
        $imgStmt = $pdo->prepare("
            SELECT * FROM product_images 
            WHERE variant_id = ? 
            ORDER BY is_primary DESC, sort_order ASC 
            LIMIT 1
        ");
        $imgStmt->execute([$variantId]);
        $currentImage = $imgStmt->fetch(PDO::FETCH_ASSOC);
    }
}

$stmt = $pdo->prepare("SELECT id, name, spirit_id FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit;
}

$spiritName = null;
if ($product['spirit_id']) {
    $s = $pdo->prepare("SELECT name FROM spirits WHERE id = ?");
    $s->execute([$product['spirit_id']]);
    $spiritName = $s->fetchColumn();
}

$attrTypes = $pdo->query("
    SELECT id, name, display_name 
    FROM attribute_types 
    ORDER BY sort_order
")->fetchAll(PDO::FETCH_ASSOC);

$attrValues = [];
foreach ($attrTypes as $type) {
    $vals = $pdo->prepare("
        SELECT id, value, display_value 
        FROM attribute_values 
        WHERE attribute_type_id = ? 
        ORDER BY sort_order, display_value
    ");
    $vals->execute([$type['id']]);
    $attrValues[$type['id']] = $vals->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Add' ?> Variant - Enono Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        .img-preview { width: 120px; height: 120px; object-fit: cover; border-radius: 12px; background: #f1f5f9; }
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
        <div class="max-w-7xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <a href="view-product.php?id=<?= $productId ?>" class="text-gray-500 hover:text-gray-800">
                    <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">
                        <?= $isEdit ? 'Edit Variant' : 'Add Variant' ?>
                    </h1>
                    <p class="text-gray-500 text-sm">
                        Product: <span class="font-medium text-gray-700"><?= htmlspecialchars($product['name']) ?></span>
                        <?php if ($spiritName): ?>
                            • Spirit: <span class="font-medium"><?= htmlspecialchars($spiritName) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="variantForm">
                <input type="hidden" name="variant_id" value="<?= $variantId ?>">
                <input type="hidden" name="product_id" value="<?= $productId ?>">

                <!-- Variant Details -->
                <div class="card p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-5">Variant Details</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" required
                                   value="<?= htmlspecialchars($variant['title'] ?? '') ?>"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"
                                   placeholder="e.g. Mukasa’s Pipe – Natural Wood">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SKU <span class="text-red-500">*</span></label>
                            <input type="text" name="sku" required
                                   value="<?= htmlspecialchars($variant['sku'] ?? '') ?>"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price (UGX) <span class="text-red-500">*</span></label>
                            <input type="number" name="price" required min="0" step="100"
                                   value="<?= $variant['price'] ?? '' ?>"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                            <input type="number" name="stock_quantity" min="0"
                                   value="<?= $variant['stock_quantity'] ?? 10 ?>"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                        </div>

                        <div class="flex items-center gap-6 pt-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?= ($variant['is_active'] ?? 1) ? 'checked' : '' ?> class="w-4 h-4">
                                <span class="text-sm font-medium">Active</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Attributes -->
                <div class="card p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-5">Attributes</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php foreach ($attrTypes as $type): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <?= htmlspecialchars($type['display_name']) ?>
                                </label>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <select name="attributes[<?= $type['id'] ?>]" 
                                            class="flex-1 min-w-0 px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 attr-select"
                                            onchange="toggleNewAttr(this)">
                                        <option value="">— Select —</option>
                                        <?php foreach ($attrValues[$type['id']] as $val): ?>
                                            <option value="<?= $val['id'] ?>"
                                                <?= in_array($val['id'], $currentAttributes) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($val['display_value']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="__new__">+ Add new...</option>
                                    </select>
                                    <input type="text" 
                                           class="hidden flex-1 min-w-0 px-4 py-2.5 border border-blue-300 rounded-xl new-attr-input"
                                           placeholder="Type new value">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Image (Only one) -->
                <div class="card p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-5">Variant Image</h2>

                    <?php if ($currentImage): ?>
                        <div class="mb-5">
                            <p class="text-sm text-gray-500 mb-3">Current Image</p>
                            <img src="../<?= htmlspecialchars($currentImage['url']) ?>" 
                                 class="img-preview border border-gray-200"
                                 onerror="this.src='../images/drum_logo.jpg'">
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <?= $currentImage ? 'Replace Image' : 'Upload Image' ?>
                        </label>
                        <input type="file" name="variant_image" accept="image/*"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                        <p class="text-xs text-gray-400 mt-1">
                            Only one image is allowed per variant. Uploading a new image will replace the existing one
                            and update the main variant image used in the shop & cart.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="view-product.php?id=<?= $productId ?>" 
                       class="px-5 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50 font-medium">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-medium flex items-center gap-2">
                        <i class="fa fa-save"></i>
                        <?= $isEdit ? 'Save Changes' : 'Create Variant' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<script>
    function toggleNewAttr(select) {
        const wrapper = select.parentElement;
        const input = wrapper.querySelector('.new-attr-input');

        if (select.value === '__new__') {
            input.classList.remove('hidden');
            input.focus();
        } else {
            input.classList.add('hidden');
            input.value = '';
        }
    }

    document.getElementById('variantForm').addEventListener('submit', function() {
        document.querySelectorAll('.attr-select').forEach(select => {
            if (select.value === '__new__') {
                const input = select.parentElement.querySelector('.new-attr-input');
                select.value = (input && input.value.trim()) ? 'new:' + input.value.trim() : '';
            }
        });
    });

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        if (window.innerWidth <= 1024) {
            document.getElementById('overlay').classList.toggle('hidden');
        }
    }
    document.getElementById('toggleSidebar')?.addEventListener('click', toggleSidebar);
</script>
</body>
</html>