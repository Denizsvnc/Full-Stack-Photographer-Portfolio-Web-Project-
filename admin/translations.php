<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$message = '';
$messageType = '';

// Tablo var mı kontrol et
$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'translations'");
    $tableExists = $stmt->rowCount() > 0;
} catch(PDOException $e) {
    $tableExists = false;
}

// Form gönderimi - çeviri güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $translations = $_POST['translations'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Mevcut çevirileri güncelle
        $updateStmt = $pdo->prepare("UPDATE translations SET tr_value = ?, en_value = ? WHERE translation_key = ?");
        foreach ($translations as $key => $values) {
            $updateStmt->execute([
                $values['tr'] ?? '',
                $values['en'] ?? '',
                $key
            ]);
        }
        
        $pdo->commit();
        
        // Cache'i temizle
        clearTranslationCache();
        
        $message = 'Çeviriler başarıyla kaydedildi!';
        $messageType = 'success';
    } catch(PDOException $e) {
        $pdo->rollBack();
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Tüm çevirileri çek ve sayfalara göre grupla
$translationsByPage = [];
if ($tableExists) {
    try {
        $stmt = $pdo->query("SELECT * FROM translations ORDER BY translation_key ASC");
        $allTranslations = $stmt->fetchAll();
        
        // Sayfalara göre grupla
        foreach ($allTranslations as $trans) {
            $key = $trans['translation_key'];
            $prefix = explode('.', $key)[0];
            
            // Sayfa eşleştirmesi
            $page = 'other';
            if ($prefix === 'nav') {
                $page = 'navigation';
            } elseif ($prefix === 'general') {
                $page = 'homepage';
            } elseif ($prefix === 'category') {
                $page = 'category_pages';
            } elseif ($prefix === 'gallery') {
                $page = 'detail_pages';
            } elseif ($prefix === 'project') {
                $page = 'project_areas';
            } elseif ($prefix === 'footer') {
                $page = 'footer';
            }
            
            if (!isset($translationsByPage[$page])) {
                $translationsByPage[$page] = [];
            }
            
            $translationsByPage[$page][] = $trans;
        }
    } catch(PDOException $e) {
        $message = 'Çeviriler yüklenirken hata: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Sayfa isimleri ve açıklamaları
$pageInfo = [
    'navigation' => ['name' => 'Navigasyon (Header)', 'description' => 'Sayfanın üst kısmındaki menü linkleri'],
    'homepage' => ['name' => 'Anasayfa', 'description' => 'Ana sayfada gösterilen metinler'],
    'category_pages' => ['name' => 'Kategori Sayfaları', 'description' => 'Editorial, Advertising, Film, Cover sayfalarındaki metinler'],
    'detail_pages' => ['name' => 'Detay Sayfaları', 'description' => 'Proje detay sayfalarındaki (gallery.php) metinler'],
    'project_areas' => ['name' => 'Proje Alanları', 'description' => 'Proje kartlarında gösterilen metinler'],
    'footer' => ['name' => 'Footer', 'description' => 'Her sayfanın alt kısmında gösterilen footer metinleri (index, editorial, advertising, film, cover, gallery)']
];

$pageTitle = "Dil Yönetimi";
include 'includes/header.php';
?>

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Dil Yönetimi</h3>
            <div class="flex items-center gap-3">
                <?php if (!$tableExists): ?>
                    <a href="create_translations_table.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        Tabloyu Oluştur
                    </a>
                <?php endif; ?>
                <a href="projects.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Geri Dön</a>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="mx-5 mb-5 rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 dark:bg-success-500/15 dark:border-success-500/20' : 'bg-error-50 border-error-200 dark:bg-error-500/15 dark:border-error-500/20'; ?>">
            <p class="text-sm <?php echo $messageType === 'success' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!$tableExists): ?>
        <div class="mx-5 mb-5 rounded-lg border border-warning-200 bg-warning-50 p-4 dark:bg-warning-500/15 dark:border-warning-500/20">
            <p class="text-sm text-warning-600 dark:text-warning-400">
                ⚠ Translations tablosu henüz oluşturulmamış. <a href="create_translations_table.php" class="underline">Tabloyu oluşturun</a>
            </p>
        </div>
    <?php else: ?>
        <div class="border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
            <form method="POST" class="space-y-8">
                <input type="hidden" name="action" value="save">
                
                <?php 
                // Sayfa sırası
                $pageOrder = ['navigation', 'homepage', 'category_pages', 'detail_pages', 'project_areas', 'footer'];
                
                foreach ($pageOrder as $pageKey): 
                    if (!isset($translationsByPage[$pageKey]) || empty($translationsByPage[$pageKey])) {
                        continue;
                    }
                    $pageTranslations = $translationsByPage[$pageKey];
                    $pageData = $pageInfo[$pageKey] ?? ['name' => ucfirst($pageKey), 'description' => ''];
                ?>
                    <div class="space-y-4 border-b border-gray-200 dark:border-gray-700 pb-8 last:border-b-0">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-1">
                                <?php echo htmlspecialchars($pageData['name']); ?>
                            </h3>
                            <?php if (!empty($pageData['description'])): ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($pageData['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="space-y-3">
                            <?php foreach ($pageTranslations as $trans): 
                                $keyParts = explode('.', $trans['translation_key']);
                                $displayName = end($keyParts); // Son kısmı al (örn: "home", "editorial", "back_to_home")
                            ?>
                                <div class="grid grid-cols-12 gap-4 items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <div class="col-span-12 sm:col-span-3">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            <?php echo htmlspecialchars($displayName); ?>
                                        </label>
                                    </div>
                                    <div class="col-span-12 sm:col-span-4">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            <span class="inline-flex items-center gap-1">
                                                <span>🇹🇷</span>
                                                <span>Türkçe (TR)</span>
                                            </span>
                                        </label>
                                        <?php if ($pageKey === 'footer'): ?>
                                            <textarea 
                                                name="translations[<?php echo htmlspecialchars($trans['translation_key']); ?>][tr]" 
                                                rows="3"
                                                placeholder="Türkçe metin"
                                                class="w-full dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><?php echo htmlspecialchars($trans['tr_value'] ?? ''); ?></textarea>
                                        <?php else: ?>
                                            <input type="text" 
                                                   name="translations[<?php echo htmlspecialchars($trans['translation_key']); ?>][tr]" 
                                                   value="<?php echo htmlspecialchars($trans['tr_value'] ?? ''); ?>" 
                                                   placeholder="Türkçe metin"
                                                   class="w-full dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-span-12 sm:col-span-4">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                            <span class="inline-flex items-center gap-1">
                                                <span>🇬🇧</span>
                                                <span>İngilizce (EN)</span>
                                            </span>
                                        </label>
                                        <?php if ($pageKey === 'footer'): ?>
                                            <textarea 
                                                name="translations[<?php echo htmlspecialchars($trans['translation_key']); ?>][en]" 
                                                rows="3"
                                                placeholder="English text"
                                                class="w-full dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><?php echo htmlspecialchars($trans['en_value'] ?? ''); ?></textarea>
                                        <?php else: ?>
                                            <input type="text" 
                                                   name="translations[<?php echo htmlspecialchars($trans['translation_key']); ?>][en]" 
                                                   value="<?php echo htmlspecialchars($trans['en_value'] ?? ''); ?>" 
                                                   placeholder="English text"
                                                   class="w-full dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:ring-offset-gray-900">
                        Tüm Değişiklikleri Kaydet
                    </button>
                    <a href="projects.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Geri Dön</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>


<?php include 'includes/footer.php'; ?>






