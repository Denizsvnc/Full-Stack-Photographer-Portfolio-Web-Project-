<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--><html><!--<![endif]-->
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
    } catch(PDOException $e) {
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
<link rel="stylesheet" href="css/main_v%3D1.css">
<link rel="stylesheet" href="css/colorbox.css">
<link rel="stylesheet" href="css/inline.css">
<script src="js/jquery-1.8.3.min.js"></script>
<script src="js/jquery.colorbox.js"></script>
<script src="js/main.js"></script> 
<script src="js/modernizr-2.6.2-respond-1.1.0.min.js"></script> 
<script src="js/jquery.griddle.js"></script> 
<script src="js/inline.js"></script>
<script src="js/ascii.js"></script>
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
                GROUP BY c.id, c.name, c.slug, c.description, c.display_order
                HAVING project_count > 0
                ORDER BY c.display_order ASC, c.name ASC
            ");
            $dbCategories = $stmt->fetchAll();
            
            if (!empty($dbCategories)) {
                $classIndex = 2; // m2'den başla
                foreach ($dbCategories as $cat) {
                    // Çeviri anahtarı oluştur
                    $translationKey = 'nav.' . $cat['slug'];
                    $categoryName = t($translationKey);
                    // Eğer çeviri yoksa kategori adını kullan
                    if ($categoryName === $translationKey) {
                        $categoryName = $cat['name'];
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
    } catch(PDOException $e) {
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

// Toplam link sayısı: Home(1) + Kategoriler + Info(1) + Language(1)
$totalLinks = 1 + count($allMenuLinks) + 2;
// Hamburger menüyü daha erken aktif hale getir (1'den fazla kategori varsa)
$useHamburger = count($allMenuLinks) > 1;

// Hamburger menüye gidecek linkler (1'den fazla kategori varsa)
if ($useHamburger && count($allMenuLinks) > 0) {
    // İlk 1 kategori linkini görünür yap, geri kalanını hamburger'e
    $displayLinks = array_slice($allMenuLinks, 0, 1);
    $hamburgerLinks = array_slice($allMenuLinks, 1);
} else {
    $displayLinks = $allMenuLinks;
    $hamburgerLinks = [];
}
?>
<div class="container"> 
<header class="mainHeader">
	<div class="m1"><a href="index.php"><?php echo t('nav.home'); ?></a></div>
	
	<?php foreach ($displayLinks as $link): ?>
		<div class="<?php echo $link['class']; ?>"><a href="<?php echo $link['url']; ?>"><?php echo $link['text']; ?></a></div>
	<?php endforeach; ?>
	
	<?php if ($useHamburger && !empty($hamburgerLinks)): ?>
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

<?php if ($useHamburger && !empty($hamburgerLinks)): ?>
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
			<?php foreach ($hamburgerLinks as $link): ?>
				<a href="<?php echo $link['url']; ?>" class="mobile-menu-link"><?php echo $link['text']; ?></a>
			<?php endforeach; ?>
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

