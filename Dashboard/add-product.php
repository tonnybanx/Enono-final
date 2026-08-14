<?php
// Dashboard/add-product.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

$currentPage = 'products';

// ===================== HANDLE CREATE =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name              = trim($_POST['name'] ?? '');
    $category_id       = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $spirit_id         = !empty($_POST['spirit_id']) ? (int)$_POST['spirit_id'] : null;
    $base_price        = (float)($_POST['base_price'] ?? 0);
    $short_description = trim($_POST['short_description'] ?? '');
    $long_description  = trim($_POST['long_description'] ?? '');
    $is_active         = isset($_POST['is_active']) ? 1 : 0;
    $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

    $variant_titles = $_POST['variant_title'] ?? [];
    $variant_skus   = $_POST['variant_sku'] ?? [];
    $variant_prices = $_POST['variant_price'] ?? [];
    $variant_stocks = $_POST['variant_stock'] ?? [];
    $variant_attrs  = $_POST['variant_attributes'] ?? [];

    if (empty($name) || $base_price <= 0) {
        die('Product name and valid base price are required.');
    }

    try {
        $pdo->beginTransaction();

        // -------------------------------------------------
        // 1. Create the main product
        // -------------------------------------------------
        $stmt = $pdo->prepare("
            INSERT INTO products 
                (category_id, spirit_id, name, short_description, long_description,
                 heritage_title, base_price, currency, is_active, is_featured)
            VALUES (?, ?, ?, ?, ?, 'Heritage & History', ?, 'UGX', ?, ?)
        ");
        $stmt->execute([
            $category_id,
            $spirit_id,
            $name,
            $short_description,
            $long_description,
            $base_price,
            $is_active,
            $is_featured
        ]);
        $productId = (int) $pdo->lastInsertId();

        // -------------------------------------------------
        // 2. Link standard attribute types (Color + Size)
        //    This matches every product in your current dump
        // -------------------------------------------------
        $pdo->prepare("
            INSERT INTO product_attribute_types (product_id, attribute_type_id, is_required, sort_order)
            VALUES (?, 1, 1, 1)
        ")->execute([$productId]);

        $pdo->prepare("
            INSERT INTO product_attribute_types (product_id, attribute_type_id, is_required, sort_order)
            VALUES (?, 2, 0, 2)
        ")->execute([$productId]);

        // -------------------------------------------------
        // 3. Upload Product-level Images (gallery)
        // -------------------------------------------------
        if (!empty($_FILES['product_images']['name'][0])) {
            $uploadDir = __DIR__ . '/../images/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $sort = 1;
            foreach ($_FILES['product_images']['tmp_name'] as $i => $tmp) {
                if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['product_images']['name'][$i], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (!in_array($ext, $allowed)) continue;

                    $filename = 'prod_' . $productId . '_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                        $pdo->prepare("
                            INSERT INTO product_images (product_id, variant_id, url, is_primary, sort_order)
                            VALUES (?, NULL, ?, ?, ?)
                        ")->execute([
                            $productId,
                            'images/products/' . $filename,
                            $sort === 1 ? 1 : 0,
                            $sort
                        ]);
                        $sort++;
                    }
                }
            }
        }

        // -------------------------------------------------
        // 4. Create Variants + attributes + images
        // -------------------------------------------------
        foreach ($variant_titles as $i => $title) {
            $title = trim($title);
            $sku   = trim($variant_skus[$i] ?? '');
            $price = (float)($variant_prices[$i] ?? 0);
            $stock = (int)($variant_stocks[$i] ?? 0);

            if (empty($title) || empty($sku) || $price <= 0) {
                continue;
            }

            // Insert variant
            // name is set to the same value as title (matches your dump style)
            $stmt = $pdo->prepare("
                INSERT INTO product_variants 
                    (product_id, spirit_id, sku, name, title, price, 
                     stock_quantity, low_stock_threshold, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 5, 1)
            ");
            $stmt->execute([
                $productId,
                $spirit_id,          // inherit from product (can be null)
                $sku,
                $title,              // name
                $title,              // title
                $price,
                $stock
            ]);
            $variantId = (int) $pdo->lastInsertId();

            // ---- Attributes ----
            if (!empty($variant_attrs[$i]) && is_array($variant_attrs[$i])) {
                foreach ($variant_attrs[$i] as $typeId => $value) {
                    if ($value === '' || $value === null) continue;

                    $attrValueId = null;

                    if (str_starts_with((string)$value, 'new:')) {
                        $newValue = trim(substr($value, 4));
                        if ($newValue === '') continue;

                        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $newValue));
                        $slug = trim($slug, '-');

                        // Avoid duplicate (attribute_type_id + value is UNIQUE)
                        $check = $pdo->prepare("
                            SELECT id FROM attribute_values 
                            WHERE attribute_type_id = ? AND value = ?
                        ");
                        $check->execute([$typeId, $slug]);
                        $existing = $check->fetchColumn();

                        if ($existing) {
                            $attrValueId = (int)$existing;
                        } else {
                            $pdo->prepare("
                                INSERT INTO attribute_values 
                                    (attribute_type_id, value, display_value, sort_order)
                                VALUES (?, ?, ?, 99)
                            ")->execute([$typeId, $slug, $newValue]);
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
            }

            // ---- Variant Images ----
            $fileKey = "variant_images_$i";
            $firstImageUrl = null;

            if (!empty($_FILES[$fileKey]['name'][0])) {
                $uploadDir = __DIR__ . '/../images/variants/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $sort = 1;
                foreach ($_FILES[$fileKey]['tmp_name'] as $j => $tmp) {
                    if ($_FILES[$fileKey]['error'][$j] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'][$j], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                        if (!in_array($ext, $allowed)) continue;

                        $filename = 'var_' . $variantId . '_' . time() . '_' . $j . '.' . $ext;
                        if (move_uploaded_file($tmp, $uploadDir . $filename)) {
                            $relativePath = 'images/variants/' . $filename;

                            // Save into product_images (gallery)
                            $pdo->prepare("
                                INSERT INTO product_images 
                                    (product_id, variant_id, url, is_primary, sort_order)
                                VALUES (?, ?, ?, ?, ?)
                            ")->execute([
                                $productId,
                                $variantId,
                                $relativePath,
                                $sort === 1 ? 1 : 0,
                                $sort
                            ]);

                            // Keep the first image so we can put it on the variant
                            if ($sort === 1) {
                                $firstImageUrl = $relativePath;
                            }
                            $sort++;
                        }
                    }
                }
            }

            // Important: write the primary image into product_variants.image_url
            // (this is what the frontend cart & product pages currently use)
            if ($firstImageUrl) {
                $pdo->prepare("
                    UPDATE product_variants 
                    SET image_url = ? 
                    WHERE id = ?
                ")->execute([$firstImageUrl, $variantId]);
            }
        }

        $pdo->commit();
        header("Location: view-product.php?id=$productId&created=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error creating product: ' . $e->getMessage());
    }
}

// ===================== LOAD DATA FOR THE FORM =====================
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

$attrTypes = $pdo->query("
    SELECT id, name, display_name 
    FROM attribute_types 
    ORDER BY sort_order
")->fetchAll(PDO::FETCH_ASSOC);

$attrValues = [];
foreach ($attrTypes as $type) {
    $stmt = $pdo->prepare("
        SELECT id, value, display_value 
        FROM attribute_values 
        WHERE attribute_type_id = ? 
        ORDER BY sort_order, display_value
    ");
    $stmt->execute([$type['id']]);
    $attrValues[$type['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Enono Market</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .card { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
        .sidebar.open { transform: translateX(0); }
        @media (min-width: 1024px) { .sidebar { transform: none !important; } }
        .variant-block { border: 1px solid #e5e7eb; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.25rem; background: #fafafa; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="flex-1 flex flex-col h-full overflow-hidden">
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <div class="flex-1 overflow-auto p-6">
        <div class="max-w-7xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <a href="products.php" class="text-gray-500 hover:text-gray-800"><i class="fa fa-arrow-left"></i></a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Add New Product</h1>
                    <p class="text-gray-500 text-sm">Create the product + its variants and images</p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="productForm">

                <!-- ===================== PRODUCT INFO ===================== -->
                <div class="card p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Product Information</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"
                                   placeholder="e.g. Ancestral Smoking Pipe">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Spirit <span class="text-gray-400 text-xs">(applies to all variants)</span>
                            </label>
                            <select name="spirit_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                                <option value="">— None —</option>
                                <?php foreach ($spirits as $sp): ?>
                                    <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Base Price (UGX) <span class="text-red-500">*</span></label>
                            <input type="number" name="base_price" required min="0" step="100"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"
                                   placeholder="e.g. 130000">
                        </div>

                        <div class="flex items-center gap-6 pt-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4">
                                <span class="text-sm font-medium">Active</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_featured" value="1" class="w-4 h-4">
                                <span class="text-sm font-medium">Featured</span>
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                            <textarea name="short_description" rows="2"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"></textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Long Description</label>
                            <textarea name="long_description" rows="4"
                                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"></textarea>
                        </div>

                        <!-- Product Images -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product Images (Gallery)</label>
                            <input type="file" name="product_images[]" multiple accept="image/*"
                                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                            <p class="text-xs text-gray-400 mt-1">These appear as the main product gallery</p>
                        </div>
                    </div>
                </div>

                <!-- ===================== VARIANTS ===================== -->
                <div class="card p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Variants</h2>
                    </div>

                    <div id="variantsContainer">
                        <div id="emptyVariants" class="text-center py-12 text-gray-500">
                            <i class="fa fa-box-open text-4xl mb-3 opacity-40"></i>
                            <p class="mb-1 font-medium">No variants added yet</p>
                            <p class="text-sm">Click the button below to add the first variant</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <button type="button" onclick="addVariant()"
                                class="w-full py-3.5 border-2 border-dashed border-gray-300 rounded-xl text-gray-600 hover:border-blue-500 hover:text-blue-600 transition flex items-center justify-center gap-2 font-medium">
                            <i class="fa fa-plus"></i>
                            <span id="addVariantBtnText">Add a variant</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="products.php" class="px-5 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50 font-medium">Cancel</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-medium flex items-center gap-2">
                        <i class="fa fa-plus"></i> Create Product & Variants
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<script>
    let variantIndex = 0;

    function addVariant() {
        const emptyState = document.getElementById('emptyVariants');
        if (emptyState) emptyState.style.display = 'none';

        document.getElementById('addVariantBtnText').textContent = 'Add another variant';

        const container = document.getElementById('variantsContainer');
        const html = `
        <div class="variant-block" data-index="${variantIndex}">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-medium text-gray-800">Variant #${variantIndex + 1}</h3>
                <button type="button" onclick="removeVariant(this)" class="text-red-500 hover:text-red-700 text-sm">
                    <i class="fa fa-trash"></i> Remove
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="variant_title[${variantIndex}]" required
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"
                           placeholder="e.g. Mukasa’s Pipe – Natural Wood">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SKU <span class="text-red-500">*</span></label>
                    <input type="text" name="variant_sku[${variantIndex}]" required
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500"
                           placeholder="e.g. PIPE-MUK-NAT">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price (UGX) <span class="text-red-500">*</span></label>
                    <input type="number" name="variant_price[${variantIndex}]" required min="0" step="100"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
                    <input type="number" name="variant_stock[${variantIndex}]" min="0" value="10"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                </div>

                <?php foreach ($attrTypes as $type): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($type['display_name']) ?></label>
                    <div class="flex gap-2">
                        <select name="variant_attributes[${variantIndex}][<?= $type['id'] ?>]"
                                class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500 attr-select"
                                onchange="toggleNewAttr(this)">
                            <option value="">— Select —</option>
                            <?php foreach ($attrValues[$type['id']] as $val): ?>
                                <option value="<?= $val['id'] ?>"><?= htmlspecialchars($val['display_value']) ?></option>
                            <?php endforeach; ?>
                            <option value="__new__">+ Add new...</option>
                        </select>
                        <input type="text" class="hidden flex-1 px-4 py-2.5 border border-blue-300 rounded-xl new-attr-input" placeholder="Type new value">
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Variant Images</label>
                    <input type="file" name="variant_images_${variantIndex}[]" multiple accept="image/*"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-500">
                    <p class="text-xs text-gray-400 mt-1">First image will also be saved as the main variant image</p>
                </div>
            </div>
        </div>`;

        container.insertAdjacentHTML('beforeend', html);
        variantIndex++;
    }

    function removeVariant(btn) {
        btn.closest('.variant-block').remove();

        if (document.querySelectorAll('.variant-block').length === 0) {
            document.getElementById('emptyVariants').style.display = 'block';
            document.getElementById('addVariantBtnText').textContent = 'Add a variant';
            variantIndex = 0;
        }
    }

    function toggleNewAttr(select) {
        const input = select.parentElement.querySelector('.new-attr-input');
        if (select.value === '__new__') {
            input.classList.remove('hidden');
            input.focus();
        } else {
            input.classList.add('hidden');
            input.value = '';
        }
    }

    // Convert "__new__" values before submit
    document.getElementById('productForm').addEventListener('submit', function() {
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