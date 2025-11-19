// Horizontal Slide-In Panels Animation System
// Triggers glass panels to slide in from the right as user scrolls down

document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Initializing Eleanor slide panel system...');
    
    // Configuration
    const observerOptions = {
        root: null,
        rootMargin: '0px',  // Changed from -100px to trigger earlier
        threshold: 0.1  // Trigger when 10% of element is visible
    };

    // Create intersection observer
    const slideObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            console.log('📍 Observed element:', entry.target, 'Is intersecting:', entry.isIntersecting);
            
            if (entry.isIntersecting) {
                console.log('✅ Triggering slide animation for element');
                
                // Add delay for staggered effect if multiple panels visible
                setTimeout(() => {
                    entry.target.classList.add('slide-in');
                    console.log('🎨 Added slide-in class');
                }, index * 150);  // Slightly longer delay for better effect
                
                // Stop observing once animated (one-time animation)
                slideObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all slide panels
    const slidePanels = document.querySelectorAll('.slide-panel');
    console.log(`🎯 Found ${slidePanels.length} slide panels to animate`);
    
    slidePanels.forEach((panel, index) => {
        console.log(`Panel ${index + 1}:`, panel);
        // Skip the hero panel which should be visible immediately
        if (panel.style.opacity === '1') {
            console.log('⏭️ Skipping hero panel (already visible)');
            return;
        }
        slideObserver.observe(panel);
    });

    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Optional: Add parallax effect to background
    let ticking = false;
    
    function updateBackground() {
        const scrolled = window.pageYOffset;
        const parallax = document.querySelector('body::before');
        
        if (parallax) {
            // Subtle parallax effect
            document.body.style.setProperty('--scroll-offset', `${scrolled * 0.5}px`);
        }
        
        ticking = false;
    }

    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(updateBackground);
            ticking = true;
        }
    });

    console.log('✨ Eleanor slide panel system initialized successfully');
});
