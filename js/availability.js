// Drives availability table: pulls unit data from Google Apps Script, normalizes it,
// then wires up filters, price sliders, pagination, and detail modals.
document.addEventListener("DOMContentLoaded", function () {
  let units = [];
  let filteredUnits = [];
  let currentBuilding = null;
  let isInitialized = false;
  const tableBody = document.getElementById("unit-table");
  
  // Initialize modals
  const unitModalEl = document.getElementById("unitModal");
  const unitModal = unitModalEl ? new bootstrap.Modal(unitModalEl) : null;
  
  const floorPlanModalEl = document.getElementById("floorPlanModal");
  const floorPlanModal = floorPlanModalEl ? new bootstrap.Modal(floorPlanModalEl) : null;
  
  const leasedModalEl = document.getElementById("leasedModal");
  const leasedModal = leasedModalEl ? new bootstrap.Modal(leasedModalEl) : null;

  // Other elements
  const unitInput = document.getElementById("unitInput");
  const unitDescription = document.getElementById("unitDescription");
  const floorPlanImage = document.getElementById("floorPlanImage");
  const leasedUnitNumber = document.getElementById("leasedUnitNumber");
  
  // Filter elements
  const bedroomFilter = document.getElementById("bedroomFilter");
  const bathroomFilter = document.getElementById("bathroomFilter");
  const outdoorFilter = document.getElementById("outdoorFilter");

  const minPriceSlider = document.getElementById("minPriceSlider");
  const maxPriceSlider = document.getElementById("maxPriceSlider");
  const minPriceDisplay = document.getElementById("minPriceDisplay");
  const maxPriceDisplay = document.getElementById("maxPriceDisplay");
  const resultsCount = document.getElementById("resultsCount");
  const clearFiltersBtn = document.getElementById("clearAllFilters");

  // Pagination Variables
  let currentPage = 1;
  const rowsPerPage = 10;

  // Show leased unit popup
  function showLeasedPopup(unitNumber) {
    if (leasedModal && leasedUnitNumber) {
      leasedUnitNumber.textContent = unitNumber;
      leasedModal.show();
    }
  }

  // Format currency
  function formatCurrency(amount) {
    if (!amount) return '';
    // Handle both "$6,700.00" and "6700" formats
    if (typeof amount === 'string' && amount.includes('$')) return amount;
    const number = parseFloat(amount.toString().replace(/[^0-9.]/g, ''));
    return isNaN(number) ? '' : '$' + number.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  // Fetch units from Google Apps Script
  async function fetchUnits() {
    tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4">Loading units...</td></tr>`;
    
    try {
      // Fetch directly from your Google Apps Script
      const response = await fetch('https://script.google.com/macros/s/AKfycbz8HBkvSlt7Z2oyWCfjUPj9KQ1mBxtiNN5kfzveliN3SgWYKJ8FbdFTEYMUjYdPXaFfCQ/exec');
      
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      
      const data = await response.json();
      units = data;

      // Log successful data fetch
      console.log(`Successfully loaded ${data.length} units from Google Apps Script`);

      
      
      // Process the data
      units.forEach(unit => {
        // Convert bedbath to bedBath for consistency 
        unit.bedBath = unit.bedbath || unit.bedBath || 'N/A';
        
        // Add "Unit " prefix if not present
        if (unit.unit && !unit.unit.toString().startsWith('Unit ')) {
          unit.unit = 'Unit ' + unit.unit;
        }

        // Convert building number to string for consistency
        unit.building = unit.building ? unit.building.toString() : '';
        
        // Format rent (handle both number and string, skip HPD)
        if (unit.rent === 'HPD' || unit.type === 'HPD') {
          unit.formattedRent = 'HPD';
        } else {
        unit.formattedRent = formatCurrency(unit.rent);
        }
        
        // Process images
        if (typeof unit.images === 'string') {
          unit.images = unit.images.split(',').map(img => img.trim());
        }
        
        // Process description (handle both string and array)
        if (typeof unit.description === 'string') {
          unit.description = unit.description.split('.').filter(d => d.trim() !== '').map(d => d.trim() + '.');
        } else if (!Array.isArray(unit.description)) {
          unit.description = [];
        }
        
        // Fix typos in view
        if (unit.view) {
          unit.view = unit.view.replace('veiw', 'view');
        }
        
        // Ensure isleased is properly set (your data already has boolean values)
        if (typeof unit.isleased === 'undefined') {
          unit.isleased = false;
        }

      });
      
      console.log(`Processed ${units.length} units successfully`);
      
      // Initialize filtering system
      filteredUnits = [...units]; // Start with all units
      initializePriceSliders();
      initializeCustomDropdowns(); // Initialize custom dropdowns
      applyAdvancedFilters();
      
      // Hide loading indicator on success (with small delay for better UX)
      setTimeout(() => {
        hideLoadingIndicator();
      }, 500);
      
    } catch (error) {
      console.error('Error fetching unit data:', error);
      tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">
        Failed to load data. Please refresh the page.
      </td></tr>`;
      
      // Hide loading indicator on error (with small delay)
      setTimeout(() => {
        hideLoadingIndicator();
      }, 500);
    }
  }

  // Advanced filtering function
  function applyAdvancedFilters() {
    filteredUnits = units.filter(unit => {
      // Skip HPD units completely
      if (unit.rent === 'HPD' || (unit.type && unit.type.toLowerCase() === 'hpd')) {
        return false;
      }

      // Building filter - use currentBuilding if set
      if (currentBuilding && unit.building !== currentBuilding) {
        return false;
      }

      // Bedroom filter
      if (bedroomFilter.value !== 'all') {
        // Check both type and bedBath fields for bedroom info
        const unitType = unit.type ? unit.type.toLowerCase().trim() : '';
        const unitBedBath = unit.bedBath ? unit.bedBath.toLowerCase().trim() : '';
        const bedValue = bedroomFilter.value.toLowerCase();
        
        // Look for bedroom count in both fields
        const bedPattern = new RegExp(`${bedValue}\\s*(bed|br|bedroom)`, 'i');
        
        if (!bedPattern.test(unitType) && !bedPattern.test(unitBedBath)) {
          return false;
        }
      }

      // Bathroom filter (extract from bedBath field)
      if (bathroomFilter.value !== 'all') {
        const bathMatch = unit.bedBath ? unit.bedBath.match(/(\d+(?:\.\d+)?)\s*(bath|ba)/i) : null;
        const unitBaths = bathMatch ? parseFloat(bathMatch[1]) : 0;
        const filterBaths = parseFloat(bathroomFilter.value);
        
        if (unitBaths !== filterBaths) {
          return false;
        }
      }

      // Outdoor space filter
      if (outdoorFilter.value !== 'all') {
        const outdoor = unit.outdoor ? unit.outdoor.toLowerCase() : 'none';
        const filterValue = outdoorFilter.value.toLowerCase();
        
        if (filterValue === 'none' && outdoor !== 'none' && outdoor !== '') {
          return false;
        } else if (filterValue !== 'none' && !outdoor.includes(filterValue)) {
          return false;
        }
      }

      // Price range filter
      const unitPrice = parseFloat(unit.rent?.toString().replace(/[^0-9.]/g, '')) || 0;
      const minPrice = parseInt(minPriceSlider.value);
      const maxPrice = parseInt(maxPriceSlider.value);
      
      if (unitPrice < minPrice || unitPrice > maxPrice) {
        return false;
      }



      return true;
    });

    updateResultsCount();
    populateTable(1); // Reset to page 1 after filtering
  }

  // Populate the table with filtered units
  function populateTable(page = 1) {
    if (!tableBody) return;
    
    if (filteredUnits.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4">
        No units match your criteria. Try adjusting your filters.
      </td></tr>`;
      return;
    }

    tableBody.innerHTML = "";
    currentPage = page;

    // Paginate and render...
    const paginatedUnits = filteredUnits.slice(
      (page - 1) * rowsPerPage,
      page * rowsPerPage
    );

    // Create table rows
    paginatedUnits.forEach(unit => {

      const row = document.createElement("tr");
      if (unit.isleased) {
        row.classList.add("leased-row");
        row.dataset.leased = "true"; // Add data attribute for easier CSS targeting
      }
      
      row.innerHTML = `
        <td>${unit.unit || ''}</td>
        <td>${unit.bedBath || ''}</td>
        <td>${unit.outdoor || ''}</td>
        <td>${unit.isleased ? "LEASED" : (unit.formattedRent || '')}</td>
        <td>
          <button class="btn btn-sm view-floor-plan" 
                  data-images='${JSON.stringify(unit.images || [])}' 
                  ${unit.isleased ? 'disabled' : ''}>
            ${unit.images && unit.images.length > 0 ? 'View' : 'N/A'}
          </button>
        </td>
      `;

      // Row click handler
      row.addEventListener("click", () => {
        console.log('Row clicked for unit:', unit.unit, 'isleased:', unit.isleased);
        if (unit.isleased) {
          showLeasedPopup(unit.unit);
        } else {
          openModal(unit);
        }
      });

      // Floor plan button handler
      const viewButton = row.querySelector(".view-floor-plan");
      if (viewButton && !unit.isleased) {
        viewButton.addEventListener("click", e => {
          e.stopPropagation();
          if (unit.images && unit.images.length > 0) {
          const images = JSON.parse(viewButton.dataset.images);
          openFloorPlanModal(images[0]);
          } else {
            alert('Floor plan not yet available for this unit. Please contact us for more information.');
          }
        });
      }

      tableBody.appendChild(row);
    });

    updatePaginationControls(filteredUnits.length, page);
  }

  // Update pagination controls 
  function updatePaginationControls(totalUnits, page) {
    const paginationContainer = document.getElementById("pagination");
    if (!paginationContainer) return;
    
    paginationContainer.innerHTML = "";
    const totalPages = Math.ceil(totalUnits / rowsPerPage);

    // Previous Button
    if (page > 1) {
      const prevButton = createPaginationButton(
        "<< Previous",
        () => {
          populateTable(currentPage - 1);
        },
        ["btn", "btn-outline-primary", "mx-2"]
      );
      paginationContainer.appendChild(prevButton);
    }

    // Page Numbers
    for (let i = 1; i <= totalPages; i++) {
      const classes = ["btn", "btn-outline-primary", "mx-1"];
      if (i === page) classes.push("active");
      
      const pageButton = createPaginationButton(
        i,
        () => {
          populateTable(i);
        },
        classes
      );
      paginationContainer.appendChild(pageButton);
    }

    // Next Button
    if (page < totalPages) {
      const nextButton = createPaginationButton(
        "Next >>",
        () => {
          populateTable(currentPage + 1);
        },
        ["btn", "btn-outline-primary", "mx-2"]
      );
      paginationContainer.appendChild(nextButton);
    }
  }

  // Update results count display
  function updateResultsCount() {
    if (resultsCount) {
      const total = filteredUnits.length;
      const available = filteredUnits.filter(unit => !unit.isleased).length;
      resultsCount.textContent = `Showing ${total} units (${available} available)`;
    }
  }

  // Initialize price range sliders
  function initializePriceSliders() {
    if (!units.length) return;

         // Find actual price range from data
     const prices = units
       .filter(unit => unit.rent !== 'HPD')
       .map(unit => parseFloat(unit.rent?.toString().replace(/[^0-9.]/g, '')) || 0)
       .filter(price => price > 0);

     if (prices.length === 0) return;

          const actualMinPrice = Math.min(...prices);
     const actualMaxPrice = Math.max(...prices);
     
     // Use the actual data range: $2,900 - $5,500
     const minPrice = 2900; // Fixed minimum
     const maxPrice = 5500; // Fixed maximum

     // Update slider attributes
     minPriceSlider.min = minPrice;
     minPriceSlider.max = maxPrice;
     minPriceSlider.value = minPrice;
     
     maxPriceSlider.min = minPrice;
     maxPriceSlider.max = maxPrice;
          maxPriceSlider.value = maxPrice;

     updatePriceDisplay();
  }

  // Update price display position to align with slider thumbs
  function updatePriceDisplayPositions() {
    const minVal = parseInt(minPriceSlider.value);
    const maxVal = parseInt(maxPriceSlider.value);
    const minRange = parseInt(minPriceSlider.min);
    const maxRange = parseInt(minPriceSlider.max);
    
    // Calculate percentage positions
    const minPercent = ((minVal - minRange) / (maxRange - minRange)) * 100;
    const maxPercent = ((maxVal - minRange) / (maxRange - minRange)) * 100;
    
    // Position the displays to align with slider thumbs
    minPriceDisplay.style.left = `${minPercent}%`;
    maxPriceDisplay.style.left = `${maxPercent}%`;
  }

  // Update price display
  function updatePriceDisplay() {
    const minVal = parseInt(minPriceSlider.value);
    const maxVal = parseInt(maxPriceSlider.value);

         // Ensure min doesn't exceed max
     if (minVal >= maxVal) {
       minPriceSlider.value = maxVal - 25;
     }

     // Ensure max doesn't go below min
     if (maxVal <= minVal) {
       maxPriceSlider.value = minVal + 25;
     }

    const formatPrice = (price) => '$' + price.toLocaleString();
    minPriceDisplay.textContent = formatPrice(parseInt(minPriceSlider.value));
    maxPriceDisplay.textContent = formatPrice(parseInt(maxPriceSlider.value));
    
    // Update positions to align with slider thumbs
    updatePriceDisplayPositions();
  }

  // Clear all filters
  function clearAllFilters() {
    // Reset dropdown filters
    bedroomFilter.value = 'all';
    bathroomFilter.value = 'all';
    outdoorFilter.value = 'all';
    
    // Update custom dropdown displays
    updateCustomDropdownDisplays();
    
    // Reset price sliders to full range
    if (units.length > 0) {
      initializePriceSliders();
    }
    
    applyAdvancedFilters();
  }

  function updateCustomDropdownDisplays() {
    const selects = [bedroomFilter, bathroomFilter, outdoorFilter];
    
    selects.forEach(select => {
      if (select) { // Check if select element exists
      const customDropdown = select.parentNode.querySelector('.custom-dropdown');
      if (customDropdown) {
        const selectedText = customDropdown.querySelector('.dropdown-selected span');
        const options = customDropdown.querySelectorAll('.dropdown-option');
        
        // Update display text
        selectedText.textContent = select.options[select.selectedIndex].text;
        
        // Update selected state
        options.forEach((option, index) => {
          option.classList.toggle('selected', index === select.selectedIndex);
        });
        }
      }
    });
  }

  // Create pagination button (keep your existing function)
  function createPaginationButton(text, onClick, classes) {
    const button = document.createElement("button");
    button.innerText = text;
    button.classList.add(...classes);
    button.addEventListener("click", onClick);
    return button;
  }

  // Open unit details modal (keep your existing function)
  function openModal(unit) {
    console.log('openModal called with unit:', unit);
    console.log('unitModal instance:', unitModal);
    try {
      if (!unitModal) {
        console.error('Unit modal not initialized');
        return;
      }
      
      // Update modal title
      const modalTitle = document.getElementById('unitModalLabel');
      if (modalTitle) {
        modalTitle.textContent = `${unit.unit} Details`;
      }
      
      // Update unit description
      const unitDescriptionEl = document.getElementById('unitDescription');
      if (unitDescriptionEl && unit.description && unit.description.length > 0) {
        unitDescriptionEl.innerHTML = unit.description
        .filter(desc => desc.trim() !== "")
        .map(desc => `<li>${desc}</li>`)
        .join("");
      } else if (unitDescriptionEl) {
        // Show unit details if no description array
        unitDescriptionEl.innerHTML = `
          <li><strong>Building:</strong> ${unit.building}</li>
          <li><strong>Unit Type:</strong> ${unit.bedBath}</li>
          <li><strong>Outdoor Space:</strong> ${unit.outdoor || 'None'}</li>
          <li><strong>Monthly Rent:</strong> ${unit.formattedRent}</li>
          ${unit.sqft ? `<li><strong>Square Footage:</strong> ${unit.sqft} sq ft</li>` : ''}
        `;
      }
      
      // Update hidden unit input for form
      const unitInputEl = document.getElementById('unitInput');
      if (unitInputEl) {
        unitInputEl.value = unit.unit;
      }
      
      // Handle unit images
      const unitImagesEl = document.getElementById('unit-images');
      if (unitImagesEl && unit.images && unit.images.length > 0) {
        unitImagesEl.innerHTML = unit.images.map(image => {
          const filename = image.split('/').pop();
          return `<img src="assets/images/units/${filename}" class="img-fluid mb-2" alt="Unit ${unit.unit}">`;
        }).join('');
      } else if (unitImagesEl) {
        unitImagesEl.innerHTML = '<p class="text-muted">Unit images coming soon.</p>';
      }
      
      // Update thank you section
      const unitThankYouName = document.getElementById('unitThankYouName');
      if (unitThankYouName) {
        unitThankYouName.textContent = `Unit ${unit.unit}`;
      }
      
      // Reset modal state - show form, hide thank you
      const form = document.getElementById('unitInterestForm');
      const thankYouSection = document.getElementById('unitThankYou');
      const unitDetailsContent = document.querySelector('#unitModal .modal-body > div:not(#unitThankYou)');
      
      if (form) {
        form.reset();
        form.parentElement.style.display = 'block';
        // Re-set the unit value after reset
        if (unitInputEl) {
          unitInputEl.value = unit.unit;
        }
      }
      
      if (thankYouSection) {
        thankYouSection.style.display = 'none';
      }
      
      // Show the modal
      console.log('About to show modal...');
      unitModal.show();
      console.log('Modal show() called');
      
    } catch (error) {
      console.error('Error opening unit modal:', error);
    }
  }

  // Open floor plan modal - FIXED PATH ISSUE
  function openFloorPlanModal(image) {
    if (!floorPlanImage || !floorPlanModal) return;
    
    // Extract filename and use correct path
    const filename = image.split('/').pop();
    floorPlanImage.src = `assets/images/units/${filename}`;
    
    floorPlanModal.show();
  }

  // Custom Dropdown System
  let dropdownEventListenerAdded = false;
  
  function initializeCustomDropdowns() {
    const selects = document.querySelectorAll('.filter-select');
    
    selects.forEach(select => {
      createCustomDropdown(select);
    });
    
    // Close dropdowns when clicking outside (only add once)
    if (!dropdownEventListenerAdded) {
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.custom-dropdown')) {
        closeAllDropdowns();
      }
    });
      dropdownEventListenerAdded = true;
    }
  }

  function createCustomDropdown(select) {
    // Check if dropdown already exists
    const existingDropdown = select.parentNode.querySelector('.custom-dropdown');
    if (existingDropdown) {
      return; // Already has a custom dropdown, skip creation
    }
    
    const container = document.createElement('div');
    container.className = 'custom-dropdown';
    
    // Create selected display
    const selected = document.createElement('div');
    selected.className = 'dropdown-selected';
    
    const selectedText = document.createElement('span');
    selectedText.textContent = select.options[select.selectedIndex].text;
    
    const arrow = document.createElement('div');
    arrow.className = 'dropdown-arrow';
    arrow.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6,9 12,15 18,9"></polyline>
      </svg>
    `;
    
    selected.appendChild(selectedText);
    selected.appendChild(arrow);
    
    // Create options container
    const options = document.createElement('div');
    options.className = 'dropdown-options';
    
    // Add options
    Array.from(select.options).forEach((option, index) => {
      const optionDiv = document.createElement('div');
      optionDiv.className = 'dropdown-option';
      if (option.selected) optionDiv.classList.add('selected');
      optionDiv.textContent = option.text;
      optionDiv.dataset.value = option.value;
      optionDiv.dataset.index = index;
      
      // Option click handler
      optionDiv.addEventListener('click', function(e) {
        e.stopPropagation();
        
        // Update original select
        select.selectedIndex = index;
        select.dispatchEvent(new Event('change'));
        
        // Update custom dropdown display
        selectedText.textContent = option.text;
        
        // Update selected state
        options.querySelectorAll('.dropdown-option').forEach(opt => {
          opt.classList.remove('selected');
        });
        optionDiv.classList.add('selected');
        
        // Close dropdown
        selected.classList.remove('active');
        options.classList.remove('show');
      });
      
      options.appendChild(optionDiv);
    });
    
    // Selected click handler
    selected.addEventListener('click', function(e) {
      e.stopPropagation();
      
      // Close other dropdowns
      closeAllDropdowns();
      
      // Toggle this dropdown
      const isActive = selected.classList.contains('active');
      if (!isActive) {
        selected.classList.add('active');
        options.classList.add('show');
      }
    });
    
    container.appendChild(selected);
    container.appendChild(options);
    
    // Replace original select
    select.parentNode.insertBefore(container, select);
    select.style.display = 'none'; // Keep for form functionality
  }

  function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-selected.active').forEach(dropdown => {
      dropdown.classList.remove('active');
    });
    document.querySelectorAll('.dropdown-options.show').forEach(options => {
      options.classList.remove('show');
    });
  }

  // Show loading indicator
  function showLoadingIndicator() {
    // Create loading overlay if it doesn't exist
    let loadingOverlay = document.getElementById('availability-loading');
    if (!loadingOverlay) {
      loadingOverlay = document.createElement('div');
      loadingOverlay.id = 'availability-loading';
      loadingOverlay.innerHTML = `
        <div class="loading-content">
          <div class="loading-spinner"></div>
          <div class="loading-text">Loading Apartments...</div>
        </div>
      `;
      document.body.appendChild(loadingOverlay);
    }
    loadingOverlay.style.display = 'flex';
  }

  // Hide loading indicator
  function hideLoadingIndicator() {
    const loadingOverlay = document.getElementById('availability-loading');
    if (loadingOverlay) {
      loadingOverlay.style.display = 'none';
    }
  }

  // Initialize Availability System for a specific building
  window.initializeAvailabilitySystem = function(building) {
    console.log('Initializing availability system for building:', building);
    
    // Show loading indicator immediately
    showLoadingIndicator();
    
    // Set the current building
    currentBuilding = building;
    
    // Only initialize once
    if (!isInitialized) {
      // Add event listeners
      // Note: Building filter is now handled by interactive building selection, not a dropdown
      if (bedroomFilter) bedroomFilter.addEventListener('change', applyAdvancedFilters);
      if (bathroomFilter) bathroomFilter.addEventListener('change', applyAdvancedFilters);
      if (outdoorFilter) outdoorFilter.addEventListener('change', applyAdvancedFilters);
      
      // Add price slider listeners
      if (minPriceSlider) {
        minPriceSlider.addEventListener('input', function() {
          updatePriceDisplay();
          applyAdvancedFilters();
        });
      }

      if (maxPriceSlider) {
        maxPriceSlider.addEventListener('input', function() {
          updatePriceDisplay();
          applyAdvancedFilters();
        });
      }

      // Clear filters button
      if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearAllFilters);
      }
      
      isInitialized = true;
    }
    
    // Fetch units and apply building filter
    fetchUnits();
  };

  // Note: fetchUnits() is no longer called automatically
  // It will be called when initializeAvailabilitySystem() is called
});

// 🎨 OVERLAY DISMISS FUNCTIONALITY
document.addEventListener('DOMContentLoaded', function() {
    // Handle overlay dismiss buttons
    const dismissButtons = document.querySelectorAll('.overlay-dismiss');
    
    dismissButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const overlay = this.closest('.slide-overlay');
            if (overlay) {
                // Smooth fade out
                overlay.style.opacity = '0';
                overlay.style.transform = 'scale(0.9) translateY(20px)';
                
                // Hide completely after animation
                setTimeout(() => {
                    overlay.style.display = 'none';
                }, 300);
            }
        });
    });
    
    // Optional: Show overlays again when slide changes
    const dots = document.querySelectorAll('.dot');
    dots.forEach(dot => {
        dot.addEventListener('click', function() {
            // Small delay to let slide transition start
            setTimeout(() => {
                const activeSlide = document.querySelector('.hero-slide.active');
                if (activeSlide) {
                    const overlay = activeSlide.querySelector('.slide-overlay');
                    if (overlay && overlay.style.display === 'none') {
                        overlay.style.display = 'block';
                        // Trigger reflow
                        overlay.offsetHeight;
                        overlay.style.opacity = '1';
                        overlay.style.transform = 'scale(1) translateY(0)';
                    }
                }
            }, 100);
    });
  });
});
