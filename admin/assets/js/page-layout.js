// Page Layout Management JavaScript
(function() {
	// HTML escape fonksiyonu (inline.js yüklenmeden önce kullanılabilmesi için)
	function escapeHtml(text) {
		if (!text) return '';
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}
	
	document.addEventListener('DOMContentLoaded', function() {
		// Sayfa seçici değiştiğinde sayfayı yenile
		var pageSelector = document.getElementById('page-selector');
		if (pageSelector) {
			pageSelector.addEventListener('change', function() {
				var selectedPage = this.value;
				var url = new URL(window.location);
				url.searchParams.set('page', selectedPage);
				window.location.href = url.toString();
			});
		}
		
		// GridStack başlat
		if (typeof GridStack === 'undefined') {
			console.error('GridStack library not loaded');
			return;
		}
		
		var gridContainer = document.getElementById('grid-container');
		
		var grid = GridStack.init({
			column: 12,
			cellHeight: 80,
			margin: 10,
			resizable: {
				handles: 'e, se, s, sw, w, nw, n, ne'
			},
			draggable: {
				handle: '.grid-stack-item-content'
			},
			disableOneColumnMode: true,
			float: false
		});
		
		// Projeleri grid'e ekle
		var projectsData = document.getElementById('projects-data');
		
		if (projectsData) {
			try {
				var projectsText = projectsData.textContent || projectsData.innerText || '';
				
				if (!projectsText) {
					// Eğer textContent boşsa, innerHTML'den al
					projectsText = projectsData.innerHTML || '';
				}
				
				var projects = JSON.parse(projectsText);
				
				projects.forEach(function(project, index) {
					// Grid pozisyonlarını kontrol et - NULL ise 0 kullan
					var x = (project.grid_x !== null && project.grid_x !== undefined) ? parseInt(project.grid_x) : 0;
					var y = (project.grid_y !== null && project.grid_y !== undefined) ? parseInt(project.grid_y) : 0;
					var w = (project.grid_w !== null && project.grid_w !== undefined) ? parseInt(project.grid_w) : 4;
					var h = (project.grid_h !== null && project.grid_h !== undefined) ? parseInt(project.grid_h) : 2;
					
					var content = '<div class="grid-stack-item-content" data-id="' + project.id + '">';
					
					if (project.image_path) {
						var imgSrc = '../' + escapeHtml(project.image_path);
						var imgAlt = escapeHtml(project.title || '');
						content += '<img src="' + imgSrc + '" alt="' + imgAlt + '">';
					}
					
					var title = escapeHtml(project.title || '');
					content += '<h4>' + title + '</h4>';
					
					if (project.category_name) {
						var catName = escapeHtml(project.category_name);
						content += '<span class="category-badge">' + catName + '</span>';
					}
					
					content += '<div class="project-id">ID: #' + project.id + '</div>';
					content += '</div>';
					
					grid.addWidget({
						x: x,
						y: y,
						w: w,
						h: h,
						content: content,
						id: 'project-' + project.id
					});
				});
			} catch(e) {
				console.error('Error parsing projects data:', e);
				console.error('Error stack:', e.stack);
			}
		} else {
			console.warn('projects-data element not found!');
		}
		
		var hasChanges = false;
		var saveButton = document.getElementById('save-layout');
		
		// Değişiklikleri takip et
		grid.on('change', function(event, items) {
			hasChanges = true;
			if (saveButton) {
				saveButton.disabled = false;
				saveButton.classList.remove('opacity-50');
			}
		});
		
		// Kaydet butonu
		if (saveButton) {
			saveButton.addEventListener('click', function() {
				if (!hasChanges) {
					alert('Kaydedilecek değişiklik yok.');
					return;
				}
				
				var items = grid.save();
				var layoutData = [];
				
				items.forEach(function(item) {
					var projectId = item.id.replace('project-', '');
					layoutData.push({
						id: projectId,
						x: item.x,
						y: item.y,
						w: item.w,
						h: item.h
					});
				});
				
				// AJAX ile kaydet
				saveButton.disabled = true;
				saveButton.innerHTML = '<svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Kaydediliyor...';
				
				var saveUrl = window.location.pathname.replace('page-layout.php', 'save-layout.php');
				var selectedPage = document.getElementById('page-selector') ? document.getElementById('page-selector').value : 'index';
				if (selectedPage === 'all') {
					selectedPage = 'index';
				}
				saveUrl += '?page=' + encodeURIComponent(selectedPage);
				
				fetch(saveUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({ layout: layoutData, page: selectedPage })
				})
				.then(function(response) {
					if (!response) {
						throw new Error('Yanıt alınamadı');
					}
					if (response.status === undefined) {
						throw new Error('Geçersiz yanıt: status bilgisi yok');
					}
					if (!response.ok) {
						return response.text().then(function(text) {
							console.error('HTTP error response:', text);
							throw new Error('HTTP error! status: ' + response.status + ', message: ' + text);
						});
					}
					return response.json().catch(function(err) {
						console.error('JSON parse error:', err);
						return response.text().then(function(text) {
							console.error('Response text:', text);
							throw new Error('Geçersiz JSON yanıtı: ' + text);
						});
					});
				})
				.then(function(data) {
					if (!data) {
						throw new Error('Boş yanıt alındı');
					}
					if (data.success) {
						hasChanges = false;
						saveButton.disabled = true;
						saveButton.classList.add('opacity-50');
						saveButton.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Değişiklikleri Kaydet';
						
						// Başarı mesajı göster
						if (typeof showAdminNotification !== 'undefined') {
							showAdminNotification('Değişiklikler başarıyla kaydedildi! Sayfa yenileniyor...', 'success');
						} else {
							alert('Değişiklikler başarıyla kaydedildi!');
						}
						
						// Sayfayı 1 saniye sonra yenile
						setTimeout(function() {
							window.location.reload();
						}, 1000);
					} else {
						alert('Hata: ' + (data.message || 'Bilinmeyen hata'));
						saveButton.disabled = false;
						saveButton.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Değişiklikleri Kaydet';
					}
				})
				.catch(function(error) {
					var errorMessage = 'Bir hata oluştu: ' + (error.message || 'Bilinmeyen hata');
					if (error.message && (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
						errorMessage = 'Sunucuya bağlanılamadı. Lütfen:\n1. save-layout.php dosyasının mevcut olduğundan emin olun\n2. Sayfayı yenileyin\n3. Tarayıcı konsolunu kontrol edin (F12)';
					} else if (error.message && error.message.includes('status: undefined')) {
						errorMessage = 'Sunucu yanıtı alınamadı. save-layout.php dosyasını kontrol edin.';
					}
					
					alert(errorMessage);
					saveButton.disabled = false;
					saveButton.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Değişiklikleri Kaydet';
				});
			});
		}
	});
})();

