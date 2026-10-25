<?php
defined('ABSPATH') || exit;
// Shared head and styles for every inner template (single, page, index, 404); the header itself
// (menu, account links, mobile menu) comes from inc/site-chrome.php, like on every other page type.
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo esc_html( wp_get_document_title() ); ?></title>
<link rel="stylesheet" href="<?= esc_url(get_template_directory_uri()) ?>/assets/fonts/fonts.css" />
<style>
:root{
  --ink:#0b2016;--green-900:#0f3d28;--green-700:#1a7a46;--green-600:#22a65a;
  --green-500:#34c759;--green-300:#86e0a6;--green-100:#d9f5e3;
  --paper:#f4faf6;--paper-2:#eaf5ee;--line:rgba(15,61,40,.12);
  --muted:#4a6555;--ease:cubic-bezier(.22,.61,.36,1);
}
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Inter',system-ui,sans-serif;color:var(--ink);background:var(--paper);-webkit-font-smoothing:antialiased;overflow-x:hidden;}
body{min-height:100vh;display:flex;flex-direction:column;}
main{flex:1 0 auto;}

/* HERO BAND */
.post-hero{
  background:linear-gradient(140deg,var(--green-900) 0%,var(--green-700) 70%,#1d8e4f 100%);
  padding:clamp(48px,6vw,80px) clamp(24px,5vw,72px) clamp(40px,5vw,64px);
  position:relative;overflow:hidden;
}
.post-hero::before{
  content:"";position:absolute;top:-120px;right:-80px;
  width:420px;height:420px;border-radius:50%;
  background:radial-gradient(circle,rgba(52,199,89,.18),transparent 65%);
  pointer-events:none;
}
.post-hero-inner{max-width:760px;margin:0 auto;position:relative;z-index:1;}
.ph-eyebrow{
  display:inline-flex;align-items:center;gap:9px;margin-bottom:16px;
  font-family:'Sora',sans-serif;font-weight:700;font-size:11px;letter-spacing:.1em;text-transform:uppercase;
  color:rgba(255,255,255,.7);
}
.ph-eyebrow .dot{width:6px;height:6px;border-radius:50%;background:var(--green-300);}
.post-hero h1{
  font-family:'Gabarito',sans-serif;font-weight:800;
  font-size:clamp(30px,4vw,52px);line-height:1.08;letter-spacing:-.02em;color:#fff;
}
.ph-meta{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-top:20px;}
.ph-date{font-size:14px;color:rgba(255,255,255,.65);}
.ph-excerpt{margin-top:16px;font-size:clamp(15px,1.4vw,18px);line-height:1.65;color:rgba(255,255,255,.82);max-width:58ch;}

/* BODY */
.post-wrap{max-width:760px;margin:0 auto;padding:clamp(48px,6vw,80px) clamp(24px,5vw,40px) clamp(64px,8vw,112px);}
.post-content h2{
  font-family:'Gabarito',sans-serif;font-weight:800;
  font-size:clamp(22px,2.4vw,30px);line-height:1.15;letter-spacing:-.02em;
  color:var(--ink);margin:48px 0 16px;
}
.post-content h2:first-child{margin-top:0;}
.post-content h3{
  font-family:'Sora',sans-serif;font-weight:700;font-size:19px;
  color:var(--ink);margin:30px 0 10px;
}
.post-content p{font-size:17px;line-height:1.78;color:#2a3d30;margin-bottom:20px;}
.post-content ul,.post-content ol{padding-left:24px;margin-bottom:18px;}
.post-content li{font-size:16.5px;line-height:1.72;color:#2a3d30;margin-bottom:7px;}
.post-content strong{font-weight:600;color:var(--ink);}
.post-content em{font-style:italic;}
.post-content a{color:var(--green-700);text-decoration:underline;}
.post-content blockquote{
  border-left:3px solid var(--green-500);margin:28px 0;
  padding:14px 22px;background:var(--green-100);border-radius:0 8px 8px 0;
}
.post-content blockquote p{margin-bottom:0;font-style:italic;}
.post-content hr{border:none;border-top:1px solid var(--line);margin:40px 0;}

/* BACK LINK */
.post-back{
  display:inline-flex;align-items:center;gap:7px;
  font-size:14px;font-weight:500;color:var(--muted);text-decoration:none;
  margin-bottom:32px;transition:color .15s;
}
.post-back:hover{color:var(--green-700);}
/* LISTINGS (blog, archives, search) */
.list-wrap{max-width:760px;margin:0 auto;padding:clamp(40px,5vw,64px) clamp(24px,5vw,40px) clamp(64px,8vw,112px);display:grid;gap:18px;}
.post-card{display:block;background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px 24px;text-decoration:none;color:inherit;transition:border-color .15s,transform .15s;}
.post-card:hover{border-color:var(--green-500);transform:translateY(-2px);}
.post-card h2{font-family:'Gabarito',sans-serif;font-weight:800;font-size:clamp(20px,2.2vw,24px);line-height:1.2;letter-spacing:-.01em;}
.post-card .pc-date{display:block;font-size:13px;color:var(--muted);margin-top:6px;}
.post-card p{font-size:15.5px;line-height:1.65;color:#2a3d30;margin-top:10px;}
.pagination{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;}
.pagination .page-numbers{font-size:14px;padding:7px 13px;border-radius:999px;border:1px solid var(--line);color:var(--green-900);text-decoration:none;background:#fff;}
.pagination .page-numbers.current{background:var(--green-700);border-color:var(--green-700);color:#fff;}
.empty-note{font-size:17px;line-height:1.7;color:#2a3d30;}
/* SEARCH FORM */
.pg-search{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px;max-width:520px;}
.pg-search input[type=search]{flex:1 1 220px;min-width:0;font:inherit;font-size:15px;padding:11px 16px;border-radius:999px;border:1.5px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;}
.pg-search input[type=search]::placeholder{color:rgba(255,255,255,.65);}
.pg-search button{font-family:'Sora',sans-serif;font-weight:600;font-size:14px;padding:11px 20px;border-radius:999px;border:0;background:var(--green-300);color:var(--green-900);cursor:pointer;}
.links-404{display:flex;flex-wrap:wrap;gap:10px;}
.links-404 a{font-size:14.5px;font-weight:500;padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--green-900);text-decoration:none;}
.links-404 a:hover{border-color:var(--green-500);}
/* CONTACT PAGE */
.contact-wrap{max-width:1080px;margin:0 auto;padding:clamp(40px,5vw,64px) clamp(20px,5vw,40px) clamp(64px,8vw,96px);display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:28px;align-items:start;}
.contact-form-card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:clamp(22px,3vw,34px);display:grid;gap:18px;}
.contact-form-card h2,.contact-direct h2{font-family:'Gabarito',sans-serif;font-weight:800;font-size:clamp(22px,2.4vw,28px);letter-spacing:-.01em;}
.contact-form{display:grid;gap:16px;}
.field-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;}
.field{display:grid;gap:6px;min-width:0;}
.field label{font-size:14px;font-weight:600;color:var(--green-900);}
.field .optional{font-weight:400;color:var(--muted);}
.field input,.field select,.field textarea{font:inherit;font-size:16px;width:100%;padding:12px 14px;border-radius:12px;border:1.5px solid rgba(15,61,40,.22);background:var(--paper);color:var(--ink);}
.field textarea{resize:vertical;min-height:140px;}
.field input:focus,.field select:focus,.field textarea:focus{outline:3px solid var(--green-500);outline-offset:1px;border-color:var(--green-700);}
.form-privacy{font-size:13.5px;color:var(--muted);}
.form-privacy a{color:var(--green-700);}
.form-submit{justify-self:start;font-family:'Sora',sans-serif;font-weight:600;font-size:15px;padding:13px 26px;border-radius:999px;border:0;background:var(--green-700);color:#fff;cursor:pointer;}
.form-submit:hover{background:var(--green-900);}
.form-notice{border-radius:12px;padding:14px 16px;font-size:15px;line-height:1.55;}
.form-notice-ok{background:var(--green-100);color:var(--green-900);border:1px solid rgba(26,122,70,.3);}
.form-notice-error{background:#fbe4e2;color:#7a1d16;border:1px solid rgba(179,38,30,.3);}
.contact-direct{display:grid;gap:14px;}
.direct-card{display:grid;gap:2px;background:var(--green-900);color:#fff;border-radius:16px;padding:18px 20px;text-decoration:none;}
.direct-card:hover{background:var(--green-700);}
.direct-label{font-size:13px;color:var(--green-300);font-weight:600;letter-spacing:.04em;text-transform:uppercase;}
.direct-value{font-family:'Gabarito',sans-serif;font-weight:800;font-size:21px;}
.direct-note{font-size:14px;color:var(--muted);}
@media(max-width:820px){.contact-wrap{grid-template-columns:minmax(0,1fr);}.field-row{grid-template-columns:minmax(0,1fr);}}
.screen-reader-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
:focus-visible{outline:3px solid var(--green-500);outline-offset:3px;border-radius:6px;}
</style>
<style id="prospergenics-site-chrome-css">
<?php echo prospergenics_2026_site_chrome_css(); // phpcs:ignore WordPress.Security.EscapeOutput -- the theme's own stylesheet. ?>
</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php echo prospergenics_2026_site_header(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts in inc/site-chrome.php. ?>
<main id="main">
