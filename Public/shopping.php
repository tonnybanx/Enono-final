<?php
// Public/shopping.php
session_start();
require_once __DIR__ . '/../config/database-connection.php';

// ---------- Filters ----------
$currentCategory = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$searchTerm      = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$page            = max(1, (int)($_GET['page'] ?? 1));
$perPage         = 16;
$offset          = ($page - 1) * $perPage;

// ---------- Categories ----------
$catStmt = $pdo->query("
    SELECT id, name 
    FROM categories 
    WHERE is_active = 1 AND deleted_at IS NULL 
    ORDER BY sort_order, name
");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- WHERE ----------
$where  = " WHERE p.is_active = 1 ";
$params = [];

if ($currentCategory !== '' && strtolower($currentCategory) !== 'all') {
    $where   .= " AND c.name = ?";
    $params[] = $currentCategory;
}

if ($searchTerm !== '') {
    $where   .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR p.long_description LIKE ?)";
    $like     = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// ---------- Count ----------
$countSql = "
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($totalProducts / $perPage));

if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// ---------- Products ----------
$sql = "
    SELECT 
        p.id,
        p.name,
        p.short_description,
        p.base_price,
        p.currency,
        p.is_featured,
        c.name AS category_name,
        (
            SELECT pv.image_url 
            FROM product_variants pv 
            WHERE pv.product_id = p.id AND pv.is_active = 1 
            ORDER BY pv.id ASC 
            LIMIT 1
        ) AS image_url,
        (
            SELECT pv.id 
            FROM product_variants pv 
            WHERE pv.product_id = p.id AND pv.is_active = 1 
            ORDER BY pv.id ASC 
            LIMIT 1
        ) AS default_variant_id
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $where
    ORDER BY p.is_featured DESC, p.name ASC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatPrice($price) {
    return 'USh ' . number_format((float)$price, 0, '.', ',');
}

function buildQuery(array $overrides = []): string {
    $q = array_merge([
        'category' => $_GET['category'] ?? 'all',
        'search'   => $_GET['search']   ?? '',
        'page'     => $_GET['page']     ?? 1,
    ], $overrides);

    // Drop empty / default values
    if (isset($q['category']) && strtolower((string)$q['category']) === 'all') {
        unset($q['category']);
    }
    if (isset($q['search']) && trim((string)$q['search']) === '') {
        unset($q['search']);
    }
    if (isset($q['page']) && (int)$q['page'] <= 1) {
        unset($q['page']);
    }

    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return $q ? ('?' . http_build_query($q)) : '';
}

// ---------- Page meta for header ----------
$pageTitle = 'Shop';
$activeNav = 'shop';

$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoUrl = !empty($company['logo_url'])
    ? '../' . ltrim($company['logo_url'], '/')
    : '../images/drum_logo.jpg';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .product-card {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        cursor: pointer;
    }
    .product-card:hover {
        transform: translateY(-6px);
    }
    .product-card img {
        transition: transform 0.6s ease;
    }
    .product-card:hover img {
        transform: scale(1.05);
    }
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .category-btn.active {
        background-color: #E8B923 !important;
        color: #0B0B0B !important;
        border-color: #E8B923 !important;
    }
</style>

