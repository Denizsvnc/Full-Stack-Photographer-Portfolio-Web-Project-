// Inline JavaScript - Header and Footer functionality

// Colorbox initialization
(function() {
	if (typeof jQuery !== 'undefined' && typeof $.colorbox !== 'undefined') {
		$(document).ready(function(){
			$(".ajax").colorbox();
			$(".youtube").colorbox({iframe:true, innerWidth:640, innerHeight:390});
			$(".vimeo").colorbox({iframe:true, width:"90%", height:"90%"});
			$(".iframe").colorbox({iframe:true, width:"90%", height:"90%"});
			$(".inline").colorbox({inline:true, width:"50%"});
			$(".callbacks").colorbox({
				onOpen:function(){ alert('onOpen: colorbox is about to open'); },
				onLoad:function(){ alert('onLoad: colorbox has started to load the targeted content'); },
				onComplete:function(){ alert('onComplete: colorbox has displayed the loaded content'); },
				onCleanup:function(){ alert('onCleanup: colorbox has begun the close process'); },
				onClosed:function(){ alert('onClosed: colorbox has completely closed'); }
			});

			$('.non-retina').colorbox({rel:'group5', transition:'none'})
			$('.retina').colorbox({rel:'group5', transition:'none', retinaImage:true, retinaUrl:true});
		});
	}
})();

// Griddle.js initialization
(function() {
	if (typeof jQuery !== 'undefined' && typeof $.fn.griddle !== 'undefined') {
		$(function() {
			// Grid pozisyonları varsa Griddle.js'i kullanma
			var hasGridLayout = $('.grid .wrap').css('display') === 'grid' || $('.grid .wrap[style*="grid"]').length > 0;
			
			if (!hasGridLayout) {
				$('.grid .wrap').imagesLoaded(function() {
					$(this).griddle({
						maxHeightLastRow: 700,
						maxRatio: 4, 
						gutter: 0, 
						attributes: 'both',
						calculateSize: true,
						end: function(elm) {
							elm.children().css({
								opacity: 1
							});
						}
					});
				});
			} else {
				// Grid layout kullanılıyorsa sadece opacity'yi ayarla
				$('.grid .wrap').imagesLoaded(function() {
					$(this).children().css({
						opacity: 1
					});
				});
			}
		});
	}
})();

// Service Worker Registration
(function() {
	if ('serviceWorker' in navigator) {
		window.addEventListener('load', function() {
			var swUrl = document.querySelector('meta[name="sw-url"]');
			if (swUrl) {
				var swPath = swUrl.getAttribute('content');
				navigator.serviceWorker.register(swPath)
					.then(function(registration) {
						// Update check
						registration.addEventListener('updatefound', function() {
							var newWorker = registration.installing;
							newWorker.addEventListener('statechange', function() {
								if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
									// Yeni versiyon mevcut, kullanıcıya bildir
									if (confirm('Yeni versiyon mevcut! Sayfayı yenilemek ister misiniz?')) {
										window.location.reload();
									}
								}
							});
						});
					})
					.catch(function(error) {
						// Service Worker kayıt hatası - sessizce devam et
					});
			}
		});
	}
})();

// Language Dropdown
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var toggle = document.querySelector('.language-toggle');
		var dropdown = document.getElementById('language-dropdown');
		
		if (toggle && dropdown) {
			toggle.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var isOpen = dropdown.style.display === 'block';
				
				if (!isOpen) {
					// Dropdown'ı göster ve konumunu ayarla
					dropdown.style.display = 'block';
					
					// Toggle butonunun konumunu al
					var toggleRect = toggle.getBoundingClientRect();
					dropdown.style.top = (toggleRect.bottom + 8) + 'px';
					dropdown.style.left = (toggleRect.right - 160) + 'px';
				} else {
					dropdown.style.display = 'none';
				}
			});
			
			// Dışarı tıklandığında kapat
			document.addEventListener('click', function(e) {
				if (toggle && dropdown && !toggle.contains(e.target) && !dropdown.contains(e.target)) {
					dropdown.style.display = 'none';
				}
			});
			
			// Language dropdown linklerine hover efekti ekle
			var langLinks = dropdown.querySelectorAll('a');
			langLinks.forEach(function(link) {
				var originalBg = link.style.background || '';
				link.addEventListener('mouseenter', function() {
					this.style.background = '#f0f0f0';
				});
				link.addEventListener('mouseleave', function() {
					this.style.background = originalBg || (this.classList.contains('active') ? '#f5f5f5' : 'transparent');
				});
			});
		}
	});
})();

