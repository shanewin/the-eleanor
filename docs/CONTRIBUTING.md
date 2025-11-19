## Contributing

Thanks for your interest in improving The Compound Bushwick site. Even if you’re the only contributor, this keeps changes consistent and easy to review.

### Quickstart
```bash
npm install
npm run lint
npm run a11y   # requires a local server; see below
```

To view locally with forms enabled:
```bash
php -S localhost:8080
```
For a static preview only:
```bash
python3 -m http.server 8080
```

### Tests and checks
- `npm run lint` runs ESLint, Stylelint, and HTMLHint.
- `npm run a11y` runs pa11y-ci against `http://localhost:8080/index.html` (start a local server first).

### Pull requests
- Branch from `main`.
- Keep changes focused and describe the user-visible impact.
- Include before/after notes or screenshots for UI changes.
- Update README/CHANGELOG when you add notable features or branding assets.

### Decisions & notes
- Availability data is fetched client-side from a Google Apps Script (see `js/availability.js`).
- Form endpoints live in `api/` (`form-handler.php`, `email-list.php`, `unit-interest.php`, `get-csrf-token.php`); update JS fetch URLs if backend hosts change.
- JSON-LD/SEO metadata lives in `index.html`; keep it aligned with live property details.
