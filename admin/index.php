<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$pageTitle = "Dashboard";

// İstatistikler
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Kategorilere göre proje sayıları
$categories = $pdo->query("
    SELECT c.name, c.slug, COUNT(p.id) as count 
    FROM categories c 
    LEFT JOIN projects p ON p.category_id = c.id 
    GROUP BY c.id, c.name, c.slug 
    ORDER BY c.display_order ASC
")->fetchAll();

// Son eklenen projeler
$recentProjects = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM projects p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
")->fetchAll();

include 'includes/header.php';
?>

<div class="grid grid-cols-1 gap-4 md:gap-6">
  <!-- Stats Grid -->
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 md:gap-6">
    <!-- Total Projects -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-100 dark:bg-brand-500/20">
        <svg class="fill-brand-500 dark:fill-brand-400" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M3.25 5.5C3.25 4.25736 4.25736 3.25 5.5 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V18.5C20.75 19.7426 19.7426 20.75 18.5 20.75H5.5C4.25736 20.75 3.25 19.7426 3.25 18.5V5.5Z" fill=""/>
        </svg>
      </div>
      <div class="mt-5 flex items-end justify-between">
        <div>
          <span class="text-sm text-gray-500 dark:text-gray-400">Toplam Proje</span>
          <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90"><?php echo $totalProjects; ?></h4>
        </div>
      </div>
    </div>

    <?php foreach ($categories as $cat): ?>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
        <svg class="fill-gray-800 dark:fill-white/90" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M8.50391 4.25C8.50391 3.83579 8.83969 3.5 9.25391 3.5H15.2777C15.4766 3.5 15.6674 3.57902 15.8081 3.71967L18.2807 6.19234C18.4214 6.333 18.5004 6.52376 18.5004 6.72268V16.75C18.5004 17.1642 18.1646 17.5 17.7504 17.5H9.25391C8.83969 17.5 8.50391 17.1642 8.50391 16.75V4.25Z" fill=""/>
        </svg>
      </div>
      <div class="mt-5 flex items-end justify-between">
        <div>
          <span class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($cat['name']); ?></span>
          <h4 class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90"><?php echo $cat['count']; ?></h4>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent Projects Table -->
  <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5">
      <div class="flex items-center justify-between">
        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Son Eklenen Projeler</h3>
        <a href="projects.php?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 4.5C12.4142 4.5 12.75 4.83579 12.75 5.25V11.25H18.75C19.1642 11.25 19.5 11.5858 19.5 12C19.5 12.4142 19.1642 12.75 18.75 12.75H12.75V18.75C12.75 19.1642 12.4142 19.5 12 19.5C11.5858 19.5 11.25 19.1642 11.25 18.75V12.75H5.25C4.83579 12.75 4.5 12.4142 4.5 12C4.5 11.5858 4.83579 11.25 5.25 11.25H11.25V5.25C11.25 4.83579 11.5858 4.5 12 4.5Z" fill="currentColor"/>
          </svg>
          Yeni Proje
        </a>
      </div>
    </div>
    <div class="p-5 border-t border-gray-100 dark:border-gray-800 sm:p-6">
      <?php if (empty($recentProjects)): ?>
        <div class="text-center py-12">
          <p class="text-gray-500 dark:text-gray-400 mb-4">Henüz proje eklenmemiş.</p>
          <a href="projects.php?action=add" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
            İlk Projeyi Ekle
          </a>
        </div>
      <?php else: ?>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <div class="max-w-full overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="border-b border-gray-100 dark:border-gray-800">
                  <th class="px-5 py-3 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">ID</p>
                    </div>
                  </th>
                  <th class="px-5 py-3 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Başlık</p>
                    </div>
                  </th>
                  <th class="px-5 py-3 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kategori</p>
                    </div>
                  </th>
                  <th class="px-5 py-3 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Görsel</p>
                    </div>
                  </th>
                  <th class="px-5 py-3 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tarih</p>
                    </div>
                  </th>
                  <th class="px-5 py-3 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">İşlemler</p>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <?php foreach ($recentProjects as $project): ?>
                <tr>
                  <td class="px-5 py-4 sm:px-6">
                    <div class="flex items-center">
                      <p class="text-gray-500 text-theme-sm dark:text-gray-400">#<?php echo $project['id']; ?></p>
                    </div>
                  </td>
                  <td class="px-5 py-4 sm:px-6">
                    <div class="flex items-center">
                      <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90"><?php echo htmlspecialchars($project['title']); ?></p>
                    </div>
                  </td>
                  <td class="px-5 py-4 sm:px-6">
                    <div class="flex items-center">
                      <p class="rounded-full bg-brand-50 px-2 py-0.5 text-theme-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-500">
                        <?php echo htmlspecialchars($project['category_name'] ?? 'Kategori Yok'); ?>
                      </p>
                    </div>
                  </td>
                  <td class="px-5 py-4 sm:px-6">
                    <div class="flex items-center">
                      <?php if ($project['image_path']): ?>
                        <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                          <img src="../<?php echo htmlspecialchars($project['image_path']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" class="h-full w-full object-cover">
                        </div>
                      <?php else: ?>
                        <span class="text-gray-400 text-theme-xs">Görsel yok</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="px-5 py-4 sm:px-6">
                    <div class="flex items-center">
                      <p class="text-gray-500 text-theme-sm dark:text-gray-400"><?php echo htmlspecialchars($project['date'] ?? '-'); ?></p>
                    </div>
                  </td>
                  <td class="px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-2">
                      <a href="projects.php?action=edit&id=<?php echo $project['id']; ?>" class="inline-flex items-center gap-1 rounded-lg border border-brand-500 bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600">
                        Düzenle
                      </a>
                      <a href="projects.php?action=delete&id=<?php echo $project['id']; ?>" class="inline-flex items-center gap-1 rounded-lg border border-error-500 bg-error-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-error-600 btn-delete">
                        Sil
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
