<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';
$messageType = '';

// Veritabanında en_name ve en_description alanları yoksa ekle
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'en_name'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN en_name VARCHAR(255) DEFAULT NULL AFTER name");
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'en_description'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN en_description TEXT DEFAULT NULL AFTER description");
    }
} catch(PDOException $e) {
    // Tablo henüz yoksa veya başka bir hata varsa sessizce geç
}

// Silme işlemi
if ($action === 'delete' && $id) {
    try {
        // Kategoriyi kullanan proje var mı kontrol et
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE category_id = ?");
        $stmt->execute([$id]);
        $projectCount = $stmt->fetchColumn();
        
        if ($projectCount > 0) {
            $message = "Bu kategoriyi kullanan {$projectCount} proje var. Önce projelerin kategorisini değiştirin!";
            $messageType = 'danger';
            $action = 'list';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            
            // Manifest.json'u güncelle
            require_once dirname(__DIR__) . '/config.php';
            updateManifestJson();
            
            $message = 'Kategori başarıyla silindi!';
            $messageType = 'success';
            $action = 'list';
        }
    } catch(PDOException $e) {
        $message = 'Silme hatası: ' . $e->getMessage();
        $messageType = 'danger';
        $action = 'list';
    }
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $en_name = trim($_POST['en_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $en_description = trim($_POST['en_description'] ?? '');
    $display_order = intval($_POST['display_order'] ?? 0);
    
    // Slug oluştur (eğer boşsa name'den)
    if (empty($slug) && !empty($name)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
    }
    
    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, en_name, slug, description, en_description, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $en_name ?: null, $slug, $description ?: null, $en_description ?: null, $display_order]);
            
            // Manifest.json'u güncelle
            require_once dirname(__DIR__) . '/config.php';
            updateManifestJson();
            
            $message = 'Kategori başarıyla eklendi!';
            $messageType = 'success';
            $action = 'list';
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'Bu kategori adı veya slug zaten kullanılıyor!';
            } else {
                $message = 'Ekleme hatası: ' . $e->getMessage();
            }
            $messageType = 'danger';
        }
    } elseif ($action === 'edit' && $id) {
        try {
            // Slug değişikliği kontrolü
            $stmt = $pdo->prepare("SELECT slug FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $oldCategory = $stmt->fetch();
            
            if ($oldCategory && $oldCategory['slug'] !== $slug) {
                // Slug değiştiyse, projelerdeki category alanını da güncelle
                $updateProjects = $pdo->prepare("UPDATE projects SET category = ? WHERE category_id = ?");
                $updateProjects->execute([$slug, $id]);
            }
            
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, en_name = ?, slug = ?, description = ?, en_description = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $en_name ?: null, $slug, $description ?: null, $en_description ?: null, $display_order, $id]);
            
            // Manifest.json'u güncelle
            require_once dirname(__DIR__) . '/config.php';
            updateManifestJson();
            
            $message = 'Kategori başarıyla güncellendi!';
            $messageType = 'success';
            $action = 'list';
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'Bu kategori adı veya slug zaten kullanılıyor!';
            } else {
                $message = 'Güncelleme hatası: ' . $e->getMessage();
            }
            $messageType = 'danger';
        }
    }
}

// Düzenleme için kategori bilgilerini getir
$category = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) {
        $message = 'Kategori bulunamadı!';
        $messageType = 'danger';
        $action = 'list';
    }
}

