<?php
require_once __DIR__ . '/includes/config_loader.php';
requireAdmin();

$pageTitle = "İletişim Bilgileri";

// Tablo var mı kontrol et
$tableExists = false;
try {
    $pdo->query("SELECT 1 FROM contact_info LIMIT 1");
    $tableExists = true;
} catch(PDOException $e) {
    $tableExists = false;
}

// Form işleme
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp_numara = trim($_POST['whatsapp_numara'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $iframe_html = trim($_POST['iframe_html'] ?? '');
    $instagram_url = trim($_POST['instagram_url'] ?? '');
    
    // iframe_html kolonunun varlığını kontrol et
    $iframeColumnExists = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM contact_info LIKE 'iframe_html'");
        $iframeColumnExists = $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        $iframeColumnExists = false;
    }
    
    // whatsapp_numara kolonunun varlığını kontrol et
    $whatsappColumnExists = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM contact_info LIKE 'whatsapp_numara'");
        $whatsappColumnExists = $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        $whatsappColumnExists = false;
    }
    
    try {
        if ($tableExists) {
            // Mevcut kaydı kontrol et
            $stmt = $pdo->query("SELECT id FROM contact_info LIMIT 1");
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Güncelle
                $columns = ['phone', 'email', 'address', 'instagram_url'];
                $values = [$phone ?: null, $email ?: null, $address ?: null, $instagram_url ?: null];
                
                if ($iframeColumnExists) {
                    $columns[] = 'iframe_html';
                    $values[] = $iframe_html ?: null;
                }
                if ($whatsappColumnExists) {
                    $columns[] = 'whatsapp_numara';
                    $values[] = $whatsapp_numara ?: null;
                }
                
                $values[] = $existing['id'];
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $setClause = implode(' = ?, ', $columns) . ' = ?';
                
                $stmt = $pdo->prepare("UPDATE contact_info SET $setClause WHERE id = ?");
                $stmt->execute($values);
            } else {
                // Yeni kayıt ekle
                $columns = ['phone', 'email', 'address', 'instagram_url'];
                $values = [$phone ?: null, $email ?: null, $address ?: null, $instagram_url ?: null];
                
                if ($iframeColumnExists) {
                    $columns[] = 'iframe_html';
                    $values[] = $iframe_html ?: null;
                }
                if ($whatsappColumnExists) {
                    $columns[] = 'whatsapp_numara';
                    $values[] = $whatsapp_numara ?: null;
                }
                
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $columnNames = implode(', ', $columns);
                
                $stmt = $pdo->prepare("INSERT INTO contact_info ($columnNames) VALUES ($placeholders)");
                $stmt->execute($values);
            }
            
            $message = 'İletişim bilgileri başarıyla kaydedildi!';
            $messageType = 'success';
        }
    } catch(PDOException $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Mevcut bilgileri çek
$contactInfo = [
    'phone' => '',
    'whatsapp_numara' => '',
    'email' => '',
    'address' => '',
    'iframe_html' => '',
    'instagram_url' => ''
];

// iframe_html kolonunun varlığını kontrol et
$iframeColumnExists = false;
$whatsappColumnExists = false;
if ($tableExists) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM contact_info LIKE 'iframe_html'");
        $iframeColumnExists = $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        $iframeColumnExists = false;
    }
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM contact_info LIKE 'whatsapp_numara'");
        $whatsappColumnExists = $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        $whatsappColumnExists = false;
    }
}

if ($tableExists) {
    try {
        $stmt = $pdo->query("SELECT * FROM contact_info LIMIT 1");
        $info = $stmt->fetch();
        if ($info) {
            $contactInfo = [
                'phone' => $info['phone'] ?? '',
                'whatsapp_numara' => $info['whatsapp_numara'] ?? '',
                'email' => $info['email'] ?? '',
                'address' => $info['address'] ?? '',
                'iframe_html' => $info['iframe_html'] ?? '',
                'instagram_url' => $info['instagram_url'] ?? ''
            ];
        }
    } catch(PDOException $e) {
        // Hata durumunda boş değerler
    }
}

include 'includes/header.php';
?>

<?php if (!$tableExists): ?>
<div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:bg-brand-500/15 dark:border-brand-500/20">
    <p class="text-sm font-medium text-brand-800 dark:text-brand-400 mb-3">
        contact_info tablosu henüz oluşturulmamış.
    </p>
    <a href="create_contact_info_table.php" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
        Tabloyu Oluştur
    </a>
</div>
<?php endif; ?>

