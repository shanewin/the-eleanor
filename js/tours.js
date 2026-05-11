/**
 * Public tour booking UI controller.
 *
 * Flow: load 14-day availability → pick day → pick 30-min slot → fill form → POST.
 * No third-party libraries; uses the Intl DateTimeFormat API so the page renders
 * everything in the building's local timezone (America/New_York).
 */
(function () {
    'use strict';

    const API_URL = '/api/public-tours.php';
    const TZ = 'America/New_York';

    // ── State ──
    const state = {
        slotsByDate: new Map(),   // 'YYYY-MM-DD' (NY-local) → [{iso, label}]
        selectedDateKey: null,
        selectedSlotIso: null,
    };

    // ── DOM ──
    const dateGrid = document.getElementById('dateGrid');
    const timeGrid = document.getElementById('timeGrid');
    const stepDate = document.querySelector('.tours-step[data-step="date"]');
    const stepTime = document.querySelector('.tours-step[data-step="time"]');
    const stepForm = document.querySelector('.tours-step[data-step="form"]');
    const selectedDateLabel = document.getElementById('selectedDateLabel');
    const selectedSummary = document.getElementById('selectedSummary');
    const successPanel = document.getElementById('successPanel');
    const successDetail = document.getElementById('successDetail');
    const card = document.getElementById('toursCard');
    const form = document.getElementById('bookingForm');
    const submitBtn = document.getElementById('submitBtn');
    const formError = document.getElementById('formError');

    document.getElementById('changeDateBtn').addEventListener('click', backToDate);
    document.getElementById('changeTimeBtn').addEventListener('click', backToTime);
    form.addEventListener('submit', onSubmit);

    // ── Helpers ──
    function fmtDate(dateObj, opts) {
        return new Intl.DateTimeFormat('en-US', Object.assign({ timeZone: TZ }, opts)).format(dateObj);
    }

    // Group an ISO timestamp into its NY-local date key (YYYY-MM-DD).
    function nyDateKey(isoStr) {
        const d = new Date(isoStr);
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit'
        }).formatToParts(d);
        const y = parts.find(p => p.type === 'year').value;
        const m = parts.find(p => p.type === 'month').value;
        const day = parts.find(p => p.type === 'day').value;
        return `${y}-${m}-${day}`;
    }

    function setError(msg) {
        if (!msg) { formError.hidden = true; formError.textContent = ''; return; }
        formError.hidden = false;
        formError.textContent = msg;
    }

    // ── Load availability ──
    async function loadAvailability() {
        const today = new Date();
        const fromKey = nyDateKey(today.toISOString());
        const to = new Date(today.getTime() + 14 * 86400000);
        const toKey = nyDateKey(to.toISOString());

        let resp;
        try {
            resp = await fetch(`${API_URL}?action=availability&from=${fromKey}&to=${toKey}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            });
        } catch (err) {
            dateGrid.innerHTML = '<div class="tours-loading">Couldn\'t load availability. Please refresh.</div>';
            return;
        }
        if (!resp.ok) {
            dateGrid.innerHTML = '<div class="tours-loading">Couldn\'t load availability. Please refresh.</div>';
            return;
        }
        const data = await resp.json();
        const slots = Array.isArray(data.slots) ? data.slots : [];

        state.slotsByDate.clear();
        slots.forEach(iso => {
            const key = nyDateKey(iso);
            if (!state.slotsByDate.has(key)) state.slotsByDate.set(key, []);
            const d = new Date(iso);
            state.slotsByDate.get(key).push({
                iso,
                label: fmtDate(d, { hour: 'numeric', minute: '2-digit', hour12: true })
            });
        });

        renderDateGrid(fromKey, toKey);
    }

    function renderDateGrid(fromKey, toKey) {
        dateGrid.innerHTML = '';

        // Generate every NY-local date in [fromKey, toKey] inclusive.
        // Use noon-UTC to dodge DST edges when stepping by day.
        const start = new Date(fromKey + 'T12:00:00Z');
        const end = new Date(toKey + 'T12:00:00Z');
        const days = [];
        for (let t = start.getTime(); t <= end.getTime(); t += 86400000) {
            days.push(new Date(t));
        }

        let anyAvail = false;
        days.forEach(d => {
            const key = nyDateKey(d.toISOString());
            const slots = state.slotsByDate.get(key) || [];
            const has = slots.length > 0;
            if (has) anyAvail = true;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tours-date-btn';
            btn.disabled = !has;
            btn.dataset.dateKey = key;
            btn.innerHTML =
                `<span class="dow">${fmtDate(d, { weekday: 'short' })}</span>` +
                `<span class="day">${fmtDate(d, { day: 'numeric' })}</span>` +
                `<span class="mon">${fmtDate(d, { month: 'short' })}</span>`;
            if (has) btn.addEventListener('click', () => selectDate(key, d));
            dateGrid.appendChild(btn);
        });

        if (!anyAvail) {
            dateGrid.innerHTML = '<div class="tours-loading">No tour times available right now. Please check back soon.</div>';
        }
    }

    function selectDate(key, dateObj) {
        state.selectedDateKey = key;
        const slots = state.slotsByDate.get(key) || [];

        selectedDateLabel.textContent = fmtDate(dateObj, {
            weekday: 'long', month: 'long', day: 'numeric'
        });

        timeGrid.innerHTML = '';
        if (slots.length === 0) {
            timeGrid.innerHTML = '<div class="tours-no-times">No times available on this day.</div>';
        } else {
            slots.forEach(s => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'tours-time-btn';
                btn.textContent = s.label;
                btn.addEventListener('click', () => selectSlot(s));
                timeGrid.appendChild(btn);
            });
        }

        stepDate.hidden = true;
        stepTime.hidden = false;
        stepForm.hidden = true;
        window.scrollTo({ top: card.offsetTop - 40, behavior: 'smooth' });
    }

    function selectSlot(slot) {
        state.selectedSlotIso = slot.iso;
        const d = new Date(slot.iso);
        selectedSummary.textContent =
            fmtDate(d, { weekday: 'long', month: 'long', day: 'numeric' }) + ' at ' + slot.label;

        stepTime.hidden = true;
        stepForm.hidden = false;
        setError('');
        window.scrollTo({ top: card.offsetTop - 40, behavior: 'smooth' });
    }

    function backToDate() {
        state.selectedDateKey = null;
        state.selectedSlotIso = null;
        stepDate.hidden = false;
        stepTime.hidden = true;
        stepForm.hidden = true;
        window.scrollTo({ top: card.offsetTop - 40, behavior: 'smooth' });
    }

    function backToTime() {
        state.selectedSlotIso = null;
        stepDate.hidden = true;
        stepTime.hidden = false;
        stepForm.hidden = true;
        window.scrollTo({ top: card.offsetTop - 40, behavior: 'smooth' });
    }

    // ── Submit ──
    async function onSubmit(e) {
        e.preventDefault();
        setError('');

        if (!state.selectedSlotIso) {
            setError('Please pick a date and time first.');
            return;
        }

        const fd = new FormData(form);
        const payload = {
            first_name:   (fd.get('first_name') || '').trim(),
            last_name:    (fd.get('last_name')  || '').trim(),
            email:        (fd.get('email')      || '').trim(),
            phone:        (fd.get('phone')      || '').trim(),
            unit:         (fd.get('unit')       || '').trim(),
            notes:        (fd.get('notes')      || '').trim(),
            website:      fd.get('website') || '',
            scheduled_at: state.selectedSlotIso,
        };

        if (!payload.first_name || !payload.last_name || !payload.email || !payload.phone) {
            setError('Please fill out the required fields.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';

        let resp, data;
        try {
            resp = await fetch(`${API_URL}?action=book`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            data = await resp.json();
        } catch (err) {
            setError('Network error. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Request Tour';
            return;
        }

        if (!resp.ok || !data.success) {
            setError(data && data.error ? data.error : 'Something went wrong. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Request Tour';

            // If the slot was taken between page load and submit, drop the user
            // back into time selection so they can pick another.
            if (resp.status === 409) {
                setTimeout(() => loadAvailability().then(() => backToDate()), 1200);
            }
            return;
        }

        // Success
        card.hidden = true;
        successPanel.hidden = false;
        successDetail.textContent = 'Your tour request is in for ' + (data.scheduled_at_display || 'your selected time') + '.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Boot ──
    loadAvailability();
})();