// Liste sayfası
if ($action === 'list') {
    $pageTitle = "Kategoriler";
    include 'includes/header.php';
    
    // Tablo var mı kontrol et
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM categories LIMIT 1");
        $tableExists = true;
    } catch(PDOException $e) {
        $tableExists = false;
    }
    
    if (!$tableExists) {
        ?>
        <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:bg-brand-500/15 dark:border-brand-500/20">
            <p class="text-sm font-medium text-brand-800 dark:text-brand-400 mb-3">Kategoriler tablosu henüz oluşturulmamış.</p>
            <a href="migrate_categories.php" class="inline-flex items-center justify-center rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Kategorileri Oluştur</a>
        </div>
        <?php
        include 'includes/footer.php';
        exit;
    }
    
    $categories = $pdo->query("SELECT c.*, COUNT(p.id) as project_count FROM categories c LEFT JOIN projects p ON c.id = p.category_id GROUP BY c.id ORDER BY c.display_order ASC, c.name ASC")->fetchAll();
    ?>
    
    <?php if ($message): ?>
        <div class="mb-5 rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 dark:bg-success-500/15 dark:border-success-500/20' : 'bg-error-50 border-error-200 dark:bg-error-500/15 dark:border-error-500/20'; ?>">
            <p class="text-sm <?php echo $messageType === 'success' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-4 sm:px-6 sm:py-5">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Tüm Kategoriler</h3>
                <a href="?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4.5C12.4142 4.5 12.75 4.83579 12.75 5.25V11.25H18.75C19.1642 11.25 19.5 11.5858 19.5 12C19.5 12.4142 19.1642 12.75 18.75 12.75H12.75V18.75C12.75 19.1642 12.4142 19.5 12 19.5C11.5858 19.5 11.25 19.1642 11.25 18.75V12.75H5.25C4.83579 12.75 4.5 12.4142 4.5 12C4.5 11.5858 4.83579 11.25 5.25 11.25H11.25V5.25C11.25 4.83579 11.5858 4.5 12 4.5Z" fill="currentColor"/>
                    </svg>
                    Yeni Kategori Ekle
                </a>
            </div>
        </div>
        <div class="p-5 border-t border-gray-100 dark:border-gray-800 sm:p-6">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <div class="max-w-full overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">ID</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Ad</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Slug</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Açıklama</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Proje Sayısı</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Sıra</p></div></th>
                  <th class="px-5 py-3 sm:px-6"><div class="flex items-center"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">İşlemler</p></div></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center">
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Henüz kategori eklenmemiş.</p>
                            <a href="?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">İlk Kategoriyi Ekle</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">#<?php echo $cat['id']; ?></p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="font-medium text-gray-800 text-theme-sm dark:text-white/90"><?php echo htmlspecialchars($cat['name']); ?></p></td>
                            <td class="px-5 py-4 sm:px-6"><code class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400"><?php echo htmlspecialchars($cat['description'] ?? '-'); ?></p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="rounded-full bg-brand-50 px-2 py-0.5 text-theme-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-500"><?php echo $cat['project_count']; ?></p>
                            </td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400"><?php echo $cat['display_order']; ?></p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-2">
                                    <a href="?action=edit&id=<?php echo $cat['id']; ?>" class="inline-flex items-center gap-1 rounded-lg border border-brand-500 bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">Düzenle</a>
                                    <?php if ($cat['project_count'] == 0): ?>
                                        <a href="?action=delete&id=<?php echo $cat['id']; ?>" class="inline-flex items-center gap-1 rounded-lg border border-error-500 bg-error-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-error-600 btn-delete">Sil</a>
                                    <?php else: ?>
                                        <button class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-400 cursor-not-allowed" disabled title="Bu kategoride projeler olduğu için silinemez.">Sil</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
<?php } else { // Add/Edit Form ?>
    <?php
    $pageTitle = $action === 'add' ? "Yeni Kategori Ekle" : "Kategori Düzenle";
    include 'includes/header.php';
    ?>
    
    <?php if ($message): ?>
        <div class="mb-5 rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 dark:bg-success-500/15 dark:border-success-500/20' : 'bg-error-50 border-error-200 dark:bg-error-500/15 dark:border-error-500/20'; ?>">
            <p class="text-sm <?php echo $messageType === 'success' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="px-5 py-4 sm:px-6 sm:py-5">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90"><?php echo $action === 'add' ? 'Yeni Kategori Ekle' : 'Kategori Düzenle'; ?></h3>
                <a href="categories.php" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Geri Dön</a>
            </div>
        </div>
        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
        <form method="POST" class="space-y-6">
            <!-- Türkçe Bilgiler -->
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">🇹🇷 Türkçe</h4>
                <div class="space-y-4">
                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kategori Adı <span class="text-error-500">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" required class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                    </div>
                    <div>
                        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Açıklama</label>
                        <textarea id="description" name="description" rows="2" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- İngilizce Bilgiler -->
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">🇬🇧 English</h4>
                <div class="space-y-4">
                    <div>
                        <label for="en_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Category Name</label>
                        <input type="text" id="en_name" name="en_name" value="<?php echo htmlspecialchars($category['en_name'] ?? ''); ?>" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" placeholder="English name (optional)">
                    </div>
                    <div>
                        <label for="en_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                        <textarea id="en_description" name="en_description" rows="2" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="English description (optional)"><?php echo htmlspecialchars($category['en_description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div>
                <label for="slug" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Slug <span class="text-error-500">*</span></label>
                <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($category['slug'] ?? ''); ?>" required pattern="[a-z0-9-]+" title="Sadece küçük harf, rakam ve tire kullanılabilir" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">URL'de kullanılacak kısa ad (örn: editorial, advertising)</p>
            </div>
            
            <div>
                <label for="display_order" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Görüntülenme Sırası</label>
                <input type="number" id="display_order" name="display_order" value="<?php echo htmlspecialchars($category['display_order'] ?? 0); ?>" min="0" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Düşük sayılar önce görüntülenir</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"><?php echo $action === 'add' ? 'Kategori Ekle' : 'Güncelle'; ?></button>
                <a href="categories.php" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">İptal</a>
            </div>
        </form>
        </div>
    </div>
    
    <script>
    // Name değiştiğinde otomatik slug oluştur
    document.getElementById('name').addEventListener('input', function() {
        const slugInput = document.getElementById('slug');
        if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
            let slug = this.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugInput.value = slug;
            slugInput.dataset.autoGenerated = 'true';
        }
    });
    
    // Slug manuel değiştirildiğinde auto-generated flag'ini kaldır
    document.getElementById('slug').addEventListener('input', function() {
        this.dataset.autoGenerated = 'false';
    });
    </script>
    
    <?php include 'includes/footer.php'; ?>
<?php } ?>

