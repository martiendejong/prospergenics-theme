<?php
defined('ABSPATH') || exit;
// Standalone single-post template — no get_header(), matches the course page pattern.
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php wp_title('—', true, 'right'); ?>ProsperGenics</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700;800;900&family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
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

/* NAV */
.pg-nav{
  position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;
  padding:18px clamp(24px,5vw,72px);
  background:rgba(244,250,246,.92);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);
  border-bottom:1px solid var(--line);
}
.pg-nav .brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
.pg-nav .brand img{height:38px;width:auto;display:block;}
.brand-text{font-family:'Sora',sans-serif;font-weight:800;font-size:20px;color:var(--green-900);letter-spacing:-.02em;}
.nav-links{display:flex;align-items:center;gap:28px;}
.nav-links a{font-size:14.5px;font-weight:500;color:var(--green-900);text-decoration:none;opacity:.8;transition:opacity .2s;}
.nav-links a:hover{opacity:1;}
.nav-cta{font-family:'Sora',sans-serif;font-weight:600;font-size:13.5px;padding:9px 17px;border-radius:999px;border:1.5px solid var(--green-700);color:var(--green-900);background:transparent;text-decoration:none;transition:all .2s;}
.nav-cta:hover{background:var(--green-700);color:#fff;}
@media(max-width:800px){.nav-links{display:none;}}

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
</style>
<?php wp_head(); ?>
</head>
<body>

<nav class="pg-nav">
  <a class="brand" href="<?= esc_url(home_url('/')) ?>">
    <img src="<?= esc_url(get_template_directory_uri()) ?>/assets/logo.png" alt="ProsperGenics" />
    <span class="brand-text">ProsperGenics</span>
  </a>
  <div class="nav-links">
    <a href="<?= esc_url(home_url('/courses/')) ?>">Courses</a>
    <a href="<?= esc_url(home_url('/members/')) ?>">Members</a>
    <a href="<?= esc_url(home_url('/tools/')) ?>">Tools</a>
    <a href="<?= esc_url(home_url('/services/')) ?>">Services</a>
    <a href="<?= esc_url(home_url('/partners/')) ?>">Partners</a>
  </div>
  <a class="nav-cta" href="<?= esc_url(home_url('/#value-test')) ?>">Take the test</a>
</nav>

<?php while (have_posts()) : the_post(); ?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>ProsperGenics</div>
    <h1><?php the_title(); ?></h1>
    <?php if (has_excerpt()): ?>
      <p class="ph-excerpt"><?php the_excerpt(); ?></p>
    <?php endif; ?>
    <div class="ph-meta">
      <span class="ph-date"><?php echo get_the_date('F j, Y'); ?></span>
    </div>
  </div>
</div>

<div class="post-wrap">
  <a class="post-back" href="<?= esc_url(home_url('/')) ?>">← Back to ProsperGenics</a>
  <div class="post-content">
    <?php echo apply_filters('the_content', get_the_content()); ?>
  </div>
</div>

<?php endwhile; ?>
<?php wp_footer(); ?>
</body>
</html>
