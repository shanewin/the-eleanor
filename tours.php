<?php
/**
 * Public tour booking page (/tours).
 *
 * Standalone — bypasses the main site's password gate, no nav, no link
 * back to index.php. All booking logic lives in api/public-tours.php.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Schedule a Tour — The Eleanor</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600&family=Tenor+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/css/tours.css?v=<?php echo @filemtime(__DIR__ . '/css/tours.css'); ?>">
    <link rel="icon" type="image/png" href="/img/favicon.png">
</head>
<body>
    <main class="tours-shell">
        <header class="tours-header">
            <div class="tours-brand">The Eleanor</div>
            <div class="tours-address">52 4th Avenue, Brooklyn, New York</div>
        </header>

        <section class="tours-hero">
            <h1>Schedule a Private Tour</h1>
            <p>Choose a date and time that works for you. A leasing agent will confirm your visit by text shortly after.</p>
        </section>

        <section class="tours-card" id="toursCard">
            <!-- Step 1: Date picker -->
            <div class="tours-step" data-step="date">
                <div class="tours-step-head">
                    <span class="tours-step-num">1</span>
                    <h2>Pick a day</h2>
                </div>
                <div class="tours-date-grid" id="dateGrid">
                    <div class="tours-loading">Loading availability…</div>
                </div>
            </div>

            <!-- Step 2: Time slots -->
            <div class="tours-step" data-step="time" hidden>
                <div class="tours-step-head">
                    <span class="tours-step-num">2</span>
                    <h2>Pick a time</h2>
                    <button type="button" class="tours-link" id="changeDateBtn">Change day</button>
                </div>
                <div class="tours-selected-date" id="selectedDateLabel"></div>
                <div class="tours-time-grid" id="timeGrid"></div>
            </div>

            <!-- Step 3: Form -->
            <div class="tours-step" data-step="form" hidden>
                <div class="tours-step-head">
                    <span class="tours-step-num">3</span>
                    <h2>Your details</h2>
                    <button type="button" class="tours-link" id="changeTimeBtn">Change time</button>
                </div>
                <div class="tours-selected-summary" id="selectedSummary"></div>
                <form class="tours-form" id="bookingForm" autocomplete="on" novalidate>
                    <div class="tours-row">
                        <label class="tours-field">
                            <span>First name *</span>
                            <input type="text" name="first_name" required autocomplete="given-name" maxlength="100">
                        </label>
                        <label class="tours-field">
                            <span>Last name *</span>
                            <input type="text" name="last_name" required autocomplete="family-name" maxlength="100">
                        </label>
                    </div>
                    <div class="tours-row">
                        <label class="tours-field">
                            <span>Email *</span>
                            <input type="email" name="email" required autocomplete="email" inputmode="email">
                        </label>
                        <label class="tours-field">
                            <span>Phone *</span>
                            <input type="tel" name="phone" required autocomplete="tel" inputmode="tel" placeholder="(555) 555-5555">
                        </label>
                    </div>
                    <label class="tours-field">
                        <span>Unit interest <em>(optional)</em></span>
                        <input type="text" name="unit" autocomplete="off" placeholder="e.g. 4B, 2-bedroom">
                    </label>
                    <label class="tours-field">
                        <span>Anything to share with us? <em>(optional)</em></span>
                        <textarea name="notes" rows="3" maxlength="1000" placeholder="Move-in timing, questions, etc."></textarea>
                    </label>
                    <!-- Honeypot — hidden from humans, bots fill it -->
                    <label class="tours-honeypot" aria-hidden="true">
                        Website <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>

                    <div class="tours-error" id="formError" hidden></div>

                    <button type="submit" class="tours-submit" id="submitBtn">Request Tour</button>
                    <p class="tours-fine-print">By submitting, you agree to receive a text from our leasing team confirming your visit.</p>
                </form>
            </div>
        </section>

        <!-- Success state -->
        <section class="tours-success" id="successPanel" hidden>
            <div class="tours-check">&#10003;</div>
            <h2>Request received</h2>
            <p id="successDetail"></p>
            <p class="tours-fine-print">We'll text you shortly to confirm your tour with one of our leasing agents.</p>
        </section>

        <footer class="tours-footer">
            <span>The Eleanor &middot; Boerum Hill, Brooklyn</span>
        </footer>
    </main>

    <script src="/js/tours.js?v=<?php echo @filemtime(__DIR__ . '/js/tours.js'); ?>"></script>
</body>
</html>
