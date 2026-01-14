<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$pageTitle = "SEO Yönetimi";

// Form işleme
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageId = $_POST['page_id'] ?? null;
    $pageIdentifier = $_POST['page_identifier'] ?? '';
    $seoTitle = $_POST['seo_title'] ?? '';
    $seoDescription = $_POST['seo_description'] ?? '';
    $footerText = $_POST['footer_text'] ?? '';
    
    try {
        if ($pageId) {
            // Güncelleme
            $stmt = $pdo->prepare("
                UPDATE page_seo 
                SET seo_title = ?, seo_description = ?, footer_text = ? 
                WHERE id = ?
            ");
            $stmt->execute([$seoTitle, $seoDescription, $footerText, $pageId]);
            $message = 'SEO bilgileri başarıyla güncellendi!';
            $messageType = 'success';
        } else {
            // Yeni ekleme
            $stmt = $pdo->prepare("
                INSERT INTO page_seo (page_identifier, seo_title, seo_description, footer_text) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$pageIdentifier, $seoTitle, $seoDescription, $footerText]);
            $message = 'SEO bilgileri başarıyla eklendi!';
            $messageType = 'success';
        }
    } catch(PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Sayfa listesini çek
try {
    $pages = $pdo->query("SELECT * FROM page_seo ORDER BY page_identifier ASC")->fetchAll();
} catch(PDOException $e) {
    // Tablo yoksa oluştur
    if ($e->getCode() == '42S02') {
        header('Location: create_seo_table.php');
        exit;
    }
    $pages = [];
}

// Düzenleme için seçilen sayfa
$editPage = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM page_seo WHERE id = ?");
    $stmt->execute([$editId]);
    $editPage = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="grid grid-cols-1 gap-4 md:gap-6">
  <!-- Başlık -->
  <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">SEO Yönetimi</h2>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Her sayfa için SEO başlık, açıklama ve footer metinlerini yönetin.</p>
  </div>

  <!-- Mesaj -->
  <?php if ($message): ?>
  <div class="rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 text-success-800' : 'bg-error-50 border-error-200 text-error-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <h3 class="mb-4 text-xl font-semibold text-gray-800 dark:text-white">
      <?php echo $editPage ? 'SEO Bilgilerini Düzenle' : 'Yeni Sayfa SEO Bilgisi Ekle'; ?>
    </h3>
    
    <form method="POST" action="">
      <?php if ($editPage): ?>
        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($editPage['id']); ?>">
        <input type="hidden" name="page_identifier" value="<?php echo htmlspecialchars($editPage['page_identifier']); ?>">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Sayfa: <strong><?php echo htmlspecialchars($editPage['page_identifier']); ?></strong>
          </label>
        </div>
      <?php else: ?>
        <div class="mb-4">
          <label for="page_identifier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Sayfa Tanımlayıcı <span class="text-red-500">*</span>
          </label>
          <input 
            type="text" 
            id="page_identifier" 
            name="page_identifier" 
            required
            placeholder="örn: index, editorial, advertising, film, cover, gallery"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          >
          <p class="mt-1 text-xs text-gray-500">Sayfa dosyasının adı (örn: index.php için "index")</p>
        </div>
      <?php endif; ?>

      <div class="mb-4">
        <label for="seo_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          SEO Başlık
        </label>
        <input 
          type="text" 
          id="seo_title" 
          name="seo_title" 
          value="<?php echo $editPage ? htmlspecialchars($editPage['seo_title']) : ''; ?>"
          placeholder="Sayfa başlığı (meta title)"
          class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        >
      </div>

      <div class="mb-4">
        <label for="seo_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          SEO Açıklama
        </label>
        <textarea 
          id="seo_description" 
          name="seo_description" 
          rows="3"
          placeholder="Sayfa açıklaması (meta description)"
          class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        ><?php echo $editPage ? htmlspecialchars($editPage['seo_description']) : ''; ?></textarea>
      </div>

      <div class="mb-4">
        <label for="footer_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Footer SEO Metni <span class="text-red-500">*</span>
        </label>
        <textarea 
          id="footer_text" 
          name="footer_text" 
          rows="5"
          required
          placeholder="Footer'da gösterilecek SEO metni"
          class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        ><?php echo $editPage ? htmlspecialchars($editPage['footer_text']) : ''; ?></textarea>
        <p class="mt-1 text-xs text-gray-500">Bu metin footer'da gösterilecek ve SEO için önemlidir.</p>
      </div>

      <div class="flex gap-3">
        <button 
          type="submit" 
          class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
        >
          <?php echo $editPage ? 'Güncelle' : 'Kaydet'; ?>
        </button>
        <?php if ($editPage): ?>
          <a 
            href="seo-management.php" 
            class="rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            İptal
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Sayfa Listesi -->
  <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="border-b border-gray-200 p-6 dark:border-gray-800">
      <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Mevcut Sayfalar</h3>
    </div>
    
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">Sayfa</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">SEO Başlık</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">Footer Metni</th>
            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300">İşlemler</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
          <?php if (empty($pages)): ?>
            <tr>
              <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                Henüz sayfa eklenmemiş. Yukarıdaki formdan ekleyebilirsiniz.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($pages as $page): ?>
              <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="font-medium text-gray-900 dark:text-white">
                    <?php echo htmlspecialchars($page['page_identifier']); ?>
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-sm text-gray-600 dark:text-gray-400">
                    <?php echo htmlspecialchars($page['seo_title'] ?: '-'); ?>
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-sm text-gray-600 dark:text-gray-400">
                    <?php echo htmlspecialchars(mb_substr($page['footer_text'] ?: '-', 0, 80)) . (mb_strlen($page['footer_text'] ?: '') > 80 ? '...' : ''); ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <a 
                    href="?edit=<?php echo $page['id']; ?>" 
                    class="text-brand-500 hover:text-brand-600 mr-3"
                  >
                    Düzenle
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>









