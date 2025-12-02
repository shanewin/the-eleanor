// Explore slider initialization with staged content reveal + mobile captions
document.addEventListener('DOMContentLoaded', function() {
    const exploreSection = document.getElementById('explore');
    if (!exploreSection) return;

    const mobileCaption = exploreSection.querySelector('.mobile-slide-caption');

    const updateMobileCaption = () => {
        if (!mobileCaption) return;
        const activeContent = exploreSection.querySelector('.swiper-slide-active .slide-content');
        if (!activeContent) return;

        mobileCaption.classList.remove('mobile-caption-visible');

        const title = activeContent.querySelector('h3')?.textContent?.trim() || '';
        const description = activeContent.querySelector('p')?.textContent?.trim() || '';
        const button = activeContent.querySelector('.slide-button');
        const buttonText = button?.textContent?.trim() || '';
        const buttonHref = button?.getAttribute('href') || '#';

        let markup = '';
        if (title) {
            markup += `<h3>${title}</h3>`;
        }
        markup += '<div class="slide-divider"></div>';
        if (description) {
            markup += `<p>${description}</p>`;
        }
        if (buttonText) {
            markup += `<a href="${buttonHref}" class="slide-button">${buttonText}</a>`;
        }

        mobileCaption.innerHTML = markup;
        requestAnimationFrame(() => {
            mobileCaption.classList.add('mobile-caption-visible');
        });
    };

    const slider = new Swiper('#explore .swiper', {
        direction: 'horizontal',
        loop: true,
        speed: 800,
        navigation: {
            nextEl: '#explore .swiper-button-next',
            prevEl: '#explore .swiper-button-prev',
        },
        pagination: {
            el: '#explore .swiper-pagination',
            clickable: true,
            renderBullet: function (index, className) {
                return '<span class="' + className + '">' + (index + 1) + '</span>';
            },
        },
        effect: 'fade',
        fadeEffect: { crossFade: true },
        on: {
            init: function () {
                exploreSection.classList.add('slider-ready');
                updateMobileCaption();
            },
            slideChange: function () {
                updateMobileCaption();
            }
        }
    });
});