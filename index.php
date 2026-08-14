<?php
/**
 * index.php – Home page (project root)
 */

require_once __DIR__ . '/config/database-connection.php';

$pageTitle = 'Home';
$activeNav = 'home';

// Company profile (needed by header/footer)
$stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoUrl = !empty($company['logo_url'])
    ? ltrim($company['logo_url'], '/')
    : 'images/drum_logo.jpg';

// Featured products (+ default variant + its image)
$productsStmt = $pdo->query("
    SELECT 
        p.id,
        p.name,
        p.short_description,
        p.base_price AS price,
        (
            SELECT pv.image_url 
            FROM product_variants pv 
            WHERE pv.product_id = p.id AND pv.is_active = 1 
            ORDER BY pv.id ASC 
            LIMIT 1
        ) AS image,
        (
            SELECT pv.id 
            FROM product_variants pv 
            WHERE pv.product_id = p.id AND pv.is_active = 1 
            ORDER BY pv.id ASC 
            LIMIT 1
        ) AS default_variant_id
    FROM products p
    WHERE p.is_active = 1 AND p.is_featured = 1
    ORDER BY p.id
    LIMIT 8
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Categories
$categoriesStmt = $pdo->query("
    SELECT id, name, description 
    FROM categories 
    WHERE is_active = 1 
    ORDER BY sort_order, name
");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Safe fallback image
$fallbackImage = 'images/drum_logo.jpg';

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .product-card {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .product-card:hover { transform: translateY(-8px); }
    .product-card img { transition: transform 0.6s ease; }
    .product-card:hover img { transform: scale(1.08); }

    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
</style>

<!-- ==================== HERO ==================== -->
<section class="bg-cover bg-center min-h-screen flex items-center relative"
         style="background-image: url('https://media.istockphoto.com/id/695152194/photo/african-drums.jpg?s=612x612&w=0&k=20&c=ixMwsApSM1kd1FMVephgxhmU5Xkm6b4Q2P09qOp2ZQQ=')">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="max-w-7xl mx-auto px-6 md:px-10 text-white relative z-10 py-24 w-full">
        <div class="max-w-7xl">
            <p class="uppercase tracking-[0.25em] text-sm md:text-base mb-6 text-white/90">
                Handmade in Uganda
            </p>
            <h1 class="heading-font text-5xl sm:text-6xl md:text-7xl lg:text-8xl font-bold leading-[1.05] tracking-tight">
                Heritage you can hold in your hands.
            </h1>
            <p class="mt-8 text-lg md:text-xl lg:text-2xl max-w-4xl leading-relaxed text-white/95">
                Every piece tells a story — shaped by the potters, carvers and weavers
                keeping Uganda’s living traditions alive.
            </p>
            <div class="mt-12 flex flex-wrap gap-5">
                <a href="Public/shopping.php"
                   class="inline-block bg-[#9b4925] text-white px-10 py-4 rounded-2xl font-medium text-lg hover:bg-[#7a3a1d] transition-colors">
                    Shop the collection
                </a>
                <a href="Public/aboutus.php"
                   class="inline-block border-2 border-white px-10 py-4 rounded-2xl font-medium text-lg hover:bg-white hover:text-[#9b4925] transition-colors">
                    About Us
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ==================== EXPLORE THE CRAFT ==================== -->
<section class="py-20 bg-[#f9f5ef]">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-start mb-12">
            <h2 class="heading-font text-4xl md:text-5xl font-semibold">Explore the craft</h2>
            <p class="text-gray-600 mt-2">All categories of traditional craftsmanship and heritage</p>
        </div>

        <div class="flex items-center gap-3">
            <button id="craft-prev"
                    class="md:hidden flex-shrink-0 w-10 h-10 rounded-full bg-white shadow-md border border-gray-200 flex items-center justify-center text-[#9b4925]">
                <i class="fa-solid fa-chevron-left text-sm"></i>
            </button>

            <div id="craft-scroll"
                 class="flex-1 flex md:grid md:grid-cols-4 gap-4 md:gap-6 overflow-x-auto pb-4 snap-x snap-mandatory scrollbar-hide">
                <?php foreach ($categories as $cat): ?>
                    <a href="Public/shopping.php?category=<?= urlencode($cat['name']) ?>">
                        <div class="category-card bg-white border border-[#e6ddd2] p-4 rounded-2xl cursor-pointer text-start hover:border-[#8B4513] shadow-sm hover:shadow-md transition-all min-w-[140px] sm:min-w-[190px] snap-start">
                            <p class="font-semibold text-lg"><?= e($cat['name']) ?></p>
                            <?php if (!empty($cat['description'])): ?>
                                <p class="text-sm text-gray-600 mt-1 hidden sm:block">
                                    <?= e($cat['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <button id="craft-next"
                    class="md:hidden flex-shrink-0 w-10 h-10 rounded-full bg-white shadow-md border border-gray-200 flex items-center justify-center text-[#9b4925]">
                <i class="fa-solid fa-chevron-right text-sm"></i>
            </button>
        </div>
    </div>
</section>

<!-- ==================== FEATURED PRODUCTS ==================== -->
<section id="shop" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-6 text-start">
        <h2 class="heading-font text-4xl md:text-5xl font-semibold mb-4">Featured Products</h2>
        <p class="text-gray-600 mb-12">All handmade by Ugandan artisans.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <?php foreach ($products as $product):
                $variantId = (int)($product['default_variant_id'] ?? 0);

                // Resolve image from DB
                $img = trim($product['image'] ?? '');
                if ($img !== '') {
                    if (
                        !str_starts_with($img, 'http://') &&
                        !str_starts_with($img, 'https://') &&
                        !str_starts_with($img, '/')
                    ) {
                        $img = ltrim($img, '/');
                    }
                } else {
                    $img = $fallbackImage;
                }
            ?>
                <div class="product-card bg-white rounded-xl overflow-hidden shadow-sm border border-gray-100 cursor-pointer"
                     onclick="window.location.href='Public/product-details.php?id=<?= (int)$product['id'] ?>&variant=<?= $variantId ?>'">
                    <div class="overflow-hidden bg-[#f5efe6]">
                        <img src="<?= e($img) ?>"
                             class="w-full h-80 object-cover"
                             alt="<?= e($product['name']) ?>"
                             onerror="this.onerror=null; this.src='<?= e($fallbackImage) ?>';">
                    </div>
                    <div class="p-6">
                        <h3 class="font-semibold text-lg"><?= e($product['name']) ?></h3>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2">
                            <?= e($product['short_description'] ?? '') ?>
                        </p>
                        <p class="mt-4 font-medium text-[#9b4925]">
                            USh <?= number_format((float)$product['price']) ?>
                        </p>

                        <?php if ($variantId > 0): ?>
                            <button type="button"
                                    onclick="event.stopPropagation(); addToCart(<?= $variantId ?>)"
                                    class="mt-6 w-full bg-[#8B4513] hover:bg-amber-900 text-white py-3.5 rounded-2xl text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                                <i class="fa-solid fa-cart-plus"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button type="button" disabled
                                    class="mt-6 w-full bg-gray-300 text-gray-500 py-3.5 rounded-2xl text-sm font-medium cursor-not-allowed">
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== ARTISAN SECTION ==================== -->
<section class="py-20 bg-[#f8f5f0]">
    <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-12 items-center">
        <img src="images/basketweaver.jpg"
             class="rounded-md w-full h-[500px] object-cover"
             alt="Artisan"
             onerror="this.onerror=null; this.src='<?= e($fallbackImage) ?>';">
        <div>
            <p class="uppercase text-amber-800 text-sm font-medium">MADE BY HAND</p>
            <h2 class="heading-font text-4xl md:text-5xl font-semibold mt-3 leading-tight">
                From the artisan's hands to your home
            </h2>
            <p class="mt-6 text-gray-600 leading-relaxed">
                When you order, Enono connects you directly with the craftsman who made it —
                supporting the families and clans who keep these traditions alive.
            </p>
        </div>
    </div>
</section>

<script>
    // Category horizontal scroll (mobile)
    (function () {
        const container = document.getElementById('craft-scroll');
        const prevBtn = document.getElementById('craft-prev');
        const nextBtn = document.getElementById('craft-next');
        if (!container || !prevBtn || !nextBtn) return;
        prevBtn.addEventListener('click', () => container.scrollBy({ left: -180, behavior: 'smooth' }));
        nextBtn.addEventListener('click', () => container.scrollBy({ left: 180, behavior: 'smooth' }));
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>