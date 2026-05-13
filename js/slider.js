// Live, Rest, Gather — three independent lightbox galleries (live / rest / gather)
document.addEventListener('DOMContentLoaded', function () {
    const exploreSection = document.getElementById('explore');
    if (!exploreSection) return;

    const categories = ['live', 'rest', 'gather'];
    const sliders = {};

    categories.forEach((cat) => {
        const lightbox = document.getElementById(`lrg-lightbox-${cat}`);
        if (!lightbox) return;
        const swiperEl = lightbox.querySelector('.lrg-lightbox__swiper');
        const counterEl = lightbox.querySelector('.lrg-lightbox__counter');
        if (!swiperEl) return;

        const totalSlides = swiperEl.querySelectorAll('.swiper-slide').length;
        const updateCounter = (index) => {
            if (counterEl) counterEl.textContent = `${index + 1} of ${totalSlides}`;
        };

        const slider = new Swiper(swiperEl, {
            direction: 'horizontal',
            loop: totalSlides > 1,
            speed: 600,
            navigation: {
                nextEl: `#lrg-lightbox-${cat} .swiper-button-next`,
                prevEl: `#lrg-lightbox-${cat} .swiper-button-prev`,
            },
            effect: 'fade',
            fadeEffect: { crossFade: true },
            // Keyboard wired manually below so only the open lightbox responds.
            keyboard: { enabled: false },
            on: {
                slideChange: function () {
                    updateCounter(this.realIndex);
                }
            }
        });

        sliders[cat] = { lightbox, slider, updateCounter };
    });

    const openLightbox = (cat, index) => {
        const entry = sliders[cat];
        if (!entry) return;
        const { lightbox, slider, updateCounter } = entry;
        // Show first so Swiper can measure the container (slideToLoop on a
        // display:none swiper sticks at slide 0 — same race as before).
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lrg-lightbox-open');

        requestAnimationFrame(() => {
            slider.update();
            slider.slideToLoop(index, 0);
            updateCounter(index);
        });
    };

    const closeAll = () => {
        Object.values(sliders).forEach(({ lightbox }) => {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
        });
        document.body.classList.remove('lrg-lightbox-open');
    };

    // Open on hero / thumb click — dispatch by data-lightbox-target
    exploreSection.querySelectorAll('[data-lightbox-target]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const cat = el.getAttribute('data-lightbox-target');
            const idx = parseInt(el.getAttribute('data-lightbox-index'), 10);
            if (Number.isFinite(idx) && sliders[cat]) openLightbox(cat, idx);
        });
    });

    // Close handlers (backdrop, ✕ button, in-slide CTA buttons) for all lightboxes
    document.querySelectorAll('.lrg-lightbox [data-lightbox-close]').forEach((el) => {
        el.addEventListener('click', (e) => {
            // CTAs are anchor links — let them navigate, just close the modal.
            if (el.tagName === 'A' && el.getAttribute('href')?.startsWith('#')) {
                closeAll();
                return;
            }
            e.preventDefault();
            closeAll();
        });
    });

    // Esc closes; ← / → only acts on the currently open lightbox
    document.addEventListener('keydown', (e) => {
        const openEntry = Object.values(sliders).find(({ lightbox }) =>
            lightbox.classList.contains('is-open')
        );
        if (!openEntry) return;
        if (e.key === 'Escape') closeAll();
        else if (e.key === 'ArrowRight') openEntry.slider.slideNext();
        else if (e.key === 'ArrowLeft') openEntry.slider.slidePrev();
    });
});
