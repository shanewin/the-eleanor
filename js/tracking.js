/**
 * The Eleanor - User Activity Tracking Script
 * Tracks section visibility, clicks, and form interactions.
 */

(function() {
    const TRACKING_ENDPOINT = 'api/track.php';
    const SESSION_COOKIE_NAME = 'eleanor_tracking_id';
    const SESSION_EXPIRY_DAYS = 30;

    // --- Helper Functions ---

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // --- Core Tracking Logic ---

    let sessionId = getCookie(SESSION_COOKIE_NAME);
    if (!sessionId) {
        sessionId = generateUUID();
        setCookie(SESSION_COOKIE_NAME, sessionId, SESSION_EXPIRY_DAYS);
    }

    function logEvent(type, name, data = {}) {
        const payload = {
            sessionId: sessionId,
            type: type,
            name: name,
            data: data,
            timestamp: new Date().toISOString()
        };

        // Use sendBeacon for more reliability on page exit, fallback to fetch
        if (navigator.sendBeacon) {
            navigator.sendBeacon(TRACKING_ENDPOINT, JSON.stringify(payload));
        } else {
            fetch(TRACKING_ENDPOINT, {
                method: 'POST',
                body: JSON.stringify(payload),
                keepalive: true,
                headers: { 'Content-Type': 'application/json' }
            }).catch(err => console.error('Tracking log failed', err));
        }
    }

    // --- Intersection Observer for Sections ---

    const sectionTimers = {};
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const sectionId = entry.target.id || entry.target.tagName.toLowerCase();
            
            if (entry.isIntersecting) {
                // Section entered view
                sectionTimers[sectionId] = Date.now();
                logEvent('visibility', 'section_enter', { section: sectionId });
            } else {
                // Section left view
                if (sectionTimers[sectionId]) {
                    const timeSpent = Date.now() - sectionTimers[sectionId];
                    logEvent('visibility', 'section_leave', { 
                        section: sectionId, 
                        secondsSpent: Math.round(timeSpent / 1000) 
                    });
                    delete sectionTimers[sectionId];
                }
            }
        });
    }, { threshold: 0.5 }); // Trigger when 50% of the section is visible

    // --- Initialize Tracking ---

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Observe all full sections
        document.querySelectorAll('section, header, footer').forEach(el => observer.observe(el));

        // 2. Track Navigation & CTA Clicks (using capture: true to bypass stopPropagation)
        document.addEventListener('click', (e) => {
            const target = e.target;
            
            // Buttons and Links
            const btn = target.closest('button, a');
            if (btn) {
                const text = btn.innerText.trim() || btn.getAttribute('aria-label') || 'icon-btn';
                const href = btn.getAttribute('href');
                logEvent('click', 'button_click', { 
                    text: text, 
                    href: href,
                    id: btn.id,
                    classes: btn.className
                });
            }

            // Availability & General filters (custom dropdowns)
            const dropdownOption = target.closest('.dropdown-option');
            if (dropdownOption) {
                const dropdown = dropdownOption.closest('.custom-dropdown');
                const filterName = dropdown ? dropdown.dataset.name : 'unknown';
                logEvent('filter', 'filter_change', {
                    filter: filterName,
                    value: dropdownOption.dataset.value || dropdownOption.innerText.trim(),
                    section: dropdown.closest('section')?.id || 'unknown'
                });
            }

            // Neighborhood Tabs
            const tabBtn = target.closest('.tab-btn');
            if (tabBtn) {
                logEvent('tab', 'tab_change', {
                    tab: tabBtn.dataset.tab,
                    text: tabBtn.innerText.trim()
                });
            }

            // Carousel Specifics (Swiper)
            const swiperNav = target.closest('.swiper-button-next, .swiper-button-prev, .swiper-pagination-bullet');
            if (swiperNav) {
                const isNext = target.closest('.swiper-button-next');
                const isPrev = target.closest('.swiper-button-prev');
                const isBullet = target.closest('.swiper-pagination-bullet');
                
                logEvent('carousel', 'carousel_nav_click', {
                    type: isNext ? 'next' : (isPrev ? 'prev' : 'pagination'),
                    value: isBullet ? swiperNav.innerText.trim() : null,
                    section: swiperNav.closest('section')?.id || 'unknown'
                });
            }
        }, { capture: true });

        // 3. Track Modal Events
        document.querySelectorAll('.modal').forEach(modalEl => {
            modalEl.addEventListener('shown.bs.modal', (e) => {
                const unit = document.getElementById('unitInput')?.value || '';
                logEvent('modal', 'modal_open', { 
                    modalId: modalEl.id,
                    unit: unit 
                });
            });
        });

        // 4. Track Form Interactions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Add session ID to hidden field if not already there
            let hiddenTracking = form.querySelector('input[name="tracking_id"]');
            if (!hiddenTracking) {
                hiddenTracking = document.createElement('input');
                hiddenTracking.type = 'hidden';
                hiddenTracking.name = 'tracking_id';
                form.appendChild(hiddenTracking);
            }
            hiddenTracking.value = sessionId;

            // Track field focus
            form.querySelectorAll('input, textarea, select').forEach(input => {
                input.addEventListener('focus', () => {
                    logEvent('form', 'field_focus', { 
                        formId: form.id, 
                        fieldName: input.name || input.id 
                    });
                });
            });

            // Track success/errors via existing JS callbacks if possible, 
            // or just track the submit button click as intent.
            form.addEventListener('submit', () => {
                logEvent('form', 'form_submit_start', { formId: form.id });
            });
        });
    });

    // Special handling for the Price Sliders (Availability)
    let priceTimeout;
    const minPriceSlider = document.getElementById('minPriceSlider');
    const maxPriceSlider = document.getElementById('maxPriceSlider');
    [minPriceSlider, maxPriceSlider].forEach(slider => {
        if (slider) {
            slider.addEventListener('input', () => {
                clearTimeout(priceTimeout);
                priceTimeout = setTimeout(() => {
                    logEvent('filter', 'price_range_change', {
                        min: minPriceSlider.value,
                        max: maxPriceSlider.value
                    });
                }, 1000); // Wait for user to stop sliding
            });
        }
    });

})();
