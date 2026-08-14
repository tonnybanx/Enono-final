<?php
// includes/footer.php

// Reuse path helpers from header, or detect again if missing
if (!isset($publicPrefix) || !isset($rootPrefix)) {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $inPublic   = (bool) preg_match('#/Public(/|$)#i', $scriptPath);
    $publicPrefix = $inPublic ? '' : 'Public/';
    $rootPrefix   = $inPublic ? '../' : '';
}

if (!isset($resolvedLogo)) {
    if (!empty($logoUrl)) {
        if (
            str_starts_with($logoUrl, 'http') ||
            str_starts_with($logoUrl, '../') ||
            str_starts_with($logoUrl, '/')
        ) {
            $resolvedLogo = $logoUrl;
        } else {
            $resolvedLogo = $rootPrefix . ltrim($logoUrl, '/');
        }
    } else {
        $resolvedLogo = $rootPrefix . 'images/drum_logo.jpg';
    }
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$companyName = $company['company_name'] ?? 'Enono';
$tagline     = $company['tagline'] ?? 'Market';
$shortDesc   = $company['short_description'] ?? "Connecting you to Africa's richest cultural heritage.";
?>

<!-- ==================== FOOTER ==================== -->
<footer class="bg-[#2C1A0F] text-white pt-16 pb-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-10">

            <!-- Brand -->
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <img src="<?= e($resolvedLogo) ?>"
                         alt="<?= e($companyName) ?>"
                         class="h-12 rounded">
                    <div>
                        <div class="heading-font text-3xl"><?= e($companyName) ?></div>
                        <span class="text-md text-[#fbbf24]"><?= e($tagline) ?></span>
                    </div>
                </div>
                <p class="text-white/70 text-sm mt-3"><?= e($shortDesc) ?></p>
            </div>

            <!-- Shop -->
            <div>
                <h4 class="font-semibold mb-4">Shop</h4>
                <ul class="space-y-2 text-sm text-white/70">
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-white">
                            Kanzu Collection
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-white">
                            Lubugo Sheets
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-white">
                            Traditional Pipes
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-white">
                            Cultural Jewelry
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h4 class="font-semibold mb-4">Company</h4>
                <ul class="space-y-2 text-sm text-white/70">
                    <li>
                        <a href="<?= e($publicPrefix) ?>aboutus.php" class="hover:text-white">
                            About us
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>blog.php" class="hover:text-white">
                            Blog
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>heritage.php" class="hover:text-white">
                            Heritage
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>contactus.php" class="hover:text-white">
                            Contact Us
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Social -->
            <div>
                <h4 class="font-semibold mb-4">Follow Us</h4>
                <div class="flex gap-5 text-2xl">
                    <a href="<?= e($company['instagram_url'] ?? '#') ?>"
                       <?= !empty($company['instagram_url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="fa-brands fa-instagram hover:text-amber-400 transition-colors"></i>
                    </a>
                    <a href="<?= e($company['facebook_url'] ?? '#') ?>"
                       <?= !empty($company['facebook_url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="fa-brands fa-facebook hover:text-amber-400 transition-colors"></i>
                    </a>
                    <a href="<?= e($company['tiktok_url'] ?? '#') ?>"
                       <?= !empty($company['tiktok_url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="fa-brands fa-tiktok hover:text-amber-400 transition-colors"></i>
                    </a>
                </div>
            </div>

        </div>

        <div class="text-center text-white/50 text-sm mt-16 border-t border-white/10 pt-8">
            © <?= date('Y') ?> <?= e($companyName) ?> <?= e($tagline) ?> • All Rights Reserved
        </div>
    </div>
</footer>

</body>
</html>