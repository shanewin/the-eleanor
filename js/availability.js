const GOOGLE_SHEET_ENDPOINT = 'https://script.google.com/macros/s/AKfycbz_-tiYBDHaMa4O4Rk6bdgJagBMLHZDf5R3SJmuZyymEUXp5ipfA8q7QHT-kS8WkbLfxQ/exec';

document.addEventListener('DOMContentLoaded', () => {
  const tableBody = document.getElementById('unit-table');
  const bedroomFilter = document.getElementById('bedroomFilter');
  const bathroomFilter = document.getElementById('bathroomFilter');
  const outdoorFilter = document.getElementById('outdoorFilter');
  const minPriceSlider = document.getElementById('minPriceSlider');
  const maxPriceSlider = document.getElementById('maxPriceSlider');
  const minPriceDisplay = document.getElementById('minPriceDisplay');
  const maxPriceDisplay = document.getElementById('maxPriceDisplay');
  const clearFiltersBtn = document.getElementById('clearAllFilters');
  const resultsCount = document.getElementById('resultsCount');

  // Initialize custom dropdowns for Availability filters
  const availabilityDropdowns = document.querySelectorAll('#availability .custom-dropdown');
  
  if (availabilityDropdowns.length) {
    const closeDropdowns = () => {
      availabilityDropdowns.forEach(dropdown => dropdown.classList.remove('active'));
    };

    availabilityDropdowns.forEach(dropdown => {
      const trigger = dropdown.querySelector('.custom-dropdown-trigger');
      const options = dropdown.querySelectorAll('.dropdown-option');
      const displayText = dropdown.querySelector('.dropdown-text');
      const dataName = dropdown.getAttribute('data-name');
      const hiddenSelect = document.getElementById(dataName);

      if (!trigger) {
        console.warn('Availability dropdown missing trigger:', dropdown);
        return;
      }

      if (!hiddenSelect) {
        console.warn('Availability dropdown missing select element:', dataName);
        return;
      }

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        event.preventDefault();
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
          if (hiddenSelect) {
            hiddenSelect.value = value;
            // Trigger change event on select to maintain filter functionality
            hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          dropdown.classList.remove('active');
        });
      });
    });

    document.addEventListener('click', (event) => {
      if (!event.target.closest('#availability .custom-dropdown')) {
        closeDropdowns();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeDropdowns();
      }
    });
  }

  let units = [];
  let filteredUnits = [];
  let currentPage = 1;
  const rowsPerPage = 10;

  // Bootstrap modals
  const unitModalEl = document.getElementById('unitModal');
  const unitModal = unitModalEl ? new bootstrap.Modal(unitModalEl) : null;
  const floorPlanModalEl = document.getElementById('floorPlanModal');
  const floorPlanModal = floorPlanModalEl ? new bootstrap.Modal(floorPlanModalEl) : null;
  const leasedModalEl = document.getElementById('leasedModal');
  const leasedModal = leasedModalEl ? new bootstrap.Modal(leasedModalEl) : null;

  const unitDescription = document.getElementById('unitDescription');
  const unitImages = document.getElementById('unit-images');
  const unitInput = document.getElementById('unitInput');
  const floorPlanImage = document.getElementById('floorPlanImage');
  const leasedUnitNumber = document.getElementById('leasedUnitNumber');

  function formatCurrency(value) {
    const number = parseFloat(value?.toString().replace(/[^0-9.]/g, ''));
    if (isNaN(number)) return '';
    return '$' + number.toLocaleString('en-US', { minimumFractionDigits: 0 });
  }

  async function fetchUnits() {
    if (!tableBody) return;
    tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">Loading units...</td></tr>`;

    try {
      const response = await fetch(GOOGLE_SHEET_ENDPOINT);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      units = data.map((unit) => {
        const normalized = { ...unit };
        normalized.bedBath = unit.bedbath || unit.bedBath || unit.type || '';
        normalized.formattedRent = formatCurrency(unit.rent);
        normalized.isleased = Boolean(unit.isleased);

        const rawImages = Array.isArray(unit.images)
          ? unit.images
          : typeof unit.images === 'string'
            ? unit.images.split(',') : [];
        normalized.images = rawImages
          .map((img) => (typeof img === 'string' ? img.trim() : ''))
          .filter((img) => !!img)
          .map((img) => (/^(https?:)?\/\//i.test(img) || img.startsWith('/')) ? img : `assets/floor-plans/${img}`);

        normalized.description = Array.isArray(unit.description) ? unit.description : [];
        const outdoorValue = typeof unit.outdoor === 'string' ? unit.outdoor.trim() : '';
        normalized.outdoor = outdoorValue || '';
        return normalized;
      });

      initializePriceSliders();
      attachFilterListeners();
      filteredUnits = [...units];
      applyFilters();
    } catch (error) {
      console.error('Failed to fetch units:', error);
      tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load units. Please refresh.</td></tr>`;
    }
  }

  function attachFilterListeners() {
    if (bedroomFilter) bedroomFilter.addEventListener('change', applyFilters);
    if (bathroomFilter) bathroomFilter.addEventListener('change', applyFilters);
    if (outdoorFilter) outdoorFilter.addEventListener('change', applyFilters);

    if (minPriceSlider) {
      minPriceSlider.addEventListener('input', () => {
        updatePriceDisplay();
        applyFilters();
      });
    }

    if (maxPriceSlider) {
      maxPriceSlider.addEventListener('input', () => {
        updatePriceDisplay();
        applyFilters();
      });
    }

    if (clearFiltersBtn) {
      clearFiltersBtn.addEventListener('click', () => {
        bedroomFilter.value = 'all';
        bathroomFilter.value = 'all';
        outdoorFilter.value = 'all';
        
        // Reset custom dropdown displays
        const bedroomDropdown = document.querySelector('#availability .custom-dropdown[data-name="bedroomFilter"]');
        const bathroomDropdown = document.querySelector('#availability .custom-dropdown[data-name="bathroomFilter"]');
        const outdoorDropdown = document.querySelector('#availability .custom-dropdown[data-name="outdoorFilter"]');
        
        if (bedroomDropdown) {
          const displayText = bedroomDropdown.querySelector('.dropdown-text');
          if (displayText) displayText.textContent = 'Any Bedrooms';
        }
        if (bathroomDropdown) {
          const displayText = bathroomDropdown.querySelector('.dropdown-text');
          if (displayText) displayText.textContent = 'Any Bathrooms';
        }
        if (outdoorDropdown) {
          const displayText = outdoorDropdown.querySelector('.dropdown-text');
          if (displayText) displayText.textContent = 'Any Outdoor Space';
        }
        
        initializePriceSliders();
        applyFilters();
      });
    }
  }

  function applyFilters() {
    filteredUnits = units.filter((unit) => {
      // Bedrooms
      if (bedroomFilter && bedroomFilter.value !== 'all') {
        const value = bedroomFilter.value;
        const pattern = new RegExp(`${value}\s*(bed|br|bedroom)`, 'i');
        const inType = pattern.test((unit.type || '').toLowerCase());
        const inBedBath = pattern.test((unit.bedBath || '').toLowerCase());
        if (!inType && !inBedBath) return false;
      }

      // Bathrooms
      if (bathroomFilter && bathroomFilter.value !== 'all') {
        const selectedBaths = parseFloat(bathroomFilter.value);
        const match = unit.bedBath?.match(/(\d+(?:\.\d+)?)\s*(bath|ba)/i);
        const unitBaths = match ? parseFloat(match[1]) : 0;
        if (unitBaths !== selectedBaths) return false;
      }

      // Outdoor
      if (outdoorFilter && outdoorFilter.value !== 'all') {
        const selectedOutdoor = outdoorFilter.value.toLowerCase();
        const unitOutdoor = unit.outdoor ? unit.outdoor.toLowerCase() : 'none';
        if (selectedOutdoor === 'none') {
          if (unitOutdoor !== 'none' && unitOutdoor !== '') return false;
        } else if (!unitOutdoor.includes(selectedOutdoor)) {
          return false;
        }
      }

      // Price
      const unitRent = parseFloat(unit.rent?.toString().replace(/[^0-9.]/g, '')) || 0;
      const minPrice = parseInt(minPriceSlider.value);
      const maxPrice = parseInt(maxPriceSlider.value);
      if (unitRent < minPrice || unitRent > maxPrice) return false;

      return true;
    });

    updateResultsCount();
    populateTable(1);
  }

  function populateTable(page = 1) {
    if (!tableBody) return;

    if (!filteredUnits.length) {
      tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4">No units match your filters.</td></tr>`;
      return;
    }

    tableBody.innerHTML = '';
    currentPage = page;
    const paginatedUnits = filteredUnits.slice((page - 1) * rowsPerPage, page * rowsPerPage);

    paginatedUnits.forEach((unit) => {
      const row = document.createElement('tr');
      if (unit.isleased) row.classList.add('leased-row');

      const floorPlanButton = unit.images && unit.images.length
        ? `<button class="btn btn-sm view-floor-plan">View</button>`
        : '';

      row.innerHTML = `
        <td>${unit.unit || ''}</td>
        <td>${unit.bedBath || ''}</td>
        <td>${unit.outdoor || ''}</td>
        <td>${unit.isleased ? 'LEASED' : unit.formattedRent || ''}</td>
        <td>${floorPlanButton}</td>
      `;

      row.addEventListener('click', () => {
        if (unit.isleased) {
          if (leasedModal && leasedUnitNumber) {
            leasedUnitNumber.textContent = unit.unit;
            leasedModal.show();
          }
        } else {
          openUnitModal(unit);
        }
      });

      const button = row.querySelector('.view-floor-plan');
      if (button && unit.images && unit.images.length) {
        button.addEventListener('click', (event) => {
          event.stopPropagation();
          openFloorPlanModal(unit.images[0]);
        });
      }

      tableBody.appendChild(row);
    });

    updatePaginationControls(filteredUnits.length, page);
  }

  function updatePaginationControls(totalUnits, page) {
    const pagination = document.getElementById('pagination');
    if (!pagination) return;
    pagination.innerHTML = '';

    const totalPages = Math.ceil(totalUnits / rowsPerPage);
    const createBtn = (label, targetPage) => {
      const button = document.createElement('button');
      button.textContent = label;
      if (targetPage === page) button.classList.add('active');
      button.addEventListener('click', () => populateTable(targetPage));
      return button;
    };

    if (page > 1) pagination.appendChild(createBtn('<<', page - 1));
    for (let i = 1; i <= totalPages; i++) pagination.appendChild(createBtn(i, i));
    if (page < totalPages) pagination.appendChild(createBtn('>>', page + 1));
  }

  function updateResultsCount() {
    if (!resultsCount) return;
    const total = filteredUnits.length;
    const available = filteredUnits.filter((unit) => !unit.isleased).length;
    resultsCount.textContent = `Showing ${total} units (${available} available)`;
  }

  function initializePriceSliders() {
    if (!minPriceSlider || !maxPriceSlider || !units.length) return;
    const prices = units
      .map((unit) => parseFloat(unit.rent?.toString().replace(/[^0-9.]/g, '')) || 0)
      .filter((price) => price > 0);

    if (!prices.length) return;
    const min = Math.min(...prices);
    const max = Math.max(...prices);

    minPriceSlider.min = min;
    minPriceSlider.max = max;
    minPriceSlider.value = min;

    maxPriceSlider.min = min;
    maxPriceSlider.max = max;
    maxPriceSlider.value = max;

    updatePriceDisplay();
  }

  function updatePriceDisplay() {
    if (!minPriceDisplay || !maxPriceDisplay) return;
    const minVal = parseInt(minPriceSlider.value);
    const maxVal = parseInt(maxPriceSlider.value);

    if (minVal >= maxVal) {
      minPriceSlider.value = maxVal - 25;
    }
    if (maxVal <= minVal) {
      maxPriceSlider.value = minVal + 25;
    }

    minPriceDisplay.textContent = formatCurrency(minPriceSlider.value);
    maxPriceDisplay.textContent = formatCurrency(maxPriceSlider.value);
  }

  function openUnitModal(unit) {
    if (!unitModal) return;
    if (unitDescription) {
      if (unit.description && unit.description.length) {
        unitDescription.innerHTML = unit.description.map((desc) => `<li>${desc}</li>`).join('');
      } else {
        unitDescription.innerHTML = `
          <li><strong>Floor plan:</strong> ${unit.bedBath || 'N/A'}</li>
          <li><strong>Outdoor space:</strong> ${unit.outdoor || 'None'}</li>
          <li><strong>Monthly rent:</strong> ${unit.formattedRent || ''}</li>
        `;
      }
    }

    if (unitImages) {
      if (unit.images && unit.images.length) {
        unitImages.innerHTML = unit.images
          .map((img) => `<img src="${img}" class="img-fluid mb-2" alt="Unit ${unit.unit}">`)
          .join('');
      } else {
        unitImages.innerHTML = '<p class="text-muted">Photos coming soon.</p>';
      }
    }

    if (unitInput) unitInput.value = unit.unit;
    const title = document.getElementById('unitModalLabel');
    if (title) title.textContent = `${unit.unit} Details`;

    const thankYou = document.getElementById('unitThankYou');
    const form = document.getElementById('unitInterestForm');
    if (thankYou) thankYou.style.display = 'none';
    if (form) form.style.display = 'block';

    unitModal.show();
  }

  function openFloorPlanModal(imageSrc) {
    if (!floorPlanModal || !floorPlanImage) return;
    if (!imageSrc) {
      alert('Floor plan coming soon.');
      return;
    }
    floorPlanImage.src = imageSrc;
    floorPlanModal.show();
  }

  // initial fetch so filters/table ready even before clicking
  fetchUnits();
});
