#!/usr/bin/env python3
"""
Generate the personal profile pages (people/<slug>/index.html) and the two list
pages (../experts/index.html, ../community/index.html) from PROFILES below.

    python build-profiles.py

One shared template, one data block: edit a member here and re-run, never edit
the generated pages by hand. The homepage slider (../index.html) links to
people/<slug>/.

These profiles are deliberately NOT a LinkedIn clone. LinkedIn is for
connecting; ProsperGenics profiles centre on the person's STORY and the VALUE
they contribute - including people who are not on LinkedIn at all. CV facts
(work, education) are present but secondary. Expertises can carry endorsements
(shown only when real endorsement data exists - never invent endorsers).

tags: "expert" puts a person on /experts/, "member" on /community/ and in the
homepage slider. A person can be both; an external expert is just "expert".

Asset URLs are relative so the pages work in the static preview;
deploy-wp-theme-vimexx.py rewrites them to the theme directory on upload.
"""
import json, os

HERE = os.path.dirname(os.path.abspath(__file__))
SITE = "https://test.prospergenics.com"
FALLBACK = "This part of the story is still being written."

# expertises: list of {"name": ..., "endorsers": [names]} - endorsers optional and
# ONLY real ones (an endorsement someone actually gave), never placeholder names.
PROFILES = [
    {
        "slug": "martien", "name": "Martien de Jong", "role": "Software Developer, Founder",
        "tags": ["expert", "member"],
        "img": "martien.jpg", "pos": "45% 25%",
        "quote": "I'm building Jengo, our AI agent platform. It runs most of what we ship.",
        "story": "Martien has been building software professionally since 2006: hospital systems, government platforms, rail apps, SEO automation. Somewhere along the way the question shifted from \"what can I build\" to \"how is value actually created\". ProsperGenics is his answer in progress: a community spanning the Netherlands and Kenya where people create value for themselves and each other, with software as the lever. Today he builds Jengo, the AI agent platform that runs most of what the community ships.",
        "contributions": [
            ("Jengo, the AI agent platform", "The system behind most of the community's products: autonomous agents that build, deploy and maintain real software."),
            ("The ProsperGenics community", "Founded the community and connects its Dutch and Kenyan sides: investment, training and real projects."),
            ("Developer training", "Brings new developers from first steps to shipping production work."),
        ],
        "expertises": [
            {"name": "AI agents & LLM systems"},
            {"name": ".NET & web platforms"},
            {"name": "Software architecture"},
            {"name": "SEO automation"},
        ],
        "work": [
            ("Founder & lead developer", "ProsperGenics", "Builds Jengo, the AI agent platform, and leads development across the community's products."),
            ("Independent software developer", "Netherlands", "Two decades of web platforms and automation for Dutch organisations, including healthcare, government and rail."),
        ],
        "education": [
            ("Hogere Informatica", "Hogeschool Windesheim, Zwolle", "2002 - 2006"),
            ("HAVO, Economie & Maatschappij", "Greijdanus College, Zwolle", "1997 - 2001"),
        ],
    },
    {
        "slug": "simitia", "name": "Simitia Mpoe", "role": "Software Developer, Entrepreneur",
        "tags": ["expert", "member"],
        "img": "sandra_profile-300x300.png", "pos": "center 25%",
        "quote": "I design and build web products, and I'm growing my own business with everything I learn.",
        "story": "Simitia came to the community to build, and that is exactly what she does: web products, design work, her own ventures. Every project adds a skill, and every skill flows back into her own business. She is one of the clearest examples of the ProsperGenics loop: learn by building, build to create value, reinvest the value in yourself.",
        "contributions": [
            ("Design & front-end work", "Designs and builds interfaces across the community's websites and products."),
            ("Her own ventures", "Growing her own business with the skills she picks up in the community."),
        ],
        "expertises": [
            {"name": "Web design"},
            {"name": "Front-end development"},
            {"name": "Entrepreneurship"},
        ],
        "work": [
            ("Software developer", "ProsperGenics", "Design and front-end work on the community's websites and products."),
            ("Entrepreneur", "", "Building her own business alongside her development work."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
    },
    {
        "slug": "diko", "name": "Diko Mohamed", "role": "Software Developer, Entrepreneur",
        "tags": ["expert", "member"],
        "img": "diko_profile-300x300.png", "pos": "center 30%",
        "quote": "I turn opportunities into products. Right now that means integrations and new partnerships.",
        "story": "Diko's talent is seeing the opportunity before anyone else does, and then actually building it. Inside the community he develops software; outside it he opens doors: integrations, partnerships, contacts that connect ProsperGenics products to the wider tech world.",
        "contributions": [
            ("Partnerships & integrations", "Connects the community's products to outside platforms and partners."),
            ("Product development", "Builds and ships software across community projects."),
        ],
        "expertises": [
            {"name": "Software development"},
            {"name": "Integrations"},
            {"name": "Business development"},
        ],
        "work": [
            ("Software developer", "ProsperGenics", "Product development, integrations and partner contacts."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
    },
    {
        "slug": "sjoerd", "name": "Sjoerd Schepman", "role": "Curator Perridon Holdings",
        "tags": ["expert"],
        "img": "sjoerd-wide.png", "pos": "50% 15%",
        "quote": "I curate the Perridon collection, researching the provenance and the stories behind every piece.",
        "story": "Sjoerd curates the Perridon art collection: researching where each work comes from, who held it, and what its story is. With the ProsperGenics team he explores how software can serve that work, from provenance research tooling to making a collection's knowledge accessible.",
        "contributions": [
            ("Provenance research", "Researches and documents the history of the works in the Perridon collection."),
            ("Provenance tooling", "Works with the team on digital tools for provenance research and collection management."),
        ],
        "expertises": [
            {"name": "Art curation"},
            {"name": "Provenance research"},
            {"name": "Collection management"},
        ],
        "work": [
            ("Curator", "Perridon Holdings", "Manages and researches the art collection."),
            ("Collaborator", "ProsperGenics", "Works with the team on provenance research tooling."),
        ],
        "education": [("", "", FALLBACK)],
    },
    {
        "slug": "faith", "name": "Faith Nkumun", "role": "Entrepreneur, Community member",
        "tags": ["member"],
        "img": "faith-nkumun-300x300.png", "pos": "center 20%",
        "quote": "I had a small store in Oloolaimutia. Here I learned the business skills to take it further.",
        "story": "Faith ran a small store in Oloolaimutia, Kenya. She joined the community to learn the business side of her business: bookkeeping, planning, trade. What she learned, she now applies to take her store further, and what she proves, she passes on to others around her.",
        "contributions": [
            ("A growing local business", "Her store serves the Oloolaimutia community, run with the business skills she built here."),
        ],
        "expertises": [
            {"name": "Retail & trade"},
            {"name": "Business planning"},
        ],
        "work": [
            ("Shopkeeper", "Oloolaimutia", "Ran her own small store serving the local community."),
            ("Community member", "ProsperGenics", "Business skills training and community projects."),
        ],
        "education": [
            ("Business skills training", "ProsperGenics", "Practical training in running and growing a small business."),
        ],
    },
    {
        "slug": "maxwell", "name": "Maxwell", "role": "Student, Community member",
        "tags": ["member"],
        "img": "maxwell-300x300.png", "pos": "center 20%",
        "quote": "I'm still studying, and I apply what I learn on real projects with real customers.",
        "story": "Maxwell is still studying, and that is precisely his strength: everything he learns in class gets tested the same week on real community projects with real customers. School gives him the theory; the community gives him the practice.",
        "contributions": [
            ("Project work", "Contributes to community products while completing his studies."),
        ],
        "expertises": [
            {"name": "Digital skills"},
        ],
        "work": [
            ("Project work", "ProsperGenics", "Contributes to community products alongside his studies."),
        ],
        "education": [
            ("Currently studying", "", "Maxwell continues his education alongside the community work."),
        ],
    },
    {
        "slug": "lessy", "name": "Lesiamon Mpoe", "role": "Co-founder, Trainer, Software Developer",
        "tags": ["expert", "member"],
        "img": "lessy_profile-300x300.png", "pos": "center 25%",
        "quote": "I develop software and train new developers. Good software starts with understanding people.",
        "story": "Lesiamon co-founded ProsperGenics and carries two of its pillars: she builds software, and she builds the people who build software. New developers in the community learn the craft from her, task by task. Her conviction: good software starts with understanding people.",
        "contributions": [
            ("Developer training", "Trains the community's new developers, from first task to production work."),
            ("Websites & web applications", "Builds and maintains software across the community's projects."),
        ],
        "expertises": [
            {"name": "Software development"},
            {"name": "Training & teaching"},
            {"name": "WordPress & websites"},
        ],
        "work": [
            ("Co-founder, trainer & software developer", "ProsperGenics", "Websites and web applications across the community's projects, and training for new developers."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
    },
    {
        "slug": "frank", "name": "Frank", "role": "Software Developer",
        "tags": ["expert", "member"],
        "img": "frank_profile-300x300.png", "pos": "center 25%",
        "quote": "I build and ship features for our web platforms, with real responsibility from day one.",
        "story": "Frank is a Kenyan software developer who got real responsibility from day one, and took it. He builds and ships features for the community's web platforms: from first commit to live deployment, including this website.",
        "contributions": [
            ("Platform features", "Builds and ships features for the community's web platforms, end to end."),
        ],
        "expertises": [
            {"name": "Web development"},
            {"name": "Shipping & deployment"},
        ],
        "work": [
            ("Software developer", "ProsperGenics", "Feature development on the community's web platforms."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
    },
    {
        "slug": "sophy", "name": "Sophy Nashipae de Jong", "role": "Community member",
        "tags": ["member"],
        "img": "sofy-300x300.png", "pos": "center 20%",
        "quote": "I'm learning Dutch with our own coach app. Learning works better when it feels personal.",
        "story": "Sophy bridges two worlds. She manages Nashipae Cultural Oasis, a cultural centre that preserves and shares Maasai culture: a place for learning, celebration and community. And she is learning Dutch with the community's own coach app, usually as the first user of whatever the team builds next.",
        "contributions": [
            ("Nashipae Cultural Oasis", "Manages the cultural centre that preserves and shares Maasai culture."),
            ("First user & tester", "New community tools meet their first real user in Sophy."),
        ],
        "expertises": [
            {"name": "Maasai culture & community"},
            {"name": "Dutch (in progress)"},
        ],
        "work": [
            ("Manager", "Nashipae Cultural Oasis", "Runs the cultural centre: learning, celebration and community gathering."),
            ("Community member", "ProsperGenics", "Tester and first user of new community tools."),
        ],
        "education": [
            ("Dutch language & integration", "", "Daily practice with the community's own coach app."),
        ],
    },
    {
        "slug": "mayiani", "name": "Mayiani Nanana Mpoe", "role": "Community member",
        "tags": ["member"],
        "img": None, "initial": "M",
        "quote": "I'm learning digital skills step by step and putting them to work in daily life.",
        "story": "Mayiani is learning digital skills step by step and puts each one to work in daily life. No leap, just steady ground gained: the quiet version of value creation the community is built on.",
        "contributions": [
            ("Digital skills in practice", "Applies what she learns directly in her daily life and surroundings."),
        ],
        "expertises": [
            {"name": "Digital basics"},
        ],
        "work": [
            ("Community member", "ProsperGenics", "Digital skills in practice."),
        ],
        "education": [
            ("Digital skills training", "ProsperGenics", "Step-by-step practical training."),
        ],
    },
    {
        "slug": "lou", "name": "Lou Marten", "role": "Marketing, Community member",
        "tags": ["expert", "member"],
        "img": None, "initial": "L",
        "quote": "I work on our brand and marketing, finding the right words for what we build.",
        "story": "Lou works on the ProsperGenics brand: the strategy, the story, and the words that make what the community builds understandable to the outside world. Builders make the value; Lou makes it visible.",
        "contributions": [
            ("Brand strategy", "Shapes how ProsperGenics tells its story and positions its products."),
        ],
        "expertises": [
            {"name": "Brand strategy"},
            {"name": "Marketing"},
            {"name": "Storytelling"},
        ],
        "work": [
            ("Marketing & brand strategy", "ProsperGenics", "Brand strategy and marketing for the community's products."),
        ],
        "education": [("", "", FALLBACK)],
    },
    {
        "slug": "natumi", "name": "Natumi", "role": "Livestock keeper, Community member",
        "tags": ["member"],
        "img": None, "initial": "N",
        "quote": "People invested in me. I grew my herd and the results now pay the school fees.",
        "story": "Community members invested in Natumi. She used it to grow her herd, runs it as a working business, and the results now pay the school fees. No app, no code: her story is value creation in its most direct form, and proof of what the community exists to do.",
        "contributions": [
            ("A growing herd", "Grew her herd through community investment and runs it as a working business."),
            ("School fees, paid", "The returns from her herd now pay the school fees."),
        ],
        "expertises": [
            {"name": "Livestock & trade"},
        ],
        "work": [
            ("Livestock keeper", "", "Grew her herd through community investment and runs it as a working business."),
        ],
        "education": [("", "", FALLBACK)],
    },
    {
        "slug": "farid", "name": "Farid Harrak", "role": "Community member",
        "tags": ["member"],
        "img": "farid-300x300.png", "pos": "center 20%",
        "quote": "I'm learning to build websites with AI, bringing my gaming passion into the web.",
        "story": "Farid is a game enthusiast learning to build websites with AI. He combines his passion for gaming with emerging AI technologies to create web experiences, developing his skills in modern, AI-powered web development one project at a time.",
        "contributions": [
            ("AI-built web experiences", "Builds websites using AI-powered tools and platforms, bringing a gamer's eye to the web."),
        ],
        "expertises": [
            {"name": "AI-assisted web development"},
            {"name": "Gaming"},
        ],
        "work": [
            ("Community member", "ProsperGenics", "Learning and building websites with AI-powered tools."),
        ],
        "education": [
            ("AI-powered web development", "ProsperGenics", "Hands-on, learning by building."),
        ],
    },
]

# ── shared page chrome ──────────────────────────────────────────────────────

STYLE = """  :root {{
    /* same palette + tokens as the homepage */
    --ink:#0b2016; --green-900:#0f3d28; --green-700:#1a7a46; --green-600:#22a65a;
    --green-500:#34c759; --green-300:#86e0a6; --green-100:#d9f5e3;
    --paper:#f4faf6; --paper-2:#eaf5ee; --line:rgba(15,61,40,.12);
    --muted:#4a6555; --body:#27402f;
    --ease:cubic-bezier(.22,.61,.36,1); --gutter:clamp(22px,5vw,72px);
  }}
  * {{ box-sizing:border-box; margin:0; padding:0; }}
  html {{ scroll-behavior:smooth; }}
  body {{ font-family:'Inter',system-ui,sans-serif; color:var(--ink); background:var(--paper); -webkit-font-smoothing:antialiased; overflow-x:hidden; }}
  img {{ display:block; max-width:100%; }}
  a {{ color:inherit; }}
  h1,h2,h3 {{ font-family:'Gabarito',sans-serif; }}
  h1,h2,h3,p {{ text-shadow:0px 2px 0 rgba(70,70,70,.05),2px 2px 0 rgba(70,70,70,.05),0px -1px 0 rgba(70,70,70,.05),2px -1px 0 rgba(70,70,70,.05); }}
  .wrap {{ max-width:1080px; margin:0 auto; padding:0 var(--gutter); }}
  :focus-visible {{ outline:3px solid var(--green-500); outline-offset:3px; border-radius:8px; }}
  .nav {{ position:relative; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:16px; max-width:1500px; margin:0 auto; padding:22px var(--gutter) 0; }}
  .brand img {{ height:68px; width:auto; }}
  .nav-links {{ display:flex; align-items:center; gap:30px; }}
  .nav-links a {{ font-size:15px; font-weight:500; color:var(--green-900); text-decoration:none; opacity:.8; transition:opacity .2s; }}
  .nav-links a:hover {{ opacity:1; }}
  .nav-links a[aria-current="page"] {{ opacity:1; font-weight:600; box-shadow:inset 0 -2px 0 var(--green-500); padding-bottom:3px; }}
  @media (max-width:560px) {{ .brand img {{ height:56px; }} .nav-links {{ gap:20px; }} }}
  .eyebrow {{ display:inline-flex; align-items:center; gap:10px; font-family:'Sora',sans-serif; font-weight:600; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:var(--green-700); padding:8px 14px 8px 10px; border-radius:999px; background:var(--green-100); border:1px solid rgba(34,166,90,.2); }}
  .eyebrow .dot {{ width:7px; height:7px; border-radius:50%; background:var(--green-500); box-shadow:0 0 0 4px rgba(52,199,89,.22); }}
  .chips {{ display:flex; flex-wrap:wrap; gap:10px; }}
  .chip {{ font-family:'Sora',sans-serif; font-weight:600; font-size:13.5px; color:var(--green-900); background:var(--green-100); border:1px solid rgba(34,166,90,.25); border-radius:999px; padding:8px 16px; }}
  .chip small {{ display:block; font-weight:500; font-size:11px; color:var(--green-700); margin-top:2px; }}
  footer {{ border-top:1px solid var(--line); background:var(--paper-2); margin-top:clamp(56px,8vw,96px); }}
  footer .wrap {{ display:flex; align-items:center; justify-content:space-between; gap:18px; padding-top:26px; padding-bottom:26px; }}
  .f-brand {{ display:flex; align-items:center; gap:12px; font-size:14px; color:var(--muted); }}
  .f-brand img {{ height:40px; }}
  footer a {{ font-size:14px; color:var(--green-900); }}"""

PROFILE_PAGE = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{name} · ProsperGenics</title>
<meta name="description" content="{name}, {role}. Story, value contributions and expertise at ProsperGenics." />
<meta name="robots" content="noindex" />
<link rel="icon" href="../../assets/logo.png" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700;800;900&family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<script type="application/ld+json">
{jsonld}
</script>
<style>
{style}
  .p-hero {{ position:relative; overflow:hidden; padding:clamp(36px,6vw,72px) 0 clamp(26px,4vw,48px); }}
  .p-hero::before {{ content:""; position:absolute; width:620px; height:620px; right:-240px; top:-80px; border-radius:50%; background:radial-gradient(circle,var(--green-100),transparent 65%); opacity:.7; pointer-events:none; }}
  .p-hero .wrap {{ position:relative; z-index:2; display:grid; grid-template-columns:300px minmax(0,1fr); gap:clamp(28px,5vw,64px); align-items:center; }}
  .p-portrait {{ position:relative; width:300px; height:330px; overflow:hidden; clip-path:polygon(26px 0,100% 0,calc(100% - 26px) 100%,0 100%); background:linear-gradient(165deg,var(--green-100),var(--paper-2) 70%); }}
  .p-portrait img {{ width:100%; height:100%; object-fit:cover; }}
  .p-portrait.p-initial {{ display:grid; place-items:center; font-family:'Sora',sans-serif; font-weight:800; font-size:110px; color:#fff; background:linear-gradient(150deg,var(--green-600),var(--green-300)); }}
  h1 {{ font-weight:800; font-size:clamp(36px,5.4vw,64px); line-height:1; letter-spacing:-.03em; }}
  .p-role {{ margin-top:10px; font-family:'Sora',sans-serif; font-weight:600; font-size:clamp(15px,1.8vw,19px); color:var(--green-700); }}
  .p-quote {{ margin-top:22px; max-width:46ch; font-family:'Sora',sans-serif; font-weight:500; font-size:clamp(17px,2vw,22px); line-height:1.45; color:var(--green-900); border-left:3px solid var(--green-500); padding-left:16px; }}
  @media (max-width:760px) {{
    .p-hero .wrap {{ grid-template-columns:1fr; }}
    .p-portrait {{ width:232px; height:252px; clip-path:polygon(20px 0,100% 0,calc(100% - 20px) 100%,0 100%); }}
    .p-portrait.p-initial {{ font-size:84px; }}
  }}
  .pv {{ padding:clamp(20px,3vw,36px) 0 0; }}
  .pv-block {{ margin-top:clamp(34px,4.5vw,56px); }}
  .pv-block h2 {{ font-weight:800; font-size:clamp(22px,2.8vw,32px); letter-spacing:-.02em; color:var(--ink); margin-bottom:18px; }}
  .pv-block h2 .accent {{ color:var(--green-600); }}
  .prose p {{ font-size:clamp(16px,1.5vw,18.5px); line-height:1.68; color:var(--body); max-width:62ch; }}
  .cards {{ list-style:none; display:grid; gap:14px; }}
  .card {{ background:#fff; border:1px solid var(--line); border-radius:18px; padding:18px 22px; max-width:720px; box-shadow:0 14px 34px -26px rgba(11,32,22,.35); }}
  .card h3 {{ font-weight:700; font-size:17px; color:var(--green-900); }}
  .card .org {{ font-size:13.5px; color:var(--muted); margin-top:2px; }}
  .card p {{ font-size:15px; line-height:1.6; color:var(--body); margin-top:8px; }}
  .bg-grid {{ display:grid; grid-template-columns:repeat(auto-fit,minmax(290px,1fr)); gap:clamp(20px,3vw,40px); max-width:900px; }}
  .bg-grid h3 {{ font-weight:800; font-size:17px; color:var(--ink); margin-bottom:12px; }}
  .bg-grid .cards {{ gap:10px; }}
  .bg-grid .card {{ padding:14px 18px; }}
  .back {{ display:inline-flex; align-items:center; gap:8px; margin-top:clamp(40px,5vw,56px); font-family:'Sora',sans-serif; font-weight:600; font-size:15px; color:var(--green-700); text-decoration:none; }}
  .back:hover {{ color:var(--green-600); }}
</style>
</head>
<body>

  <header class="nav">
    <a class="brand" href="../../" aria-label="ProsperGenics · home"><img src="../../assets/logo.png" alt="ProsperGenics" /></a>
    <nav class="nav-links" aria-label="Main">
      <a href="../../">Home</a>
      <a href="../../about/">About</a>
      <a href="../../experts/">Experts</a>
      <a href="../../community/">Community</a>
    </nav>
  </header>

  <main>
    <section class="p-hero">
      <div class="wrap">
        <div class="p-portrait{initial_class}">{portrait}</div>
        <div>
          <p class="eyebrow"><span class="dot"></span>{eyebrow}</p>
          <h1 style="margin-top:20px">{name}</h1>
          <p class="p-role">{role}</p>
          <blockquote class="p-quote">"{quote}"</blockquote>
        </div>
      </div>
    </section>

    <section class="pv">
      <div class="wrap">
        <div class="pv-block">
          <h2>The <span class="accent">story</span></h2>
          <div class="prose"><p>{story}</p></div>
        </div>
        <div class="pv-block">
          <h2>Value <span class="accent">created</span></h2>
          <ul class="cards">
{contributions}
          </ul>
        </div>
        <div class="pv-block">
          <h2>Expertise &amp; <span class="accent">endorsements</span></h2>
          <div class="chips">
{expertises}
          </div>
        </div>
        <div class="pv-block">
          <h2>Back<span class="accent">ground</span></h2>
          <div class="bg-grid">
            <div>
              <h3>Work history</h3>
              <ul class="cards">
{work_items}
              </ul>
            </div>
            <div>
              <h3>Education &amp; training</h3>
              <ul class="cards">
{edu_items}
              </ul>
            </div>
          </div>
        </div>
        <a class="back" href="../../#voices">&larr; Back to the team</a>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <div class="f-brand"><img src="../../assets/logo.png" alt="" /><span>ProsperGenics · building prosperity together</span></div>
      <a href="../../">Home</a>
    </div>
  </footer>

</body>
</html>
"""

LIST_PAGE = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{title} · ProsperGenics</title>
<meta name="description" content="{meta_desc}" />
<meta name="robots" content="noindex" />
<link rel="icon" href="../assets/logo.png" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700;800;900&family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<script type="application/ld+json">
{jsonld}
</script>
<style>
{style}
  .l-hero {{ position:relative; overflow:hidden; padding:clamp(36px,6vw,72px) 0 clamp(10px,2vw,24px); }}
  .l-hero::before {{ content:""; position:absolute; width:620px; height:620px; right:-240px; top:-80px; border-radius:50%; background:radial-gradient(circle,var(--green-100),transparent 65%); opacity:.7; pointer-events:none; }}
  .l-hero .wrap {{ position:relative; z-index:2; }}
  h1 {{ font-weight:800; font-size:clamp(38px,6vw,76px); line-height:1; letter-spacing:-.03em; margin-top:22px; max-width:20ch; }}
  h1 .accent {{ background:linear-gradient(100deg,var(--green-700) 0%,var(--green-500) 55%,var(--green-300) 100%); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }}
  .lede {{ margin-top:22px; max-width:56ch; font-size:clamp(16.5px,1.8vw,19.5px); line-height:1.6; color:var(--body); }}
  .grid {{ display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:clamp(18px,2.5vw,28px); padding:clamp(34px,5vw,56px) 0 0; }}
  .pcard {{ display:block; text-decoration:none; background:#fff; border:1px solid var(--line); border-radius:22px; overflow:hidden; box-shadow:0 18px 44px -30px rgba(11,32,22,.4); transition:transform .25s var(--ease), box-shadow .25s var(--ease); }}
  .pcard:hover {{ transform:translateY(-4px); box-shadow:0 26px 54px -30px rgba(11,32,22,.5); }}
  .pc-media {{ position:relative; height:240px; overflow:hidden; background:linear-gradient(165deg,var(--green-100),var(--paper-2) 70%); }}
  .pc-media img {{ width:100%; height:100%; object-fit:cover; }}
  .pc-media.pc-initial {{ display:grid; place-items:center; font-family:'Sora',sans-serif; font-weight:800; font-size:84px; color:#fff; background:linear-gradient(150deg,var(--green-600),var(--green-300)); }}
  .pc-body {{ padding:18px 20px 22px; }}
  .pc-body h2 {{ font-weight:800; font-size:20px; letter-spacing:-.01em; color:var(--ink); }}
  .pc-role {{ font-size:13.5px; color:var(--green-700); font-weight:600; margin:4px 0 10px; font-family:'Sora',sans-serif; }}
  .pc-line {{ font-size:14.5px; line-height:1.55; color:var(--body); margin-bottom:12px; }}
  .pc-body .chips {{ gap:7px; }}
  .pc-body .chip {{ font-size:12px; padding:5px 12px; }}
</style>
</head>
<body>

  <header class="nav">
    <a class="brand" href="../" aria-label="ProsperGenics · home"><img src="../assets/logo.png" alt="ProsperGenics" /></a>
    <nav class="nav-links" aria-label="Main">
      <a href="../">Home</a>
      <a href="../about/">About</a>
      <a href="../experts/"{cur_experts}>Experts</a>
      <a href="../community/"{cur_community}>Community</a>
    </nav>
  </header>

  <main>
    <section class="l-hero">
      <div class="wrap">
        <p class="eyebrow"><span class="dot"></span>{eyebrow}</p>
        <h1>{heading}</h1>
        <p class="lede">{lede}</p>
      </div>
    </section>
    <section>
      <div class="wrap">
        <div class="grid">
{cards}
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <div class="f-brand"><img src="../assets/logo.png" alt="" /><span>ProsperGenics · building prosperity together</span></div>
      <a href="../">Home</a>
    </div>
  </footer>

</body>
</html>
"""


def card_item(title, org, desc):
    h = "                <li class=\"card\">\n"
    if title:
        h += f"                  <h3>{title}</h3>\n"
    if org:
        h += f"                  <div class=\"org\">{org}</div>\n"
    if desc:
        h += f"                  <p>{desc}</p>\n"
    h += "                </li>"
    return h


def expertise_chip(e):
    endorsers = e.get("endorsers") or []
    inner = e["name"]
    if endorsers:
        inner += f"<small>endorsed by {', '.join(endorsers)}</small>"
    return f"            <span class=\"chip\">{inner}</span>"


def person_jsonld(m, url):
    d = {
        "@context": "https://schema.org",
        "@type": "Person",
        "name": m["name"],
        "jobTitle": m["role"],
        "url": url,
        "knowsAbout": [e["name"] for e in m["expertises"]],
        "memberOf": {"@type": "Organization", "name": "ProsperGenics", "url": SITE},
    }
    if m.get("img"):
        d["image"] = f"{SITE}/wp-content/themes/prospergenics-2026/assets/{m['img']}"
    return d


def build_profiles():
    for m in PROFILES:
        if m.get("img"):
            portrait = f"<img src=\"../../assets/{m['img']}\" alt=\"{m['name']}\" style=\"object-position: {m['pos']}\" />"
            initial_class = ""
        else:
            portrait = m["initial"]
            initial_class = " p-initial"
        eyebrow = "ProsperGenics expert" if "expert" in m["tags"] else "ProsperGenics community"
        html = PROFILE_PAGE.format(
            style=STYLE,
            name=m["name"], role=m["role"], quote=m["quote"], story=m["story"],
            eyebrow=eyebrow, portrait=portrait, initial_class=initial_class,
            jsonld=json.dumps(person_jsonld(m, f"{SITE}/people/{m['slug']}/"), indent=2),
            contributions="\n".join(card_item(t, "", d) for t, d in m["contributions"]),
            expertises="\n".join(expertise_chip(e) for e in m["expertises"]),
            work_items="\n".join(card_item(*w) for w in m["work"]),
            edu_items="\n".join(card_item(*e) for e in m["education"]),
        )
        out_dir = os.path.join(HERE, m["slug"])
        os.makedirs(out_dir, exist_ok=True)
        with open(os.path.join(out_dir, "index.html"), "w", encoding="utf-8") as f:
            f.write(html)


def list_card(m):
    if m.get("img"):
        media = f"<div class=\"pc-media\"><img src=\"../assets/{m['img']}\" alt=\"{m['name']}\" style=\"object-position: {m['pos']}\" /></div>"
    else:
        media = f"<div class=\"pc-media pc-initial\">{m['initial']}</div>"
    chips = "\n".join(f"              <span class=\"chip\">{e['name']}</span>" for e in m["expertises"][:3])
    return f"""          <a class="pcard" href="../people/{m['slug']}/">
            {media}
            <div class="pc-body">
              <h2>{m['name']}</h2>
              <div class="pc-role">{m['role']}</div>
              <p class="pc-line">{m['quote']}</p>
              <div class="chips">
{chips}
              </div>
            </div>
          </a>"""


def build_list(folder, tag, title, eyebrow, heading, lede, meta_desc):
    people = [m for m in PROFILES if tag in m["tags"]]
    jsonld = {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": title,
        "itemListElement": [
            {"@type": "ListItem", "position": i + 1,
             "item": person_jsonld(m, f"{SITE}/people/{m['slug']}/")}
            for i, m in enumerate(people)
        ],
    }
    html = LIST_PAGE.format(
        style=STYLE, title=title, meta_desc=meta_desc, eyebrow=eyebrow,
        heading=heading, lede=lede,
        jsonld=json.dumps(jsonld, indent=2),
        cards="\n".join(list_card(m) for m in people),
        cur_experts=' aria-current="page"' if folder == "experts" else "",
        cur_community=' aria-current="page"' if folder == "community" else "",
    )
    out_dir = os.path.normpath(os.path.join(HERE, "..", folder))
    os.makedirs(out_dir, exist_ok=True)
    with open(os.path.join(out_dir, "index.html"), "w", encoding="utf-8") as f:
        f.write(html)
    return len(people)


def build():
    build_profiles()
    n_exp = build_list(
        "experts", "expert", "ProsperGenics experts", "Our experts",
        'Experts who <span class="accent">build value.</span>',
        "From AI agent platforms to provenance research: the experts in and around the ProsperGenics community, each with their own story and the value they contribute.",
        "The experts in and around the ProsperGenics community: AI, software, design, marketing, art and more.")
    n_com = build_list(
        "community", "member", "ProsperGenics community", "Community stories",
        'Stories of <span class="accent">value creation.</span>',
        "ProsperGenics is a community of people creating value: some write software, some run a store, some grow a herd. These are their stories and contributions.",
        "Stories and value contributions of the ProsperGenics community members.")
    print(f"built {len(PROFILES)} profiles, {n_exp} experts, {n_com} community members")


if __name__ == "__main__":
    build()