// PWA Install Prompt
(function() {
	var deferredPrompt;
	var installPrompt = document.getElementById('pwa-install-prompt');
	var installBtn = document.getElementById('pwa-install-btn');
	var dismissBtn = document.getElementById('pwa-dismiss-btn');
	
	if (!installPrompt || !installBtn || !dismissBtn) {
		return; // Elementler yoksa çık
	}
	
	// Daha önce dismiss edilmiş mi kontrol et
	var dismissed = localStorage.getItem('pwa-install-dismissed');
	var dismissedTime = dismissed ? parseInt(dismissed) : 0;
	var oneWeekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
	
	// beforeinstallprompt event - Chrome, Edge, Samsung Internet
	window.addEventListener('beforeinstallprompt', function(e) {
		e.preventDefault();
		deferredPrompt = e;
		
		// Eğer bir hafta içinde dismiss edilmediyse göster
		if (dismissedTime < oneWeekAgo) {
			installPrompt.style.display = 'block';
		}
	});
	
	// Install butonu
	installBtn.addEventListener('click', async function() {
		if (!deferredPrompt) {
			// Manuel yükleme talimatları göster
			alert('Uygulamayı yüklemek için:\n\nChrome/Edge: Adres çubuğundaki yükle butonuna tıklayın\nSafari (iOS): Paylaş > Ana Ekrana Ekle\nFirefox: Menü > Sayfayı Yükle');
			installPrompt.style.display = 'none';
			return;
		}
		
		deferredPrompt.prompt();
		var result = await deferredPrompt.userChoice;
		
		deferredPrompt = null;
		installPrompt.style.display = 'none';
	});
	
	// Dismiss butonu
	dismissBtn.addEventListener('click', function() {
		installPrompt.style.display = 'none';
		localStorage.setItem('pwa-install-dismissed', Date.now().toString());
	});
	
	// App yüklüyse prompt'u gösterme
	if (window.matchMedia('(display-mode: standalone)').matches || 
		window.navigator.standalone === true) {
		installPrompt.style.display = 'none';
	}
	
	// iOS için özel mesaj
	var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
	if (isIOS && !window.navigator.standalone) {
		// iOS'ta beforeinstallprompt event çalışmaz, manuel göster
		if (dismissedTime < oneWeekAgo) {
			setTimeout(function() {
				installPrompt.style.display = 'block';
				installBtn.textContent = 'Nasıl Yüklenir?';
				installBtn.onclick = function() {
					alert('iOS\'ta uygulamayı yüklemek için:\n\n1. Safari\'de bu sayfayı açın\n2. Paylaş butonuna (kare ve ok) tıklayın\n3. "Ana Ekrana Ekle" seçeneğini seçin');
					installPrompt.style.display = 'none';
					localStorage.setItem('pwa-install-dismissed', Date.now().toString());
				};
			}, 3000); // 3 saniye sonra göster
		}
	}
})();

// Mobile Hamburger Menu
(function() {
	if (typeof jQuery !== 'undefined') {
		$(document).ready(function() {
			// Close popup when clicking close button
			$(document).on('click', '.aj-close', function(e) {
				e.preventDefault();
				if (typeof AJPopup !== 'undefined') {
					AJPopup.close();
				}
			});

			// Mobile Hamburger Menu Toggle
			var menuToggle = $('#mobile-menu-toggle');
			var menuOverlay = $('#mobile-menu');
			var menuClose = $('#mobile-menu-close');

			if (menuToggle.length && menuOverlay.length) {
				menuToggle.on('click', function(e) {
					e.preventDefault();
					menuOverlay.addClass('active');
					$('body').css('overflow', 'hidden');
				});

				menuClose.on('click', function(e) {
					e.preventDefault();
					menuOverlay.removeClass('active');
					$('body').css('overflow', '');
				});

				// Overlay'e tıklandığında kapat
				menuOverlay.on('click', function(e) {
					if ($(e.target).is(menuOverlay)) {
						menuOverlay.removeClass('active');
						$('body').css('overflow', '');
					}
				});

				// Menü linkine tıklandığında kapat
				$('.mobile-menu-link').on('click', function() {
					menuOverlay.removeClass('active');
					$('body').css('overflow', '');
				});
			}
		});
	}
})();

// WhatsApp Link Hover Effect
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var whatsappLinks = document.querySelectorAll('.info-page-whatsapp-link');
		whatsappLinks.forEach(function(link) {
			link.addEventListener('mouseenter', function() {
				this.style.background = '#20BA5A';
			});
			link.addEventListener('mouseleave', function() {
				this.style.background = '#25D366';
			});
		});
	});
})();

