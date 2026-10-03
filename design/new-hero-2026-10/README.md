# ProsperGenics — new homepage hero (design, Oct 2026)

Static design prototype for the redesigned homepage hero. Open `index.html` in a browser.

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

## Files
- `index.html` — self-contained (inline CSS + JS).
- `assets/` — logo, team photo (`hero-bg.jpg` source, `hero-top.jpg` cropped band) and the
  per-slide photos (Sjoerd, Maxwell, Faith, Sandra, and the Lessy+Diko launcher photo).
