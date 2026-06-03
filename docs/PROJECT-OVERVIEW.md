# USFQ Galápagos Kiosk — Project Overview & Redesign Brief

> **✅ UPDATE (jun 2026): the redesign described as "about to happen" below is now IMPLEMENTED and
> live.** The new "Mosaico" screen + unified PHP API run at `api.julianmaya.com` (source in `cms/`
> and `kiosk/`). See **[`KIOSK-2.0.md`](KIOSK-2.0.md)** for the as-built architecture and operations.
> This file remains as the original brief / historical context.

> **Purpose of this document.** The project is about to be redesigned from scratch. This file
> identifies *what the project is*, *what it does*, *which resources are worth carrying forward*,
> and *which are disposable*. It closes with open questions that need answers before the redesign
> begins.
>
> Companion docs (kept, see [Reusable assets](#5-reusable-assets-the-keepers)):
> `ARCHITECTURE.md` · `ICS-CORS-GUIDE.md` · `NOAA-TIDES.md` · `TZID-REFERENCE.md` ·
> `BRAND-GUIDELINES.md` · `KNOWN-ISSUES.md`

---

## 1. Identity

| | |
|---|---|
| **Name** | Kiosko USFQ Galápagos |
| **What** | Digital-signage / information kiosk for the USFQ San Cristóbal campus (Galápagos, Ecuador) |
| **Where it runs** | Full-screen browser on 16:9 monitors/TVs (1920×1080) around campus, plus a mobile variant |
| **Stack** | 100% client-side **vanilla HTML/CSS/JS** — no framework, no bundler, no build step, no npm |
| **Backend** | None for the screens themselves. One external CMS endpoint exists (see §4.3) |
| **Language** | UI and source comments are in Spanish (es-EC) |
| **Timezone** | Galápagos = **UTC-6, no DST**. Implemented as IANA `America/Costa_Rica` for browser compatibility |

---

## 2. What it does

The kiosk shows, on a loop, real-time campus information pulled live from public sources:

1. **Weekly events** — current week (Mon–Sun) grouped by day, auto-scrolling, with a live "AHORA"
   (happening-now) badge and color-coded event types. Source: a public Outlook 365 ICS calendar.
2. **Tides of the day** — high/low tide times and heights for San Cristóbal. Source: NOAA Tides API.
3. **Live clock & date** — in Galápagos time, Spanish locale.
4. **Event poster carousel** — image posters for upcoming events from a small custom CMS.
5. **Photo slideshow** — rotating campus/student photos as ambient background.
6. **Announcements** — static campus info screen (prototype: `screens/anuncios.html`).

A launcher (`kiosko.html`) cycles between screens inside an `<iframe>` on a timer.

### Resilience model
Every external data source has a **fallback**: if all CORS proxies fail, hardcoded
`useFallbackData()` events render; if NOAA fails, `renderTidesFallback()` runs. The screen is
designed to **never go blank**.

---

## 3. Current file structure

```
usfq_galapagos_kiosk/
├── html/
│   ├── index.html            ← PRODUCTION screen (dark ocean theme). Self-contained, ~1150 lines.
│   ├── index_blanco.html     ← theme experiment (white/brand)        ─┐
│   ├── index_blue.html       ← theme experiment (blue)               │  duplicate variants
│   ├── index_dark_logo.html  ← theme experiment (dark + logo)        │  (see §6 — disposable)
│   ├── index_movil.html      ← mobile/portrait variant              ─┘
│   ├── kiosko.html           ← screen-cycling launcher (iframe)
│   ├── config.js             ← centralized config (only partially used — see §6)
│   └── img/
│       ├── *.png / *.jpg      ← USFQ Galápagos logos (color / black / white)
│       ├── apple-touch-icon.png
│       └── photos/            ← 61 optimized .webp campus photos (~15 MB)
├── screens/
│   └── anuncios.html         ← announcements screen (prototype, imports config.js)
├── scripts/
│   ├── optimize_images.py    ← batch resize→webp campus photos (Pillow)
│   └── make_icon.py          ← generate apple-touch-icon
├── docs/                     ← technical guides (this folder)
└── README.md                 ← full project documentation (Spanish)
```

---

## 4. External data sources

### 4.1 Outlook 365 ICS calendar (events)
- **URL** (in `config.js` & hardcoded in screens): `outlook.office365.com/owa/calendar/…/calendar.ics`
- **Problem:** Outlook sends no CORS headers → browser blocks direct fetch.
- **Solution:** cascade of public CORS proxies (codetabs → allorigins → corsproxy.io → direct).
  Validation is **by content** (`text.includes('BEGIN:VCALENDAR')`), *not* by HTTP status.
- **Hard part:** Microsoft's proprietary TZIDs (`Central America Standard Time`,
  `tzone://Microsoft/Utc`, …) → resolved by `resolveMicrosoftTZID()`. Full guide:
  `ICS-CORS-GUIDE.md` + `TZID-REFERENCE.md`.
- Supports recurring events (`RRULE`: DAILY/WEEKLY/MONTHLY, UNTIL/COUNT/INTERVAL/BYDAY),
  expanded only within the current week for performance.

### 4.2 NOAA Tides & Currents (tides)
- **Free, public, no API key.** Endpoint `api.tidesandcurrents.noaa.gov/.../datagetter`.
- Station `9992401` = San Cristóbal. Returns 4 hi/lo extremes per day. CORS works directly.
- Full guide: `NOAA-TIDES.md`. (Replaced Stormglass, which needed a key + had a 10-call/day limit.)

### 4.3 Custom CMS — event posters  ⚠️ *not documented elsewhere*
- **Endpoint:** `https://usfq.juli.wiki/carteleras/events-cms/api/events.php?from=YYYY-MM-DD&until=YYYY-MM-DD`
- Returns JSON `{ ok, events: [{ title, event_date, image_url }] }`; drives the bottom poster carousel.
- This is the **one piece of server-side infrastructure** in the project and lives outside this repo.
  Its source/admin is **not in this codebase** — needs to be located before/during redesign (see §7).

---

## 5. Reusable assets (the keepers)

These are the hard-won, battle-tested pieces worth porting into the new design. Most are **pure
functions** with no UI coupling — ideal to extract into ES modules.

### 5.1 JavaScript logic (currently all inside `html/index.html`)
| Function(s) | What it solves | Reuse value |
|---|---|---|
| `parseICS`, `parseICSDate` | Parse raw .ics into events | **High** |
| `resolveMicrosoftTZID` | Map Microsoft TZIDs → UTC offset | **High** (rare, hard-earned) |
| `expandRRule`, `expandAndSort`, `groupRecurringEvents` | Recurring-event expansion + dedup | **High** |
| `fetchCalendar` + `CORS_PROXIES` cascade + `bustCache` | Proxy fallback fetch | **High** |
| `useFallbackData` | Hardcoded backup events | Medium (update content) |
| `fetchTides`, `parseTideTime`, `renderTides`, `renderTidesFallback` | NOAA tides | **High** |
| `detectType` | Keyword→category color mapping (academico/club/deporte/social/bienestar) | Medium (keywords are local) |
| `fetchCMSPosters`, `buildCarouselSlides`, `initCarousel` | CMS poster carousel | Medium |
| `initPhotoSlideshow` | Ambient photo rotation | Medium |
| `formatTime`, `getWeekBounds`, `getRelativeDay`, `isHappeningNow`, `stripEmojis` | Date/time + text helpers | High |
| `setupAutoScroll` | Smooth bidirectional auto-scroll for overflow lists | Medium |

> **Note:** these functions are **duplicated verbatim across all `index_*.html` variants**. In the
> redesign, extract them **once** into shared modules (e.g. `lib/ics.js`, `lib/tides.js`, `lib/time.js`).

### 5.2 Configuration
- `html/config.js` — the *intended* single source of truth (`KIOSK_CONFIG`). The redesign should make
  **all** screens actually consume it (today only `index_movil.html` and `anuncios.html` do — §6).

### 5.3 Media & brand
- **Logos** (`html/img/`): color, black, white variants — keep.
- **61 campus photos** (`html/img/photos/*.webp`, ~15 MB) — already optimized, reusable as-is.
- **Brand system** (`BRAND-GUIDELINES.md`): colors, gradient, Jost font — authoritative for the new look.

### 5.4 Tooling
- `scripts/optimize_images.py` — resize→webp pipeline (Pillow). Reusable.
- `scripts/make_icon.py` — icon generation. Reusable.

### 5.5 Knowledge
- The `docs/` guides encode non-obvious lessons (CORS quirks, TZID mapping, NOAA params). **Keep and
  carry forward** — they will save days during the rebuild.

---

## 6. The "trash" (disposable / cleanup)

Things that add weight, confusion, or risk and that the redesign should drop or fix.

### 6.1 Redundant theme variants — **strongest cleanup candidate**
`index_blanco.html`, `index_blue.html`, `index_dark_logo.html` are **full ~850–1200-line copies** of
`index.html` that differ only in CSS/theme. They each re-duplicate *all* the JS logic above.
- **Risk:** any logic fix must be made in 4–5 places (and historically wasn't).
- **Redesign action:** collapse to **one** screen + a theme/CSS layer; delete the rest.
- `index_movil.html` (portrait/mobile) is a legitimately different layout — keep its *intent*, but
  rebuild it on the shared logic rather than as a copy.

### 6.2 Committed OS cruft
- `.DS_Store`, `html/.DS_Store`, `html/img/.DS_Store` are **tracked in git**. Remove and add a
  `.gitignore` (there is **none** at the repo root today).

### 6.3 Local-only clutter (not tracked, but present on disk)
- `.venv/` — a Python virtualenv (~tens of MB), only needed to run the two scripts. Not in git;
  fine to delete/recreate. Should be gitignored regardless.
- `.claude/worktrees/` — **two complete duplicate checkouts** of the whole repo
  (`interesting-cannon-a0dd9a`, `sharp-darwin`). These inflate the working tree massively
  (repo is ~90 MB on disk). Safe to remove.

### 6.4 Config drift (fix, don't delete)
- `config.js` exists but **production `index.html` ignores it** and hardcodes `ICS_URL`,
  `NOAA_STATION`, `CORS_PROXIES`, `TIMEZONE`, etc. Same values are pasted into every variant.
- Documented as deliberate ("don't break prod"), but it defeats the purpose of central config.
  **Redesign action:** every screen imports `config.js` (or a config module) — zero hardcoded values.

### 6.5 Doc inconsistency
- `ARCHITECTURE.md`/`BRAND-GUIDELINES.md` reference `index_white.html`, but the actual file is
  `index_blanco.html`. Naming should be reconciled (and mostly mooted by §6.1).

---

## 7. Open questions for the redesign

These need decisions from you before/while rebuilding:

1. **Scope of the redesign** — visual reskin only, or also re-architecture (extract shared JS
   modules, real config-driven screens)? My recommendation: both, since the duplication is the
   biggest maintenance cost.
2. **Theme direction** — which of the existing looks wins (dark ocean / white-brand / blue), or a
   brand-new visual language? Should the brand guidelines (Jost + green/blue gradient) be the basis?
3. **Which screens stay?** Events + Tides + Posters + Photos + Announcements — keep all? Add/remove
   any (e.g. weather, transport, dining menu, news)?
4. **The CMS (§4.3)** — Where does the `events.php` CMS source live? Should it be brought into this
   repo, expanded, or replaced? Is it the long-term home for event posters/announcements?
5. **Hosting** — keep the "open an HTML file / static server" model, or move to a small served app
   (which would also let us drop the CORS proxies by proxying ICS server-side)?
6. **Config source of truth** — single `config.js`, or per-deployment config (different stations/
   calendars for other campuses)? Any plan to reuse this for non-Galápagos USFQ campuses?
7. **Language** — keep Spanish-only, or add bilingual (es/en) support for international visitors?
8. **Tech constraints** — must it stay framework-free vanilla JS (matters for the campus TVs/LG
   browser compatibility noted in git history), or is a light build step acceptable now?

---

## 8. TL;DR for the rebuild

- **Keep:** the data-fetching brains (ICS parsing + Microsoft TZID handling, NOAA tides, CORS
  cascade, fallbacks), the config concept, logos + 61 photos, brand system, Python scripts, and the
  `docs/` knowledge base.
- **Drop:** the 4 duplicate theme HTML files, tracked `.DS_Store`s, `.venv/`, `.claude/worktrees/`.
- **Fix:** consolidate logic into shared modules, make every screen actually read `config.js`, add a
  `.gitignore`, reconcile doc/file naming.
- **Decide:** the 8 questions in §7 — especially theme direction and the fate of the external CMS.
```
