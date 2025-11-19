// OPTION B: Panels Appear & Disappear - Cinematic Scroll System
// Building always visible, panels slide in from right and out to left

document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Initializing Eleanor cinematic scroll system (Option B)...');
    
    const slidePanels = document.querySelectorAll('.slide-panel');
    console.log(`🎯 Found ${slidePanels.length} panels to animate`);
    
    // Skip the hero panel (should always be visible)
    const animatedPanels = Array.from(slidePanels).filter(panel => {
        return panel.style.opacity !== '1';
    });
    
    console.log(`✨ Animating ${animatedPanels.length} panels`);

    function updatePanelPositions() {
        const scrollY = window.scrollY;
        const windowHeight = window.innerHeight;
        const windowCenter = scrollY + (windowHeight / 2);
        
        animatedPanels.forEach((panel, index) => {
            const rect = panel.getBoundingClientRect();
            const panelTop = scrollY + rect.top;
            const panelBottom = panelTop + rect.height;
            const panelCenter = panelTop + (rect.height / 2);
            
            // Define zones for animation
            const startZone = panelTop - windowHeight;  // Start sliding in
            const enterZone = panelTop;                  // Fully visible
            const exitZone = panelBottom;                // Start sliding out
            const endZone = panelBottom + windowHeight;  // Fully gone
            
            let translateX;
            
            // PHASE 1: Before panel reaches viewport - Off screen right
            if (scrollY < startZone) {
                translateX = 100; // 100vw off screen to the right
            }
            // PHASE 2: Panel sliding IN from right
            else if (scrollY >= startZone && scrollY < enterZone) {
                const progress = (scrollY - startZone) / (enterZone - startZone);
                translateX = 100 - (progress * 100); // 100vw → 0vw
            }
            // PHASE 3: Panel is CENTERED and visible
            else if (scrollY >= enterZone && scrollY < exitZone) {
                translateX = 0; // Fully visible at 0vw
            }
            // PHASE 4: Panel sliding OUT to left
            else if (scrollY >= exitZone && scrollY < endZone) {
                const progress = (scrollY - exitZone) / (endZone - exitZone);
                translateX = -(progress * 100); // 0vw → -100vw
            }
            // PHASE 5: After panel exits - Off screen left
            else {
                translateX = -100; // -100vw off screen to the left
            }
            
            // Calculate opacity for smooth fade
            let opacity;
            if (scrollY < startZone || scrollY > endZone) {
                opacity = 0;
            } else if (scrollY >= enterZone && scrollY < exitZone) {
                opacity = 1;
            } else if (scrollY >= startZone && scrollY < enterZone) {
                const progress = (scrollY - startZone) / (enterZone - startZone);
                opacity = progress;
            } else {
                const progress = (scrollY - exitZone) / (endZone - exitZone);
                opacity = 1 - progress;
            }
            
            // Apply transforms
            const glassPanel = panel.querySelector('.glass-panel');
            if (glassPanel) {
                glassPanel.style.transform = `translateX(${translateX}vw)`;
                glassPanel.style.opacity = opacity;
            }
        });
    }
    
    // Throttle scroll events for performance
    let ticking = false;
    
    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                updatePanelPositions();
                ticking = false;
            });
            ticking = true;
        }
    }
    
    // Initialize positions on load
    updatePanelPositions();
    
    // Update on scroll
    window.addEventListener('scroll', onScroll, { passive: true });
    
    // Update on resize
    window.addEventListener('resize', () => {
        setTimeout(updatePanelPositions, 100);
    }, { passive: true });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    });
    
    console.log('✨ Eleanor cinematic scroll system initialized successfully');
    console.log('📜 Scroll to see panels slide in from right and out to left!');
});