<?php if ($tableExists && !$iframeColumnExists): ?>
<div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:bg-brand-500/15 dark:border-brand-500/20">
    <p class="text-sm font-medium text-brand-800 dark:text-brand-400 mb-3">
        iframe_html kolonu henüz eklenmemiş. Google Maps iframe kullanmak için bu kolonu ekleyin.
    </p>
    <a href="add_iframe_column.php" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
        iframe Kolonunu Ekle
    </a>
</div>
<?php endif; ?>

<?php if ($tableExists && !$whatsappColumnExists): ?>
<div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:bg-brand-500/15 dark:border-brand-500/20">
    <p class="text-sm font-medium text-brand-800 dark:text-brand-400 mb-3">
        whatsapp_numara kolonu henüz eklenmemiş. WhatsApp entegrasyonu için bu kolonu ekleyin.
    </p>
    <a href="add_whatsapp_column.php" class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
        WhatsApp Kolonunu Ekle
    </a>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="mb-5 rounded-lg border p-4 <?php echo $messageType === 'success' ? 'bg-success-50 border-success-200 dark:bg-success-500/15 dark:border-success-500/20' : 'bg-error-50 border-error-200 dark:bg-error-500/15 dark:bg-error-500/20'; ?>">
    <p class="text-sm <?php echo $messageType === 'success' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'; ?>"><?php echo htmlspecialchars($message); ?></p>
</div>
<?php endif; ?>

<div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">İletişim Bilgileri</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">İletişim bilgilerini güncelleyin. Bu bilgiler info sayfasında gösterilecektir.</p>
    </div>
    
    <form method="POST" action="" class="p-5 sm:p-6">
        <div class="space-y-4">
            <!-- Telefon -->
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Telefon Numarası
                </label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    value="<?php echo htmlspecialchars($contactInfo['phone']); ?>"
                    placeholder="+90.2122510060"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                >
            </div>
            
            <!-- WhatsApp -->
            <?php if ($whatsappColumnExists): ?>
            <div>
                <label for="whatsapp_numara" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    WhatsApp Numarası
                </label>
                <input 
                    type="tel" 
                    id="whatsapp_numara" 
                    name="whatsapp_numara" 
                    value="<?php echo htmlspecialchars($contactInfo['whatsapp_numara']); ?>"
                    placeholder="+905551234567"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    WhatsApp'ta mesaj göndermek için kullanılacak numara. Ülke kodu ile birlikte girin (örn: +905551234567)
                </p>
            </div>
            <?php endif; ?>
            
            <!-- E-posta -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    E-posta Adresi
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($contactInfo['email']); ?>"
                    placeholder="info@artandist.com"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                >
            </div>
            
            <!-- Açık Adres -->
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Açık Adres
                </label>
                <textarea 
                    id="address" 
                    name="address" 
                    rows="3"
                    placeholder="Palaska Sok 31A., Cihangir Beyoglu, 34425 Istanbul, Turkey"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                ><?php echo htmlspecialchars($contactInfo['address']); ?></textarea>
            </div>
            
            <!-- Konum (Google Maps iframe) -->
            <?php if ($iframeColumnExists): ?>
            <div>
                <label for="iframe_html" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Google Maps iframe Kodu
                </label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Google Maps'ten aldığınız iframe HTML kodunu buraya yapıştırın. Google Maps'te konumunuzu açın, "Paylaş" butonuna tıklayın, "Haritayı yerleştir" sekmesini seçin ve iframe kodunu kopyalayın.
                </p>
                <textarea 
                    id="iframe_html" 
                    name="iframe_html" 
                    rows="6"
                    placeholder='<iframe src="https://www.google.com/maps/embed?pb=..." width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>'
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 font-mono text-sm"
                ><?php echo htmlspecialchars($contactInfo['iframe_html']); ?></textarea>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    <strong>Örnek:</strong> Google Maps'te konumunuzu açın → Paylaş → Haritayı yerleştir → HTML kodunu kopyalayın
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Instagram -->
            <div>
                <label for="instagram_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Instagram URL
                </label>
                <input 
                    type="url" 
                    id="instagram_url" 
                    name="instagram_url" 
                    value="<?php echo htmlspecialchars($contactInfo['instagram_url']); ?>"
                    placeholder="https://instagram.com/artandist"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-800 focus:border-brand-300 focus:ring-brand-500/10 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                >
            </div>
        </div>
        
        <div class="flex justify-end mt-6">
            <button 
                type="submit" 
                class="inline-flex items-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-6 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Kaydet
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
