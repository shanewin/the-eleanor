# The Compound Bushwick Website

Marketing site for The Compound (Bushwick, Brooklyn) with dynamic availability pulled from Google Sheets, neighborhood highlights, and application CTAs.

- **Live site:** https://thecompoundbushwick.com (production)
- **Stack:** HTML/CSS (Bootstrap-based theme), vanilla JS + jQuery, Google Apps Script JSON feed backed by Google Sheets, PHP endpoints for form submissions.
- **Highlights:** Availability table with filtering/pagination sourced from Google Sheets, SEO-friendly metadata + JSON-LD, modal-based waitlist/email/unit-interest forms.

## Preview

![Screenshot of The Compound Bushwick website](img/readme/compound-screenshot.png)

Video walkthrough: host externally (e.g., Loom/Drive) and link here if desired.

If you serve locally on a different port (e.g., 8083), update `.pa11yci` and the examples below accordingly.

## Project Structure
- `index.html` — single-page experience with hero, amenities, availability table, neighborhood, email/waitlist modals.
- `css/` — theme styles (`style.css`, `plugins.css`, `neighborhood.css`).
- `js/availability.js` — pulls unit data from Google Apps Script, normalizes it, and powers filters/pagination/modals.
- `js/waitlist.js`, `js/email-list.js`, `js/unit-interest.js` — AJAX form handlers for the waitlist/email list/unit-interest modals.
- `api/` — server endpoints expected by the JS form code (`form-handler.php`, `email-list.php`, `unit-interest.php`, `get-csrf-token.php`).
- `img/`, `fonts/`, `assets/` — static assets and branding.

## Architecture
- Frontend: static HTML/CSS/JS (Bootstrap + jQuery) served over HTTPS.
- Dynamic inventory: Google Sheets → Google Apps Script → JSON feed consumed by `js/availability.js`.
- Forms: PHP endpoints in `api/` for waitlist, email list, and unit interest; CSRF token issued by `api/get-csrf-token.php`.
- Assets: locally served images/videos referenced in `index.html`; favicon/webmanifest in repo root.

## Dynamic Inventory via Google Sheets
- Unit availability, pricing, and descriptions are sourced from a Google Sheet, exposed via a Google Apps Script endpoint consumed in `js/availability.js` (see `fetchUnits()`).
- To update inventory/pricing/content, edit the Google Sheet; no code changes are needed as long as the Apps Script URL stays the same.
- If the Apps Script URL changes, update the `fetch` URL inside `js/availability.js` and redeploy the static assets.

## Running Locally
Because the forms submit to PHP endpoints, use a simple PHP server in the project root:
```bash
php -S localhost:8080
```
Then open http://localhost:8080.

If you only need to view the static UI (no form submissions), any static server works:
```bash
python3 -m http.server 8000
```

## External/Data Dependencies
- Availability data is fetched client-side from the Google Apps Script URL configured in `js/availability.js` (`fetchUnits()`).
- CSRF token for the waitlist form is pulled from `api/get-csrf-token.php`.
- Email list and unit interest posts expect JSON responses from `api/email-list.php` and `api/unit-interest.php` respectively.
- Videos and imagery are served from local `img/` assets referenced in `index.html`.

## Quality & Testing
- Linting: ESLint (JS), Stylelint (CSS), HTMLHint (markup).
- Accessibility smoke: pa11y-ci against the local build.
- Add the GitHub Actions workflow (see `.github/workflows/quality.yml`) to run checks on each push/PR.

Run locally:
```bash
npm install
npm run lint
npm run a11y
```

## Deployment Notes
- Sync the favicon/webmanifest files in the repo root if branding changes.
- Ensure the PHP endpoints (in `api/`) are deployed alongside the static files, or update the JS fetch URLs to point to your backend.
- If you change the Google Apps Script endpoint, update it in `js/availability.js`.
- If you relocate the PHP endpoints, keep CSRF issuance (`api/get-csrf-token.php`) and form handlers accessible over HTTPS.

## Security Notes
- Form submissions use POST endpoints under `api/` and fetch a CSRF token from `api/get-csrf-token.php`.
- Input should be validated/sanitized server-side in the PHP handlers; keep the Apps Script endpoint read-only for inventory data.
- Serve the site over HTTPS to protect form data in transit.

## Ops Playbook
- Update inventory/pricing: edit the Google Sheet; verify the Apps Script endpoint remains unchanged.
- Rotate Apps Script URL: change the fetch URL in `js/availability.js`, deploy static assets.
- Deploy forms: ensure `api/` PHP endpoints are deployed and reachable over HTTPS; CSRF token endpoint must be accessible to clients.
- Verify: run `npm run lint` and `npm run a11y` locally or check the GitHub Actions workflow before merging/publishing.

## Housekeeping
- Extra template pages were removed; `index.html` is the canonical entry point.
- Keep `js/` fetch endpoints and any schema/SEO JSON-LD in `index.html` up to date with real property info.
