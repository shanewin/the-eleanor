document.addEventListener('DOMContentLoaded', () => {
  const trigger = document.getElementById('menuTrigger');
  const triggerMobile = document.getElementById('menuTriggerMobile');
  const overlay = document.getElementById('navOverlay');
  const closeButton = document.getElementById('closeButton');
  const body = document.body;
  const navLinks = document.querySelectorAll('.nav-link');

  if (!overlay || !closeButton) return;

  const openMenu = () => {
    overlay.classList.add('active');
    body.classList.add('nav-open');
  };

  const closeMenu = () => {
    overlay.classList.remove('active');
    body.classList.remove('nav-open');
  };

  if (trigger) {
    trigger.addEventListener('click', openMenu);
  }

  if (triggerMobile) {
    triggerMobile.addEventListener('click', openMenu);
  }
  
  closeButton.addEventListener('click', closeMenu);
  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && overlay.classList.contains('active')) {
      closeMenu();
    }
  });

  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (overlay.classList.contains('active')) {
        closeMenu();
      }
    });
  });

  // Only handle dropdowns outside of #availability (Availability has its own handler)
  const allDropdowns = document.querySelectorAll('.custom-dropdown');
  const dropdowns = Array.from(allDropdowns).filter(dropdown => !dropdown.closest('#availability'));

  if (dropdowns.length) {
    const closeDropdowns = () => {
      dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
    };

    dropdowns.forEach(dropdown => {
      const trigger = dropdown.querySelector('.custom-dropdown-trigger');
      const options = dropdown.querySelectorAll('.dropdown-option');
      const displayText = dropdown.querySelector('.dropdown-text');
      const hiddenInput = dropdown.parentElement.querySelector('input[type="hidden"]');

      if (!trigger) {
        return;
      }

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const isActive = dropdown.classList.contains('active');
        closeDropdowns();
        if (!isActive) {
          dropdown.classList.add('active');
        }
      });

      options.forEach(option => {
        option.addEventListener('click', (event) => {
          event.stopPropagation();
          const value = option.dataset.value ?? option.textContent.trim();
          if (displayText) {
            displayText.textContent = option.textContent.trim();
          }
          if (hiddenInput) {
            hiddenInput.value = value;
          }
          dropdown.classList.remove('active');
        });
      });
    });

    document.addEventListener('click', (event) => {
      const clickedDropdown = event.target.closest('.custom-dropdown');
      if (clickedDropdown && !clickedDropdown.closest('#availability')) {
        // This is a waitlist dropdown, handled by main.js
        return;
      }
      if (!clickedDropdown || !clickedDropdown.closest('#availability')) {
        closeDropdowns();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeDropdowns();
      }
    });
  }

  const neighborhoodTabs = document.querySelectorAll('.neighborhood-tabs');
  neighborhoodTabs.forEach(section => {
    const buttons = section.querySelectorAll('.tab-btn');
    const panels = section.querySelectorAll('.tab-content');

    buttons.forEach(button => {
      button.addEventListener('click', () => {
        const targetId = button.dataset.tab;
        buttons.forEach(btn => btn.classList.remove('active'));
        panels.forEach(panel => panel.classList.remove('active'));

        button.classList.add('active');
        const targetPanel = section.querySelector(`#${targetId}`);
        if (targetPanel) {
          targetPanel.classList.add('active');
        }
      });
    });
  });
});