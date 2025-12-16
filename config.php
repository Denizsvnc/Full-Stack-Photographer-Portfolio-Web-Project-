<?php
// Veritabanı bağlantı ayarları
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kursad_portfolio');

// Site ayarları
define('SITE_URL', 'http://localhost/kursad');
define('ADMIN_URL', SITE_URL . '/admin');

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Veritabanı yoksa setup sayfasına yönlendir
    if ($e->getCode() == 1049) {
        if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
            die("
                <div style='font-family: Arial; padding: 40px; text-align: center;'>
                    <h2 style='color: #e74c3c;'>Veritabanı Bulunamadı</h2>
                    <p>Veritabanı henüz oluşturulmamış. Lütfen önce kurulumu yapın.</p>
                    <a href='setup.php' style='display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>
                        Kurulumu Başlat
                    </a>
                </div>
            ");
        }
    }
    // Diğer hatalar için normal mesaj
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Admin kontrolü
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Admin kontrolü ve yönlendirme
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

// Dil yönetimi
function getCurrentLanguage() {
    // Önce session'dan kontrol et
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], ['tr', 'en'])) {
        return $_SESSION['language'];
    }
    
    // Sonra cookie'den kontrol et
    if (isset($_COOKIE['language']) && in_array($_COOKIE['language'], ['tr', 'en'])) {
        $_SESSION['language'] = $_COOKIE['language'];
        return $_COOKIE['language'];
    }
    
    // Varsayılan dil: Türkçe
    return 'tr';
}

function setLanguage($lang) {
    if (in_array($lang, ['tr', 'en'])) {
        $_SESSION['language'] = $lang;
        setcookie('language', $lang, time() + (365 * 24 * 60 * 60), '/'); // 1 yıl
        return true;
    }
    return false;
}

// Proje verilerini mevcut dile göre getir
function getLocalizedProject($project, $field) {
    $lang = getCurrentLanguage();
    
    // Eğer çeviri kolonları varsa kullan
    if ($field === 'title') {
        if ($lang === 'en' && !empty($project['en_title'])) {
            return $project['en_title'];
        }
        if ($lang === 'tr' && !empty($project['tr_title'])) {
            return $project['tr_title'];
        }
        // Fallback: eski title kolonu
        return $project['title'] ?? '';
    }
    
    if ($field === 'description') {
        if ($lang === 'en' && !empty($project['en_description'])) {
            return $project['en_description'];
        }
        if ($lang === 'tr' && !empty($project['tr_description'])) {
            return $project['tr_description'];
        }
        // Fallback: eski description kolonu
        return $project['description'] ?? '';
    }
    
    return $project[$field] ?? '';
}

// Çeviri cache'i
static $translationCache = null;

// Çeviri fonksiyonu
function t($key, $params = []) {
    global $pdo;
    static $translationCache = null;
    
    // Cache'i yükle
    if ($translationCache === null) {
        $translationCache = [];
        try {
            $stmt = $pdo->query("SELECT translation_key, tr_value, en_value FROM translations");
            $translations = $stmt->fetchAll();
            foreach ($translations as $trans) {
                $translationCache[$trans['translation_key']] = [
                    'tr' => $trans['tr_value'],
                    'en' => $trans['en_value']
                ];
            }
        } catch(PDOException $e) {
            // Tablo yoksa boş cache
            $translationCache = [];
        }
    }
    
    // Mevcut dili al
    $lang = getCurrentLanguage();
    
    // Çeviriyi bul
    $translation = '';
    if (isset($translationCache[$key])) {
        $translation = $translationCache[$key][$lang] ?? $translationCache[$key]['tr'] ?? $key;
    } else {
        // Cache'de yoksa, veritabanından direkt çek (cache'i güncelle)
        try {
            $stmt = $pdo->prepare("SELECT tr_value, en_value FROM translations WHERE translation_key = ?");
            $stmt->execute([$key]);
            $trans = $stmt->fetch();
            if ($trans) {
                $translationCache[$key] = [
                    'tr' => $trans['tr_value'],
                    'en' => $trans['en_value']
                ];
                $translation = $trans[$lang . '_value'] ?? $trans['tr_value'] ?? $key;
            } else {
                // Çeviri bulunamadı, key'i döndür
                $translation = $key;
            }
        } catch(PDOException $e) {
            $translation = $key;
        }
    }
    
    // Parametreleri değiştir ({param} formatında)
    if (!empty($params)) {
        foreach ($params as $paramKey => $paramValue) {
            $translation = str_replace('{' . $paramKey . '}', $paramValue, $translation);
        }
    }
    
    return $translation;
}

