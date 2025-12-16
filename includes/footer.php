</div>

<!-- Footer SEO Section -->
<?php
// Mevcut sayfa identifier'ını belirle
$currentPageFile = basename($_SERVER['PHP_SELF']);
$pageIdentifier = str_replace('.php', '', $currentPageFile);

// Gallery sayfası için özel kontrol
if ($pageIdentifier === 'gallery' && isset($_GET['ident'])) {
    $pageIdentifier = 'gallery';
}

// Footer metnini çeviri sisteminden çek
$footerSeoText = '';
$footerKey = 'footer.' . $pageIdentifier;
$footerSeoText = t($footerKey);

// Eğer çeviri sisteminde yoksa, page_seo tablosundan çek (geriye dönük uyumluluk)
if (empty($footerSeoText) || $footerSeoText === $footerKey) {
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT footer_text FROM page_seo WHERE page_identifier = ?");
            $stmt->execute([$pageIdentifier]);
            $seoData = $stmt->fetch();
            if ($seoData && !empty($seoData['footer_text'])) {
                $footerSeoText = $seoData['footer_text'];
            }
        } catch(PDOException $e) {
            // Tablo yoksa veya hata varsa sessizce devam et
            $footerSeoText = '';
        }
    }
}
?>

<?php if (!empty($footerSeoText)): ?>
<footer class="footer-seo">
    <div>
        <div class="footer-seo-content">
            <?php echo nl2br(htmlspecialchars($footerSeoText)); ?>
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- PWA Install Prompt -->
<div id="pwa-install-prompt">
    <div id="pwa-install-prompt-title">Uygulamayı Yükle</div>
    <div id="pwa-install-prompt-text">
        Bu uygulamayı cihazınıza yükleyerek daha hızlı erişim sağlayabilirsiniz.
    </div>
    <div id="pwa-install-prompt-buttons">
        <button id="pwa-install-btn">Yükle</button>
        <button id="pwa-dismiss-btn">Daha Sonra</button>
    </div>
</div>

<div id="info-popup" class="aj-popup aj-hidden padding"> <span class="aj-close btn btn-circle"></span>
  <div class="aj-popup-content">
    <div id="main">
            <div id="under_top">
        <div class="one">
        <li class="Category">Representation</li>
    		 <li> <i class="title">Artandist</i><br>
            <br>
            Palaska Sok 31A.<br>
            Cihangir Beyoglu<br>
            34425 Istanbul<br>
            Turkey</li>
          <li> P +90.2122510060<br>
            F +90.2122510060 </li>
          <li>info@art<i>and</i>ist.com<br>
          <a href="http://www.artandist.com">www.art<i>and</i>ist.com</a></li>
          </div>
           <div class="two">
        <!-- 
<li class="Category"></il>
        	<li> <i class="title"> Lighthouse Photoagency</i><br/>
            <br/>
         Passeig de Gracia 37, 2-2<br/>
            Barcelona<br/>
            Catalunya 08007<br/>
            Spain</li>
          <li> P +34.934872329<br/>
            </li>
          <li>hello@<i>lh-photoagency</i>.com<br/>
          <a href="http://www.lh-photoagency.com">www.<i>lh-photoagency</i>.com</a></li>
 -->
	 </div>
        <div class="three">
        <li class="Category">Syndication
          </li><li> <i class="title">Blaublut Edition </i> <br>
            <br>
            Rupprechtstrasse 25<br>
            80636 <br>
            München<br>
            Germany</li>
          <li> P +49.8989057950<br>
            F +49.89890579520<br>
          </li>
          <li>info@<i>blaublut</i>-edition.com<br>
          <a href="http://www.blaublut-edition.com">www.<i>blaublut</i>-edition.com</a></li>
        </div>
      </div>
      
      
    </div>
  </div>
</div>


</body></html>

