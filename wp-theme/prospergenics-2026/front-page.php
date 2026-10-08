<?php /* Front page - generated from design/new-hero-2026-10/index.html by build-wp-theme.py; edit the source, not this file. */ ?>
<!DOCTYPE html>
<html lang="en" <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>ProsperGenics — Create value</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<style>
  :root {
    /* ProsperGenics palette — deep forest → vivid growth green */
    --ink:        #0b2016;   /* near-black forest, for the big type */
    --green-900:  #0f3d28;
    --green-700:  #1a7a46;
    --green-600:  #22a65a;
    --green-500:  #34c759;   /* the "growth" accent */
    --green-300:  #86e0a6;
    --green-100:  #d9f5e3;
    --paper:      #f4faf6;   /* off-white with a green whisper */
    --paper-2:    #eaf5ee;
    --line:       rgba(15,61,40,.12);
    --muted:      #4a6555;
    --white:      #ffffff;
    --shadow:     0 24px 60px -20px rgba(11,32,22,.35);
    --radius:     28px;
    --ease:       cubic-bezier(.22,.61,.36,1);
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

  /* ─────────────────────────────  TOP BAR  ───────────────────────────── */
  .nav {
    position: absolute; inset: 0 0 auto 0; z-index: 20;
    display: flex; align-items: center; justify-content: space-between;
    padding: 28px clamp(24px, 5vw, 72px);
  }
  .brand { display: flex; align-items: center; }
  .brand img { height: 42px; width: auto; display: block; }
  .nav-links { display: flex; align-items: center; gap: 34px; }
  .nav-links a { font-size: 15px; font-weight: 500; color: var(--green-900); text-decoration: none; opacity: .8; transition: opacity .2s; }
  .nav-links a:hover { opacity: 1; }
  .nav-cta { font-family:'Sora',sans-serif; font-weight:600; font-size:14px; padding:10px 18px; border-radius:999px; border:1.5px solid var(--green-700); color:var(--green-900); background:transparent; cursor:pointer; transition:all .2s; }
  .nav-cta:hover { background: var(--green-700); color: #fff; }
  @media (max-width: 880px){ .nav-links{ display:none; } }

  /* ─────────────────────────────  HERO  ───────────────────────────── */
  .hero { position: relative; max-width: 1500px; margin: 0 auto; padding: 0 clamp(24px, 5vw, 72px); }

  /* TOP half: message (left, on paper) + team photo fading in from ~30% */
  .hero-top { position: relative; min-height: 520px; display: flex; align-items: flex-start; padding-top: 74px; }
  .hero-top-photo {
    position: absolute; top: 34px; bottom: 0; left: 50%; width: 100vw; transform: translateX(-50%);
    z-index: 0; pointer-events: none;
    background: url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/hero-top.png') center 12% / cover no-repeat;
    opacity: .97;
    /* full screen width; soft fade at top + bottom into the paper */
    -webkit-mask-image: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.05) 5%, rgba(0, 0, 0, 0.2) 10%, rgba(0, 0, 0, 0.5) 15%, #000 20%, #000 80%, rgba(0, 0, 0, 0.8) 85%, rgba(0, 0, 0, 0.4) 90%, rgba(0, 0, 0, 0.1) 95%, transparent 100%);
            mask-image: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.05) 5%, rgba(0, 0, 0, 0.2) 10%, rgba(0, 0, 0, 0.5) 15%, #000 20%, #000 80%, rgba(0, 0, 0, 0.8) 85%, rgba(0, 0, 0, 0.4) 90%, rgba(0, 0, 0, 0.1) 95%, transparent 100%);
  }
  /* BOTTOM half: slider on a plain (paper) background */
  .hero-bottom { position: relative; z-index: 5; padding: 30px 0 72px; display: flex; align-items: center; gap: clamp(40px, 6vw, 80px); }

  @media (max-width: 980px){
    .hero { padding: 0; }
    /* column-reverse + order values: full-bleed photo on top, headline below it */
    .hero-top { display: flex; flex-direction: column-reverse; min-height: 0; padding-top: 0; }
    .copy { order: 1; position: relative; z-index: 2; max-width: 100%; padding: 20px; }
    .copy::before { display: none; }            /* text is on clean bg on mobile; no halo needed */
    h1 { font-size: clamp(46px, 15vw, 72px); }
    .sub { font-size: clamp(17px, 4.6vw, 21px); max-width: 100%; margin-top: 18px; }
    .hero-top-photo {
      order: 2; position: relative; left: auto; right: auto; top: 0; margin: 0; transform: none;
      width: 100vw; height: 232px; border-radius: 0; opacity: 1;
      background-position: center 16%;
      -webkit-mask-image: linear-gradient(to bottom, #000 84%, transparent 100%);
              mask-image: linear-gradient(to bottom, #000 84%, transparent 100%);
    }
    .hero-bottom { padding: 20px; }
  }

  /* ---- LEFT: the message ---- */
  .copy { position: relative; z-index: 5; max-width: 620px; }
  /* paper halo behind the text: opaque-ish in the middle, fully transparent toward the edges,
     so the full-width team photo stays visible around it but the copy stays readable */
  .copy::before {
    content: ""; position: absolute; z-index: -1; inset: -48px -110px -280px -320px; pointer-events: none;
    background: radial-gradient(ellipse 56% 86% at 58% 48%,
      rgba(244,250,246,.97) 0%, rgba(244,250,246,.80) 34%, rgba(244,250,246,.75) 50%, rgba(244,250,246,0) 70%);
  }
  .eyebrow {
    display: inline-flex; align-items: center; gap: 10px;
    font-family:'Sora',sans-serif; font-weight:600; font-size:13px; letter-spacing:.08em; text-transform:uppercase;
    color: var(--green-700);
    padding: 8px 14px 8px 10px; border-radius: 999px;
    background: var(--green-100); border: 1px solid rgba(34,166,90,.2);
    margin-bottom: 28px;
  }
  .eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green-500); box-shadow: 0 0 0 4px rgba(52,199,89,.22); }

  h1 {
    font-family: 'Sora', sans-serif;
    font-weight: 800;
    font-size: clamp(54px, 8vw, 104px);
    line-height: .92;
    letter-spacing: -.035em;
    color: var(--ink);
  }
  h1 .accent {
    display: block;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--green-500) 55%, var(--green-300) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
  }
  .sub {
    margin-top: 26px;
    font-family: 'Sora', sans-serif;
    font-weight: 500;
    font-size: clamp(19px, 2.3vw, 25px);
    line-height: 1.35;
    color: var(--green-900);
    max-width: 30ch;
  }
  .lede {
    margin-top: 20px;
    font-size: 16.5px; line-height: 1.6; color: var(--muted);
    max-width: 44ch;
  }

  .actions { margin-top: 38px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
  .btn {
    display: inline-flex; align-items: center; gap: 10px;
    font-family: 'Sora', sans-serif; font-weight: 600; font-size: 16px;
    padding: 16px 26px; border-radius: 999px; cursor: pointer; border: none;
    text-decoration: none; transition: transform .2s var(--ease), box-shadow .2s var(--ease);
  }
  .btn .arrow { transition: transform .25s var(--ease); }
  .btn:hover .arrow { transform: translateX(5px); }
  .btn-primary {
    color: #fff;
    background: linear-gradient(100deg, var(--green-700), var(--green-600));
    box-shadow: 0 14px 30px -12px var(--green-600);
  }
  .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 20px 38px -12px var(--green-600); }
  .btn-ghost { color: var(--green-900); background: transparent; padding-left: 8px; }
  .btn-ghost:hover { color: var(--green-700); }

  /* trust strip */
  .trust { margin-top: 48px; display: flex; align-items: center; gap: 22px; flex-wrap: wrap; color: var(--muted); font-size: 13.5px; }
  .trust .sep { width: 4px; height: 4px; border-radius: 50%; background: var(--green-300); }
  .trust b { color: var(--green-900); font-weight: 600; }

  /* ---- RIGHT: the graphic ---- */
  .stage { position: relative; height: clamp(440px, 60vw, 640px); }
  @media (max-width: 980px){ .stage { height: clamp(380px, 85vw, 520px); margin-top: 8px; } }

  /* the big organic growth-blob holding the photo */
  .orb {
    position: absolute; inset: 0; margin: auto;
    width: 100%; height: 100%;
  }
  .blob {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    width: 108%; height: 108%;
    background: radial-gradient(130% 120% at 30% 20%, var(--green-300) 0%, var(--green-500) 42%, var(--green-700) 100%);
    border-radius: 42% 58% 55% 45% / 52% 44% 56% 48%;
    filter: saturate(1.05);
    box-shadow: var(--shadow);
    animation: morph 14s var(--ease) infinite;
  }
  @keyframes morph {
    0%,100% { border-radius: 42% 58% 55% 45% / 52% 44% 56% 48%; }
    33%     { border-radius: 58% 42% 48% 52% / 44% 56% 46% 54%; }
    66%     { border-radius: 48% 52% 60% 40% / 58% 42% 54% 46%; }
  }
  /* concentric growth-ring echo */
  .ring { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); border-radius: 50%; border: 1.5px dashed rgba(26,122,70,.28); }
  .ring.r1 { width: 112%; height: 112%; animation: spin 46s linear infinite; }
  .ring.r2 { width: 128%; height: 128%; border-style: solid; border-color: rgba(52,199,89,.12); animation: spin 70s linear infinite reverse; }
  @keyframes spin { to { transform: translate(-50%,-50%) rotate(360deg); } }

  /* the team photo, cut-out, sitting inside the blob */
  .photo-wrap {
    position: absolute; inset: 6% 4% 0 4%;
    display: flex; align-items: flex-end; justify-content: center;
    border-radius: 40% 56% 52% 46% / 50% 44% 56% 50%;
    overflow: hidden;
    -webkit-mask-image: radial-gradient(120% 115% at 50% 40%, #000 60%, transparent 100%);
            mask-image: radial-gradient(120% 115% at 50% 40%, #000 60%, transparent 100%);
  }
  .photo-wrap img { width: 100%; height: 100%; object-fit: cover; object-position: 46% 34%; transform: scale(1.38); transform-origin: 46% 38%; }
  /* placeholder until the real cut-out team photo is dropped in */
  .photo-ph {
    position: absolute; inset: 14% 12% 8% 12%;
    border-radius: 24px;
    background: linear-gradient(160deg, rgba(255,255,255,.22), rgba(255,255,255,.05));
    border: 1.5px dashed rgba(255,255,255,.55);
    display: grid; place-items: center; text-align: center;
    color: rgba(255,255,255,.92); font-family:'Sora',sans-serif; font-weight:600; font-size:15px; line-height:1.5;
    backdrop-filter: blur(2px);
  }
  .photo-ph span { display:block; font-weight:400; font-size:12.5px; opacity:.85; margin-top:6px; }

  /* geometry: square · circle · triangle (brand primitives) floating */
  .geo { position: absolute; z-index: 6; }
  .geo.sq {
    width: 86px; height: 86px; right: -2%; top: 8%;
    background: var(--white); border-radius: 18px;
    box-shadow: var(--shadow); display: grid; place-items: center;
    animation: float 7s var(--ease) infinite;
  }
  .geo.sq::after { content:""; width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg,var(--green-600),var(--green-500)); }
  .geo.ci {
    width: 70px; height: 70px; left: -3%; top: 26%;
    background: var(--green-900); border-radius: 50%;
    box-shadow: var(--shadow); display: grid; place-items: center;
    animation: float 9s var(--ease) infinite reverse;
  }
  .geo.ci::after { content:""; width: 26px; height: 26px; border-radius: 50%; border: 5px solid var(--green-300); }
  .geo.tri {
    left: 4%; bottom: 6%;
    width: 0; height: 0; border-left: 34px solid transparent; border-right: 34px solid transparent;
    border-bottom: 58px solid var(--white);
    filter: drop-shadow(0 16px 24px rgba(11,32,22,.25));
    animation: float 8s var(--ease) 1s infinite;
  }
  @keyframes float { 0%,100%{ transform: translateY(0) rotate(0); } 50%{ transform: translateY(-16px) rotate(4deg); } }

  /* floating value-proof chips — concrete evidence, not slogans */
  .chip {
    position: absolute; z-index: 7; text-decoration: none;
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.94); backdrop-filter: blur(10px);
    border: 1px solid var(--line); border-radius: 16px;
    padding: 11px 15px; box-shadow: var(--shadow);
    font-size: 13.5px; font-weight: 700; color: var(--green-900);
    animation: float 10s var(--ease) infinite;
    transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s;
  }
  .chip:hover { transform: translateY(-4px) scale(1.03); border-color: var(--green-300); box-shadow: 0 26px 50px -18px var(--green-700); }
  .chip .ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; font-size: 16px; background: var(--green-100); flex: none; }
  .chip small { display:block; font-weight:500; font-size:11px; color: var(--muted); margin-top: 1px; }
  .chip.c1 { right: -7%; top: 12%;  animation-delay: .2s; }
  .chip.c2 { left: -10%; top: 34%;  animation-delay: 1.1s; }
  .chip.c3 { right: -9%; bottom: 24%; animation-delay: 1.7s; }
  .chip.c4 { left: -7%; bottom: 6%;  animation-delay: 2.3s; }
  @media (max-width: 1200px){ .chip.c1,.chip.c3{ right:-2%; } .chip.c2,.chip.c4{ left:-2%; } }
  @media (max-width: 980px){ .chip, .geo.sq { display: none; } }

  /* the four value links as a row under the CTAs */
  .quicklinks { display: flex; margin-top: 30px; flex-wrap: wrap; gap: 10px; }
  .quicklinks a {
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    font-family:'Sora',sans-serif; font-weight:600; font-size: 13.5px; color: var(--green-900);
    background: rgba(255,255,255,.9); border: 1px solid var(--line); border-radius: 999px; padding: 10px 16px;
    transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s;
  }
  .quicklinks a:hover { transform: translateY(-2px); border-color: var(--green-300); box-shadow: 0 14px 28px -14px var(--green-700); }
  .quicklinks a .ic { font-size: 15px; }

  /* faint full-hero team photo backdrop, radially faded */
  .hero-photobg {
    position: absolute; top: 0; bottom: 0; left: 50%; width: 100vw; transform: translateX(-50%);
    z-index: 0; pointer-events: none;
    background: url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/hero-bg.jpg') center 28% / cover no-repeat;
    opacity: .62;
  }
  /* a wash to keep text crisp over the photo (stronger on the left, behind the copy) */
  .hero-wash {
    position: absolute; top: 0; bottom: 0; left: 50%; width: 100vw; transform: translateX(-50%);
    z-index: 0; pointer-events: none;
    background: linear-gradient(100deg, var(--paper) 0%, rgba(244,250,246,.94) 26%, rgba(244,250,246,.6) 50%, rgba(244,250,246,.22) 78%, rgba(244,250,246,.08) 100%);
  }

  /* soft ambient background shapes */
  .bg-arc { position: absolute; z-index: 0; border-radius: 50%; filter: blur(2px); opacity: .5; pointer-events: none; }
  .bg-arc.a1 { width: 620px; height: 620px; right: -220px; top: -180px; background: radial-gradient(circle, var(--green-100), transparent 65%); }
  .bg-arc.a2 { width: 480px; height: 480px; left: -200px; bottom: -160px; background: radial-gradient(circle, var(--paper-2), transparent 70%); opacity:.9; }

  /* ───────────────  SECOND MESSAGE: "see how value gets created"  ─────────────── */
  .proof {
    position: relative; z-index: 5;
    max-width: 1500px; margin: 0 auto;
    padding: 20px clamp(24px, 5vw, 72px) clamp(16px, 2.4vw, 32px);
  }
  /* intro: "alchemy of prosperity" text left, picture right */
  .proof-intro {
    display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
    gap: clamp(32px, 6vw, 80px); align-items: center;
  }
  .proof-copy h2.alchemy {
    font-family:'Sora',sans-serif; font-weight:800;
    font-size: clamp(38px, 4.6vw, 66px); line-height: 1.0; letter-spacing: -.03em; color: var(--ink);
    max-width: 13ch;
  }
  .proof-copy h2.alchemy .accent {
    display: block;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--green-500) 55%, var(--green-300) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    padding-bottom: .06em;
  }
  .proof-copy p { margin-top: 22px; font-size: clamp(16.5px, 1.5vw, 19px); line-height: 1.65; color: var(--muted); max-width: 46ch; }
  /* layout box only; the reveal animation owns this element's transform */
  .proof-visual { position: relative; aspect-ratio: 5 / 4; }
  /* .pv-scale carries the whole skewed panel (same parallelogram cut as the
     showcase slider, a touch larger) AND the scroll-driven scale, so the cut
     scales along with the picture */
  .proof-visual .pv-scale {
    position: relative; display: block; width: 100%; height: 100%; transform-origin: center; transform: scale(0.8);
    clip-path: polygon(34px 0, 100% 0, calc(100% - 34px) 100%, 0 100%);
    background: var(--green-900); overflow: hidden; text-decoration: none;
  }
  .proof-visual img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s var(--ease); }
  .pv-link:hover img { transform: scale(1.06); }
  /* half-transparent caption band, same slanted treatment as the showcase slider captions */
  .pv-caption {
    position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; height: 32%;
    display: flex; align-items: flex-end; padding: 0 30px 20px 28px;
    background: linear-gradient(183deg, rgba(8,26,17,0) 0%, rgba(8,26,17,.55) 35%, rgba(8,26,17,.92) 80%);
    clip-path: polygon(0 18%, 100% 34%, 100% 100%, 0 100%);
    font-family:'Sora',sans-serif; font-weight: 700; font-size: clamp(17px, 1.6vw, 22px);
    color: #fff; letter-spacing: -.015em;
  }
  .pv-caption .arrow { margin-left: 10px; transition: transform .25s var(--ease); }
  .pv-link:hover .pv-caption .arrow { transform: translateX(6px); }
  @media (max-width: 980px){
    .proof-intro { grid-template-columns: 1fr; gap: 26px; }
    .proof-visual { aspect-ratio: 16 / 10; }
    /* scroll effects are desktop-only for now */
    .proof-visual .pv-scale { clip-path: polygon(20px 0, 100% 0, calc(100% - 20px) 100%, 0 100%); transform: none; }
  }
  .hero-bottom-cta { flex: 1; }
  .hbc-head {
    font-family: 'Sora', sans-serif; font-weight: 800;
    font-size: clamp(36px, 4vw, 56px); line-height: 1.0; letter-spacing: -.03em;
    color: var(--ink); margin-bottom: 16px;
  }
  .hbc-head .accent {
    display: block;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--green-500) 55%, var(--green-300) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
  }
  .hbc-body { font-size: 16px; line-height: 1.6; color: var(--muted); }
  @media (max-width: 980px) { .hero-bottom-cta { display: none; } }

  /* ───────────────  INTERACTIVE 4-SLIDE SHOWCASE  ─────────────── */
  .showcase { display: flex; align-items: stretch; gap: 20px; flex: none; width: 598px; height: 332px; }
  /* left menu: transparent, sits on the hero bg */
  .showcase-menu { width: 174px; flex: none; display: flex; flex-direction: column; justify-content: center; gap: 7px; }
  .sc-tab {
    display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; border: none; cursor: pointer;
    background: transparent; color: var(--ink); font-family:'Sora',sans-serif; font-weight:700; font-size:15.5px;
    padding: 12px 15px; border-radius: 12px; transition: all .2s var(--ease);
  }
  .sc-tab svg { width: 21px; height: 21px; fill: var(--green-700); flex: none; transition: fill .2s; }
  .sc-tab .arr { margin-left: auto; font-size: 18px; opacity: 0; transform: translateX(-6px); transition: all .2s var(--ease); }
  .sc-tab:hover { color: var(--green-700); }
  .sc-tab.active { background: var(--green-600); color: #fff; box-shadow: 0 16px 30px -14px var(--green-600); }
  .sc-tab.active svg { fill: #fff; }
  .sc-tab.active .arr { opacity: 1; transform: none; }
  /* right panel: parallelogram with subtly slanted left + right edges */
  .showcase-panel {
    position: relative; flex: 1; display: block; text-decoration: none; overflow: hidden;
    background-color: var(--green-900); background-size: cover; background-position: center;
    clip-path: polygon(26px 0, 100% 0, calc(100% - 26px) 100%, 0 100%);
    transition: background-image .35s var(--ease);
  }
  /* dark caption band with a slanted top edge (higher on the left) */
  .sc-caption {
    position: absolute; left: 0; right: 0; bottom: 0; height: 37%; z-index: 2;
    background: linear-gradient(183deg, rgba(8,26,17,0) 0%, rgba(8,26,17,.58) 32%, rgba(8,26,17,.95) 72%);
    clip-path: polygon(0 10%, 100% 24%, 100% 100%, 0 100%);
    display: flex; flex-direction: column; justify-content: flex-end; padding: 0 56px 22px 28px;
  }
  .sc-caption h3 { font-family:'Sora',sans-serif; font-weight:700; font-size: 20px; color:#fff; line-height:1.1; letter-spacing:-.015em; margin-bottom: 7px; }
  .sc-caption p { font-size: 12px; line-height:1.4; color: rgba(255,255,255,.85); max-width: 30ch; }
  .sc-go { position:absolute; right: 22px; bottom: 22px; z-index:3; margin-right: 20px; width: 40px; height:40px; border-radius:50%; border:1.5px solid rgba(255,255,255,.7); color:#fff; display:grid; place-items:center; font-size:17px; transition: all .2s var(--ease); }
  .showcase-panel:hover .sc-go { background:#fff; color: var(--green-900); border-color:#fff; }
  @media (max-width: 620px){
    .showcase { flex-direction: column; height: auto; max-width: 100%; gap: 14px; }
    .showcase-menu { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .sc-tab { justify-content: flex-start; white-space: nowrap; font-size: 14px; padding: 12px 14px; }
    .sc-tab .arr { display: none; }
    .showcase-panel { flex: none; width: 100%; height: 300px; clip-path: polygon(18px 0, 100% 0, calc(100% - 18px) 100%, 0 100%); }
    .sc-caption { height: 44%; padding: 0 46px 18px 22px; }
    .sc-caption h3 { font-size: 20px; }
  }

  /* ───────────────  VALUE TEST · "How valuable are you?"  ─────────────── */
  /* bottom padding reserves room for the 1.2 scroll-zoom (origin top, box grows downward)
     so the banner heading below never collides with the scaled test panel */
  .vtest { max-width: 1500px; margin: 0 auto; padding: clamp(28px, 3.6vw, 52px) clamp(24px, 5vw, 72px) clamp(40px, 8vw, 130px); }
  .vtest .projects-head { text-align: center; }
  .vtest .projects-head p { margin-left: auto; margin-right: auto; }
  .vtest-box {
    margin-top: 34px; transform-origin: 50% 0;
    background: #fff; border: 1px solid var(--line); transform: skewX(-3deg);
    box-shadow: 0 30px 70px -50px rgba(11,32,22,.45);
  }
  .vtest-in { display: block; transform: skewX(3deg); padding: clamp(28px, 4vw, 56px) clamp(24px, 5vw, 72px); }
  .vt-progress { display: flex; gap: 8px; margin-bottom: 22px; }
  .vt-dot { width: 34px; height: 5px; background: var(--green-100); transition: background .3s; }
  .vt-dot.done { background: var(--green-500); }
  .vt-question { font-family:'Sora',sans-serif; font-weight: 800; font-size: clamp(22px, 2.6vw, 36px); line-height: 1.15; letter-spacing: -.02em; color: var(--ink); max-width: 28ch; }
  .vt-email { min-height: 0; }
  /* the field BOX is skewed like the panel, but the typed text stays straight: the wrapper
     carries the skew + styling, the textarea inside is counter-skewed and invisible */
  .vt-field {
    margin-top: 22px; transform: skewX(-3deg);
    background: var(--paper); border: 1.5px solid var(--line); transition: border-color .2s;
  }
  .vt-field:focus-within { border-color: var(--green-600); }
  .vt-answer {
    display: block; transform: skewX(3deg);
    width: calc(100% - 12px); margin: 0 auto; min-height: 110px; resize: vertical;
    font-family: 'Inter', system-ui, sans-serif; font-size: 16.5px; line-height: 1.6; color: var(--ink);
    padding: 16px 18px; background: transparent; border: none; border-radius: 0; outline: none;
  }
  .vt-nav { margin-top: 22px; display: flex; align-items: center; gap: 14px; }
  .vt-back { background: none; border: none; cursor: pointer; font-family:'Sora',sans-serif; font-weight: 600; font-size: 14px; color: var(--muted); padding: 10px 0; }
  .vt-back:hover { color: var(--green-700); }
  .vt-hint { margin-left: auto; font-size: 13px; color: var(--muted); }
  .vt-busy { display: flex; align-items: center; gap: 16px; padding: 26px 0 10px; font-family:'Sora',sans-serif; font-weight: 600; font-size: 17px; color: var(--green-900); }
  .vt-busy .l-blob { position: relative; inset: auto; width: 46px; height: 46px; flex: none; animation: morph 4s var(--ease) infinite; }
  .vt-profile {
    font-family:'Sora',sans-serif; font-weight: 800; font-size: clamp(34px, 4vw, 58px); line-height: 1.02; letter-spacing: -.03em;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--green-500) 55%, var(--green-300) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    padding-bottom: .08em;
  }
  .vt-result-text { margin-top: 14px; font-size: clamp(16.5px, 1.5vw, 19px); line-height: 1.65; color: var(--ink); max-width: 62ch; }
  .vt-cta { margin-top: 26px; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
  .vt-cta .btn-primary {
    display: inline-flex; align-items: center; gap: 10px; text-decoration: none;
    font-family:'Sora',sans-serif; font-weight: 600; font-size: 16px; padding: 16px 26px; border-radius: 999px;
    color: #fff; background: linear-gradient(100deg, var(--green-700), var(--green-600));
    box-shadow: 0 14px 30px -12px var(--green-600); transition: transform .2s var(--ease);
  }
  .vt-cta .btn-primary:hover { transform: translateY(-2px); }
  .vt-cta-note { font-size: 14.5px; color: var(--muted); max-width: 40ch; }
  .vt-error { margin-top: 16px; font-size: 14px; color: #a33; }
  @media (max-width: 640px){
    .vtest-box, .vtest-in, .vt-field, .vt-answer { transform: none; }
    .vtest-in { padding: 24px 18px; }
    .vt-answer { width: 100%; }
  }

  /* ───────────────  COMMUNITY VOICES · flowing review banner  ─────────────── */
  .voices { position: relative; padding: clamp(10px, 1.6vw, 22px) 0 clamp(20px, 2.6vw, 34px); overflow: hidden; }
  .voices-head { max-width: 1500px; margin: 0 auto; padding: 0 clamp(24px, 5vw, 72px); }
  /* same typography as the alchemy heading and the "Not sure where to start?" block */
  .voices-head h2, .projects-head h2 {
    font-family:'Sora',sans-serif; font-weight:800; font-size: clamp(38px, 4.6vw, 66px);
    line-height: 1.0; letter-spacing: -.03em; color: var(--ink);
  }
  .voices-head h2 .accent, .projects-head h2 .accent {
    display: block;
    background: linear-gradient(100deg, var(--green-700) 0%, var(--green-500) 55%, var(--green-300) 100%);
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    padding-bottom: .06em;
  }
  .voices-head p, .projects-head p { margin-top: 22px; font-size: clamp(16.5px, 1.5vw, 19px); line-height: 1.65; color: var(--muted); max-width: 46ch; }
  .flow {
    /* vertical padding gives the lens (taller than the cards) room to stay fully visible,
       also while the whole strip scroll-zooms to 1.2 */
    position: relative; margin-top: 28px; padding: 56px 0 64px;
    -webkit-mask-image: linear-gradient(to right, transparent, #000 7%, #000 93%, transparent);
            mask-image: linear-gradient(to right, transparent, #000 7%, #000 93%, transparent);
  }
  /* the stream runs left-to-right and loops seamlessly: the card set is rendered twice and the
     track slides exactly one set-width (-50% -> 0), so the last frame equals the first */
  .flow-track { display: flex; width: max-content; animation: flow-ltr 127s linear infinite; filter: saturate(.4); }
  .flow:hover .flow-track { animation-play-state: paused; }
  @keyframes flow-ltr { from { transform: translateX(-50%); } to { transform: translateX(0); } }
  .flow-set { display: flex; }
  /* portrait left, text right; the empty 1fr edge rows centre the text block vertically */
  .flow-card {
    position: relative; flex: none; width: 480px; margin-right: 56px;
    display: grid; grid-template-columns: 216px minmax(0, 1fr); grid-template-rows: 1fr auto auto auto 1fr;
    column-gap: 20px;
  }
  /* gentle bob: whole sine periods so each loop ends at its start; 7/9/11 share no factor with
     each other or with the 127s stream and 19s lens morph, so the combination never settles.
     Cycle of 3 must divide the cards-per-set count, or the duplicate set breaks the seam. */
  @keyframes bob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
  .flow-set .flow-card:nth-child(3n+1) { animation: bob 7s ease-in-out infinite; }
  .flow-set .flow-card:nth-child(3n+2) { animation: bob 9s ease-in-out infinite; }
  .flow-set .flow-card:nth-child(3n)   { animation: bob 11s ease-in-out infinite; }
  /* the per-member triangles are retired in favour of two banner-wide triforces */
  .fc-ring { display: none; }
  /* two dashed-outline triforces drift across the WHOLE banner and wrap around: they leave
     one side and re-enter on the other (both keyframe ends are off-screen, so the loop jump
     is invisible; the banner's edge mask fades them out before the border anyway). Nesting
     gives each motion its own axis: outer = x-drift, span = y-weave + size pulse, i = spin.
     All durations are prime and pairwise different, so the pattern never visibly repeats. */
  .flow-tri { position: absolute; top: 0; bottom: 0; left: 0; width: 52px; pointer-events: none; animation: tri-x 41s linear infinite; }
  .flow-tri span {
    position: absolute; left: 0; width: 52px; height: 48px;
    animation: tri-y 13s ease-in-out infinite alternate, tri-puls 23s ease-in-out infinite;
  }
  .flow-tri i {
    display: block; width: 100%; height: 100%;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 44 40' fill='none' stroke='%231a7a46' stroke-opacity='.55' stroke-width='1.5' stroke-dasharray='3 3'%3E%3Cpath d='M22 1 L43 39 L1 39 Z'/%3E%3Cpath d='M11.5 20 L32.5 20 L22 39 Z'/%3E%3C/svg%3E") center / contain no-repeat;
    animation: tri-spin 29s linear infinite;
  }
  .flow-tri2 { width: 40px; animation-duration: 53s; animation-direction: reverse; }
  .flow-tri2 span { width: 40px; height: 37px; animation-duration: 17s, 37s; animation-direction: alternate-reverse, reverse; }
  .flow-tri2 i { animation-duration: 31s; animation-direction: reverse; }
  @keyframes tri-x { from { left: -70px; } to { left: 100%; } }
  @keyframes tri-y { from { top: 4%; } to { top: 82%; } }
  @keyframes tri-puls { 0%, 100% { transform: scale(.65); } 50% { transform: scale(1.35); } }
  @keyframes tri-spin { to { transform: rotate(360deg); } }
  /* portrait: straight rectangle with the same slanted cut as the showcase panels */
  .fc-portrait {
    grid-column: 1; grid-row: 1 / -1; position: relative; display: block; width: 216px; height: 220px;
    overflow: hidden; clip-path: polygon(19px 0, 100% 0, calc(100% - 19px) 100%, 0 100%);
    background: linear-gradient(165deg, var(--green-100), var(--paper-2) 70%);
  }
  .fc-portrait img { width: 100%; height: 100%; object-fit: cover; }
  /* no photo yet: initial on a brand gradient, swap for a real portrait later */
  .fc-portrait.fc-initial {
    display: grid; place-items: center; font-family:'Sora',sans-serif; font-weight: 800;
    font-size: 58px; color: #fff; background: linear-gradient(150deg, var(--green-600), var(--green-300));
  }
  .fc-name { grid-column: 2; grid-row: 2; font-family:'Sora',sans-serif; font-weight: 800; font-size: 17.5px; color: var(--green-900); line-height: 1.2; }
  .fc-role { grid-column: 2; grid-row: 3; font-size: 13px; color: var(--muted); margin: 4px 0 10px; }
  .fc-quote { grid-column: 2; grid-row: 4; font-size: 14px; line-height: 1.6; color: var(--ink); }
  /* the alchemy lens: same slanted-rectangle form as the portraits (skew keeps the dashed
     border, clip-path would not), a bit bigger than a portrait and fully visible mid-stream.
     The track is slightly desaturated and the lens re-saturates whatever flows through it, so
     every voice "comes to life" as it passes the centre - value creation, literally. */
  .flow-lens {
    position: absolute; left: 50%; top: 50%;
    width: min(280px, 72vw); height: 300px;
    transform: translate(-50%,-50%) skewX(-5deg);
    pointer-events: none; z-index: 3;
    border: 2px dashed rgba(26,122,70,.38);
    -webkit-backdrop-filter: saturate(2.6) brightness(1.02);
            backdrop-filter: saturate(2.6) brightness(1.02);
    box-shadow: 0 30px 70px -45px rgba(26,122,70,.5);
  }
  /* no backdrop-filter support -> don't leave the whole stream dull */
  @supports not ((backdrop-filter: saturate(1)) or (-webkit-backdrop-filter: saturate(1))) { .flow-track { filter: none; } }
  @media (max-width: 640px){
    .flow-card { width: 384px; margin-right: 40px; grid-template-columns: 172px minmax(0, 1fr); column-gap: 16px; }
    .fc-portrait { width: 172px; height: 184px; clip-path: polygon(16px 0, 100% 0, calc(100% - 16px) 100%, 0 100%); }
    .fc-portrait.fc-initial { font-size: 48px; }
    .flow-tri { width: 38px; }
    .flow-tri span { width: 38px; height: 35px; }
    .flow-tri2 { width: 30px; }
    .flow-tri2 span { width: 30px; height: 28px; }
    .fc-quote { font-size: 13px; }
    .flow-lens { width: 56vw; height: 256px; }
  }

  /* ───────────────  PROJECT SHOWCASES · expanding strip  ─────────────── */
  .projects { max-width: 1500px; margin: 0 auto; padding: clamp(28px, 3.6vw, 52px) clamp(24px, 5vw, 72px) 110px; }
  .pstrip { display: flex; margin-top: 34px; height: 340px; }
  .pstrip + .pstrip { margin-top: 16px; }
  .pcard {
    position: relative; flex: 1; min-width: 0; display: block; overflow: hidden; text-decoration: none;
    background-color: var(--green-900); background-size: cover; background-position: center;
    clip-path: polygon(28px 0, 100% 0, calc(100% - 28px) 100%, 0 100%);
    transition: flex .6s var(--ease);
  }
  .pcard + .pcard { margin-left: -12px; }
  .pcard:hover, .pcard:focus-visible { flex: 2.6; }
  .pcard::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(8,26,17,.18), rgba(8,26,17,0) 40%); }
  .pc-tag {
    position: absolute; top: 16px; left: 36px; z-index: 2;
    font-family:'Sora',sans-serif; font-weight: 700; font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase;
    color: #fff; background: rgba(8,26,17,.55); -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
    padding: 6px 11px; border-radius: 999px;
  }
  .pc-tag.live::before { content: ""; display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--green-500); margin-right: 7px; }
  /* the strips scale as one block from the top edge, so they grow downward into the
     section's bottom padding instead of over the heading above */
  .pstrips { transform-origin: 50% 0; }
  .pc-caption {
    position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; height: 52%;
    display: flex; flex-direction: column; justify-content: flex-end; padding: 0 44px 20px 28px;
    background: linear-gradient(183deg, rgba(8,26,17,0) 0%, rgba(8,26,17,.55) 30%, rgba(8,26,17,.95) 75%);
    clip-path: polygon(0 12%, 100% 26%, 100% 100%, 0 100%);
  }
  /* no project names (Martien 07-10): only the short description, large enough to fill the block */
  .pc-caption p {
    font-family:'Sora',sans-serif; font-weight: 600; font-size: 16.5px; line-height: 1.35;
    color: #fff; letter-spacing: -.01em; max-width: 30ch;
  }
  @media (max-width: 760px){
    .pstrip { flex-direction: column; height: auto; }
    .pcard { flex: none; height: 190px; clip-path: polygon(18px 0, 100% 0, calc(100% - 18px) 100%, 0 100%); }
    .pcard + .pcard { margin-left: 0; margin-top: 14px; }
  }

  /* ───────────────  PARTNERS · slanted chips  ─────────────── */
  .plist { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 34px; transform-origin: 50% 0; }
  .partner {
    position: relative; display: block; text-decoration: none;
    padding: 18px 34px 20px 30px; background: #fff; border: 1px solid var(--line);
    transform: skewX(-5deg); transition: transform .2s var(--ease), box-shadow .2s var(--ease), border-color .2s;
  }
  .partner:hover { transform: skewX(-5deg) translateY(-4px); box-shadow: var(--shadow); border-color: transparent; }
  .pr-in { display: block; transform: skewX(5deg); }
  .pr-in strong { display: block; font-family:'Sora',sans-serif; font-weight: 700; font-size: 17px; color: var(--green-900); }
  .pr-what { display: block; font-size: 13px; color: var(--muted); margin-top: 3px; }
  .pr-domain { display: block; font-family:'Sora',sans-serif; font-weight: 600; font-size: 13px; color: var(--green-700); margin-top: 8px; }
  .partner.pending { background: var(--paper-2); cursor: default; }
  .partner.pending .pr-domain { color: var(--muted); }
  .pr-tag {
    position: absolute; top: -9px; right: 12px; transform: skewX(5deg);
    font-family:'Sora',sans-serif; font-weight: 700; font-size: 10px; letter-spacing: .08em; text-transform: uppercase;
    background: var(--green-100); color: var(--green-700); border: 1px solid rgba(34,166,90,.25);
    padding: 3px 9px; border-radius: 999px;
  }
  @media (max-width: 640px){ .partner { flex: 1 1 100%; } }

  /* ───────────────  FLOATING CHAT LAUNCHER (bottom-right)  ─────────────── */
  .launcher { position: fixed; right: 30px; bottom: 30px; z-index: 60; display: flex; align-items: flex-end; gap: 12px; }
  .launcher-orb {
    position: relative; width: 242px; height: 186px; border: none; background: transparent;
    padding: 0; cursor: pointer; filter: drop-shadow(0 20px 38px rgba(11,32,22,.44));
    transition: transform .2s var(--ease);
  }
  .launcher-orb:hover { transform: translateY(-3px) scale(1.02); }
  /* rings orbit the button on a small circle while spinning; one cycle ends exactly at its
     start (whole turns only) so the loop is seamless, and 17s/26s are co-prime so the two
     rings' combined pattern takes ~7.4 min to realign */
  .launcher-orb .l-ring { position: absolute; inset: -5px; border-radius: 50%; border: 2.5px dashed rgba(26,122,70,.6); animation: ring-orbit 17s linear infinite; }
  .launcher-orb .l-ring.l-ring2 { inset: -1px; border-style: solid; border-color: rgba(52,199,89,.38); animation: ring-orbit 26s linear infinite reverse; }
  @keyframes ring-orbit {
    from { transform: rotate(0turn) translateX(8px) rotate(0turn); }
    to   { transform: rotate(1turn) translateX(8px) rotate(1turn); }
  }
  .launcher-orb .l-blob {
    position: absolute; inset: 0; border-radius: 46% 54% 55% 45% / 52% 46% 56% 48%;
    background: radial-gradient(130% 120% at 30% 20%, var(--green-300), var(--green-500) 45%, var(--green-700));
    animation: morph 12s var(--ease) infinite;
  }
  .launcher-orb .l-photo {
    position: absolute; inset: 6px; overflow: hidden;
    border-radius: 46% 54% 55% 45% / 52% 46% 56% 48%; animation: morph 12s var(--ease) infinite;
  }
  .launcher-orb .l-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 30%; transform: scale(2.5); transform-origin: 39% 54%; }
  .launcher-orb .l-pulse {
    position: absolute; right: 12px; top: 8px; width: 15px; height: 15px; border-radius: 50%;
    background: var(--green-500); border: 2.5px solid #fff; animation: pulse 2s infinite;
  }
  /* the CoachOS panel defaults to bottom:88px (embed.js inline CSS) which half-covers the orb;
     lift it above the launcher (30px bottom + 186px orb + 20px gap) and shorten it to fit.
     On small screens the orb is smaller and space is scarce, so keep the widget's own layout. */
  @media (min-width: 561px){
    .co-embed-panel { bottom: 236px !important; height: min(560px, calc(100vh - 268px)) !important; }
  }
  /* the photo is scaled 2.5x inside a clipped circle and the decorative rings orbit around the launcher; their boxes would otherwise swallow taps around the launcher - taps fall through to the button instead */
  .launcher-orb .l-photo, .launcher-orb .l-ring { pointer-events: none; }
  @keyframes pulse { 0%{box-shadow:0 0 0 0 rgba(52,199,89,.55);} 70%{box-shadow:0 0 0 13px rgba(52,199,89,0);} 100%{box-shadow:0 0 0 0 rgba(52,199,89,0);} }
  .launcher-bubble {
    position: absolute; z-index: 5; right: 272px; bottom: 60px; white-space: nowrap;
    background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 11px 16px;
    box-shadow: var(--shadow); font-family:'Sora',sans-serif; font-weight:700; font-size:13.5px; color: var(--green-900);
  }
  .launcher-bubble small { display:block; font-weight:500; font-size:11.5px; color:var(--muted); margin-top:2px; }
  .launcher-bubble::after { content:""; position:absolute; right:-7px; bottom:20px; width:13px; height:13px; background:#fff; border-right:1px solid var(--line); border-bottom:1px solid var(--line); transform: rotate(-45deg); }
  .wa-btn {
    z-index: 5; margin-left: -50px; margin-top: 127px;
    width: 54px; height: 54px; border-radius: 50%; background: #25D366; display: grid; place-items: center;
    box-shadow: 0 10px 24px -8px rgba(37,211,102,.75); text-decoration: none;
    transition: transform .2s var(--ease);
  }
  .wa-btn:hover { transform: scale(1.09); }
  .wa-btn svg { width: 30px; height: 30px; fill: #fff; }
  @media (max-width: 560px){ .launcher { right: 18px; bottom: 18px; gap: 8px; } .launcher-orb { width: 144px; height: 124px; } .launcher-bubble { display: none; } .wa-btn { width: 46px; height: 46px; margin-top: 72px; } .wa-btn svg { width: 25px; height: 25px; } }

  /* subtle double text-shadow on key text */
  h1.reveal, .sub.reveal, .hbc-head, .sc-caption h3, .sc-caption p {
    text-shadow: 0px 2px 0 rgba(70,70,70,.05), 2px 2px 0 rgba(70,70,70,.05), 0px -1px 0 rgba(70,70,70,.05), 2px -1px 0 rgba(70,70,70,.05);
  }

  /* entrance animations */
  .reveal { opacity: 0; transform: translateY(22px); animation: rise .9s var(--ease) forwards; }
  .d1{animation-delay:.05s} .d2{animation-delay:.15s} .d3{animation-delay:.28s} .d4{animation-delay:.4s} .d5{animation-delay:.52s}
  @keyframes rise { to { opacity:1; transform:none; } }
  @media (prefers-reduced-motion: reduce){ *{ animation:none !important; } .reveal{ opacity:1; transform:none; } .proof-visual .pv-scale{ transform:none; } }
</style>
<?php wp_head(); ?>
</head>
<body>

  <section class="hero">
    <!-- TOP: message (left, on paper) + team photo fading in from ~30% -->
    <div class="hero-top">
      <div class="hero-top-photo"></div>
      <div class="copy">
        <h1 class="reveal d1">Create<span class="accent">value</span></h1>
        <p class="sub reveal d2">Evolve into the best version of yourself. Earn an income while creating opportunities for others.</p>
      </div>
    </div>

    <!-- BOTTOM: interactive 4-slide showcase on a plain background -->
    <div class="hero-bottom">
      <div class="showcase reveal d3">
        <div class="showcase-menu">
          <button class="sc-tab active" data-i="0"><svg viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm0 13L4.21 11.78 12 7.56l7.79 4.22L12 16zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg><span>Courses</span><span class="arr">→</span></button>
          <button class="sc-tab" data-i="1"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg><span>Members</span><span class="arr">→</span></button>
          <button class="sc-tab" data-i="2"><svg viewBox="0 0 24 24"><path d="M13 13v8h8v-8h-8zM3 21h8v-8H3v8zM3 3v8h8V3H3zm13.66-1.31L11 7.34 16.66 13l5.66-5.66-5.66-5.65z"/></svg><span>Tools</span><span class="arr">→</span></button>
          <button class="sc-tab" data-i="3"><svg viewBox="0 0 24 24"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg><span>Services</span><span class="arr">→</span></button>
          <button class="sc-tab" data-i="4"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg><span>Partners</span><span class="arr">→</span></button>
        </div>
        <a class="showcase-panel" id="scPanel" href="/courses">
          <div class="sc-caption">
            <h3 id="scTitle">Learn and get certified</h3>
            <p id="scDesc">Turn knowledge into provable, useful skills.</p>
          </div>
          <span class="sc-go">→</span>
        </a>
      </div>
      <div class="hero-bottom-cta reveal d4">
        <p class="hbc-head">Not sure where <span class="accent">to start?</span></p>
        <p class="hbc-body">Tell us what you want to achieve. We'll help you find the next step.</p>
      </div>
    </div>
  </section>

  <!-- SECOND MESSAGE · who we are (summary of the About page) -->
  <section class="proof" id="about">
    <div class="proof-intro">
      <div class="proof-copy reveal d1">
        <h2 class="alchemy">Discover the&nbsp;alchemy <span class="accent">of prosperity</span></h2>
        <p>ProsperGenics is a community of people who want to create value for themselves, for others, and for the communities around them.</p>
      </div>
      <div class="proof-visual reveal d2">
        <a class="pv-scale pv-link" href="/courses">
          <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/discover-alchemy.png" alt="A ProsperGenics community session - browse the course material" />
          <span class="pv-caption">Browse Course Material <span class="arrow">&rarr;</span></span>
        </a>
      </div>
    </div>
  </section>

  <!-- VALUE TEST · five open questions, analysed by the CoachOS 'value-test' context on the
       prospergenics tenant (coaching.martiendejong.nl); everyone comes out valuable, each in
       their own way. Result ends with a CTA to the courses signup. -->
  <section class="vtest" id="value-test">
    <div class="projects-head">
      <h2>How valuable <span class="accent">are you?</span></h2>
      <p>Five honest questions. Take the test and find out where your value lies.</p>
    </div>
    <div class="vtest-box" data-scroll-scale="1.2" data-scroll-scale-window="0.65">
      <div class="vtest-in" id="vtStage">
        <div class="vt-progress" id="vtProgress" aria-hidden="true"></div>
        <div class="vt-question" id="vtQuestion"></div>
        <div class="vt-field"><textarea class="vt-answer" id="vtAnswer" rows="4" placeholder="Just write what comes up - there are no wrong answers."></textarea></div>
        <div class="vt-nav">
          <button class="vt-back" id="vtBack" type="button" style="visibility:hidden">&larr; Back</button>
          <button class="btn btn-primary" id="vtNext" type="button">Next <span class="arrow">&rarr;</span></button>
          <span class="vt-hint" id="vtHint"></span>
        </div>
        <div class="vt-error" id="vtError" style="display:none"></div>
      </div>
    </div>
  </section>

  <!-- COMMUNITY VOICES · flowing banner. PLACEHOLDER quotes: swap for real Google Reviews
       once the ProsperGenics Business Profile is live. Keep the number of cards per
       .flow-set a multiple of 3 (see the bob keyframes note in the CSS). -->
  <section class="voices" id="voices">
    <div class="voices-head">
      <h2>Meet the <span class="accent">alchemists</span></h2>
      <p>The people who turn skills, ideas and opportunities into real value.</p>
    </div>
    <div class="flow" data-scroll-scale="1.3" data-scroll-scale-from="0.8" data-scroll-scale-window="0.65">
      <div class="flow-tri" aria-hidden="true"><span><i></i></span></div>
      <div class="flow-tri flow-tri2" aria-hidden="true"><span><i></i></span></div>
      <div class="flow-track">
        <div class="flow-set">
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/martien.jpg" alt="Martien de Jong" style="object-position: 45% 25%" /></span>
            <div class="fc-name">Martien de Jong</div>
            <div class="fc-role">Software Developer, Founder</div>
            <p class="fc-quote">"Kenya and the Netherlands think differently about value. Combining both is our biggest strength."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sandra_profile-300x300.png" alt="Simitia Mpoe" style="object-position: center 25%" /></span>
            <div class="fc-name">Simitia Mpoe</div>
            <div class="fc-role">Software Developer, Entrepreneur</div>
            <p class="fc-quote">"ProsperGenics connects people who want to build something with the people who can help them do it."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/diko_profile-300x300.png" alt="Diko Mohamed" style="object-position: center 30%" /></span>
            <div class="fc-name">Diko Mohamed</div>
            <div class="fc-role">Software Developer, Entrepreneur</div>
            <p class="fc-quote">"What I like most: nobody waits for permission. You see an opportunity, you build it."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sjoerd-wide.png" alt="Sjoerd Schepman" style="object-position: 50% 15%" /></span>
            <div class="fc-name">Sjoerd Schepman</div>
            <div class="fc-role">Curator Perridon Holdings</div>
            <p class="fc-quote">"I've seen this team turn an idea into a working product in weeks. That pace is rare."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/faith-nkumun-300x300.png" alt="Faith Nkumun" style="object-position: center 20%" /></span>
            <div class="fc-name">Faith Nkumun</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"I joined to learn digital skills. Now I help run real projects and teach others what I've learned."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/maxwell-300x300.png" alt="Maxwell" style="object-position: center 20%" /></span>
            <div class="fc-name">Maxwell</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"We work on real products with real customers. You grow faster here than in any classroom."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/lessy_profile-300x300.png" alt="Lessy" style="object-position: center 25%" /></span>
            <div class="fc-name">Lessy</div>
            <div class="fc-role">Software Developer</div>
            <p class="fc-quote">"Every task I pick up teaches me something I can use in the next one. We build, we learn, we build again."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/frank_profile-300x300.png" alt="Frank" style="object-position: center 25%" /></span>
            <div class="fc-name">Frank</div>
            <div class="fc-role">Software Developer</div>
            <p class="fc-quote">"Working here means real responsibility from day one. You don't watch, you ship."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sofy-300x300.png" alt="Sophy Nashipae de Jong" style="object-position: center 20%" /></span>
            <div class="fc-name">Sophy Nashipae de Jong</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"I practise my Dutch with our own coach app. Learning works better when it feels personal."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait fc-initial" aria-hidden="true">M</span>
            <div class="fc-name">Mayiani Nanana Mpoe</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"Value creation is not abstract here. You see it change daily life around you."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait fc-initial" aria-hidden="true">L</span>
            <div class="fc-name">Lou Marten</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"Good ideas are everywhere. What makes the difference is a community that helps you build them."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring" aria-hidden="true"></span>
            <span class="fc-portrait fc-initial" aria-hidden="true">N</span>
            <div class="fc-name">Natumi</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"I started by asking questions. Now people come to ask me."</p>
          </article>
        </div>
        <div class="flow-set" aria-hidden="true">
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/martien.jpg" alt="" style="object-position: 45% 25%" /></span>
            <div class="fc-name">Martien de Jong</div>
            <div class="fc-role">Software Developer, Founder</div>
            <p class="fc-quote">"Kenya and the Netherlands think differently about value. Combining both is our biggest strength."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sandra_profile-300x300.png" alt="" style="object-position: center 25%" /></span>
            <div class="fc-name">Simitia Mpoe</div>
            <div class="fc-role">Software Developer, Entrepreneur</div>
            <p class="fc-quote">"ProsperGenics connects people who want to build something with the people who can help them do it."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/diko_profile-300x300.png" alt="" style="object-position: center 30%" /></span>
            <div class="fc-name">Diko Mohamed</div>
            <div class="fc-role">Software Developer, Entrepreneur</div>
            <p class="fc-quote">"What I like most: nobody waits for permission. You see an opportunity, you build it."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sjoerd-wide.png" alt="" style="object-position: 50% 15%" /></span>
            <div class="fc-name">Sjoerd Schepman</div>
            <div class="fc-role">Curator Perridon Holdings</div>
            <p class="fc-quote">"I've seen this team turn an idea into a working product in weeks. That pace is rare."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/faith-nkumun-300x300.png" alt="" style="object-position: center 20%" /></span>
            <div class="fc-name">Faith Nkumun</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"I joined to learn digital skills. Now I help run real projects and teach others what I've learned."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/maxwell-300x300.png" alt="" style="object-position: center 20%" /></span>
            <div class="fc-name">Maxwell</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"We work on real products with real customers. You grow faster here than in any classroom."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/lessy_profile-300x300.png" alt="" style="object-position: center 25%" /></span>
            <div class="fc-name">Lessy</div>
            <div class="fc-role">Software Developer</div>
            <p class="fc-quote">"Every task I pick up teaches me something I can use in the next one. We build, we learn, we build again."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/frank_profile-300x300.png" alt="" style="object-position: center 25%" /></span>
            <div class="fc-name">Frank</div>
            <div class="fc-role">Software Developer</div>
            <p class="fc-quote">"Working here means real responsibility from day one. You don't watch, you ship."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sofy-300x300.png" alt="" style="object-position: center 20%" /></span>
            <div class="fc-name">Sophy Nashipae de Jong</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"I practise my Dutch with our own coach app. Learning works better when it feels personal."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait fc-initial">M</span>
            <div class="fc-name">Mayiani Nanana Mpoe</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"Value creation is not abstract here. You see it change daily life around you."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait fc-initial">L</span>
            <div class="fc-name">Lou Marten</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"Good ideas are everywhere. What makes the difference is a community that helps you build them."</p>
          </article>
          <article class="flow-card">
            <span class="fc-ring"></span>
            <span class="fc-portrait fc-initial">N</span>
            <div class="fc-name">Natumi</div>
            <div class="fc-role">Community member</div>
            <p class="fc-quote">"I started by asking questions. Now people come to ask me."</p>
          </article>
        </div>
      </div>
      <div class="flow-lens" aria-hidden="true"></div>
    </div>
  </section>

  <!-- TOOLS · the systems we built ourselves and use daily; most prestigious first.
       Target pages (/jengo demo-request, /courses, /citizenship) are part of the
       listing-pages plan (docs/listing-pages-architecture.md). -->
  <section class="projects" id="projects">
    <div class="projects-head">
      <h2>Tools we use, <span class="accent">built ourselves</span></h2>
      <p>The systems behind our daily work. We use them, we build them, we offer them.</p>
    </div>
    <div class="pstrips" data-scroll-scale="1.2">
      <div class="pstrip">
        <a class="pcard" href="/jengo" aria-label="Jengo" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/jengo-task-outcomes.png')">
          <span class="pc-tag">Jengo</span>
          <div class="pc-caption"><p>Give your AI an extra brain.</p></div>
        </a>
        <a class="pcard" href="/courses" aria-label="AI trainings" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sandra.png'); background-position: 60% 20%">
          <span class="pc-tag">AI trainings</span>
          <div class="pc-caption"><p>AI training and certification.</p></div>
        </a>
        <a class="pcard" href="/citizenship" aria-label="Citizenship courses" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/jengo-werkplek-duo.png'); background-color: #101826">
          <span class="pc-tag">Citizenship courses</span>
          <div class="pc-caption"><p>Dutch citizenship and language courses.</p></div>
        </a>
      </div>
    </div>
  </section>

  <!-- SERVICES · what we do for clients; each card leads to a showcase page with the
       real projects as examples (CoachOS: POG dorpsgids, citizenship, Karibu Mara;
       research: Art Revisionist; provenance: Bugatti Insights; online presence:
       SEO God, Port of Giethoorn, Art Revisionist). -->
  <section class="projects" id="services">
    <div class="projects-head">
      <h2>Our <span class="accent">services</span></h2>
      <p>What we do for organisations, powered by the systems above.</p>
    </div>
    <div class="pstrips" data-scroll-scale="1.2">
      <div class="pstrip">
        <a class="pcard" href="/services/coachos" aria-label="CoachOS platform" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/jengo-telefoon-chat.png'); background-color: var(--paper-2)">
          <span class="pc-tag">CoachOS</span>
          <div class="pc-caption"><p>AI chat, courses and avatar videos for your organisation, on your own platform.</p></div>
        </a>
        <a class="pcard" href="/services/research" aria-label="Research and investigation" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/artrevisionist.jpg'); background-position: 28% 35%">
          <span class="pc-tag">Research &amp; investigation</span>
          <div class="pc-caption"><p>Deep research that corrects the record, with evidence anyone can verify.</p></div>
        </a>
        <a class="pcard" href="/services/provenance" aria-label="Provenance" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/bugatti-insights.jpg')">
          <span class="pc-tag">Provenance</span>
          <div class="pc-caption"><p>Provenance systems that give objects a verifiable history.</p></div>
        </a>
        <a class="pcard" href="/services/online-presence" aria-label="Online presence" style="background-image:url('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/seomagic.png')">
          <span class="pc-tag">Online presence</span>
          <div class="pc-caption"><p>Websites, SEO, AI-optimisation and social media promotion that get you found.</p></div>
        </a>
      </div>
    </div>
  </section>

  <!-- PARTNERS · mirrors the Partners slide in the hero; /partners becomes the full
       filterable list page (see docs/listing-pages-architecture.md) -->
  <section class="projects" id="partners">
    <div class="projects-head">
      <h2>Initiatives by <span class="accent">our members</span></h2>
      <p>Companies and organisations our members built and run.</p>
    </div>
    <div class="plist" data-scroll-scale="1.2">
      <a class="partner" href="https://martiendejong.nl" target="_blank" rel="noopener">
        <span class="pr-in"><strong>Martien de Jong</strong><span class="pr-what">Software development</span><span class="pr-domain">martiendejong.nl</span></span>
      </a>
      <a class="partner" href="https://bugattiinsights.com" target="_blank" rel="noopener">
        <span class="pr-in"><strong>Bugatti Insights</strong><span class="pr-what">Automotive provenance research</span><span class="pr-domain">bugattiinsights.com</span></span>
      </a>
      <a class="partner" href="https://artrevisionist.com" target="_blank" rel="noopener">
        <span class="pr-in"><strong>Art Revisionist</strong><span class="pr-what">Historical art attribution research</span><span class="pr-domain">artrevisionist.com</span></span>
      </a>
      <span class="partner pending">
        <span class="pr-tag">Pending</span>
        <span class="pr-in"><strong>Eblossm</strong><span class="pr-what">Partnership in progress</span><span class="pr-domain">coming soon</span></span>
      </span>
      <span class="partner pending">
        <span class="pr-tag">Pending</span>
        <span class="pr-in"><strong>Port of Giethoorn</strong><span class="pr-what">Village tourism platform</span><span class="pr-domain">portofgiethoorn.com</span></span>
      </span>
      <span class="partner pending">
        <span class="pr-tag">Pending</span>
        <span class="pr-in"><strong>De Dames van De Jonge</strong><span class="pr-what">Boat rental · Giethoorn</span><span class="pr-domain">coming soon</span></span>
      </span>
      <span class="partner pending">
        <span class="pr-tag">Pending</span>
        <span class="pr-in"><strong>AgentX</strong><span class="pr-what">Partnership in progress</span><span class="pr-domain">coming soon</span></span>
      </span>
    </div>
  </section>

  <!-- Floating chat launcher: ProsperGenics coach (CoachOS tenant) + WhatsApp to Sandra -->
  <div class="launcher">
    <div class="launcher-bubble">Chat with ProsperGenics<small>Ask about tools, training &amp; projects</small></div>
    <button class="launcher-orb" aria-label="Open ProsperGenics chat">
      <span class="l-ring"></span><span class="l-ring l-ring2"></span>
      <span class="l-blob"></span>
      <span class="l-photo"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/contact-bg.jpg" alt="Lessy &amp; Diko — chat with ProsperGenics" /></span>
      <span class="l-pulse"></span>
      <!-- stopPropagation: the whole orb is the CoachOS chat trigger (embed.js listens via
           closest('.launcher-orb') on document); without it a WhatsApp click would also toggle the chat -->
      <a class="wa-btn" href="https://wa.me/254XXXXXXXXX" target="_blank" rel="noopener" title="WhatsApp Sandra" onclick="event.stopPropagation()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.2 4.79 1.2h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m0 1.67c2.2 0 4.27.86 5.82 2.42a8.2 8.2 0 0 1 2.42 5.82c0 4.54-3.7 8.24-8.25 8.24-1.52 0-3.01-.41-4.3-1.19l-.31-.18-3.12.82.83-3.04-.2-.32a8.17 8.17 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24M8.53 7.33c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.43 1.03 2.6.13.17 1.76 2.68 4.25 3.76.59.26 1.06.41 1.42.52.6.19 1.14.16 1.57.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.08.15-1.18-.06-.1-.23-.16-.48-.29-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.42.08-.16.04-.3-.02-.42-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.42-.14-.01-.31-.01-.48-.01"/></svg>
      </a>
    </button>
  </div>

  <!-- ProsperGenics coach chat (CoachOS tenant 'prospergenics'): takeover mode, the launcher
       orb is the trigger so the widget renders no floating button of its own -->
  <script src="https://coaching.martiendejong.nl/coaching/embed.js?v=2" data-tenant="prospergenics"
          data-lang="en" data-title="Chat with ProsperGenics" data-color="#1a7a46"
          data-trigger=".launcher-orb" data-position="right" async></script>

  <script>
    const SLIDES = [
      { t:"Learn and get certified", d:"Turn knowledge into provable, useful skills.",                               img:"<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sandra.png",              pos:"90% 32%",    href:"/courses" },
      { t:"Find value-creators", d:"Meet engineers, designers, researchers and entrepreneurs.", img:"<?php echo esc_url( get_template_directory_uri() ); ?>/assets/sjoerd-wide.png",          pos:"80% 45%",    href:"/members" },
      { t:"Expand your capacity", d:"A curated directory of online tools that make your life easier.",               img:"<?php echo esc_url( get_template_directory_uri() ); ?>/assets/programs-bg.jpg",         pos:"center 45%", href:"/tools" },
      { t:"Maximize your value", d:"Lets build and optimize your processes together.",                              img:"<?php echo esc_url( get_template_directory_uri() ); ?>/assets/maximize.png",             pos:"center 32%", href:"/services" },
      { t:"Our partners", d:"The companies and organisations we create value with.",                               img:"<?php echo esc_url( get_template_directory_uri() ); ?>/assets/value.png",                pos:"center 55%", href:"/partners" },
    ];
    const panel = document.getElementById('scPanel');
    const title = document.getElementById('scTitle');
    const desc  = document.getElementById('scDesc');
    const tabs  = Array.from(document.querySelectorAll('.sc-tab'));
    let idx = 0, timer;
    function show(i){
      idx = i; const s = SLIDES[i];
      tabs.forEach((t,j)=> t.classList.toggle('active', j===i));
      panel.style.backgroundImage = "url('" + s.img + "')";
      panel.style.backgroundPosition = s.pos;
      title.textContent = s.t; desc.textContent = s.d;
      panel.setAttribute('href', s.href);
    }
    function restart(){ clearInterval(timer); timer = setInterval(()=> show((idx+1)%SLIDES.length), 5000); }
    tabs.forEach((t,i)=>{
      t.addEventListener('click', e=>{ e.preventDefault(); show(i); restart(); });
      t.addEventListener('mouseenter', ()=>{ show(i); restart(); });
    });
    show(0); restart();

    // ── Value test: five open questions, analysed by CoachOS (tenant prospergenics,
    //    context value-test). The answer format is fixed by the context prompt:
    //    first line = profile name, blank line, then the explanation.
    (function () {
      const QUESTIONS = [
        "What could you talk about for hours without getting tired?",
        "What do people around you keep asking your help with?",
        "What did you recently make, fix or organise that you were quietly proud of?",
        "If money did not matter for a year, how would you spend your days?",
        "What about the world annoys you so much that you would love to change it?"
      ];
      const API_BASE = "https://coaching.martiendejong.nl/coaching";
      const stage = document.getElementById('vtStage');
      const progress = document.getElementById('vtProgress');
      const questionEl = document.getElementById('vtQuestion');
      const answerEl = document.getElementById('vtAnswer');
      const backBtn = document.getElementById('vtBack');
      const nextBtn = document.getElementById('vtNext');
      const hintEl = document.getElementById('vtHint');
      const errorEl = document.getElementById('vtError');
      if (!stage) return;
      const answers = QUESTIONS.map(function () { return ''; });
      let step = 0;

      QUESTIONS.forEach(function () {
        const d = document.createElement('span');
        d.className = 'vt-dot';
        progress.appendChild(d);
      });

      function render() {
        const dots = progress.children;
        for (let i = 0; i < dots.length; i++) dots[i].classList.toggle('done', i <= step);
        questionEl.textContent = (step + 1) + '. ' + QUESTIONS[step];
        answerEl.value = answers[step];
        backBtn.style.visibility = step === 0 ? 'hidden' : 'visible';
        nextBtn.innerHTML = step === QUESTIONS.length - 1 ? 'Reveal my value <span class="arrow">&rarr;</span>' : 'Next <span class="arrow">&rarr;</span>';
        hintEl.textContent = (step + 1) + ' / ' + QUESTIONS.length;
        answerEl.focus({ preventScroll: true });
      }

      backBtn.addEventListener('click', function () {
        answers[step] = answerEl.value;
        if (step > 0) { step--; render(); }
      });

      nextBtn.addEventListener('click', function () {
        answers[step] = answerEl.value;
        if (step < QUESTIONS.length - 1) { step++; render(); return; }
        finish();
      });

      function renderResult(text) {
        const br = text.indexOf('\n');
        const profile = br > 0 ? text.slice(0, br).trim() : 'The Alchemist';
        const rest = br > 0 ? text.slice(br).trim() : text;
        stage.innerHTML =
          '<div class="vt-profile"></div>' +
          '<div class="vt-result-text"></div>' +
          '<div class="vt-cta">' +
          '  <a class="btn-primary" href="/courses">Start developing your value <span class="arrow">&rarr;</span></a>' +
          '  <span class="vt-cta-note">Your value is real. Our courses help you turn it into skills, income and impact - sign up and start today.</span>' +
          '</div>';
        stage.querySelector('.vt-profile').textContent = profile;
        stage.querySelector('.vt-result-text').textContent = rest;
      }

      // After the five questions: ask for an email address. The AI analysis only runs after
      // the visitor clicks the confirmation link (abuse brake + confirmed lead).
      function finish() {
        const compiled = "My answers to the value test:\n" + QUESTIONS.map(function (q, i) {
          return (i + 1) + '. ' + q + '\n- ' + (answers[i].trim() || '(no answer)');
        }).join('\n');
        stage.innerHTML =
          '<div class="vt-question">Almost there - where can we send your value profile?</div>' +
          '<div class="vt-field"><input type="email" class="vt-answer vt-email" id="vtEmail" placeholder="you@example.com" autocomplete="email"></div>' +
          '<div class="vt-nav">' +
          '  <button class="btn btn-primary" id="vtSend" type="button">Send my confirmation link <span class="arrow">&rarr;</span></button>' +
          '  <span class="vt-hint">One email, no spam. Your profile unlocks after you confirm.</span>' +
          '</div>' +
          '<div class="vt-error" id="vtErr2" style="display:none"></div>';
        const emailEl = document.getElementById('vtEmail');
        const sendBtn = document.getElementById('vtSend');
        const errEl = document.getElementById('vtErr2');
        emailEl.focus({ preventScroll: true });
        sendBtn.addEventListener('click', function () {
          const email = emailEl.value.trim();
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
            errEl.textContent = 'That email address does not look right yet.';
            errEl.style.display = 'block';
            return;
          }
          sendBtn.disabled = true;
          errEl.style.display = 'none';
          fetch(API_BASE + '/api/leadtest/aanvraag', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Tenant': 'prospergenics' },
            body: JSON.stringify({ email: email, antwoorden: compiled, contextSlug: 'value-test' })
          }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
          }).then(function () {
            stage.innerHTML =
              '<div class="vt-question">Check your inbox!</div>' +
              '<div class="vt-result-text">We sent a confirmation link to <strong></strong>. ' +
              'Click it and your personal value profile appears right here. (No mail? Check your spam folder.)</div>';
            stage.querySelector('strong').textContent = email;
          }).catch(function () {
            sendBtn.disabled = false;
            errEl.textContent = 'Sending the confirmation mail did not work. Please try again in a minute.';
            errEl.style.display = 'block';
          });
        });
      }

      // Returning from the confirmation mail: ?vt=<token> -> run the analysis and show the result.
      function confirmFlow(token) {
        stage.innerHTML = '<div class="vt-busy"><span class="l-blob"></span>Email confirmed - the alchemists are weighing your answers&hellip;</div>';
        setTimeout(function () { document.getElementById('value-test').scrollIntoView({ block: 'center' }); }, 300);
        fetch(API_BASE + '/api/leadtest/bevestig', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Tenant': 'prospergenics' },
          body: JSON.stringify({ token: token })
        }).then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        }).then(function (data) {
          renderResult((data && data.resultaat) ? data.resultaat.trim() : '');
        }).catch(function () {
          stage.innerHTML = '<div class="vt-result-text">This confirmation link is not valid (anymore), or the alchemists are momentarily overwhelmed. Take the test again or try the link once more in a minute.</div>' +
            '<div class="vt-cta"><a class="btn-primary" href="/courses">Explore the courses <span class="arrow">&rarr;</span></a></div>';
        });
        if (window.history && history.replaceState) {
          const url = new URL(window.location.href);
          url.searchParams.delete('vt');
          history.replaceState(null, '', url.toString());
        }
      }

      const vtToken = new URLSearchParams(window.location.search).get('vt');
      if (vtToken) { confirmFlow(vtToken); } else { render(); }
    })();

    // Parallax: set top = scrollY * 0.5 + 34px so the element moves at half scroll speed
    (function(){
      const photo = document.querySelector('.hero-top-photo');
      const showcasePanel = document.querySelector('.showcase-panel');
      const alchemyVisual = document.querySelector('.proof-visual');
      const alchemyScale = document.querySelector('.proof-visual .pv-scale');
      const voicesSec = document.querySelector('.voices');
      const alchemistPics = document.querySelectorAll('.fc-portrait img');
      // Generic scroll-zoom (house rule since 07-10): give any element data-scroll-scale="1.2"
      // and it grows to that factor while its parent section scrolls into view.
      // Optional: data-scroll-scale-from (start factor, default 1) and
      // data-scroll-scale-window (fraction of the viewport height over which the growth
      // runs, default 0.85 - smaller = finished at a lower scroll position).
      // Every new homepage section gets this attribute on its main visual block.
      const scrollScaled = Array.prototype.map.call(document.querySelectorAll('[data-scroll-scale]'), function (el) {
        // keep any CSS transform the element already has (e.g. the test panel's skew):
        // the inline scroll-zoom transform would otherwise replace it, which un-skews the
        // box while its counter-skewed content keeps leaning - instant accidental italics
        const basis = getComputedStyle(el).transform;
        return {
          el: el,
          base: (basis && basis !== 'none') ? basis + ' ' : '',
          target: parseFloat(el.getAttribute('data-scroll-scale')) || 1.2,
          from: parseFloat(el.getAttribute('data-scroll-scale-from')) || 1,
          window: parseFloat(el.getAttribute('data-scroll-scale-window')) || 0.85,
          anchor: el.closest('section') || el
        };
      });
      if (!photo || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      // scroll effects are desktop-only for now; clear anything set when crossing the breakpoint
      const mobile = window.matchMedia('(max-width: 980px)');
      function clearEffects() {
        photo.style.top = ''; photo.style.bottom = '';
        if (showcasePanel) { showcasePanel.style.marginTop = ''; showcasePanel.style.marginBottom = ''; showcasePanel.style.marginLeft = ''; showcasePanel.style.marginRight = ''; showcasePanel.style.transform = ''; }
        if (alchemyScale) { alchemyScale.style.transform = ''; }
        alchemistPics.forEach(function (im) { im.style.transform = ''; });
        scrollScaled.forEach(function (it) { it.el.style.transform = ''; });
      }
      // the base transforms differ per breakpoint (the test panel's skew is off on mobile):
      // re-read them with the inline transform cleared whenever we cross back to desktop,
      // otherwise a page loaded at mobile width keeps base '' and the zoom strips the skew
      function recaptureBases() {
        scrollScaled.forEach(function (it) {
          it.el.style.transform = '';
          const t = getComputedStyle(it.el).transform;
          it.base = (t && t !== 'none') ? t + ' ' : '';
        });
      }
      mobile.addEventListener('change', function (e) { if (e.matches) { clearEffects(); } else { recaptureBases(); update(); } });
      let ticking = false;
      function update() {
        if (mobile.matches) { clearEffects(); ticking = false; return; }
        const y = window.scrollY;
        photo.style.top = (y * 0.5 + 34) + 'px';
        // bottom edge slides from 0 to -100px over the first 500px of scroll
        photo.style.bottom = (Math.min(y, 500) / 500 * -100) + 'px';
        // showcase panel bulge: ramps up over the first 250px of scroll and stays; eases back only when scrolling up
        if (showcasePanel) {
          const t = Math.min(y / 250, 1);
          showcasePanel.style.marginTop = (20 * t) + 'px';
          showcasePanel.style.marginBottom = (-20 * t) + 'px';
          showcasePanel.style.marginLeft = (30 * t) + 'px';
          showcasePanel.style.marginRight = (-30 * t) + 'px';
          showcasePanel.style.transform = 'scale(' + (1 + 0.07 * t) + ')';
        }
        // alchemy picture: 0.8 while entering at the bottom of the viewport,
        // grows to full size while visibly scrolling up (done at ~35% viewport height)
        if (alchemyVisual && alchemyScale) {
          const vh = window.innerHeight;
          const top = alchemyVisual.getBoundingClientRect().top;
          const a = Math.min(1, Math.max(0, (vh * 0.9 - top) / (vh * 0.55)));
          alchemyScale.style.transform = 'scale(' + (0.8 + 0.2 * a) + ')';
        }
        // alchemist portraits zoom from 1 to 1.1 while the voices strip scrolls into view;
        // the growth spans ~85% of a viewport height of scrolling so it reads as scroll-driven
        // (the img scales inside the clipped portrait, so the slanted cut stays put)
        if (voicesSec && alchemistPics.length) {
          const vh2 = window.innerHeight;
          const vtop = voicesSec.getBoundingClientRect().top;
          const v = Math.min(1, Math.max(0, (vh2 - vtop) / (vh2 * 0.85)));
          const vs = 'scale(' + (1 + 0.1 * v) + ')';
          alchemistPics.forEach(function (im) { im.style.transform = vs; });
        }
        // generic scroll-zoom for every [data-scroll-scale] block (team banner, project strips, ...);
        // one transform per block keeps composites like the duplicated marquee sets seamless
        if (scrollScaled.length) {
          const vh3 = window.innerHeight;
          scrollScaled.forEach(function (it) {
            const st = it.anchor.getBoundingClientRect().top;
            const sp = Math.min(1, Math.max(0, (vh3 - st) / (vh3 * it.window)));
            it.el.style.transform = it.base + 'scale(' + (it.from + (it.target - it.from) * sp) + ')';
          });
        }
        ticking = false;
      }
      update();
      window.addEventListener('scroll', function() {
        if (!ticking) { requestAnimationFrame(update); ticking = true; }
      }, { passive: true });
    })();
  </script>

<?php wp_footer(); ?>
</body>
</html>
