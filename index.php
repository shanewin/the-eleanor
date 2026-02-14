<?php
session_start();

$previewPassword = 'glasskey';
$errorMessage = '';

if (isset($_GET['clear_access'])) {
    unset($_SESSION['eleanor_home_access']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
    $submittedPassword = trim($_POST['access_password']);
    if (hash_equals($previewPassword, $submittedPassword)) {
        $_SESSION['eleanor_home_access'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $errorMessage = 'Incorrect password. Please try again.';
}

if (empty($_SESSION['eleanor_home_access'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Eleanor | Private Preview</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link 
    href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500&family=Cormorant:wght@400;500;600&family=Tenor+Sans&display=swap" 
    rel="stylesheet"
  >
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="icon" type="image/png" href="img/favicon.png">
</head>
<body class="password-gate-body">
  <div class="background-overlay"></div>
  <main class="password-gate">
    <div class="password-card">
      <div class="password-logo">The Eleanor</div>
      <p class="password-copy">Private preview. Enter the password to continue.</p>
      <?php if ($errorMessage): ?>
        <p class="password-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <form method="post" class="password-form">
        <label for="access_password">Access Password</label>
        <input 
          type="password" 
          name="access_password" 
          id="access_password" 
          class="password-field" 
          placeholder="Enter password" 
          required
        >
        <button type="submit" class="btn btn-primary password-submit">Enter Site</button>
      </form>
      <p class="password-hint">Need the password? Contact the leasing team.</p>
    </div>
  </main>
</body>
</html>
<?php
  exit;
endif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>The Eleanor | Luxury Residences in Boerum Hill, Brooklyn</title>

  <!-- Google Fonts for typography system -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link 
    href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500&family=Cormorant:wght@400;500;600&family=Tenor+Sans&display=swap" 
    rel="stylesheet"
  >
  <link 
    rel="stylesheet" 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
    crossorigin="anonymous" 
    referrerpolicy="no-referrer"
  >
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
    rel="stylesheet" 
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" 
    crossorigin="anonymous"
  >
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
  <!-- Main stylesheet -->
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="css/availability.css" />
  <link rel="stylesheet" href="css/neighborhood.css" />
  <link rel="stylesheet" href="css/waitlist.css" />
  <link rel="stylesheet" href="css/slider.css" />
  <link rel="icon" type="image/png" href="img/favicon.png">

  <meta name="description" content="Discover The Eleanor: Modern luxury residences in Boerum Hill, Brooklyn. Featuring light-filled interiors, warm oak flooring, and a landscaped rooftop terrace. Schedule a viewing today.">
  <link rel="canonical" href="https://eleanor.nyc">

  <meta property="og:type" content="website">
  <meta property="og:title" content="The Eleanor | Luxury Residences in Boerum Hill, Brooklyn">
  <meta property="og:description" content="Modern luxury residences featuring light-filled interiors and a landscaped rooftop terrace.">
  <meta property="og:image" content="img/background-hero-cropped.jpg">
  <meta property="og:url" content="https://eleanor.nyc">
</head>
<body>

  <!-- Soft overlay on top of gradient -->
  <div class="background-overlay"></div>

  <!-- Vertical navigation trigger -->
  <nav class="vertical-nav">
    <button class="menu-trigger" id="menuTrigger">
      <span>Menu</span>
    </button>
  </nav>

  <nav class="section-nav" aria-label="Section navigation">
    <button class="arrow up" type="button" aria-label="Previous section">
      <img src="img/arrow-up.svg" alt="" role="presentation">
    </button>
    <button class="arrow down" type="button" aria-label="Next section">
      <img src="img/arrow-down.svg" alt="" role="presentation">
    </button>
  </nav>

  <!-- Fullscreen menu overlay -->
  <div class="nav-overlay" id="navOverlay">
    <button class="close-button" id="closeButton">&times;</button>
    <div class="nav-overlay-inner">
    <div class="nav-links">
        <a href="#about" class="nav-link">About</a>
        <a href="#explore" class="nav-link">Live, Rest, Gather</a>
        <a href="#availability" class="nav-link">Availability</a>
        <a href="#neighborhood" class="nav-link">Neighborhood</a>
        <a href="#waitlist" class="nav-link">WaitList</a>
      </div>

      <div class="nav-divider"></div>
      <div class="nav-brand-block">
        <div class="nav-logo">THE ELEANOR</div>
        <div class="nav-address">52 4th Avenue, Brooklyn, New York</div>
        <img src="/img/doorway-logo.png" alt="Doorway Logo" class="doorway-logo">
      </div>
    </div>
  </div>
  <main class="page">

    <section id="about" class="hero full-section">
    <!-- Original hero layout without container wrapper:
    <div class="hero-inner">

      <div class="hero-text-column">
        <div class="hero-brand-row">
          <div class="hero-brand-block">
            <div class="hero-logo">The Eleanor</div>
            <div class="hero-meta">52 4th Avenue, Brooklyn, New York</div>
          </div>

          <button class="menu-trigger menu-trigger-mobile" id="menuTriggerMobile">
            <span>Menu</span>
          </button>
        </div>

        <div class="hero-card">
          <div class="hero-content">
            <div class="hero-eyebrow">New Residences in Boerum Hill</div>

            <h1>Quiet Luxury Meets Brooklyn Charm</h1>

            <p class="hero-subtitle">
              The Eleanor presents modern homes with terrazzo lobby floors, sage-toned
              feature walls, marble-look countertops, and warm oak flooring. Light-filled
              interiors offer a gallery-like calm—Brooklyn living, refined.
            </p>
            <p class="hero-subtitle">
              Thoughtfully designed amenity spaces—including a landscaped rooftop terrace—extend the building’s warm, contemporary character while offering quiet places to unwind above the neighborhood.
            </p>
            <div class="hero-feature-chips">
              <span class="chip">Studios to Two-Bedrooms</span>
              <span class="chip">Oak Flooring &amp; Two-Tone Cabinetry</span>
              <span class="chip">Private Balconies and Terraces</span>
            </div>

            <div class="hero-actions">
              <a href="#availability" class="btn btn-primary">Check Availability</a>
              <a href="#waitlist" class="btn btn-ghost">Join Wait List</a>
            </div>
          </div>
        </div>
      </div>

      <div class="hero-media">
        <div class="hero-media-main">
          <img src="img/background-hero-cropped.jpg" alt="The Eleanor exterior" />
        </div>
        <div class="hero-media-secondary">
          <img src="img/7С.jpeg" alt="The Eleanor residence interior" />
        </div>
      </div>

    </div>
    -->
    <div class="container">
      <div class="hero-inner">

        <!-- Left column: brand + copy -->
        <div class="hero-text-column">
          <div class="hero-brand-row">
            <div class="hero-brand-block">
              <div class="hero-logo">The Eleanor</div>
              <div class="hero-meta">52 4th Avenue, Brooklyn, New York</div>
            </div>

            <button class="menu-trigger menu-trigger-mobile" id="menuTriggerMobile">
              <span>Menu</span>
            </button>
          </div>

          <div class="hero-card">
            <div class="hero-content">
              <div class="hero-eyebrow">New Residences in Boerum Hill</div>

              <h1>Quiet Luxury Meets Brooklyn Charm</h1>

              <p class="hero-subtitle">
                The Eleanor presents modern homes with terrazzo lobby floors, sage-toned
                feature walls, marble-look countertops, and warm oak flooring. Light-filled
                interiors offer a gallery-like calm—Brooklyn living, refined.
              </p>
              <p class="hero-subtitle">
                Thoughtfully designed amenity spaces—including a landscaped rooftop terrace—extend the building’s warm, contemporary character while offering quiet places to unwind above the neighborhood.
              </p>
              <div class="hero-feature-chips">
                <span class="chip">Studios to Two-Bedrooms</span>
                <span class="chip">Oak Flooring &amp; Two-Tone Cabinetry</span>
                <span class="chip">Private Balconies and Terraces</span>
              </div>
              

              <div class="hero-actions">
                <a href="#availability" class="btn btn-primary">Check Availability</a>
                <a href="#waitlist" class="btn btn-ghost">Join Wait List</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Right column: imagery -->
        <div class="hero-media">
          <div class="hero-media-main">
            <img src="img/background-hero-cropped.jpg" alt="The Eleanor exterior" />
          </div>
          <div class="hero-media-secondary">
            <img src="img/7С.jpeg" alt="The Eleanor residence interior" />
          </div>
        </div>

      </div>
    </div>
  </section>

      <!-- ======= EXPLORE SECTION ======= -->
    <section id="explore" class="portfolio-section section-padding full-section">
      <div class="container">
        <div class="content-container">
            <div class="section-header">
                <div class="section-subtitle">The Eleanor</div>
                <h2 class="section-title">Live, Rest, Gather</h2>
                <p>Spaces shaped with intention—from open living areas to restful rooms and thoughtfully crafted places to gather. A calm, contemporary building experience grounded in warmth and design.</p>
            </div>
            
            <div class="slider-container">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <!-- Slide 1 -->
                    <div class="swiper-slide">
                        <div class="portfolio-slide">
                            <div class="slide-image">
                                <img src="img/slider-pics/elevated_living.jpg" alt="The Eleanor Elevated Living">
                            </div>
                            <div class="slide-overlay">
                                <div class="slide-title">Elevated Living</div>
                            </div>
                            <div class="slide-content">
                                <h3>Elevated Living</h3>
                                <div class="slide-divider"></div>
                                <p>Homes shaped by The Eleanor’s refined materials—warm oak flooring, sculpted stone surfaces, and expansive windows that bring natural light deep into the space. Designed with a modern, effortless layout, it reflects the calm, contemporary character found throughout the building.</p>
                                <a href="#" class="slide-button">Join the Wait List</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Slide 2 -->
                    <div class="swiper-slide">
                        <div class="portfolio-slide">
                            <div class="slide-image">
                                <img src="img/slider-pics/light_filled_living.jpg" alt="The Eleanor Light-Filled Living">
                            </div>
                            <div class="slide-overlay">
                                <div class="slide-title">Light-Filled Living</div>
                            </div>
                            <div class="slide-content">
                                <h3>Light-Filled Living</h3>
                                <div class="slide-divider"></div>
                                <p>Expansive glass brings The Eleanor’s design ethos to life—natural light, warm oak flooring, and refined stone surfaces shaping a calm, modern environment. Every residence reflects the building’s commitment to crafted materials, soft textures, and elevated city living..</p>
                                <a href="#" class="slide-button">Check Availability</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Slide 3 -->
                    <div class="swiper-slide">
                        <div class="portfolio-slide">
                            <div class="slide-image">
                                <img src="img/slider-pics/rest_well.jpg" alt="The Eleanor Rest Well">
                            </div>
                            <div class="slide-overlay">
                                <div class="slide-title">Rest Well</div>
                            </div>
                            <div class="slide-content">
                                <h3>Rest Well</h3>
                                <div class="slide-divider"></div>
                                <p>Soft textures, warm neutrals, and natural light reflect The Eleanor’s commitment to quiet luxury—spaces crafted for balance, comfort, and a sense of calm at the end of each day.</p>
                                <a href="#" class="slide-button">Join the Wait List</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Slide 4 -->
                    <div class="swiper-slide">
                        <div class="portfolio-slide">
                            <div class="slide-image">
                                <img src="img/slider-pics/crafted_intention.jpg" alt="The Eleanor Crafted with Intention">
                            </div>
                            <div class="slide-overlay">
                                <div class="slide-title">Crafted with Intention</div>
                            </div>
                            <div class="slide-content">
                                <h3>Crafted with Intention</h3>
                                <div class="slide-divider"></div>
                                <p>Refined materials and modern craftsmanship define The Eleanor’s kitchens—marble-look surfaces, warm oak flooring, and considered cabinetry creating spaces designed for connection, creativity, and everyday ease.</p>
                                <a href="#" class="slide-button">Join the Wait List</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Slide 5 -->
                    <div class="swiper-slide">
                        <div class="portfolio-slide">
                            <div class="slide-image">
                                <img src="img/slider-pics/design_to_flow.jpg" alt="The Eleanor Garden">
                            </div>
                            <div class="slide-overlay">
                                <div class="slide-title">Designed to Flow</div>
                            </div>
                            <div class="slide-content">
                                <h3>Designed to Flow</h3>
                                <div class="slide-divider"></div>
                                <p>Generous glazing, warm oak flooring, and refined stone elements embody The Eleanor’s modern architectural identity. A calm palette and thoughtful detailing shape spaces that feel open, airy, and quietly elevated—designed with light, materiality, and intention at the forefront.</p>
                                <a href="#" class="slide-button">Check Availability</a>
                            </div>
                        </div>
                    </div>

                </div>
                
                <!-- Navigation -->
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
            
            <!-- Pagination (Numbers) - Outside swiper for better positioning -->
            <div class="swiper-pagination"></div>
            </div>
            <div class="mobile-slide-caption" aria-live="polite"></div>
        </div>
      </div>
    </section>

    <section id="availability" class="section-padding full-section">
      <div class="container">
      <div class="content-container">
            <div style="text-align: center; margin-bottom: 30px;">
              <div class="section-subtitle">The Eleanor</div>
              <h2 class="section-title">Availability</h2>
            </div>

            <div class="availability-hero-section">
              <div class="availability-subtitle-content">
                <p class="section-subtitle-glow">
                  Discover available homes at The Eleanor. Browse, filter, and schedule a viewing.
                </p>
              </div>
              <div class="availability-meta">
                <p class="availability-note">Updated daily by our leasing team</p>
              </div>
            </div>

            <div class="advanced-filters-container">
              <div class="filter-header">
                <h3>Find Your Home</h3>
                <button id="clearAllFilters" class="clear-filters-btn">Clear All Filters</button>
              </div>
              <div class="filters-grid">
                <div class="filter-group">
                  <label class="filter-label">Bedrooms</label>
                  <div class="custom-dropdown-wrapper">
                    <div class="custom-dropdown" data-name="bedroomFilter">
                      <div class="custom-dropdown-trigger">
                        <span class="dropdown-text">Any Bedrooms</span>
                        <div class="dropdown-arrow">
                          <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                            <path d="M1 1.5L6 6.5L11 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                        </div>
                      </div>
                      <div class="custom-dropdown-options">
                        <div class="dropdown-option" data-value="all">Any Bedrooms</div>
                        <div class="dropdown-option" data-value="1">1 Bedroom</div>
                        <div class="dropdown-option" data-value="2">2 Bedrooms</div>
                        <div class="dropdown-option" data-value="3">3 Bedrooms</div>
                      </div>
                    </div>
                    <select id="bedroomFilter" class="filter-select" style="display: none;">
                      <option value="all">Any Bedrooms</option>
                      <option value="1">1 Bedroom</option>
                      <option value="2">2 Bedrooms</option>
                      <option value="3">3 Bedrooms</option>
                    </select>
                  </div>
                </div>
                <div class="filter-group">
                  <label class="filter-label">Bathrooms</label>
                  <div class="custom-dropdown-wrapper">
                    <div class="custom-dropdown" data-name="bathroomFilter">
                      <div class="custom-dropdown-trigger">
                        <span class="dropdown-text">Any Bathrooms</span>
                        <div class="dropdown-arrow">
                          <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                            <path d="M1 1.5L6 6.5L11 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                        </div>
                      </div>
                      <div class="custom-dropdown-options">
                        <div class="dropdown-option" data-value="all">Any Bathrooms</div>
                        <div class="dropdown-option" data-value="1">1 Bathroom</div>
                        <div class="dropdown-option" data-value="1.5">1.5 Bathrooms</div>
                        <div class="dropdown-option" data-value="2">2 Bathrooms</div>
                      </div>
                    </div>
                    <select id="bathroomFilter" class="filter-select" style="display: none;">
                      <option value="all">Any Bathrooms</option>
                      <option value="1">1 Bathroom</option>
                      <option value="1.5">1.5 Bathrooms</option>
                      <option value="2">2 Bathrooms</option>
                    </select>
                  </div>
                </div>
                <div class="filter-group">
                  <label class="filter-label">Outdoor Space</label>
                  <div class="custom-dropdown-wrapper">
                    <div class="custom-dropdown" data-name="outdoorFilter">
                      <div class="custom-dropdown-trigger">
                        <span class="dropdown-text">Any Outdoor Space</span>
                        <div class="dropdown-arrow">
                          <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                            <path d="M1 1.5L6 6.5L11 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                        </div>
                      </div>
                      <div class="custom-dropdown-options">
                        <div class="dropdown-option" data-value="all">Any Outdoor Space</div>
                        <div class="dropdown-option" data-value="none">No Outdoor Space</div>
                        <div class="dropdown-option" data-value="balcony">Balcony</div>
                        <div class="dropdown-option" data-value="terrace">Terrace</div>
                      </div>
                    </div>
                    <select id="outdoorFilter" class="filter-select" style="display: none;">
                      <option value="all">Any Outdoor Space</option>
                      <option value="none">No Outdoor Space</option>
                      <option value="balcony">Balcony</option>
                      <option value="terrace">Terrace</option>
                    </select>
                  </div>
                </div>
                <div class="filter-group filter-group-wide">
                  <label class="filter-label">Monthly Rent</label>
                  <div class="price-range-container">
                    <div class="price-display">
                      <span id="minPriceDisplay">$2,900</span>
                      <span class="price-separator">to</span>
                      <span id="maxPriceDisplay">$5,500</span>
                    </div>
                    <div class="dual-range-slider">
                      <input type="range" id="minPriceSlider" class="price-slider price-slider-min" min="2900" max="5500" value="2900" step="25">
                      <input type="range" id="maxPriceSlider" class="price-slider price-slider-max" min="2900" max="5500" value="5500" step="25">
                    </div>
                  </div>
                </div>
              </div>
              <div class="filter-results">
                <span id="resultsCount">Showing all units</span>
              </div>
            </div>

            <div class="units-table-container">
              <table class="units-table">
                <thead>
                  <tr>
                    <th>Unit</th>
                    <th>Type</th>
                    <th>Outdoor Space</th>
                    <th>Rent</th>
                    <th>Floor Plan</th>
                  </tr>
                </thead>
                <tbody id="unit-table"></tbody>
              </table>
            </div>

            <div style="display: flex; justify-content: center; margin-top: 24px;">
              <nav aria-label="Unit pagination">
                <div id="pagination"></div>
              </nav>
            </div>
      </div>
      </div>
    </section>
    <div class="section-bridge">
      <div class="section-bridge__image">
        <img src="img/slider-pics/design_to_flow.jpg" alt="Textured architectural detail at The Eleanor">
      </div>
    </div>
  </main>

  <div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="unitModalLabel">Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="unit-images" class="mb-4"></div>
          <h4>Description</h4>
          <ul id="unitDescription"></ul>
          <form id="unitInterestForm" action="api/unit-interest.php" method="POST">
            <input type="hidden" name="csrf_token" class="csrf_token" value="">
            <input type="hidden" id="unitInput" name="unit" />
            <h4>Interested?</h4>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="firstName" class="form-label">First Name *</label>
                <input type="text" class="form-control" id="firstName" name="firstName" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="lastName" class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="lastName" name="lastName" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="phone" class="form-label">Phone</label>
                <input type="tel" class="form-control" id="phone" name="phone">
              </div>
            </div>
            <div class="mb-3">
              <label for="moveInDate" class="form-label">Desired Move-in Date</label>
              <input type="date" class="form-control" id="moveInDate" name="moveInDate">
            </div>
            <div class="mb-3">
              <label for="message" class="form-label">Message</label>
              <textarea class="form-control" id="message" name="message" rows="3" placeholder="Tell us about yourself and why you'd like to live at The Eleanor..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Submit Interest</button>
          </form>
          <div id="unitThankYou" style="display: none;" class="text-center">
            <div class="alert alert-success">
              <h4><i class="fas fa-check-circle"></i> Thank You!</h4>
              <p>Your interest in <span id="unitThankYouName">this unit</span> has been submitted successfully.</p>
              <p>We'll be in touch within 24 hours to discuss availability and next steps.</p>
            </div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="floorPlanModal" tabindex="-1" aria-labelledby="floorPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="floorPlanModalLabel">Floor Plan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <img id="floorPlanImage" src="" alt="Floor Plan" class="img-fluid">
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="leasedModal" tabindex="-1" aria-labelledby="leasedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="leasedModalLabel">Unit Not Available</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <p>Unit <strong id="leasedUnitNumber"></strong> is currently leased.</p>
          <p>Please check our other available units or join our waitlist to be notified when similar units become available.</p>
          <button type="button" class="btn btn-primary" onclick="window.location.href='#waitlist'">Join Wait List</button>
        </div>
      </div>
    </div>
  </div>

  <section id="neighborhood" class="section-padding full-section">
    <div class="container">
    <div class="content-container">
      <div class="neighborhood-tabs">
          <div class="text-center neighborhood-header">
            <div class="section-subtitle">The Eleanor</div>
            <div class="section-title">Explore BoCoCa</div>
            <p class="neighborhood-intro">
              A vibrant and highly desirable area that sits at the convergence of Downtown Brooklyn, Boerum Hill, and Gowanus. Use these curated tabs to plan your life around The Eleanor.
            </p>
          </div>

          <div class="tab-navigation" role="tablist">
            <button class="tab-btn active" data-tab="attractions" type="button">Attractions</button>
            <button class="tab-btn" data-tab="transit" type="button">Transit</button>
            <button class="tab-btn" data-tab="restaurants" type="button">Restaurants</button>
            <button class="tab-btn" data-tab="nightlife" type="button">Nightlife</button>
            <button class="tab-btn" data-tab="parks" type="button">Parks</button>
            <button class="tab-btn" data-tab="living" type="button">Living Here</button>
          </div>

          <article id="living" class="tab-content">
            <div class="living-grid">
              <div class="living-copy">
                <h3>Is BoCoCa a Good Place to Live?</h3>
                <h4>Living at a Brooklyn Crossroads: What You Need to Know</h4>
                <p>
                  This area, at the convergence of Boerum Hill, Cobble Hill, Carroll Gardens, and Downtown Brooklyn, is one of the borough's most connected and vibrant hubs. It offers an unbeatable mix of historic charm, modern convenience, and a rapidly evolving cultural scene, making it ideal for those who want to be in the heart of it all.
                </p>

                <div class="living-benefits-card">
                  <div class="benefit-item">
                    <i class="fas fa-subway"></i>
                    <span>Unbeatable transit hub with 10+ subway lines and the LIRR</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-music"></i>
                    <span>Thriving culture with Barclays Center, BAM, and Gowanus breweries</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-utensils"></i>
                    <span>World-class dining from Atlantic Avenue eateries to Smith Street bistros</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-chart-line"></i>
                    <span>A high-demand area with significant new development and investment</span>
                  </div>
                  <div class="benefit-item">
                    <i class="fas fa-building"></i>
                    <span>A unique blend of historic brownstones and modern luxury residences</span>
                  </div>
                </div>

                <h4>Urban Energy vs. Quiet Charm: The Best of Both Worlds</h4>
                <p>
                  This location offers immediate access to the energy of Barclays Center and Downtown Brooklyn, while peaceful, tree-lined blocks of Boerum Hill are just steps away. It's the perfect balance of hyper-convenience and neighborhood charm.
                </p>
              </div>

              <aside class="living-sidebar">
                <div class="why-card">
                  <h4>Why Choose BoCoCa?</h4>
                  <div class="why-list">
                    <div>
                      <i class="fas fa-map-marker-alt"></i>
                      <p><strong>Location:</strong> The most connected transit hub in Brooklyn</p>
                    </div>
                    <div>
                      <i class="fas fa-dollar-sign"></i>
                      <p><strong>Value:</strong> Premium location with endless amenities at your doorstep</p>
                    </div>
                    <div>
                      <i class="fas fa-palette"></i>
                      <p><strong>Culture:</strong> A dynamic mix of sports, performance art, and industrial chic</p>
                    </div>
                    <div>
                      <i class="fas fa-utensils"></i>
                      <p><strong>Dining:</strong> An epicurean paradise with globally-recognized restaurants</p>
                    </div>
                    <div>
                      <i class="fas fa-home"></i>
                      <p><strong>Housing:</strong> Boutique luxury residences like The Eleanor in a landmark setting</p>
                    </div>
                  </div>
                </div>
              </aside>
            </div>
          </article>

          <article id="transit" class="tab-content">
            <div class="map-section">
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3025.634!2d-73.9794849!3d40.6831375!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25badadb05227%3A0x1de33d7222bcce8a!2s52%204th%20Ave%2C%20Brooklyn%2C%20NY%2011217!5e0!3m2!1sen!2sus!4v1234567890123!5m2!1sen!2sus"
                width="100%"
                height="100%"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
              </iframe>
            </div>
            <h3>Getting Around</h3>
            <p>The Eleanor's location at 52 4th Avenue is at one of Brooklyn's best-connected transit hubs:</p>
            <div class="transit-lines">
              <div class="transit-item">
                <span class="subway-icon subway-2">2</span>
                <span class="subway-icon subway-3">3</span>
                <span class="subway-icon subway-4">4</span>
                <span class="subway-icon subway-5">5</span>
                <div>
                  <span class="transit-line">Atlantic Ave-Barclays Ctr</span>
                  <span class="transit-distance">0.2 mi</span>
                </div>
              </div>
              <div class="transit-item">
                <span class="subway-icon subway-b">B</span>
                <span class="subway-icon subway-q">Q</span>
                <div>
                  <span class="transit-line">Atlantic Ave-Barclays Ctr</span>
                  <span class="transit-distance">0.2 mi</span>
                </div>
              </div>
              <div class="transit-item">
                <span class="subway-icon subway-d">D</span>
                <span class="subway-icon subway-n">N</span>
                <span class="subway-icon subway-r">R</span>
                <span class="subway-icon subway-w">W</span>
                <div>
                  <span class="transit-line">Atlantic Ave-Barclays Ctr</span>
                  <span class="transit-distance">0.2 mi</span>
                </div>
              </div>
            </div>
            <p class="transit-note">Access to 10+ subway lines and the LIRR. Reach Manhattan in under 15 minutes.</p>
          </article>

          <article id="attractions" class="tab-content active">
            <div class="map-section">
              <video 
                width="100%" 
                height="100%" 
                autoplay 
                muted 
                loop 
                playsinline
                style="object-fit: cover;">
                <source src="video/Dissolve_D1358_233_003_1920x1080px_h264.mp4" type="video/mp4">
                Your browser does not support the video tag.
              </video>
            </div>
            <h3>Top Attractions &amp; Landmarks</h3>
            <p>You're at the center of Brooklyn's cultural and entertainment scene. Don't miss these iconic spots:</p>
            <div class="location-card">
              <div class="card-image" style="background-image: url('img/neighborhood/barclays-center-brooklyn.webp')"></div>
              <div class="card-details">
                <h4>Barclays Center</h4>
                <p>World-class arena hosting the Brooklyn Nets, major concerts, and premier events. A cornerstone of the neighborhood's energy.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>3 min walk | 620 Atlantic Ave</span>
                </div>
              </div>
            </div>
            <div class="location-card">
              <div class="card-image" style="background-image: url('img/neighborhood/brookyn-academy-of-music.jpg');"></div>
              <div class="card-details">
                <h4>Brooklyn Academy of Music (BAM)</h4>
                <p>A leading performing arts center presenting innovative theater, dance, music, and cinema since 1861.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>8 min walk | 30 Lafayette Ave</span>
                </div>
              </div>
            </div>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1580519542036-c47de6196ba5?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>Gowanus Canal & Arts Scene</h4>
                <p>Explore the evolving industrial waterfront, home to acclaimed breweries, artist studios, and unique event spaces.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>10 min walk | 2nd St & 3rd Ave</span>
                </div>
              </div>
            </div>
          </article>

          <article id="restaurants" class="tab-content">
            <h3>Food &amp; Dining</h3>
            <p>From legendary institutions to the city's hottest new tables, the culinary options here are exceptional.</p>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>Smith Street & Court Street</h4>
                <p>Stroll these iconic streets for a dense collection of acclaimed bistros, cozy wine bars, and diverse international cuisine.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>5 min walk | Smith & Atlantic Ave</span>
                </div>
              </div>
            </div>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1559339352-11d035aa65de?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>DeKalb Market Hall</h4>
                <p>A massive underground food hall at City Point featuring dozens of vendors, from Katz's Deli to ramen and arepas.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>7 min walk | 445 Albee Sq W</span>
                </div>
              </div>
            </div>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1544025162-d76694265947?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>Atlantic Avenue Eateries</h4>
                <p>A famed corridor for Middle Eastern cuisine, featuring decades-old restaurants, bakeries, and specialty food shops.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>2 min walk | Atlantic Ave & 4th Ave</span>
                </div>
              </div>
            </div>
          </article>

          <article id="nightlife" class="tab-content">
            <h3>Bars &amp; Nightlife</h3>
            <p>From historic pubs and cocktail lounges to Gowanus breweries, find your perfect spot for the evening.</p>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1532634922-8fe0b757fb13?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>Gowanus Breweries</h4>
                <p>Industrial-chic taprooms like Threes Brewing and Wild East Brewing serving craft beer in massive, communal spaces.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>12 min walk | 4th Ave & Douglass St</span>
                </div>
              </div>
            </div>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1572116469036-4f2b748d0e1d?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>Boerum Hill Cocktail Bars</h4>
                <p>Discover intimate, sophisticated bars and lounges hidden along the neighborhood's charming side streets.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>8 min walk | Smith & Wyckoff St</span>
                </div>
              </div>
            </div>
            <div class="location-card">
              <div class="card-image" style="background-image: url('https://images.unsplash.com/photo-1436076863939-06870fe779c2?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');"></div>
              <div class="card-details">
                <h4>Brooklyn Night Bazaar</h4>
                <p>A vibrant market and event space featuring food vendors, arcade games, live music, and a lively bar scene.</p>
                <div class="distance-info">
                  <i class="fas fa-walking"></i>
                  <span>15 min walk | 150 Greenpoint Ave</span>
                </div>
              </div>
            </div>
          </article>

          <article id="parks" class="tab-content">
            <h3>Parks &amp; Recreation</h3>
            <p>Escape the urban energy in these beautiful, historic nearby parks.</p>
            <div class="park-grid">
              <div class="park-card" style="background-image: url('https://images.unsplash.com/photo-1471289549423-04adaecfa1b6?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');">
                <div class="park-content">
                  <h4>Fort Greene Park</h4>
                  <p>A scenic, historic park designed by Olmsted and Vaux, featuring rolling hills, walking paths, and a revolutionary war monument.</p>
                  <div class="distance-info">
                    <i class="fas fa-walking"></i>
                    <span>15 min walk | Washington Park & Willoughby Ave</span>
                  </div>
                </div>
              </div>
              <div class="park-card" style="background-image: url('https://images.unsplash.com/photo-1507146423386-6f5d6a4ef2c3?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');">
                <div class="park-content">
                  <h4>Prospect Park</h4>
                  <p>Brooklyn's flagship 526-acre park, offering a lake, meadows, the Prospect Park Zoo, and year-round cultural festivals.</p>
                  <div class="distance-info">
                    <i class="fas fa-walking"></i>
                    <span>20 min walk | Prospect Park West & 9th St</span>
                  </div>
                </div>
              </div>
              <div class="park-card" style="background-image: url('https://images.unsplash.com/photo-1542327892-9827786c2f93?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80');">
                <div class="park-content">
                  <h4>Brooklyn Bridge Park</h4>
                  <p>A stunning 85-acre waterfront park with panoramic Manhattan views, piers, playgrounds, and recreational activities.</p>
                  <div class="distance-info">
                    <i class="fas fa-walking"></i>
                    <span>25 min walk | 334 Furman St</span>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
    </div>
    </div>
  </section>

  <section id="waitlist" class="section-padding full-section">
        <div class="container">
        <div class="content-container">
          <div class="text-center">
            <div class="section-subtitle">The Eleanor</div>
            <div class="section-title">Wait List</div>
            <p class="waitlist-intro">
              Secure your place in line for exclusive priority access when leasing opportunities open to the public.
            </p>
          </div>
          <div>
              <div class="apply-form">
                <form id="waitlistForm" action="api/form-handler.php" method="POST" class="form1 clearfix">
                  <input type="hidden" name="csrf_token" id="csrf_token" value="">
                  <input type="hidden" id="unit" name="unit">

                  <div class="row g-4">
                    <div class="col-lg-6">
                      <label for="firstName">First Name <span>*</span></label>
                      <input type="text" class="form-control" id="waitlistFirstName" name="firstName" placeholder="First Name" required>
                    </div>
                    <div class="col-lg-6">
                      <label for="lastName">Last Name <span>*</span></label>
                      <input type="text" class="form-control" id="waitlistLastName" name="lastName" placeholder="Last Name" required>
                    </div>
                  </div>

                  <div class="row g-4">
                    <div class="col-lg-6">
                      <label for="email">Email <span>*</span></label>
                      <input type="email" class="form-control email-input" id="waitlistEmail" name="email" placeholder="Email Address" required>
                    </div>
                    <div class="col-lg-6">
                      <label for="phone">Phone Number <span>*</span></label>
                      <input type="tel" class="form-control phone-input" id="waitlistPhone" name="phone" required maxlength="14" placeholder="(123) 456-7890">
                    </div>
                  </div>

                  <div class="row g-4">
                    <div class="col-lg-6">
                      <label for="moveInDate">Move-In Date</label>
                      <input type="date" class="form-control" id="waitlistMoveInDate" name="moveInDate">
                    </div>
                    <div class="col-lg-6">
                      <label for="budget">Budget</label>
                      <input type="text" class="form-control budget-input" id="waitlistBudget" name="budget" placeholder="$2,500">
                    </div>
                  </div>

                  <div class="row g-4">
                    <div class="col-lg-6">
                      <label for="hearAboutUs">How Did You Hear About Us?</label>
                      <div class="custom-dropdown-wrapper">
                        <div class="custom-dropdown" data-name="hearAboutUs">
                          <div class="custom-dropdown-trigger">
                            <span class="dropdown-text">Select an option</span>
                            <div class="dropdown-arrow">
                              <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                                <path d="M1 1.5L6 6.5L11 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                              </svg>
                            </div>
                          </div>
                          <div class="custom-dropdown-options">
                            <div class="dropdown-option" data-value="">Select an option</div>
                            <div class="dropdown-option" data-value="Google">Google</div>
                            <div class="dropdown-option" data-value="Friend">Friend</div>
                            <div class="dropdown-option" data-value="Social Media">Social Media</div>
                            <div class="dropdown-option" data-value="Advertisement">Advertisement</div>
                            <div class="dropdown-option" data-value="Real Estate Website">Real Estate Website</div>
                            <div class="dropdown-option" data-value="Walking By">Walking By</div>
                            <div class="dropdown-option" data-value="Other">Other</div>
                          </div>
                        </div>
                        <input type="hidden" name="hearAboutUs" id="hearAboutUs">
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <label for="unitType">Preferred Unit Type</label>
                      <div class="custom-dropdown-wrapper">
                        <div class="custom-dropdown" data-name="unitType">
                          <div class="custom-dropdown-trigger">
                            <span class="dropdown-text">Any Unit Type</span>
                            <div class="dropdown-arrow">
                              <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
                                <path d="M1 1.5L6 6.5L11 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                              </svg>
                            </div>
                          </div>
                          <div class="custom-dropdown-options">
                            <div class="dropdown-option" data-value="">Any Unit Type</div>
                            <div class="dropdown-option" data-value="1 Bedroom">1 Bedroom</div>
                            <div class="dropdown-option" data-value="2 Bedroom">2 Bedroom</div>
                            <div class="dropdown-option" data-value="3 Bedroom">3 Bedroom</div>
                          </div>
                        </div>
                        <input type="hidden" name="unitType" id="waitlistUnit">
                      </div>
                    </div>
                  </div>

                  <div class="row g-4">
                    <div class="col-12">
                      <label for="message">Message</label>
                      <textarea class="form-control" id="waitlistMessage" name="message" rows="4" placeholder="Tell us about yourself and what you're looking for at The Eleanor..."></textarea>
                    </div>
                  </div>

                  <div class="row g-4">
                    <div class="col-12">
                      <button type="submit" class="btn-form1-submit">Join Wait List</button>
                    </div>
                  </div>
                </form>

                <div id="waitlistThankYou" class="waitlist-thankyou text-center">
                  <h4><i class="fas fa-check-circle"></i> Thank You!</h4>
                  <p>You've been added to The Eleanor wait list. We'll contact you as soon as units become available.</p>
                  <p>Keep an eye on your inbox for exclusive updates and early access opportunities.</p>
                </div>
              </div>
          </div>
      </div>
      </div>
  </section>

  <footer class="micro-footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-address">The Eleanor | 52 4th Avenue, Brooklyn, NY</div>
        <img src="/img/doorway-logo.png" alt="Doorway Logo" class="footer-logo">
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/gh/cferdinandi/smooth-scroll/dist/smooth-scroll.polyfills.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
  <script src="js/availability.js"></script>
  <script src="js/unit-interest.js"></script>
  <script src="js/waitlist.js"></script>
  <script src="js/main.js"></script>
  <script src="js/section-scroller.js"></script>
  <script src="js/slider.js"></script>
</body>
</html>
