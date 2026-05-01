# Lead Scoring System

## Overview

The Eleanor's lead scoring system evaluates prospective tenants based on **leasing-relevant signals** — affordability, intent, engagement, and enrichment quality. Every lead receives a letter grade (A+ through F) calculated in real-time from form submission data and enrichment results.

The score is designed to answer one question for the broker: **"How likely is this person to sign a lease?"**

---

## Score Breakdown

| Signal | Max Points | What It Measures |
|--------|:----------:|------------------|
| Affordability Match | +30 | Can they afford the unit? |
| High Intent | +20 | How serious are they? |
| Verified Professional | +15 | Do we know who they are? |
| Engagement | +15 | How much did they explore the site? |
| LinkedIn Verified | +10 | Is their identity confirmed via LinkedIn? |
| Budget Provided | +5 | Did they fill in a budget? |
| Timeline Set | +5 | Did they provide a move-in date? |
| **Maximum Possible** | **100** | |

### Negative Signals

| Signal | Points | What It Means |
|--------|:------:|---------------|
| Budget Risk | -10 | Inferred salary is too low for stated budget |

---

## Letter Grade Scale

| Score | Grade | Meaning |
|:-----:|:-----:|---------|
| 90-100 | A+ | Ideal prospect — high affordability, strong intent, fully verified |
| 80-89 | A | Excellent prospect — meets most criteria |
| 70-79 | B+ | Strong prospect — minor gaps |
| 60-69 | B | Good prospect — worth pursuing |
| 50-59 | C+ | Average prospect — some concerns |
| 40-49 | C | Below average — needs manual review |
| 30-39 | D | Weak prospect — missing key signals |
| 0-29 | F | Insufficient data or major red flags |

---

## Signal Details

### 1. Affordability Match (up to +30 or -10)

**Source:** PDL `inferred_salary` field + submitted `budget`

**Industry Standard:** Monthly rent should be no more than 1/40th of annual gross salary (the "40x rule"). For a $3,500/month apartment, the applicant should earn at least $140,000/year.

```
required_salary = budget × 40

IF inferred_salary >= required_salary:
    +30 points — "Can Afford" ✅
ELSE IF inferred_salary >= required_salary × 0.60:
    +15 points — "Borderline Afford" ⚠️
ELSE:
    -10 points — "Budget Risk" ❌
```

**Example:**
- Budget: $3,500/month → requires $140,000/year
- PDL inferred salary: $150,000-250,000 → lower bound $150,000
- $150,000 >= $140,000 → **Can Afford (+30)**

