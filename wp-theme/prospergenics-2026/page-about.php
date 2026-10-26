<?php /* About page - generated from design/new-hero-2026-10/about/index.html by build-wp-theme.py; edit the source, not this file. */ ?>
<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>About ProsperGenics — exploring how value is created</title>
<!-- site-chrome:meta -->
<meta name="description" content="A community in Kenya and the Netherlands that creates value together: courses and certification, AI tools, services and the people behind them." />
<meta property="og:description" content="A community in Kenya and the Netherlands that creates value together: courses and certification, AI tools, services and the people behind them." />
  <!-- /site-chrome:meta -->
<!-- test/preview environment: keep out of search indexes (production About page lives on prospergenics.com) -->
<meta property="og:type" content="website" />
<meta property="og:title" content="About ProsperGenics — exploring how value is created" />
<meta property="og:image" content="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/hero-top.jpg" />
<link rel="icon" href="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/logo.webp" />
<link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/fonts/fonts.css" />
<script>document.documentElement.className = 'js';</script>
<style>
  :root {
    /* same palette + tokens as the new homepage hero (../index.html) */
    --ink:        #0b2016;
    --green-900:  #0f3d28;
    --green-700:  #1a7a46;
    --green-600:  #22a65a;
    --green-500:  #34c759;
    --green-300:  #86e0a6;
    --green-100:  #d9f5e3;
    --accent-end: #1f9a55;   /* lightest stop of the accent heading gradient: 3.4:1 on paper (UX-15; #86e0a6 was 1.5:1) */
    --paper:      #f4faf6;
    --paper-2:    #eaf5ee;
    --line:       rgba(15,61,40,.12);
    --muted:      #4a6555;
    --body:       #27402f;
    --white:      #ffffff;
    --shadow:     0 24px 60px -20px rgba(11,32,22,.35);
    --radius:     28px;
    --ease:       cubic-bezier(.22,.61,.36,1);
    --gutter:     clamp(22px, 5vw, 72px);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--ink);
    background: var(--paper);
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
  }
  img { display: block; max-width: 100%; }
  a { color: inherit; }
  .wrap { max-width: 1240px; margin: 0 auto; padding: 0 var(--gutter); }
  .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  :focus-visible { outline: 3px solid var(--focus-ring); outline-offset: 3px; border-radius: 8px; }
  .genetics, .finale { --focus-ring: #fff; } /* dark green panels: white ring (UX-9) */

  /* ─────────────  TOP BAR  ───────────── */

  /* ─────────────  HERO  ───────────── */
  .eyebrow {
    display: inline-flex; align-items: center; gap: 10px;
    font-family:'Sora',sans-serif; font-weight:600; font-size:13px; letter-spacing:.08em; text-transform:uppercase;
    color: var(--green-700);
    padding: 8px 14px 8px 10px; border-radius: 999px;
    background: var(--green-100); border: 1px solid rgba(34,166,90,.2);
    margin-bottom: 26px;
  }
  .eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-500); box-shadow: 0 0 0 4px rgba(52,199,89,.22); }

  .hero { position: relative; overflow: hidden; padding: clamp(36px, 6vw, 84px) 0 0; }
  .hero::before, .hero::after { content: ""; position: absolute; z-index: 0; border-radius: 50%; pointer-events: none; }
  .hero::before { width: 620px; height: 620px; right: -240px; top: 0; background: radial-gradient(circle, var(--green-100), transparent 65%); opacity: .7; }
  .hero::after  { width: 460px; height: 460px; left: -220px; top: 160px; background: radial-gradient(circle, var(--paper-2), transparent 70%); }
  .hero .wrap { position: relative; z-index: 2; }
  h1 {
    font-family: 'Sora', sans-serif; font-weight: 800;
    font-size: clamp(40px, 6.6vw, 88px);
    line-height: .98; letter-spacing: -.035em;
    color: var(--ink); max-width: 20ch;
  }
  h1 .accent {
    display: block;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--accent-end) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    padding-bottom: .08em;
  }
  .lede {
    margin-top: 28px; max-width: 34ch;
    font-family: 'Sora', sans-serif; font-weight: 500;
    font-size: clamp(19px, 2.3vw, 26px); line-height: 1.38; color: var(--green-900);
  }
  .hero { padding-bottom: clamp(10px, 2vw, 24px); }

  /* ─────────────  SECTIONS  ───────────── */
  .block { position: relative; padding: clamp(56px, 8vw, 112px) 0 0; }
  h2 {
    font-family: 'Sora', sans-serif; font-weight: 800;
    font-size: clamp(30px, 4.4vw, 54px); line-height: 1.04; letter-spacing: -.03em; color: var(--ink);
    max-width: 18ch;
  }
  .prose p { font-size: clamp(17px, 1.55vw, 19.5px); line-height: 1.68; color: var(--body); max-width: 56ch; }
  .prose p + p { margin-top: 1.05em; }
  .prose strong { color: var(--ink); font-weight: 600; }
  .prose .punch { font-family: 'Sora', sans-serif; font-weight: 700; font-size: clamp(20px, 2.2vw, 26px); line-height: 1.3; color: var(--green-900); letter-spacing: -.01em; }
  .prose .punch.soft { font-weight: 600; color: var(--green-700); }

  .split { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: clamp(32px, 6vw, 88px); align-items: center; }
  .split.flip > :first-child { order: 2; }
  .prose h2, .split h2 { margin-bottom: 28px; }
  @media (max-width: 900px){
    .split, .split.flip { grid-template-columns: 1fr; gap: 34px; }
    .split.flip > :first-child { order: 0; }
  }

  /* the big question */
  .question-card {
    position: relative; overflow: hidden;
    background: var(--white); border: 1px solid var(--line); border-radius: var(--radius);
    padding: clamp(28px, 4vw, 52px); box-shadow: var(--shadow);
  }
  .question-card::before { content:""; position:absolute; inset: 0 0 auto 0; height: 5px; background: linear-gradient(90deg, var(--green-600), var(--green-300)); }
  .question-card .q-label { font-family:'Sora',sans-serif; font-weight:600; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color: var(--green-700); margin-bottom: 14px; }
  .question-card .q {
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: clamp(30px, 4.2vw, 52px); line-height: 1.04; letter-spacing: -.03em;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--accent-end) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; padding-bottom: .06em;
  }
  .question-card .q-examples { margin-top: 22px; font-size: clamp(16px, 1.45vw, 18px); line-height: 1.62; color: var(--body); }

  .honest { margin-top: 30px; padding-left: 20px; border-left: 4px solid var(--green-500); }
  .honest p { font-family: 'Sora', sans-serif; font-weight: 700; font-size: clamp(20px, 2.2vw, 26px); line-height: 1.3; color: var(--green-900); letter-spacing: -.01em; max-width: none; }
  .honest p + p { margin-top: 4px; color: var(--green-700); }

  .genetics {
    margin-top: clamp(30px, 4vw, 48px);
    display: grid; grid-template-columns: auto 1fr; gap: 20px; align-items: start;
    background: linear-gradient(135deg, var(--green-900), #0b2016);
    color: #fff; border-radius: var(--radius); padding: clamp(24px, 3.6vw, 44px);
    box-shadow: var(--shadow);
  }
  .genetics .k { width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; font-size: 26px; background: rgba(134,224,166,.16); border: 1px solid rgba(134,224,166,.3); }
  .genetics p { font-size: clamp(17px, 1.7vw, 21px); line-height: 1.6; color: rgba(255,255,255,.88); max-width: 62ch; }
  .genetics p b { color: var(--green-300); font-family: 'Sora', sans-serif; font-weight: 700; }
  @media (max-width: 560px){ .genetics { grid-template-columns: 1fr; gap: 14px; } }

  /* Kenya ⇄ Netherlands exchange card (text-based; photos live on the homepage) */
  .exchange {
    background: var(--white); border: 1px solid var(--line); border-radius: var(--radius);
    padding: clamp(28px, 4vw, 48px); box-shadow: var(--shadow); text-align: center;
    max-width: 460px; margin: 0 auto; position: relative; overflow: hidden;
  }
  .exchange::before { content:""; position:absolute; inset: 0 0 auto 0; height: 5px; background: linear-gradient(90deg, var(--green-600), var(--green-300)); }
  .exchange .pair { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; font-family:'Sora',sans-serif; font-weight:800; font-size: clamp(20px, 2.4vw, 27px); letter-spacing: -.02em; color: var(--green-900); }
  .exchange .pair .sw { color: var(--green-700); font-size: clamp(24px, 2.8vw, 34px); font-weight: 600; }
  .exchange p { margin-top: 16px; color: var(--muted); font-size: 15px; line-height: 1.6; }

  /* the learning cycle */
  .cycle-wrap { margin-top: clamp(44px, 6vw, 72px); }
  .cycle-intro { font-family: 'Sora', sans-serif; font-weight: 700; font-size: clamp(20px, 2.2vw, 26px); color: var(--green-900); letter-spacing: -.01em; margin-bottom: 22px; }
  ol.cycle {
    list-style: none; counter-reset: step; display: flex; flex-wrap: wrap; align-items: stretch; gap: 14px 0;
    background: var(--white); border: 1px solid var(--line); border-radius: var(--radius); padding: clamp(18px, 2.4vw, 28px); box-shadow: 0 18px 44px -26px rgba(11,32,22,.3);
  }
  ol.cycle li {
    counter-increment: step; position: relative; flex: 1 1 0; min-width: 138px;
    display: flex; flex-direction: column; align-items: flex-start; gap: 10px;
    padding: 6px 28px 6px 6px; font-family: 'Sora', sans-serif; font-weight: 700; font-size: 17px; line-height: 1.2; color: var(--green-900);
  }
  ol.cycle li::before {
    content: counter(step); width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center;
    font-size: 14px; font-weight: 700; color: #fff; background: linear-gradient(135deg, var(--green-700), var(--green-500));
  }
  ol.cycle li:not(:last-child)::after {
    content: "→"; position: absolute; right: 4px; top: 8px; font-size: 22px; font-weight: 600; color: var(--green-700);
  }
  ol.cycle li.again { color: var(--green-700); }
  ol.cycle li.again::before { content: "↺"; font-size: 19px; background: var(--green-900); }
  ol.cycle li.again::after { display: none; }
  @media (max-width: 1000px){
    ol.cycle li { flex: 1 1 30%; min-width: 150px; }
    ol.cycle li:nth-child(3)::after { content: ""; }
  }
  @media (max-width: 620px){
    ol.cycle { flex-direction: column; gap: 0; padding: 20px 22px; }
    ol.cycle li { flex: none; flex-direction: row; align-items: center; gap: 16px; padding: 10px 0 24px 0; min-width: 0; }
    ol.cycle li:last-child { padding-bottom: 10px; }
    ol.cycle li:not(:last-child)::after { content: "↓"; right: auto; left: 8px; top: auto; bottom: -2px; font-size: 18px; }
  }

  /* forms of value */
  .values { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
  .values span {
    display: inline-flex; align-items: center; gap: 8px;
    font-family:'Sora',sans-serif; font-weight:600; font-size: 14px; color: var(--green-900);
    background: rgba(255,255,255,.92); border: 1px solid var(--line); border-radius: 999px; padding: 10px 16px;
  }
  .values span::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--green-500); box-shadow: 0 0 0 4px rgba(52,199,89,.2); }
  .values-label { margin-top: 30px; font-family:'Sora',sans-serif; font-weight:600; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color: var(--green-700); }
  .values-label + .values { margin-top: 12px; }

  .tool-note {
    margin-top: 30px; border-radius: 22px; padding: clamp(22px, 3vw, 32px);
    background: var(--green-100); border: 1px solid rgba(34,166,90,.22);
  }
  .tool-note p { font-size: 16.5px; line-height: 1.62; color: var(--body); }
  .tool-note strong { color: var(--ink); font-weight: 600; }
  .tool-note .t { margin-top: 14px; font-family:'Sora',sans-serif; font-weight:700; font-size: clamp(19px, 2vw, 23px); line-height: 1.3; color: var(--green-900); letter-spacing: -.01em; }

  /* closing statement */
  .finale {
    position: relative; overflow: hidden; margin-top: clamp(40px, 5vw, 64px);
    background: linear-gradient(135deg, var(--green-900) 0%, #0b2016 100%); color: #fff;
    border-radius: var(--radius); padding: clamp(32px, 5.5vw, 76px); box-shadow: var(--shadow);
  }
  .finale::before { content:""; position:absolute; width: 520px; height: 520px; right: -170px; top: -210px; border-radius: 50%; background: radial-gradient(circle, rgba(52,199,89,.38), transparent 66%); pointer-events: none; }
  .finale > * { position: relative; }
  .finale .no { font-family:'Sora',sans-serif; font-weight: 600; font-size: clamp(17px, 1.9vw, 22px); color: rgba(255,255,255,.78); }
  .finale .big { margin-top: 10px; font-family:'Sora',sans-serif; font-weight: 800; font-size: clamp(30px, 5vw, 62px); line-height: 1.04; letter-spacing: -.03em; max-width: 18ch; }
  .finale .big .accent { display: block; color: var(--green-300); }
  .finale .believe { margin-top: 26px; font-size: clamp(17px, 1.7vw, 21px); line-height: 1.6; color: rgba(255,255,255,.88); max-width: 52ch; }
  .finale .believe b { color: #fff; font-family:'Sora',sans-serif; font-weight: 700; }
  .actions { margin-top: 34px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
  .btn {
    display: inline-flex; align-items: center; gap: 10px;
    font-family: 'Sora', sans-serif; font-weight: 600; font-size: 16px;
    padding: 16px 26px; border-radius: 999px; cursor: pointer; border: none;
    text-decoration: none; transition: transform .2s var(--ease), box-shadow .2s var(--ease), color .2s;
  }
  .btn .arrow { transition: transform .25s var(--ease); }
  .btn:hover .arrow { transform: translateX(5px); }
  .btn-primary { color: #fff; background: linear-gradient(100deg, var(--green-700), var(--green-600)); box-shadow: 0 14px 30px -12px var(--green-600); }
  .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -12px var(--green-600); }
  .btn-ghost { color: #fff; background: transparent; padding-left: 8px; }
  .btn-ghost:hover { color: var(--green-300); }

  /* where the learning happens */
  .practice { padding-bottom: clamp(60px, 8vw, 110px); }
  .practice-head { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; flex-wrap:wrap; margin-bottom: 28px; }
  .practice-head h2 { font-size: clamp(26px, 3.4vw, 40px); max-width: 22ch; letter-spacing: -.02em; font-weight: 700; }
  .practice-head p { color: var(--muted); font-size: 15.5px; line-height: 1.55; max-width: 42ch; }
  .cards { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
  @media (max-width: 1100px){ .cards { grid-template-columns: repeat(2,1fr); } }
  @media (max-width: 560px){ .cards { grid-template-columns: 1fr; } }
  .card {
    position: relative; overflow: hidden;
    background: var(--white); border: 1px solid var(--line); border-radius: 20px;
    padding: 22px 20px 24px; transition: transform .25s var(--ease), box-shadow .25s var(--ease), border-color .25s;
  }
  .card:hover { transform: translateY(-6px); box-shadow: var(--shadow); border-color: transparent; }
  .card .k { width: 42px; height: 42px; border-radius: 12px; display:grid; place-items:center; font-size:20px; background: var(--green-100); margin-bottom: 16px; }
  .card h3 { font-family:'Sora',sans-serif; font-weight:700; font-size:17px; color:var(--green-900); margin-bottom: 7px; }
  .card p { font-size: 13.5px; line-height: 1.5; color: var(--muted); }
  .card .tag { margin-top: 14px; font-size: 11.5px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color: var(--green-700); }
  .card::before { content:""; position:absolute; inset:auto 0 0 0; height:3px; background: linear-gradient(90deg,var(--green-600),var(--green-300)); transform: scaleX(0); transform-origin:left; transition: transform .3s var(--ease); }
  .card:hover::before { transform: scaleX(1); }
  /* footer */

  /* scroll reveal (progressive: content stays visible without JS) */
  .js .rv { opacity: 0; transform: translateY(22px); transition: opacity .8s var(--ease), transform .8s var(--ease); }
  .js .rv.in { opacity: 1; transform: none; }
  .js .card.rv.in { transition: opacity .8s var(--ease), transform .25s var(--ease), box-shadow .25s var(--ease), border-color .25s; }
  .reveal { opacity: 0; transform: translateY(22px); animation: rise .9s var(--ease) forwards; }
  .d1{animation-delay:.05s} .d2{animation-delay:.15s} .d3{animation-delay:.28s}
  @keyframes rise { to { opacity:1; transform:none; } }
  @media (prefers-reduced-motion: reduce){
    *{ animation:none !important; transition:none !important; }
    .reveal, .js .rv { opacity:1; transform:none; }
  }
  /* site-chrome:css */

  /* ── site header + footer: wp/inc/site-chrome/site-chrome.css, the one copy for every page type ──
     WordPress prints it from inc/site-chrome.php (inner pages and the jengo-listings takeover);
     site_chrome.py copies it into index.html, about/index.html and the people/experts/community pages.
     Literal colours and every rule scoped under .site-header / .site-footer, so pages whose own
     stylesheets reuse .pg-nav, .brand, .nav-links or .nav-cta (the jengo-listings plugin) can't change it. */
  /* the focus ring, one token for every page type (audit UX-9): green-700 is 5.07:1 on paper,
     5.36:1 on white; dark green surfaces (the footer, the inner-page heroes) switch it to white,
     >= 4.1:1 on every green they use. Page styles use var(--focus-ring, #1a7a46). */
  :root { --focus-ring: #1a7a46; }
  .site-footer, .post-hero { --focus-ring: #fff; }
  .site-header :focus-visible, .site-footer :focus-visible, .skip-link:focus-visible { outline: 3px solid var(--focus-ring); outline-offset: 3px; }
  /* skip link (audit UX-16): first in <body> on every page, off screen until it has focus */
  .skip-link { position: fixed; top: calc(12px + var(--wp-admin--admin-bar--height, 0px)); left: 16px; z-index: 100000; padding: 12px 18px; border-radius: 10px; background: #0f3d28; color: #fff; font: 600 15px/1.2 'Inter', system-ui, sans-serif; text-decoration: none; transform: translateY(calc(-100% - 24px)); }
  .skip-link:focus { transform: none; }
  .site-header { position: sticky; top: 0; z-index: 50; box-sizing: border-box; width: 100%; max-width: none; margin: 0; display: flex; align-items: center; gap: 28px; min-height: 76px; padding: 14px clamp(20px, 5vw, 72px); background: rgba(244,250,246,.94); -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px); border: 0; border-bottom: 1px solid rgba(15,61,40,.12); font-family: 'Inter', system-ui, sans-serif; transition: transform .3s cubic-bezier(.22,.61,.36,1); }
  .site-header *, .site-header *::before, .site-header *::after { box-sizing: border-box; }
  .site-header .brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; margin: 0 auto 0 0; padding: 0; text-decoration: none; }
  .site-header .brand img { height: 40px; width: auto; display: block; }
  .site-header .brand-text { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 20px; line-height: 1; color: #0f3d28; letter-spacing: -.02em; white-space: nowrap; }
  .site-header .nav-links { display: flex; align-items: center; gap: 28px; margin: 0; padding: 0; }
  .site-header .nav-links a { font-size: 15px; font-weight: 500; line-height: 1.2; color: #0f3d28; text-decoration: none; white-space: nowrap; opacity: .8; padding: 4px 0; box-shadow: none; transition: opacity .2s; }
  .site-header .nav-links a:hover, .site-header .nav-links a:focus-visible { opacity: 1; }
  /* the current section (aria-current, set by the shared nav render on every page type): underline
     only, no bolder weight, so links never shift between pages */
  .site-header .nav-links a[aria-current="page"] { opacity: 1; box-shadow: inset 0 -2px 0 #1a7a46; }
  .site-header .nav-account { position: relative; display: flex; align-items: center; gap: 18px; flex-shrink: 0; }
  .site-header .nav-account-link { font-size: 15px; font-weight: 500; line-height: 1.2; color: #0f3d28; text-decoration: none; white-space: nowrap; opacity: .8; transition: opacity .2s; }
  .site-header .nav-account-link:hover, .site-header .nav-account-link:focus-visible { opacity: 1; }
  .site-header .nav-account-join { font-weight: 600; color: #1a7a46; opacity: 1; }
  .site-header .nav-account-toggle { display: inline-flex; align-items: center; gap: 8px; margin: 0; padding: 2px; border: 0; border-radius: 999px; background: transparent; color: #0f3d28; font: 500 15px/1.2 'Inter', system-ui, sans-serif; white-space: nowrap; cursor: pointer; }
  .site-header .nav-account-toggle::after { content: ""; width: 7px; height: 7px; margin: 0 4px 0 2px; border-right: 1.5px solid currentColor; border-bottom: 1.5px solid currentColor; transform: translateY(-2px) rotate(45deg); transition: transform .2s; }
  .site-header .nav-account-toggle[aria-expanded="true"]::after { transform: translateY(2px) rotate(225deg); }
  .site-header .nav-account-toggle img, .site-header .nav-account-initial { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
  .site-header .nav-account-initial { display: inline-flex; align-items: center; justify-content: center; background: #d9f5e3; color: #0f3d28; font-weight: 700; font-size: 14px; }
  .site-header .nav-account-menu { position: absolute; top: calc(100% + 14px); right: 0; z-index: 2; display: grid; min-width: 210px; padding: 6px; background: #fff; border: 1px solid rgba(15,61,40,.12); border-radius: 14px; box-shadow: 0 24px 60px -20px rgba(11,32,22,.35); }
  .site-header .nav-account-menu[hidden] { display: none; }
  .site-header .nav-account-menu a { display: flex; align-items: center; min-height: 42px; padding: 0 12px; border-radius: 10px; font-size: 15px; font-weight: 500; color: #0f3d28; text-decoration: none; white-space: nowrap; }
  .site-header .nav-account-menu a:hover, .site-header .nav-account-menu a:focus-visible { background: #eaf5ee; }
  .site-header .nav-cta { display: inline-flex; align-items: center; flex-shrink: 0; margin: 0; font-family: 'Sora', sans-serif; font-weight: 600; font-size: 14px; line-height: 1.2; padding: 10px 18px; border-radius: 999px; border: 1.5px solid #1a7a46; color: #0f3d28; background: transparent; text-decoration: none; white-space: nowrap; cursor: pointer; transition: background .2s, color .2s; }
  .site-header .nav-cta:hover { background: #1a7a46; color: #fff; }
  .site-header .nav-toggle { display: none; flex-shrink: 0; width: 44px; height: 44px; margin: 0; padding: 0; border-radius: 12px; border: 1.5px solid rgba(15,61,40,.12); background: #fff; cursor: pointer; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; }
  .site-header .nav-toggle[aria-expanded="true"] { background: #eaf5ee; border-color: #86e0a6; }
  .site-header .nav-toggle-bars, .site-header .nav-toggle-bars::before, .site-header .nav-toggle-bars::after { display: block; width: 20px; height: 2px; border-radius: 2px; background: #0f3d28; transition: transform .2s cubic-bezier(.22,.61,.36,1), opacity .2s; }
  .site-header .nav-toggle-bars { position: relative; }
  .site-header .nav-toggle-bars::before, .site-header .nav-toggle-bars::after { content: ""; position: absolute; left: 0; }
  .site-header .nav-toggle-bars::before { top: -6px; }
  .site-header .nav-toggle-bars::after { top: 6px; }
  .site-header .nav-toggle[aria-expanded="true"] .nav-toggle-bars { background: transparent; }
  .site-header .nav-toggle[aria-expanded="true"] .nav-toggle-bars::before { transform: translateY(6px) rotate(45deg); }
  .site-header .nav-toggle[aria-expanded="true"] .nav-toggle-bars::after { transform: translateY(-6px) rotate(-45deg); }
  .site-header .nav-panel { position: absolute; top: 100%; left: 12px; right: 12px; margin-top: 6px; max-height: calc(100vh - 100px); overflow-y: auto; background: #fff; border: 1px solid rgba(15,61,40,.12); border-radius: 18px; box-shadow: 0 24px 60px -20px rgba(11,32,22,.35); padding: 10px; }
  .site-header .nav-panel[hidden] { display: none; }
  .site-header .nav-panel nav { display: grid; }
  .site-header .nav-panel a { display: flex; align-items: center; min-height: 48px; padding: 0 14px; border-radius: 12px; font-size: 17px; font-weight: 500; color: #0f3d28; text-decoration: none; opacity: 1; }
  .site-header .nav-panel a:hover, .site-header .nav-panel a:focus-visible { background: #eaf5ee; }
  .site-header .nav-panel a[aria-current="page"] { background: #eaf5ee; box-shadow: inset 3px 0 0 #1a7a46; }
  .site-header .nav-panel .nav-panel-account { display: grid; margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(15,61,40,.12); }
  .site-header .nav-panel .nav-panel-cta { margin-top: 6px; justify-content: center; background: #1a7a46; color: #fff; font-family: 'Sora', sans-serif; font-weight: 600; }
  .site-header .nav-panel .nav-panel-cta:hover { background: #0f3d28; }
  /* below 1181px the links and account links move into the menu, so the bar always stays one row */
  @media (max-width: 1180px) {
    .site-header { gap: 16px; }
    .site-header .nav-links, .site-header .nav-account { display: none; }
    .site-header .nav-toggle { display: inline-flex; }
  }
  @media (min-width: 1181px) { .site-header .nav-panel { display: none !important; } }
  @media (max-width: 880px) {
    .site-header { min-height: 68px; padding: 12px 16px; }
    .site-header > .nav-cta { display: none; }
    .site-header .brand img { height: 36px; }
  }
  /* homepage: fixed over the hero, visible at load, hidden while scrolling down (site-chrome.js) */
  .site-header--autohide { position: fixed; left: 0; right: 0; }
  .site-header--autohide.is-hidden { transform: translateY(calc(-100% - 60px)); }
  /* keyboard users always see where focus is, even before the script runs */
  .site-header--autohide.is-hidden:focus-within { transform: none; }
  /* below the WordPress admin bar (32px, 46px from 782px down; it scrolls away below 601px) */
  .site-header--adminbar { top: 32px; }
  @media (max-width: 782px) { .site-header--adminbar { top: 46px; } }
  @media (max-width: 600px) { .site-header--adminbar:not(.site-header--autohide) { top: 0; } }
  @media (prefers-reduced-motion: reduce) { .site-header, .site-header * { transition: none !important; } }

  .site-footer { background: #0f3d28; color: rgba(255,255,255,.78); margin: clamp(48px, 7vw, 96px) 0 0; padding: 0; border: 0; font-family: 'Inter', system-ui, sans-serif; }
  .site-footer *, .site-footer *::before, .site-footer *::after { box-sizing: border-box; }
  .site-footer .site-footer-inner { max-width: 1500px; margin: 0 auto; padding: clamp(40px, 5vw, 64px) clamp(20px, 5vw, 72px) 28px; display: grid; grid-template-columns: minmax(0, 1.4fr) repeat(3, minmax(0, 1fr)); gap: 32px 40px; }
  .site-footer .f-brand { display: grid; gap: 14px; align-content: start; }
  .site-footer .f-brand img { height: 44px; width: auto; background: #f4faf6; border-radius: 10px; padding: 4px; }
  .site-footer .f-brand p { font-size: 15px; line-height: 1.6; max-width: 34ch; color: rgba(255,255,255,.78); margin: 0; text-shadow: none; }
  .site-footer h2 { font-family: 'Sora', sans-serif !important; font-size: 13px; font-weight: 700; line-height: 1.3; letter-spacing: .1em; text-transform: uppercase; color: #86e0a6; margin: 0 0 14px; text-shadow: none; }
  .site-footer ul { list-style: none; display: grid; gap: 10px; margin: 0; padding: 0; }
  .site-footer li { margin: 0; padding: 0; }
  .site-footer a { color: #fff; text-decoration: none; font-size: 15px; line-height: 1.4; }
  .site-footer a:hover { text-decoration: underline; }
  .site-footer .site-footer-base { max-width: 1500px; margin: 0 auto; padding: 18px clamp(20px, 5vw, 72px) 28px; border-top: 1px solid rgba(255,255,255,.14); display: flex; flex-wrap: wrap; gap: 10px 28px; font-size: 13.5px; }
  .site-footer .site-footer-base nav { display: flex; flex-wrap: wrap; gap: 8px 20px; }
  .site-footer .site-footer-base a { font-size: 13.5px; color: rgba(255,255,255,.85); }
  @media (max-width: 880px) {
    .site-footer .site-footer-inner { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .site-footer .f-brand { grid-column: 1 / -1; }
  }

  /* /site-chrome:css */
</style>
<?php wp_head(); ?>
</head>
<body>

  <!-- site-chrome:header (inc/site-chrome.php) -->
  <?php echo prospergenics_2026_site_header( array( 'current' => 'About' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

  <main id="main">
    <!-- HERO -->
    <section class="hero">
      <div class="wrap">
        <p class="eyebrow reveal d1"><span class="dot"></span>About ProsperGenics</p>
        <h1 class="reveal d2">We are exploring <span class="accent">how value is created.</span></h1>
        <p class="lede reveal d3">ProsperGenics is a community of people who want to create value — for themselves, for others, and for the communities around them.</p>
      </div>
    </section>

    <!-- THE QUESTION -->
    <section class="block" id="question">
      <div class="wrap">
        <h2 class="sr-only">The big question</h2>
        <div class="split">
          <div class="prose rv">
            <p>But behind that simple idea is a much bigger question:</p>
            <p>What makes one idea turn into an opportunity, one skill into an income, one connection into a collaboration, or one project into something that improves people’s lives?</p>
            <div class="honest">
              <p>We don’t pretend to have the final answer.</p>
              <p>We are trying to find out.</p>
            </div>
          </div>
          <div class="question-card rv">
            <div class="q-label">The question</div>
            <p class="q">How is value actually created?</p>
          </div>
        </div>
        <div class="genetics rv">
          <div class="k" aria-hidden="true">🧬</div>
          <p>You could say we are searching for the <b>genetics of prosperity</b>: the principles, conditions and combinations that allow people to create more value with what they already have.</p>
        </div>
      </div>
    </section>

    <!-- WE LEARN BY DOING -->
    <section class="block" id="learn-by-doing">
      <div class="wrap">
        <div class="prose rv">
          <h2>We learn by doing</h2>
          <p>ProsperGenics is not only a research project. <strong>Our laboratory is the real world.</strong></p>
          <p>We build businesses and technology. We work with entrepreneurs and communities. We teach and learn new skills. We connect people who can help each other. We experiment with AI and new ways of working. And we work on real projects with real customers, opportunities and consequences.</p>
          <p>Some of those experiments succeed. Others don’t.</p>
          <p class="punch soft">Both teach us something.</p>
          <p>We try to capture those lessons, turn them into shared knowledge, and use what we learn to make the next project better.</p>
        </div>
        <div class="cycle-wrap rv">
          <p class="cycle-intro">That creates a continuous cycle:</p>
          <ol class="cycle" aria-label="The ProsperGenics learning cycle">
            <li>Learn</li>
            <li>Connect</li>
            <li>Build</li>
            <li>Create value</li>
            <li>Measure what happened</li>
            <li class="again">Learn again</li>
          </ol>
        </div>
      </div>
    </section>

    <!-- EMPOWERMENT -->
    <section class="block" id="empowerment">
      <div class="wrap">
        <div class="split">
          <div class="prose rv">
            <h2>Empowerment is the goal</h2>
            <p>For us, creating value is not simply about making more money.</p>
            <p>Money can be one form of value. So can knowledge, skills, time, independence, opportunity, stronger communities, useful technology or helping somebody else accomplish something they could not accomplish before.</p>
            <p>That is why empowerment is central to ProsperGenics.</p>
            <p class="punch">We want people to become <strong>more capable</strong>, not more dependent on us.</p>
          </div>
          <div class="rv">
            <div class="values-label" aria-hidden="true" style="margin-top:0">Forms of value</div>
            <div class="values" aria-hidden="true">
              <span>Money</span><span>Knowledge</span><span>Skills</span><span>Time</span><span>Independence</span><span>Opportunity</span><span>Stronger communities</span><span>Useful technology</span><span>Helping somebody do what they couldn’t before</span>
            </div>
            <div class="tool-note">
              <p>Technology — including AI and the systems we build around Jengo — can dramatically increase what a person or small team is able to accomplish. But <strong>technology is a tool, not the purpose.</strong></p>
              <p class="t">The purpose is what people become capable of doing with it.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- DIFFERENT PEOPLE, DIFFERENT PLACES -->
    <section class="block" id="together-across-places">
      <div class="wrap">
        <div class="split flip">
          <div class="rv">
            <div class="exchange" aria-hidden="true">
              <div class="pair"><span class="place">🇰🇪 Kenya</span><span class="sw">⇄</span><span class="place">🇳🇱 Netherlands</span></div>
              <p>Different experiences, skills, cultures and opportunities — one shared search for how value is created.</p>
            </div>
          </div>
          <div class="prose rv">
            <h2>Different people. Different places. Shared learning.</h2>
            <p>ProsperGenics grew from collaboration between people in <strong>Kenya</strong> and the <strong>Netherlands</strong>, bringing together very different experiences, skills, cultures and opportunities.</p>
            <p class="punch soft">That diversity matters.</p>
            <p>If we want to understand value creation, we cannot study it from only one perspective.</p>
            <p>What creates value for a software company in the Netherlands may look very different from what creates value for an entrepreneur in Kenya, a young person learning a new skill, a local tourism business, or a community trying to create new economic opportunities.</p>
            <p>By working together across those different contexts, we challenge our assumptions and learn from each other.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- BUILDING PROSPERITY TOGETHER -->
    <section class="block" id="together">
      <div class="wrap">
        <div class="prose rv">
          <h2>Building prosperity together</h2>
          <p>We don’t believe prosperity has to be a zero-sum game.</p>
          <p>The most interesting forms of value creation are often those where several people become better off at the same time: someone learns, someone earns, a customer gets something useful, a business grows, knowledge is created and that knowledge helps the next person.</p>
          <p class="punch">That is the kind of system we want to understand and build.</p>
        </div>
        <div class="finale rv">
          <p class="no">ProsperGenics is therefore not based on a finished formula.</p>
          <p class="big">It is a community <span class="accent">searching for one.</span></p>
          <p class="believe">And we believe the best way to understand how value is created is to keep creating it — <b>together.</b></p>
          <div class="actions">
            <a class="btn btn-primary" href="#practice">See where we practise it <span class="arrow">→</span></a>
            <a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to home</a>
          </div>
        </div>
      </div>
    </section>

    <!-- WHERE THE LEARNING HAPPENS: the real projects -->
    <section class="block practice" id="practice">
      <div class="wrap">
        <div class="practice-head rv">
          <h2>Where the learning happens</h2>
          <p>Our research runs inside real projects. These are some of the places where we practise it.</p>
        </div>
        <div class="cards">
          <article class="card rv"><div class="k" aria-hidden="true">🌍</div><h3>Karibu Mara</h3><p>Give local businesses access to customers and visitors.</p><div class="tag">Platform · live</div></article>
          <article class="card rv"><div class="k" aria-hidden="true">🧰</div><h3>AI Tools</h3><p>Find and understand the systems that expand what you can do.</p><div class="tag">Directory</div></article>
          <article class="card rv"><div class="k" aria-hidden="true">🎓</div><h3>Training</h3><p>Turn knowledge into useful, certified skills you can prove.</p><div class="tag">Learn · certify</div></article>
          <article class="card rv"><div class="k" aria-hidden="true">👥</div><h3>People</h3><p>Connect capability with demand — experts available for work.</p><div class="tag">Directory</div></article>
          <article class="card rv"><div class="k" aria-hidden="true">⚡</div><h3>Jengo</h3><p>Multiply what a person or team can actually build.</p><div class="tag">Technology</div></article>
        </div>
      </div>
    </section>
  </main>

  <!-- site-chrome:footer (inc/site-chrome.php) -->
  <?php echo prospergenics_2026_site_footer(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

  <script>
    (function () {
      var els = document.querySelectorAll('.rv');
      if (!('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); return; }
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          var t = en.target;
          t.classList.add('in'); io.unobserve(t);
          setTimeout(function () { t.style.transitionDelay = ''; }, 1200); // keep hover transitions instant afterwards
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      els.forEach(function (e, i) { e.style.transitionDelay = ((i % 5) * 60) + 'ms'; io.observe(e); });
    })();
  </script>

  <!-- site-chrome:js -->
  <script>
// Site header (wp/inc/site-chrome/site-chrome.js, shared by every page type): the mobile menu and
// the account menu (toggle; close on Esc, link click, outside click and resize), and on the
// homepage (.site-header--autohide) hidden until the visitor scrolls past the hero and back up.
(function () {
  var header = document.querySelector('.site-header');
  if (!header) return;
  function menu(btn, panel, labels) {
    if (!btn || !panel) return null;
    function setOpen(open, returnFocus) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (labels) btn.setAttribute('aria-label', open ? labels[1] : labels[0]);
      panel.hidden = !open;
      if (open) { header.classList.remove('is-hidden'); var first = panel.querySelector('a'); if (first) first.focus(); }
      else if (returnFocus) { btn.focus(); }
    }
    btn.addEventListener('click', function () { setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
    panel.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false, true); });
    document.addEventListener('click', function (e) { if (!panel.hidden && !btn.contains(e.target) && !panel.contains(e.target)) setOpen(false); });
    return setOpen;
  }
  var mobile = menu(header.querySelector('.nav-toggle'), document.getElementById('mobile-menu'), ['Open menu', 'Close menu']);
  var account = menu(header.querySelector('.nav-account-toggle'), document.getElementById('account-menu'), null);
  var wide = window.matchMedia('(min-width: 1181px)');
  function onWidth() {
    if (wide.matches && mobile) mobile(false);
    if (!wide.matches && account) account(false);
  }
  if (wide.addEventListener) wide.addEventListener('change', onWidth); else if (wide.addListener) wide.addListener(onWidth);

  if (!header.classList.contains('site-header--autohide')) return;
  // Homepage: the hero already shows the menu links, so the header stays hidden until the visitor
  // has scrolled past the hero and scrolls back up (Martien, 26 Oct). Scrolling down or back into
  // the hero hides it again. An open menu or keyboard focus inside it always keeps it visible.
  var hero = document.querySelector('main > section.hero');
  var lastY = window.scrollY || 0, ticking = false;
  function keyboardFocusInside() {
    try { return !!header.querySelector(':focus-visible'); } catch (e) { return false; }
  }
  function pastHero(y) {
    return hero ? y > hero.getBoundingClientRect().bottom + y - header.offsetHeight : y > header.offsetHeight;
  }
  function update() {
    ticking = false;
    var y = window.scrollY || 0;
    if (header.querySelector('[aria-expanded="true"]') || keyboardFocusInside()) {
      header.classList.remove('is-hidden');
    } else if (!pastHero(y) || y > lastY + 2) {
      header.classList.add('is-hidden');
    } else if (y < lastY - 2) {
      header.classList.remove('is-hidden');
    }
    lastY = y;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  header.addEventListener('focusin', function () { header.classList.remove('is-hidden'); });
})();

    // scroll-fade:start (kept identical in index.html and site_chrome.py; tests/test-2026-scroll-fade.php)
    // Scroll top-fade: content fades out only in a thin band at the top of the viewport, i.e. the part
    // the reader has already scrolled past. Mask only: no greyscale or scaling of whole sections, never
    // on forms, the chat or whatever has focus, and off under prefers-reduced-motion.
    (function () {
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
      var items = Array.prototype.slice.call(document.querySelectorAll(
        'main > section:not(.hero) > *, main > section.hero > .hero-bottom, main > section.hero > .hero-top > .copy > .copy-inner'));
      if (!items.length) return;
      var skip = 'form, .launcher, .co-embed-root, [data-no-fade]';
      var ticking = false;
      function clear(el) { el.style.maskImage = ''; el.style.webkitMaskImage = ''; }
      function update() {
        ticking = false;
        var band = Math.min(window.innerHeight * 0.12, 96);
        var off = reduce.matches || window.scrollY < 40;
        var focused = document.activeElement;
        items.forEach(function (el) {
          var t = el.getBoundingClientRect().top;
          if (off || t >= 0 || el.matches(skip) || el.querySelector(skip) ||
              (focused && focused !== document.body && el.contains(focused))) { clear(el); return; }
          var g = 'linear-gradient(to bottom,transparent ' + (-t) + 'px,#000 ' + (band - t) + 'px)';
          el.style.maskImage = g; el.style.webkitMaskImage = g;
        });
      }
      function request() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
      window.addEventListener('scroll', request, { passive: true });
      window.addEventListener('resize', request, { passive: true });
      document.addEventListener('focusin', request);
      if (reduce.addEventListener) reduce.addEventListener('change', request);
      update();
    })();
    // scroll-fade:end
  </script>
  <!-- /site-chrome:js -->
<?php wp_footer(); ?>
</body>
</html>
