<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!-->
<html><!--<![endif]-->

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <?php
    // Mevcut sayfa identifier'ını belirle
    $currentPageFile = basename($_SERVER['PHP_SELF']);
    $pageIdentifier = str_replace('.php', '', $currentPageFile);

    // Gallery sayfası için özel kontrol
    if ($pageIdentifier === 'gallery' && isset($_GET['ident'])) {
        $pageIdentifier = 'gallery';
    }

    // Veritabanından SEO bilgilerini çek
    $seoTitle = isset($pageTitle) ? $pageTitle : 'Kürşad Karakuş Digital Portfolio';
    $seoDescription = '';

    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT seo_title, seo_description FROM page_seo WHERE page_identifier = ?");
            $stmt->execute([$pageIdentifier]);
            $seoData = $stmt->fetch();
            if ($seoData) {
                if (!empty($seoData['seo_title'])) {
                    $seoTitle = $seoData['seo_title'];
                }
                if (!empty($seoData['seo_description'])) {
                    $seoDescription = $seoData['seo_description'];
                }
            }
        } catch (PDOException $e) {
            // Tablo yoksa veya hata varsa varsayılan değerleri kullan
        }
    }
    ?>
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1a1a1a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kürşad Karakuş">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#1a1a1a">
    <?php
    // SITE_URL tanımlı değilse, otomatik olarak belirle
    if (!defined('SITE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $path = dirname($scriptName);
        // Windows path'lerini düzelt
        $path = str_replace('\\', '/', $path);
        // Path'in sonunda / olmalı
        if ($path !== '/' && !empty($path)) {
            $path = rtrim($path, '/') . '/';
        }
        define('SITE_URL', $protocol . '://' . $host . $path);
    }
    ?>
    <meta name="msapplication-TileColor" content="#1a1a1a">
    <meta name="msapplication-config" content="<?php echo rtrim(SITE_URL, '/'); ?>/browserconfig.xml">
    <link rel="manifest" href="<?php echo rtrim(SITE_URL, '/'); ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo rtrim(SITE_URL, '/'); ?>/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@300;400;500&family=Montserrat:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/css/main.css?v=2">
    <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/css/colorbox.css">
    <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/css/inline.css">
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/jquery-1.8.3.min.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/jquery.colorbox.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/main.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/modernizr-2.6.2-respond-1.1.0.min.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/jquery.griddle.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/inline.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/ascii.js"></script>
    <?php if (isset($pageIdentifier) && $pageIdentifier === 'gallery'): ?>
        <!-- Flickity CSS must load before gallery.css -->
        <link rel="stylesheet" href="https://unpkg.com/flickity@2.3.0/dist/flickity.min.css">
        <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/css/gallery.css?v=<?php echo time(); ?>">
        <script src="https://unpkg.com/imagesloaded@5.0.0/imagesloaded.pkgd.min.js"></script>
        <script src="https://unpkg.com/flickity@2.3.0/dist/flickity.pkgd.min.js"></script>
        <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/gallery.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
    <meta name="sw-url" content="<?php echo rtrim(SITE_URL, '/'); ?>/sw.js">
</head>

<body>
    <!--[if lt IE 7]>
    <p class="chromeframe">You are using an <strong>outdated</strong> browser. Please 
    <a href="http://browsehappy.com/">upgrade your browser</a> or 
    <a href="http://www.google.com/chromeframe/?redirect=true">
    activate Google Chrome Frame</a> to improve your experience.</p>
<![endif]-->
    <?php
    // Kategorileri veritabanından dinamik olarak çek
    $allMenuLinks = []; // Tüm kategori linkleri
    $categoryCounts = [];
    $displayLinks = [];
    $hamburgerLinks = [];
    $useHamburger = false;
    $currentLang = getCurrentLanguage();
    $langs = [
        'tr' => ['code' => 'tr', 'name' => 'Türkçe', 'flag' => '🇹🇷'],
        'en' => ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧']
    ];

    if (isset($pdo)) {
        try {
            // Categories tablosu var mı kontrol et
            $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
            $categoriesTableExists = $stmt->rowCount() > 0;

            if ($categoriesTableExists) {
                // Veritabanından tüm kategorileri çek (proje sayısı > 0 olanlar)
                $stmt = $pdo->query("
                SELECT c.*, COUNT(DISTINCT p.id) as project_count 
                FROM categories c 
                LEFT JOIN projects p ON (p.category_id = c.id OR p.category = c.slug)
                GROUP BY c.id
                HAVING project_count > 0
                ORDER BY c.display_order ASC, c.name ASC
            ");
                $dbCategories = $stmt->fetchAll();

                if (!empty($dbCategories)) {
                    $classIndex = 2; // m2'den başla
                    foreach ($dbCategories as $cat) {
                        // Dil seçimine göre kategori adını belirle
                        $categoryName = $cat['name']; // Varsayılan Türkçe
                        if ($currentLang === 'en' && !empty($cat['en_name'])) {
                            $categoryName = $cat['en_name'];
                        }
                        // Eğer veritabanında yoksa çeviri dosyasından dene
                        $translationKey = 'nav.' . $cat['slug'];
                        $translatedName = t($translationKey);
                        if ($translatedName !== $translationKey) {
                            $categoryName = $translatedName;
                        }

                        // Sayfa dosyası varsa direkt kullan, yoksa category.php ile dinamik göster
                        $categoryFile = $cat['slug'] . '.php';
                        $knownCategories = ['editorial', 'advertising', 'film', 'cover'];
                        if (in_array($cat['slug'], $knownCategories) || file_exists(__DIR__ . '/../' . $categoryFile)) {
                            $categoryUrl = $categoryFile;
                        } else {
                            // Dinamik category.php kullan
                            $categoryUrl = 'category.php?slug=' . urlencode($cat['slug']);
                        }

                        $allMenuLinks[] = [
                            'class' => 'm' . $classIndex,
                            'url' => $categoryUrl,
                            'text' => $categoryName
                        ];
                        $categoryCounts[$cat['slug']] = $cat['project_count'];
                        $classIndex++;
                    }
                }
            }

            // Eğer veritabanından kategori gelmediyse, eski yöntemi kullan (geriye dönük uyumluluk)
            if (empty($allMenuLinks)) {
                $categories = ['editorial', 'advertising', 'film', 'cover'];
                $classIndex = 2;
                foreach ($categories as $cat) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE category = ?");
                    $stmt->execute([$cat]);
                    $count = $stmt->fetchColumn();
                    $categoryCounts[$cat] = $count;

                    if ($count > 0) {
                        $allMenuLinks[] = [
                            'class' => 'm' . $classIndex,
                            'url' => $cat . '.php',
                            'text' => t('nav.' . $cat)
                        ];
                        $classIndex++;
                    }
                }
            }
        } catch (PDOException $e) {
            // Hata durumunda eski kategorileri göster
            $categories = ['editorial', 'advertising', 'film', 'cover'];
            $classIndex = 2;
            foreach ($categories as $cat) {
                $categoryCounts[$cat] = 1;
                $allMenuLinks[] = [
                    'class' => 'm' . $classIndex,
                    'url' => $cat . '.php',
                    'text' => t('nav.' . $cat)
                ];
                $classIndex++;
            }
        }
    }

    // Hamburger menüyü aktif yap (1'den fazla kategori varsa)
    $useHamburger = count($allMenuLinks) > 1;


    // Masaüstünde TÜM kategoriler görünür
    $displayLinks = $allMenuLinks; // Tüm kategorileri göster
    $hamburgerLinks = []; // Hamburger'da ekstra kategori yok
    // Mobil için tüm kategoriler hamburger'de olacak
    $mobileHamburgerLinks = $allMenuLinks;
    ?>
    <div class="container">
        <header class="mainHeader">
            <div class="m1"><a href="index.php"><?php echo t('nav.home'); ?></a></div>

            <?php foreach ($displayLinks as $link): ?>
                <div class="<?php echo $link['class']; ?>"><a
                        href="<?php echo $link['url']; ?>"><?php echo $link['text']; ?></a></div>
            <?php endforeach; ?>

            <?php if ($useHamburger): ?>
                <!-- Hamburger Menu Button -->
                <div class="m-hamburger">
                    <button id="mobile-menu-toggle" class="mobile-menu-btn" type="button" aria-label="Menu">
                        <span class="hamburger-icon">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="m6"><a href="info.php"><?php echo t('nav.info'); ?></a></div>
            <div class="m7">
                <button class="language-toggle" type="button">
                    <span><?php echo $langs[$currentLang]['flag']; ?></span>
                    <span><?php echo strtoupper($currentLang); ?></span>
                    <span>▼</span>
                </button>
            </div>
        </header>

        <?php if ($useHamburger): ?>
            <!-- Mobile Hamburger Menu -->
            <div id="mobile-menu" class="mobile-menu-overlay">
                <div class="mobile-menu-content">
                    <div class="mobile-menu-header">
                        <h3>Menü</h3>
                        <button id="mobile-menu-close" class="mobile-menu-close" aria-label="Kapat">
                            <span></span>
                            <span></span>
                        </button>
                    </div>
                    <nav class="mobile-menu-nav">
                        <?php foreach ($mobileHamburgerLinks as $link): ?>
                            <a href="<?php echo $link['url']; ?>" class="mobile-menu-link"><?php echo $link['text']; ?></a>
                        <?php endforeach; ?>
                        <a href="info.php" class="mobile-menu-link"><?php echo t('nav.info'); ?></a>
                    </nav>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dropdown body'ye taşındı, navbar'ı engellemez -->
        <div id="language-dropdown">
            <?php foreach ($langs as $lang): ?>
                <a href="change_language.php?lang=<?php echo $lang['code']; ?>"
                    class="<?php echo $currentLang === $lang['code'] ? 'active' : ''; ?>">
                    <span><?php echo $lang['flag']; ?></span>
                    <span><?php echo $lang['name']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>