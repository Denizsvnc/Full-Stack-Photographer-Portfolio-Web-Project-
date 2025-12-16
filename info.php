<?php
require_once 'config.php';

$pageTitle = t('nav.info') . " - Kürşad Karakuş Digital Portfolio";

// İletişim bilgilerini veritabanından çek
$contactInfo = [
    'phone' => '',
    'whatsapp_numara' => '',
    'email' => '',
    'address' => '',
    'iframe_html' => '',
    'instagram_url' => ''
];

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'contact_info'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
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
    }
} catch(PDOException $e) {
    // Hata durumunda boş değerler
}

// Mevcut dili al
$currentLang = getCurrentLanguage();

// WhatsApp mesajı (dil seçimine göre)
$whatsappMessage = t('info.whatsapp_message');

// WhatsApp linki oluştur
function generateWhatsAppLink($phone, $message) {
    if (empty($phone)) {
        return '';
    }
    
    // Telefon numarasından + ve boşlukları temizle, sadece rakamları al
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    if (empty($cleanPhone)) {
        return '';
    }
    
    // WhatsApp link formatı: https://wa.me/905551234567?text=Merhabalar%20,
    $encodedMessage = urlencode($message);
    return "https://wa.me/{$cleanPhone}?text={$encodedMessage}";
}

$whatsappLink = generateWhatsAppLink($contactInfo['whatsapp_numara'], $whatsappMessage);

include 'includes/header.php';
?>

<section class="mainContent info-page-section">
    <div class="container info-page-container">
        <h1 class="info-page-title">
            <?php echo t('nav.info'); ?>
        </h1>
        
        <div class="info-page-content">
            <?php if (!empty($contactInfo['phone']) || !empty($contactInfo['whatsapp_numara']) || !empty($contactInfo['email']) || !empty($contactInfo['address']) || !empty($contactInfo['iframe_html']) || !empty($contactInfo['instagram_url'])): ?>
                
                <!-- Telefon -->
                <?php if (!empty($contactInfo['phone'])): ?>
                <div class="info-page-item">
                    <h3><?php echo t('info.phone'); ?></h3>
                    <p>
                        <a href="tel:<?php echo htmlspecialchars($contactInfo['phone']); ?>" class="info-page-link">
                            <?php echo htmlspecialchars($contactInfo['phone']); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- WhatsApp -->
                <?php if (!empty($contactInfo['whatsapp_numara']) && !empty($whatsappLink)): ?>
                <div class="info-page-item">
                    <h3><?php echo t('info.whatsapp'); ?></h3>
                    <p>
                        <a 
                            href="<?php echo htmlspecialchars($whatsappLink); ?>" 
                            target="_blank"
                            rel="noopener noreferrer"
                            class="info-page-whatsapp-link"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            <?php echo t('info.whatsapp_send_message'); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- E-posta -->
                <?php if (!empty($contactInfo['email'])): ?>
                <div class="info-page-item">
                    <h3><?php echo t('info.email'); ?></h3>
                    <p>
                        <a href="mailto:<?php echo htmlspecialchars($contactInfo['email']); ?>" class="info-page-link">
                            <?php echo htmlspecialchars($contactInfo['email']); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Adres -->
                <?php if (!empty($contactInfo['address'])): ?>
                <div class="info-page-item">
                    <h3><?php echo t('info.address'); ?></h3>
                    <p style="white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($contactInfo['address'])); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Harita (iframe) -->
                <?php if (!empty($contactInfo['iframe_html'])): ?>
                <div class="info-page-item">
                    <h3 style="margin-bottom: 15px;"><?php echo t('info.location'); ?></h3>
                    <div class="info-page-iframe-wrapper">
                        <?php 
                        // iframe HTML'ini olduğu gibi göster (admin tarafından güvenilir kabul edilir)
                        // iframe'in responsive olması için wrapper div kullanıyoruz
                        $iframe_html = $contactInfo['iframe_html'];
                        // width ve height attribute'larını kaldır, style ile kontrol edeceğiz
                        $iframe_html = preg_replace('/width="[^"]*"/i', '', $iframe_html);
                        $iframe_html = preg_replace('/height="[^"]*"/i', '', $iframe_html);
                        // style attribute ekle veya güncelle
                        if (strpos($iframe_html, 'style=') === false) {
                            $iframe_html = str_replace('<iframe', '<iframe class="info-page-iframe"', $iframe_html);
                        } else {
                            $iframe_html = preg_replace('/style="([^"]*)"/i', 'class="info-page-iframe" style="$1"', $iframe_html);
                        }
                        echo $iframe_html; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Instagram -->
                <?php if (!empty($contactInfo['instagram_url'])): ?>
                <div class="info-page-item">
                    <h3><?php echo t('info.instagram'); ?></h3>
                    <p>
                        <a 
                            href="<?php echo htmlspecialchars($contactInfo['instagram_url']); ?>" 
                            target="_blank"
                            rel="noopener noreferrer"
                            class="info-page-instagram-link"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                            <?php echo htmlspecialchars($contactInfo['instagram_url']); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p class="info-page-no-contact">
                    <?php echo t('info.no_contact_info'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="info-page-back-container">
            <a href="index.php" class="info-page-back-link">
                ← <?php echo t('general.back_to_portfolio'); ?>
            </a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