**Note:** If salary data is unavailable (PDL didn't return it), this signal is skipped entirely — no points added or subtracted.

### 2. Intent Signal (+10 or +20)

**Source:** Which form they submitted

```
IF source is "Unit Interest":
    +20 points — "High Intent" 🔥
    (They clicked on a specific unit and filled out the inquiry form)

ELSE IF source is "Waitlist":
    +10 points — "Waitlist" 📋
    (General interest, not tied to a specific unit)

ELSE (Mailing List):
    +0 points
    (Just signed up for updates)
```

### 3. Verified Professional (+15)

**Source:** Enrichment pipeline (PDL → Apollo → LinkedIn)

```
IF lead has both job_title AND company:
    +15 points — "Verified Professional" 💼
```

This confirms we successfully identified who they are professionally. A lead with no enrichment data gets 0 points here.

### 4. Engagement (+10 or +15)

**Source:** Behavioral tracking (`activity_logs` table)

```
IF tracking_events >= 10:
    +15 points — "Highly Engaged" 📊
    (Spent significant time on the site, clicked multiple sections)

ELSE IF tracking_events >= 5:
    +10 points — "Engaged" 📊
    (Explored the site before submitting)

ELSE:
    +0 points
    (Submitted quickly without exploring)
```

### 5. LinkedIn Verified (+10)

**Source:** Enrichment pipeline

```
IF lead has a linkedin_url:
    +10 points — "LinkedIn Verified" 🔗
```

A LinkedIn profile confirms identity and provides career context.

### 6. Budget Provided (+5)

**Source:** Form submission

```
IF budget field is filled in and > 0:
    +5 points — "Budget Provided" 💰
```

### 7. Timeline Set (+5)

**Source:** Form submission

```
IF move_in_date field is filled in:
    +5 points — "Timeline Set" 📅
```

A move-in date signals the lead has a concrete timeline, not just browsing.

---

## Scoring Examples

### Example 1: Ideal Lead (A+)
```
Shane Winter — Revenue Operations Systems Engineer @ Doorway NYC
- Budget: $3,500 | Salary: $100,000-150,000
- Source: Unit Interest (Unit 302)
- 12 tracking events
- LinkedIn verified

Scoring:
  Can Afford:           +30  ($100k >= $140k required? No → Borderline)
  Wait — $100k < $140k: +10  (Borderline Afford)
  High Intent:          +20  (Unit Interest form)
  Verified Professional:+15  (has title + company)
  Highly Engaged:       +15  (12 events)
  LinkedIn Verified:    +10
  Budget Provided:      +5
  Timeline Set:         +5
  TOTAL:                80 → A
```

### Example 2: Casual Browser (C)
```
Jane Doe — No enrichment data
- Budget: not provided
- Source: Waitlist
- 2 tracking events
- No LinkedIn

Scoring:
  Affordability:        +0   (no salary data)
  Waitlist:             +10
  Verified Professional:+0   (no enrichment)
  Engagement:           +0   (2 events < 5)
  LinkedIn:             +0
  Budget:               +0
  Timeline:             +0
  TOTAL:                10 → F
```

### Example 3: High-Income Unit Seeker (A+)
```
John Smith — VP of Finance @ Goldman Sachs
- Budget: $5,000 | Salary: $300,000-500,000
- Source: Unit Interest (Unit 1201)
- 8 tracking events
- LinkedIn verified

Scoring:
  Can Afford:           +30  ($300k >= $200k required)
  High Intent:          +20  (Unit Interest)
  Verified Professional:+15
  Engaged:              +10  (8 events)
  LinkedIn Verified:    +10
  Budget Provided:      +5
  Timeline Set:         +5
  TOTAL:                95 → A+
```

---

## Data Sources for Scoring

| Signal | Data Source | When Captured |
|--------|------------|---------------|
| Budget | Form submission | On submit |
| Move-in date | Form submission | On submit |
| Source/Intent | Form type (waitlist vs unit interest) | On submit |
| Tracking events | `activity_logs` table via JS tracking | During browsing |
| Job title | PDL → Apollo → LinkedIn Scraper | Post-submit enrichment |
| Company | PDL → Apollo → LinkedIn Scraper | Post-submit enrichment |
| LinkedIn URL | PDL → Apollo → LinkedIn Scraper | Post-submit enrichment |
| Inferred salary | People Data Labs `inferred_salary` | Post-submit enrichment |

---

## Design Decisions

1. **Affordability is the heaviest signal (+30)** — a lead who can't afford the unit is not a viable prospect regardless of other signals.

2. **No penalty for missing data** — if PDL doesn't return a salary, we don't assume the worst. The signal is simply skipped.

3. **Intent > Engagement** — clicking on a specific unit (+20) is a stronger buying signal than spending time browsing (+15).

4. **Salary uses the lower bound** — PDL returns ranges like "$150,000-250,000". We use the lower number for a conservative affordability check.

5. **No career-based scoring** — we don't score based on seniority level, company revenue, or career tenure. Those are B2B sales metrics, not leasing metrics.

6. **Grade is calculated client-side** — the algorithm runs in JavaScript on the admin dashboard, not stored in the database. This means grades update in real-time as engagement data flows in.
