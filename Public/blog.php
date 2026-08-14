<?php
require_once __DIR__ . '/../config/database-connection.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function resolveImagePath(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '../') || str_starts_with($path, '/')) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

function getCompanyProfile(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1 LIMIT 1");
    return $stmt->fetch() ?: [];
}

function getImageByCategory(PDO $pdo, string $category, int $position = 1): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM general_images
         WHERE category = :category AND is_active = 1
         ORDER BY sort_order ASC
         LIMIT 1 OFFSET :offset"
    );
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $position - 1, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ?: null;
}

function getBlogPosts(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT * FROM blog_posts
         WHERE is_active = 1
         ORDER BY sort_order ASC, published_at DESC"
    );
    return $stmt->fetchAll();
}

function formatPostDate(?string $date): string
{
    if (!$date) {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('F j, Y', $timestamp) : '';
}

$company = getCompanyProfile($pdo);
$logoImage = getImageByCategory($pdo, 'logo', 1);
$logoUrl = resolveImagePath($logoImage['url'] ?? ($company['logo_url'] ?? 'images/drum_logo.jpg'));

$posts = getBlogPosts($pdo);

$pageTitle = 'Blog';
$activeNav = 'blog';
$bodyBgClass = 'bg-white';
require __DIR__ . '/../includes/header.php';
?>

    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="text-center mb-16">
            <h1 class="text-5xl font-bold heading-font text-[#2C1A0F]">Stories of Craftsmanship</h1>
            <p class="text-xl text-gray-600 mt-4 max-w-2xl mx-auto">
                Discover the stories behind our products, the hands that create them, and the rich African heritage they carry.
            </p>
        </div>

        <div class="space-y-20">
            <?php foreach ($posts as $i => $post): ?>
                <?php $reversed = ($i % 2 === 1); ?>
                <div class="grid md:grid-cols-12 gap-10 items-center blog-card">
                    <div class="md:col-span-<?= $reversed ? '7 md:order-2' : '5' ?>">
                        <img src="<?= e(resolveImagePath($post['image_url'])) ?>" class="blog-image rounded-2xl shadow-xl max-h-[600px] w-full" alt="<?= e($post['title']) ?>">
                    </div>
                    <div class="md:col-span-<?= $reversed ? '5 md:order-1' : '7' ?>">
                        <div class="flex items-center gap-3 text-sm text-amber-600 mb-4">
                            <span class="px-4 py-1 bg-amber-100 rounded-full"><?= e($post['category']) ?></span>
                            <span class="text-gray-400">•</span>
                            <span><?= (int) $post['read_time_minutes'] ?> min read</span>
                        </div>
                        <h3 class="text-4xl font-semibold leading-tight mb-4"><?= e($post['title']) ?></h3>
                        <p class="text-gray-600 text-lg leading-relaxed"><?= e($post['excerpt']) ?></p>
                        <div class="mt-8 flex items-center gap-4">
                            <img src="<?= e(resolveImagePath($post['author_image_url'])) ?>" class="w-12 h-12 rounded-full object-cover">
                            <div>
                                <p class="font-medium"><?= e($post['author_name']) ?> • <?= e($post['author_country']) ?></p>
                                <p class="text-xs text-gray-500"><?= e(formatPostDate($post['published_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-20">
            <button onclick="alert('More stories about our products and artisans coming soon ✨')"
                    class="bg-[#2C1A0F] text-white px-12 py-5 rounded-3xl text-lg font-medium hover:bg-black transition">
                Read More Stories
            </button>
        </div>
    </div>

<?php require __DIR__ . '/../includes/footer.php'; ?>