<div class="mx-auto bg-[#0B0B0B] min-h-screen">
    <!-- Header Section -->
    <div class="w-full bg-[#0B0B0B] border-b border-[#2A2A2A]">
        <div class="mb-8 pt-12 max-w-7xl mx-auto px-6 pb-2">
            <p class="uppercase tracking-[0.2em] text-sm text-[#D4A017] mb-3">THE COLLECTION</p>
            <h1 class="heading-font text-3xl md:text-5xl font-semibold text-white">
                Shop <span class="text-[#D4A017]">Buganda Crafts</span>
            </h1>
        </div>

        <!-- Categories -->
        <div class="max-w-7xl mx-auto px-6 pb-8">
            <div class="flex flex-wrap gap-3 justify-start mb-2 scrollbar-hide overflow-x-auto pb-2">
                <a href="<?= htmlspecialchars(buildQuery(['category' => 'all', 'page' => 1, 'search' => $searchTerm])) ?>"
                   class="category-btn px-7 py-3 rounded-2xl text-sm font-medium border transition-colors
                   <?= strtolower($currentCategory) === 'all'
                        ? 'active bg-[#E8B923] text-[#0B0B0B] border-[#E8B923]'
                        : 'bg-[#141414] border-[#2A2A2A] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017]' ?>">
                    All
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= htmlspecialchars(buildQuery(['category' => $cat['name'], 'page' => 1, 'search' => $searchTerm])) ?>"
                       class="category-btn px-7 py-3 rounded-2xl text-sm font-medium border transition-colors
                       <?= $currentCategory === $cat['name']
                            ? 'active bg-[#E8B923] text-[#0B0B0B] border-[#E8B923]'
                            : 'bg-[#141414] border-[#2A2A2A] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017]' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Products -->
    <div class="w-full px-6 pb-16 pt-10 max-w-7xl mx-auto">

        <?php if ($totalProducts > 0): ?>
            <p class="text-sm text-gray-400 mb-8">
                Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalProducts) ?>
                of <?= number_format($totalProducts) ?> products
            </p>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-7">
            <?php if (empty($products)): ?>
                <div class="col-span-full text-center py-24 text-gray-500">
                    <p class="text-2xl mb-4 text-gray-400">No products found.</p>
                    <a href="shopping.php" class="text-[#D4A017] hover:text-[#E8B923] hover:underline text-sm">
                        Back to all products
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                        $img = $p['image_url'] ?? '';
                        if ($img && !str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
                            $img = '../' . ltrim($img, '/');
                        }
                        if (!$img) {
                            $img = '../images/drum_logo.jpg';
                        }
                        $variantId = (int)($p['default_variant_id'] ?? 0);
                    ?>
                    <div class="product-card bg-[#141414] rounded-xl overflow-hidden border border-[#D4A017]/30 hover:border-[#D4A017] transition-all duration-300"
                         onclick="window.location.href='product-details.php?id=<?= (int)$p['id'] ?>&variant=<?= $variantId ?>'">
                        
                        <div class="overflow-hidden bg-[#1C1C1C]">
                            <img src="<?= htmlspecialchars($img) ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 class="w-full h-56 md:h-64 object-cover"
                                 onerror="this.onerror=null; this.src='../images/drum_logo.jpg'">
                        </div>

                        <div class="p-5">
                            <h3 class="font-semibold text-base text-white leading-snug line-clamp-1">
                                <?= htmlspecialchars($p['name']) ?>
                            </h3>
                            <p class="text-sm text-gray-400 mt-1.5 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($p['short_description'] ?? '') ?>
                            </p>
                            <p class="mt-3 font-medium text-[#D4A017] text-lg">
                                <?= formatPrice($p['base_price']) ?>
                            </p>

                            <?php if ($variantId > 0): ?>
                                <button type="button"
                                        onclick="event.stopPropagation(); addToCart(<?= $variantId ?>)"
                                        class="mt-4 w-full bg-[#E8B923] hover:bg-[#F0C94A] text-[#0B0B0B] py-3 rounded-xl text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                                    <i class="fa-solid fa-cart-plus"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button type="button" disabled
                                        class="mt-4 w-full bg-[#2A2A2A] text-gray-500 py-3 rounded-xl text-sm font-medium cursor-not-allowed">
                                    Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-14 flex flex-wrap items-center justify-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars(buildQuery(['page' => $page - 1])) ?>"
                       class="px-5 py-2.5 rounded-xl border border-[#2A2A2A] bg-[#141414] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017] text-sm font-medium transition-colors">
                        ← Previous
                    </a>
                <?php endif; ?>

                <?php
                $window = 2;
                $start  = max(1, $page - $window);
                $end    = min($totalPages, $page + $window);

                if ($start > 1) {
                    echo '<a href="' . htmlspecialchars(buildQuery(['page' => 1])) . '" class="px-4 py-2.5 rounded-xl border border-[#2A2A2A] bg-[#141414] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017] text-sm transition-colors">1</a>';
                    if ($start > 2) echo '<span class="px-2 text-gray-500">…</span>';
                }

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="<?= htmlspecialchars(buildQuery(['page' => $i])) ?>"
                       class="px-4 py-2.5 rounded-xl border text-sm font-medium transition-colors
                       <?= $i === $page
                           ? 'bg-[#E8B923] text-[#0B0B0B] border-[#E8B923]'
                           : 'bg-[#141414] border-[#2A2A2A] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017]' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor;

                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="px-2 text-gray-500">…</span>';
                    echo '<a href="' . htmlspecialchars(buildQuery(['page' => $totalPages])) . '" class="px-4 py-2.5 rounded-xl border border-[#2A2A2A] bg-[#141414] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017] text-sm transition-colors">' . $totalPages . '</a>';
                }
                ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars(buildQuery(['page' => $page + 1])) ?>"
                       class="px-5 py-2.5 rounded-xl border border-[#2A2A2A] bg-[#141414] text-gray-300 hover:border-[#D4A017] hover:text-[#D4A017] text-sm font-medium transition-colors">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>