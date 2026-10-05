# ProsperGenics — new homepage hero (design, Oct 2026)

Static design prototype for the redesigned homepage hero. Open `index.html` in a browser.

**Live preview:** https://test.prospergenics.com
Redeploy after changes with: `python deploy-to-test.py` (see that file for requirements).

## Concept
Two messages, two levels (per the strategy discussion):
1. **Hero (top):** "Create value." + subline, over a full-width team photo that fades in
   at the top/bottom and sits behind a soft paper halo so the copy stays readable.
2. **Below:** an interactive 4-slide showcase (People / Services / Tools / Learn) on a
   plain background — each slide = photo + title + description + link. "AI" is one branch,
   not the whole identity.

Plus a floating chat launcher (bottom-right) that will open the **ProsperGenics CoachOS
tenant** chat, with a WhatsApp-to-Sandra button beside it.

## Status / TODO before it goes live
- Real destination URLs for the 4 slides (now `/people`, `/services`, `/tools`, `/learn`).
- Sandra's WhatsApp number (placeholder `wa.me/254XXXXXXXXX`).
- Wire the chat launcher to the CoachOS `prospergenics` tenant (JengoWork task 4819).
- Port to the WordPress theme (`front-page.php` / template part + enqueued CSS/JS).
- About page (task 4895) is at `/about/` on the test site; the same copy still has to be
  ported to the live WordPress About page after Martien's review (production is not touched).

## About page (`about/index.html`, task 4895)
Canonical identity: ProsperGenics is a **community researching and practising value creation**
— it does *not* claim a finished/proven formula. Copy = the approved draft from task 4895
(source of truth: Jengo Knowledge project `prospergenics`: `prospergenics-identity-value-creation-2026-10-05.md`,
`projectprofiel.md`, `website-redesign-value-creation-canonical-brief.md`). Same tokens/fonts/
components as `index.html`; self-contained like the homepage, shares `../assets/`. Marked
`noindex` because it is a test page. Links back to the homepage "proof" section (`../#proof`).

## Files
- `index.html` — self-contained (inline CSS + JS).
- `about/index.html` — the English About page, self-contained, served at `/about/`.
- `assets/` — logo (`logo.png`, cropped from the theme logo), team photo (`hero-bg.jpg` source,
  `hero-top.jpg` cropped band) and the per-slide photos (Sjoerd, Maxwell, Faith, Sandra, and the
  Lessy+Diko launcher photo).
