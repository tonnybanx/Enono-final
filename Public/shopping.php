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
    .product-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; cursor: pointer; }
    .product-card:hover { transform: translateY(-6px); }
    .product-card img { transition: transform 0.6s ease; }
    .product-card:hover img { transform: scale(1.05); }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    .category-btn.active {
        background-color: #8B4513 !important;
        color: white !important;
        border-color: #8B4513 !important;
    }
</style>

<div class="mx-auto">
    <div class="w-full bg-[#faf5ef]">
        <div class="mb-10 pt-10 max-w-7xl mx-auto px-6 pb-4">
            <p class="uppercase tracking-widest text-amber-800 text-sm font-medium">THE COLLECTION</p>
            <h1 class="heading-font text-2xl md:text-4xl font-semibold">Shop Buganda Crafts</h1>
        </div>

        <!-- Search (submit only on button / Enter) -->
        <form method="GET" action="shopping.php" class="max-w-7xl mx-auto px-6 pb-4">
            <!-- Keep category when searching -->
            <?php if ($currentCategory !== '' && strtolower($currentCategory) !== 'all'): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($currentCategory) ?>">
            <?php endif; ?>

            <div class="mb-6 max-w-2xl">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text"
                               name="search"
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               placeholder="Search crafts..."
                               class="w-full border border-gray-200 rounded-3xl py-4 pl-14 pr-6 focus:outline-none focus:border-[#8B4513] text-base">
                    </div>
                    <button type="submit"
                            class="px-6 py-3 rounded-3xl bg-[#8B4513] hover:bg-[#6B3410] text-white text-sm font-medium whitespace-nowrap">
                        Search
                    </button>
                    <?php if ($searchTerm !== ''): ?>
                        <a href="shopping.php<?= $currentCategory !== '' && strtolower($currentCategory) !== 'all'
                                ? '?category=' . urlencode($currentCategory)
                                : '' ?>"
                           class="px-5 py-3 rounded-3xl border border-gray-200 bg-white hover:bg-gray-50 text-sm font-medium whitespace-nowrap text-gray-700">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($searchTerm !== ''): ?>
                    <p class="mt-3 text-sm text-gray-600">
                        Results for
                        <span class="font-medium text-gray-900">“<?= htmlspecialchars($searchTerm) ?>”</span>
                        ·
                        <a href="shopping.php" class="text-[#8B4513] hover:underline">Back to all products</a>
                    </p>
                <?php endif; ?>
            </div>
        </form>

        <!-- Categories -->
        <div class="max-w-7xl mx-auto px-6 pb-4">
            <div class="flex flex-wrap gap-3 justify-start mb-8 scrollbar-hide overflow-x-auto pb-2">
                <a href="<?= htmlspecialchars(buildQuery(['category' => 'all', 'page' => 1, 'search' => $searchTerm])) ?>"
                   class="category-btn px-8 py-3 rounded-3xl text-sm font-medium border
                   <?= strtolower($currentCategory) === 'all' ? 'active bg-[#8B4513] text-white border-[#8B4513]' : 'bg-white border-gray-200 hover:bg-gray-50' ?>">
                    All
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= htmlspecialchars(buildQuery(['category' => $cat['name'], 'page' => 1, 'search' => $searchTerm])) ?>"
                       class="category-btn px-8 py-3 rounded-3xl text-sm font-medium border
                       <?= $currentCategory === $cat['name'] ? 'active bg-[#8B4513] text-white border-[#8B4513]' : 'bg-white border-gray-200 hover:bg-gray-50' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Products -->
    <div class="w-full px-6 pb-10 max-w-7xl mx-auto">

        <?php if ($totalProducts > 0): ?>
            <p class="text-sm text-gray-500 mb-6">
                Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalProducts) ?>
                of <?= number_format($totalProducts) ?> products
            </p>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <?php if (empty($products)): ?>
                <div class="col-span-full text-center py-20 text-gray-400">
                    <p class="text-2xl mb-3">No products found.</p>
                    <?php if ($searchTerm !== ''): ?>
                        <a href="shopping.php" class="text-[#8B4513] hover:underline text-sm">
                            Back to all products
                        </a>
                    <?php endif; ?>
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
                    <div class="product-card bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100"
                         onclick="window.location.href='product-details.php?id=<?= (int)$p['id'] ?>&variant=<?= $variantId ?>'">
                        <div class="overflow-hidden">
                            <img src="<?= htmlspecialchars($img) ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 class="w-full h-56 md:h-64 object-cover"
                                 onerror="this.onerror=null; this.src='../images/drum_logo.jpg'">
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold text-base leading-tight"><?= htmlspecialchars($p['name']) ?></h3>
                            <p class="text-sm text-gray-600 mt-2 line-clamp-2"><?= htmlspecialchars($p['short_description'] ?? '') ?></p>
                            <p class="mt-4 font-medium text-[#9b4925]"><?= formatPrice($p['base_price']) ?></p>

                            <?php if ($variantId > 0): ?>
                                <button type="button"
                                        onclick="event.stopPropagation(); addToCart(<?= $variantId ?>)"
                                        class="mt-6 w-full bg-[#8B4513] hover:bg-amber-900 text-white py-3 rounded-2xl text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                                    <i class="fa-solid fa-cart-plus"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button type="button" disabled
                                        class="mt-6 w-full bg-gray-300 text-gray-500 py-3 rounded-2xl text-sm font-medium cursor-not-allowed">
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
                       class="px-5 py-2.5 rounded-2xl border border-gray-200 bg-white hover:bg-gray-50 text-sm font-medium">
                        ← Previous
                    </a>
                <?php endif; ?>

                <?php
                $window = 2;
                $start  = max(1, $page - $window);
                $end    = min($totalPages, $page + $window);

                if ($start > 1) {
                    echo '<a href="' . htmlspecialchars(buildQuery(['page' => 1])) . '" class="px-4 py-2.5 rounded-2xl border border-gray-200 bg-white hover:bg-gray-50 text-sm">1</a>';
                    if ($start > 2) echo '<span class="px-2 text-gray-400">…</span>';
                }

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="<?= htmlspecialchars(buildQuery(['page' => $i])) ?>"
                       class="px-4 py-2.5 rounded-2xl border text-sm font-medium
                       <?= $i === $page
                           ? 'bg-[#8B4513] text-white border-[#8B4513]'
                           : 'bg-white border-gray-200 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor;

                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="px-2 text-gray-400">…</span>';
                    echo '<a href="' . htmlspecialchars(buildQuery(['page' => $totalPages])) . '" class="px-4 py-2.5 rounded-2xl border border-gray-200 bg-white hover:bg-gray-50 text-sm">' . $totalPages . '</a>';
                }
                ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= htmlspecialchars(buildQuery(['page' => $page + 1])) ?>"
                       class="px-5 py-2.5 rounded-2xl border border-gray-200 bg-white hover:bg-gray-50 text-sm font-medium">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>