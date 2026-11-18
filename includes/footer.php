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
$tagline     = $company['tagline'] ?? 'Obuwanga Nennono Kuntiko';
$shortDesc   = $company['short_description'] ?? "Connecting you to Africa's richest cultural heritage.";
?>

<!-- ==================== FOOTER ==================== -->
<footer class="bg-black text-white pt-16 pb-10 border-t border-[#E8A92B]/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-10">

            <!-- Brand -->
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <img src="<?= e($resolvedLogo) ?>"
                         alt="<?= e($companyName) ?>"
                         class="h-12 rounded">
                    <div>
                        <div class="heading-font text-3xl text-[#F2B84B]"><?= e($companyName) ?></div>
                        <span class="heading-font text-md italic text-white"><?= e($tagline) ?></span>
                    </div>
                </div>
                <p class="text-gray-400 text-sm mt-3"><?= e($shortDesc) ?></p>
            </div>

            <!-- Shop -->
            <div>
                <h4 class="font-semibold mb-4 text-[#E8A92B]">Shop</h4>
                <ul class="space-y-2 text-sm text-gray-300">
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-[#E8A92B] transition-colors">
                            Kanzu Collection
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-[#E8A92B] transition-colors">
                            Lubugo Sheets
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-[#E8A92B] transition-colors">
                            Traditional Pipes
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>shopping.php" class="hover:text-[#E8A92B] transition-colors">
                            Cultural Jewelry
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h4 class="font-semibold mb-4 text-[#E8A92B]">Company</h4>
                <ul class="space-y-2 text-sm text-gray-300">
                    <li>
                        <a href="<?= e($publicPrefix) ?>aboutus.php" class="hover:text-[#E8A92B] transition-colors">
                            About us
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>blog.php" class="hover:text-[#E8A92B] transition-colors">
                            Blog
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>heritage.php" class="hover:text-[#E8A92B] transition-colors">
                            Heritage
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($publicPrefix) ?>contactus.php" class="hover:text-[#E8A92B] transition-colors">
                            Contact Us
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Social -->
            <div>
                <h4 class="font-semibold mb-4 text-[#E8A92B]">Follow Us</h4>
                <div class="flex gap-5 text-2xl text-gray-200">
                    <a href="<?= e($company['instagram_url'] ?? '#') ?>"
                       <?= !empty($company['instagram_url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="fa-brands fa-instagram hover:text-[#E8A92B] transition-colors"></i>
                    </a>
                    <a href="<?= e($company['facebook_url'] ?? '#') ?>"
                       <?= !empty($company['facebook_url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="fa-brands fa-facebook hover:text-[#E8A92B] transition-colors"></i>
                    </a>
                    <a href="<?= e($company['tiktok_url'] ?? '#') ?>"
                       <?= !empty($company['tiktok_url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="fa-brands fa-tiktok hover:text-[#E8A92B] transition-colors"></i>
                    </a>
                </div>
            </div>

        </div>

        <div class="text-center text-gray-500 text-sm mt-16 border-t border-[#E8A92B]/20 pt-8">
            © <?= date('Y') ?> <?= e($companyName) ?> <?= e($tagline) ?> • All Rights Reserved
        </div>
    </div>
</footer>

</body>
</html>