// Çeviri cache'ini temizle (admin panelinde çeviri güncellendiğinde)
function clearTranslationCache() {
    global $translationCache;
    $translationCache = null;
}

// Layout settings'i getir
function getLayoutSettings() {
    global $pdo;
    static $settingsCache = null;
    
    if ($settingsCache !== null) {
        return $settingsCache;
    }
    
    $defaultSettings = [
        'horizontal_margin' => 'none',
        'vertical_margin' => 'medium'
    ];
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'layout_settings'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            $stmt = $pdo->query("SELECT * FROM layout_settings LIMIT 1");
            $settings = $stmt->fetch();
            if ($settings) {
                $settingsCache = [
                    'horizontal_margin' => $settings['horizontal_margin'] ?? 'none',
                    'vertical_margin' => $settings['vertical_margin'] ?? 'medium'
                ];
                return $settingsCache;
            }
        }
    } catch(PDOException $e) {
        // Hata durumunda varsayılan değerleri döndür
    }
    
    $settingsCache = $defaultSettings;
    return $settingsCache;
}

// Margin değerini px olarak döndür
function getMarginValue($marginType) {
    $settings = getLayoutSettings();
    $margin = $settings[$marginType] ?? 'none';
    
    switch ($margin) {
        case 'none':
            return '0';
        case 'medium':
            return '10';
        case 'large':
            return '20';
        default:
            return '0';
    }
}

// Manifest.json dosyasını otomatik güncelle
function updateManifestJson() {
    global $pdo;
    
    $manifestPath = __DIR__ . '/manifest.json';
    $knownCategorySlugs = ['editorial', 'advertising', 'film', 'cover'];
    $shortcuts = [];
    
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
                foreach ($dbCategories as $cat) {
                    // Sadece sayfa dosyası olan kategorileri ekle
                    $categoryFile = $cat['slug'] . '.php';
                    if (in_array($cat['slug'], $knownCategorySlugs) || file_exists(__DIR__ . '/' . $categoryFile)) {
                        $shortcuts[] = [
                            'name' => $cat['name'],
                            'short_name' => $cat['name'],
                            'description' => $cat['description'] ?? ($cat['name'] . ' çalışmaları'),
                            'url' => './' . $categoryFile,
                            'icons' => [
                                [
                                    'src' => './favicon.ico',
                                    'sizes' => '48x48'
                                ]
                            ]
                        ];
                    }
                }
            }
        }
        
        // Eğer veritabanından kategori gelmediyse, projects tablosundan kontrol et
        if (empty($shortcuts)) {
            foreach ($knownCategorySlugs as $slug) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE category = ?");
                $stmt->execute([$slug]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $categoryNames = [
                        'editorial' => 'Editorial',
                        'advertising' => 'Advertising',
                        'film' => 'Film',
                        'cover' => 'Cover'
                    ];
                    
                    $categoryDescriptions = [
                        'editorial' => 'Editorial çalışmalar',
                        'advertising' => 'Reklam çalışmaları',
                        'film' => 'Film ve video çalışmaları',
                        'cover' => 'Kapak çalışmaları'
                    ];
                    
                    $shortcuts[] = [
                        'name' => $categoryNames[$slug] ?? ucfirst($slug),
                        'short_name' => $categoryNames[$slug] ?? ucfirst($slug),
                        'description' => $categoryDescriptions[$slug] ?? (ucfirst($slug) . ' çalışmaları'),
                        'url' => './' . $slug . '.php',
                        'icons' => [
                            [
                                'src' => './favicon.ico',
                                'sizes' => '48x48'
                            ]
                        ]
                    ];
                }
            }
        }
    } catch(PDOException $e) {
        // Hata durumunda boş shortcuts
        $shortcuts = [];
    }
    
    // Manifest JSON oluştur
    $manifest = [
        'name' => 'Kürşad Karakuş Digital Portfolio',
        'short_name' => 'Kürşad Karakuş',
        'description' => 'Kürşad Karakuş\'un dijital portfolyosu. Editorial, advertising, film ve cover çalışmaları.',
        'start_url' => './index.php',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#1a1a1a',
        'orientation' => 'portrait-primary',
        'scope' => './',
        'icons' => [
            [
                'src' => './favicon.ico',
                'sizes' => '48x48',
                'type' => 'image/x-icon'
            ]
        ],
        'categories' => ['photography', 'portfolio', 'art'],
        'screenshots' => [],
        'shortcuts' => $shortcuts
    ];
    
    // JSON dosyasını yaz
    $jsonContent = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents($manifestPath, $jsonContent);
    
    return true;
}
?>

