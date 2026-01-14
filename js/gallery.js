// Gallery Page JavaScript - Complete FOUC Prevention
// This script ensures the page only becomes visible after Flickity is fully initialized

(function () {
    'use strict';

    console.log('[Gallery] Script started');

    var carousel = document.querySelector('.carousel');
    var galleryPage = document.querySelector('.gallery-detail-page');

    // Critical: Show page function
    function showGallery() {
        if (galleryPage) {
            console.log('[Gallery] Making page visible');
            galleryPage.classList.add('loaded');
        }
    }

    // Initialize Flickity with proper sequencing
    function initFlickity() {
        if (!carousel) {
            console.log('[Gallery] No carousel found, showing page immediately');
            showGallery();
            return;
        }

        console.log('[Gallery] Initializing Flickity...');

        try {
            // Initialize Flickity
            var flkty = new Flickity(carousel, {
                cellAlign: 'center',
                contain: true,
                pageDots: true,
                prevNextButtons: true,
                wrapAround: true,
                autoPlay: false, // Manuel kontrol edeceğiz
                adaptiveHeight: false,
                imagesLoaded: true,
                dragThreshold: 10,
                percentPosition: false,
                setGallerySize: true
            });

            console.log('[Gallery] Flickity initialized');

            // Dinamik autoPlay - Video süresine göre
            var autoPlayTimer = null;

            function startAutoPlay() {
                clearTimeout(autoPlayTimer);

                var currentSlide = flkty.selectedElement;
                var video = currentSlide.querySelector('video');
                var delay = 4000; // Varsayılan: Görsel için 4 saniye

                if (video) {
                    // Video varsa, video süresini al
                    if (video.duration && !isNaN(video.duration)) {
                        delay = video.duration * 1000; // Saniyeyi milisaniyeye çevir
                        console.log('[Gallery] Video detected, duration: ' + video.duration + 's');
                    } else {
                        // Video süresi henüz yüklenmediyse, metadata yüklendiğinde tekrar dene
                        video.addEventListener('loadedmetadata', function () {
                            if (video.duration && !isNaN(video.duration)) {
                                delay = video.duration * 1000;
                                console.log('[Gallery] Video metadata loaded, duration: ' + video.duration + 's');
                                startAutoPlay(); // Yeniden başlat
                            }
                        }, { once: true });
                        delay = 5000; // Geçici olarak 5 saniye bekle
                    }
                } else {
                    console.log('[Gallery] Image detected, using 4s delay');
                }

                autoPlayTimer = setTimeout(function () {
                    flkty.next();
                    startAutoPlay(); // Bir sonraki slide için tekrar başlat
                }, delay);
            }

            // Slide değiştiğinde autoPlay'i yeniden başlat
            flkty.on('change', function () {
                startAutoPlay();
                console.log('[Gallery] Flickity initialized');
            });



            // Event: When Flickity is ready
            flkty.on('ready', function () {
                console.log('[Gallery] Flickity ready event fired');
                carousel.style.opacity = '1';

                // Small delay to ensure layout is stable
                setTimeout(function () {
                    flkty.resize();
                    console.log('[Gallery] Flickity resized');
                    showGallery();
                    startAutoPlay(); // AutoPlay'i başlat
                }, 100);
            });

            // Event: On settle (after drag/resize)
            flkty.on('settle', function () {
                flkty.resize();
            });

            // Window resize handler
            var resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    flkty.resize();
                }, 200);
            });

        } catch (error) {
            console.error('[Gallery] Flickity initialization error:', error);
            // If Flickity fails, still show the page
            showGallery();
        }
    }

    // Main initialization - wait for images
    if (carousel) {
        var images = carousel.querySelectorAll('img');

        if (images.length > 0 && typeof imagesLoaded !== 'undefined') {
            console.log('[Gallery] Waiting for ' + images.length + ' images to load...');

            // Use imagesLoaded to wait for all images
            imagesLoaded(carousel, function () {
                console.log('[Gallery] All images loaded');
                initFlickity();
            });

            // Fallback timeout (max 4 seconds wait)
            setTimeout(function () {
                if (!galleryPage.classList.contains('loaded')) {
                    console.log('[Gallery] Timeout reached, forcing initialization');
                    initFlickity();
                }
            }, 4000);

        } else {
            // No images or imagesLoaded not available
            console.log('[Gallery] No images or imagesLoaded unavailable, initializing immediately');
            initFlickity();
        }
    } else {
        // No carousel, just show the page
        console.log('[Gallery] No carousel element found');
        showGallery();
    }

})();
