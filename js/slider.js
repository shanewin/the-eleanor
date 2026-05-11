// Live, Rest, Gather — lightbox-driven gallery
document.addEventListener('DOMContentLoaded', function () {
    const exploreSection = document.getElementById('explore');
    if (!exploreSection) return;

    const lightbox = document.getElementById('lrg-lightbox');
    const swiperEl = lightbox?.querySelector('.lrg-lightbox__swiper');
    const counterEl = lightbox?.querySelector('.lrg-lightbox__counter');
    if (!lightbox || !swiperEl) return;

    const totalSlides = swiperEl.querySelectorAll('.swiper-slide').length;

    const updateCounter = (index) => {
        if (!counterEl) return;
        counterEl.textContent = `${index + 1} of ${totalSlides}`;
    };

    const slider = new Swiper(swiperEl, {
        direction: 'horizontal',
        loop: true,
        speed: 600,
        navigation: {
            nextEl: '#lrg-lightbox .swiper-button-next',
            prevEl: '#lrg-lightbox .swiper-button-prev',
        },
        effect: 'fade',
        fadeEffect: { crossFade: true },
        keyboard: { enabled: true },
        on: {
            slideChange: function () {
                updateCounter(this.realIndex);
            }
        }
    });

    const openLightbox = (index) => {
        // Show lightbox first so Swiper can measure its container.
        // If we call slideToLoop while the lightbox is display:none, Swiper
        // sees 0-width slides and can't navigate — stays stuck at slide 0.
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lrg-lightbox-open');

        requestAnimationFrame(() => {
            slider.update();
            slider.slideToLoop(index, 0);
            updateCounter(index);
        });
    };

    const closeLightbox = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lrg-lightbox-open');
    };

    // Open on hero / thumb click
    exploreSection.querySelectorAll('[data-lightbox-index]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const idx = parseInt(el.getAttribute('data-lightbox-index'), 10);
            if (Number.isFinite(idx)) openLightbox(idx);
        });
    });

    // Close handlers (backdrop, ✕ button, in-slide CTA buttons)
    lightbox.querySelectorAll('[data-lightbox-close]').forEach((el) => {
        el.addEventListener('click', (e) => {
            // Allow CTA links to navigate; just close the modal so the anchor scroll works.
            if (el.tagName === 'A' && el.getAttribute('href')?.startsWith('#')) {
                closeLightbox();
                return;
            }
            e.preventDefault();
            closeLightbox();
        });
    });

    // Esc to close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.classList.contains('is-open')) {
            closeLightbox();
        }
    });
});
