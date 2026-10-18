#!/usr/bin/env python3
"""
Generate the team member profile pages (team/<slug>/index.html) from PROFILES below.

    python build-profiles.py

One shared template, one data block: edit a member here and re-run, never edit the
generated pages by hand. The homepage slider (../index.html) links to team/<slug>/.
Asset URLs are relative (../../assets/) so the pages work in the static preview;
deploy-wp-theme-vimexx.py rewrites them to the theme directory on upload.
"""
import os

HERE = os.path.dirname(os.path.abspath(__file__))

FALLBACK = "This part of the story is still being written."

PROFILES = [
    {
        "slug": "martien", "name": "Martien de Jong", "role": "Software Developer, Founder",
        "img": "martien.jpg", "pos": "45% 25%",
        "quote": "I'm building Jengo, our AI agent platform. It runs most of what we ship.",
        "profile": "Dutch software developer and the founder of ProsperGenics. Martien has been building software professionally since 2006, from hospital systems and government platforms to SEO automation. Today he builds Jengo, the AI agent platform behind most of what the community ships, and connects the Dutch and Kenyan sides of ProsperGenics.",
        "work": [
            ("Founder & lead developer", "ProsperGenics", "Builds Jengo, the AI agent platform, and leads development across the community's products."),
            ("Independent software developer", "Netherlands", "Two decades of web platforms and automation for Dutch organisations, including healthcare, government and rail."),
        ],
        "education": [
            ("Hogere Informatica", "Hogeschool Windesheim, Zwolle", "2002 - 2006"),
            ("HAVO, Economie & Maatschappij", "Greijdanus College, Zwolle", "1997 - 2001"),
        ],
        "skills": ["AI agents & LLM systems", ".NET & web platforms", "Architecture & automation", "Mentoring"],
    },
    {
        "slug": "simitia", "name": "Simitia Mpoe", "role": "Software Developer, Entrepreneur",
        "img": "sandra_profile-300x300.png", "pos": "center 25%",
        "quote": "I design and build web products, and I'm growing my own business with everything I learn.",
        "profile": "Developer and entrepreneur from Kenya. Simitia designs and builds web products within the community and is growing her own business with the skills she picks up along the way.",
        "work": [
            ("Software developer", "ProsperGenics", "Design and front-end work on the community's websites and products."),
            ("Entrepreneur", "", "Building her own business alongside her development work."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
        "skills": ["Web design", "Front-end development", "Entrepreneurship"],
    },
    {
        "slug": "diko", "name": "Diko Mohamed", "role": "Software Developer, Entrepreneur",
        "img": "diko_profile-300x300.png", "pos": "center 30%",
        "quote": "I turn opportunities into products. Right now that means integrations and new partnerships.",
        "profile": "Developer and entrepreneur who spots opportunities and turns them into working products. Diko works on integrations and partnerships that connect the community's products to the wider world.",
        "work": [
            ("Software developer", "ProsperGenics", "Product development, integrations and partner contacts."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
        "skills": ["Software development", "Integrations", "Business development"],
    },
    {
        "slug": "sjoerd", "name": "Sjoerd Schepman", "role": "Curator Perridon Holdings",
        "img": "sjoerd-wide.png", "pos": "50% 15%",
        "quote": "I curate the Perridon collection, researching the provenance and the stories behind every piece.",
        "profile": "Curator of the Perridon collection. Sjoerd researches the provenance and history of the works in the collection, and works with the ProsperGenics team on digital tools for provenance research and collection management.",
        "work": [
            ("Curator", "Perridon Holdings", "Manages and researches the art collection."),
            ("Collaborator", "ProsperGenics", "Works with the team on provenance research tooling."),
        ],
        "education": [("", "", FALLBACK)],
        "skills": ["Art curation", "Provenance research", "Collection management"],
    },
    {
        "slug": "faith", "name": "Faith Nkumun", "role": "Entrepreneur, Community member",
        "img": "faith-nkumun-300x300.png", "pos": "center 20%",
        "quote": "I had a small store in Oloolaimutia. Here I learned the business skills to take it further.",
        "profile": "Entrepreneur from Oloolaimutia, Kenya. Faith had a small store there and joined the community to learn business skills: bookkeeping, planning and trade. She is putting those skills to work to take her business further.",
        "work": [
            ("Shopkeeper", "Oloolaimutia", "Ran her own small store serving the local community."),
            ("Community member", "ProsperGenics", "Business skills training and community projects."),
        ],
        "education": [
            ("Business skills training", "ProsperGenics", "Practical training in running and growing a small business."),
        ],
        "skills": ["Retail & trade", "Business planning", "Community work"],
    },
    {
        "slug": "maxwell", "name": "Maxwell", "role": "Student, Community member",
        "img": "maxwell-300x300.png", "pos": "center 20%",
        "quote": "I'm still studying, and I apply what I learn on real projects with real customers.",
        "profile": "Maxwell is still studying and combines his studies with hands-on work on community projects, applying what he learns in class to real products with real customers.",
        "work": [
            ("Project work", "ProsperGenics", "Contributes to community products alongside his studies."),
        ],
        "education": [
            ("Currently studying", "", "Maxwell continues his education alongside the community work."),
        ],
        "skills": ["Digital skills", "Project work"],
    },
    {
        "slug": "lessy", "name": "Lesiamon Mpoe", "role": "Co-founder, Trainer, Software Developer",
        "img": "lessy_profile-300x300.png", "pos": "center 25%",
        "quote": "I develop software and train new developers. Good software starts with understanding people.",
        "profile": "Co-founder of ProsperGenics. Lesiamon develops software, builds websites and web applications, and trains the community's new developers. Her starting point: good software starts with understanding people.",
        "work": [
            ("Co-founder, trainer & software developer", "ProsperGenics", "Websites and web applications across the community's projects, and training for new developers."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
        "skills": ["WordPress & websites", "Web development", "Training & teaching"],
    },
    {
        "slug": "frank", "name": "Frank", "role": "Software Developer",
        "img": "frank_profile-300x300.png", "pos": "center 25%",
        "quote": "I build and ship features for our web platforms, with real responsibility from day one.",
        "profile": "Kenyan software developer in the ProsperGenics team. Frank builds and ships features for the community's web platforms, from first commit to deployment.",
        "work": [
            ("Software developer", "ProsperGenics", "Feature development on the community's web platforms."),
        ],
        "education": [
            ("Hands-on developer training", "ProsperGenics", "Learning by building, on real projects with real users."),
        ],
        "skills": ["Web development", "Shipping & deployment"],
    },
    {
        "slug": "sophy", "name": "Sophy Nashipae de Jong", "role": "Community member",
        "img": "sofy-300x300.png", "pos": "center 20%",
        "quote": "I'm learning Dutch with our own coach app. Learning works better when it feels personal.",
        "profile": "Sophy is learning Dutch through the community's own coach app and is often the first user of the new tools the team builds.",
        "work": [
            ("Community member", "ProsperGenics", "Tester and first user of new community tools."),
        ],
        "education": [
            ("Dutch language & integration", "", "Daily practice with the community's own coach app."),
        ],
        "skills": ["Dutch (in progress)", "Testing new tools"],
    },
    {
        "slug": "mayiani", "name": "Mayiani Nanana Mpoe", "role": "Community member",
        "img": None, "initial": "M",
        "quote": "I'm learning digital skills step by step and putting them to work in daily life.",
        "profile": "Community member from Kenya. Mayiani is learning digital skills step by step and puts them to work in daily life.",
        "work": [
            ("Community member", "ProsperGenics", "Digital skills in practice."),
        ],
        "education": [
            ("Digital skills training", "ProsperGenics", "Step-by-step practical training."),
        ],
        "skills": ["Digital basics", "Community life"],
    },
    {
        "slug": "lou", "name": "Lou Marten", "role": "Marketing, Community member",
        "img": None, "initial": "L",
        "quote": "I work on our brand and marketing, finding the right words for what we build.",
        "profile": "Lou works on the ProsperGenics brand and marketing: the strategy, the story and the words that explain what the community builds.",
        "work": [
            ("Marketing & brand strategy", "ProsperGenics", "Brand strategy and marketing for the community's products."),
        ],
        "education": [("", "", FALLBACK)],
        "skills": ["Brand strategy", "Marketing", "Writing"],
    },
    {
        "slug": "natumi", "name": "Natumi", "role": "Livestock keeper, Community member",
        "img": None, "initial": "N",
        "quote": "People invested in me. I grew my herd and the results now pay the school fees.",
        "profile": "Livestock keeper from Kenya. Community members invested in Natumi; she grew her herd with it, and the results now pay the school fees. Her story is value creation in its most direct form.",
        "work": [
            ("Livestock keeper", "", "Grew her herd through community investment and runs it as a working business."),
        ],
        "education": [("", "", FALLBACK)],
        "skills": ["Livestock & trade"],
    },
]

PAGE = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{name} · ProsperGenics team</title>
<meta name="description" content="{name}, {role} at ProsperGenics." />
<meta name="robots" content="noindex" />
<link rel="icon" href="../../assets/logo.png" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700;800;900&family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<style>
  :root {{
    /* same palette + tokens as the homepage (../../index.html) */
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
  @media (max-width:560px) {{ .brand img {{ height:56px; }} .nav-links {{ gap:20px; }} }}

  /* hero: slanted portrait left (same cut as the homepage cards), intro right */
  .p-hero {{ position:relative; overflow:hidden; padding:clamp(36px,6vw,72px) 0 clamp(26px,4vw,48px); }}
  .p-hero::before {{ content:""; position:absolute; width:620px; height:620px; right:-240px; top:-80px; border-radius:50%; background:radial-gradient(circle,var(--green-100),transparent 65%); opacity:.7; pointer-events:none; }}
  .p-hero .wrap {{ position:relative; z-index:2; display:grid; grid-template-columns:300px minmax(0,1fr); gap:clamp(28px,5vw,64px); align-items:center; }}
  .p-portrait {{ position:relative; width:300px; height:330px; overflow:hidden; clip-path:polygon(26px 0,100% 0,calc(100% - 26px) 100%,0 100%); background:linear-gradient(165deg,var(--green-100),var(--paper-2) 70%); }}
  .p-portrait img {{ width:100%; height:100%; object-fit:cover; }}
  .p-portrait.p-initial {{ display:grid; place-items:center; font-family:'Sora',sans-serif; font-weight:800; font-size:110px; color:#fff; background:linear-gradient(150deg,var(--green-600),var(--green-300)); }}
  .p-eyebrow {{ display:inline-flex; align-items:center; gap:10px; font-family:'Sora',sans-serif; font-weight:600; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:var(--green-700); padding:8px 14px 8px 10px; border-radius:999px; background:var(--green-100); border:1px solid rgba(34,166,90,.2); margin-bottom:20px; }}
  .p-eyebrow .dot {{ width:7px; height:7px; border-radius:50%; background:var(--green-500); box-shadow:0 0 0 4px rgba(52,199,89,.22); }}
  h1 {{ font-weight:800; font-size:clamp(36px,5.4vw,64px); line-height:1; letter-spacing:-.03em; }}
  .p-role {{ margin-top:10px; font-family:'Sora',sans-serif; font-weight:600; font-size:clamp(15px,1.8vw,19px); color:var(--green-700); }}
  .p-quote {{ margin-top:22px; max-width:46ch; font-family:'Sora',sans-serif; font-weight:500; font-size:clamp(17px,2vw,22px); line-height:1.45; color:var(--green-900); border-left:3px solid var(--green-500); padding-left:16px; }}
  @media (max-width:760px) {{
    .p-hero .wrap {{ grid-template-columns:1fr; }}
    .p-portrait {{ width:232px; height:252px; clip-path:polygon(20px 0,100% 0,calc(100% - 20px) 100%,0 100%); }}
    .p-portrait.p-initial {{ font-size:84px; }}
  }}

  /* CV sections */
  .cv {{ padding:clamp(20px,3vw,36px) 0 clamp(64px,8vw,104px); }}
  .cv-block {{ margin-top:clamp(34px,4.5vw,56px); }}
  .cv-block h2 {{ font-weight:800; font-size:clamp(22px,2.8vw,32px); letter-spacing:-.02em; color:var(--ink); margin-bottom:18px; }}
  .cv-block h2 .accent {{ color:var(--green-600); }}
  .prose p {{ font-size:clamp(16px,1.5vw,18.5px); line-height:1.68; color:var(--body); max-width:62ch; }}
  .cv-list {{ list-style:none; display:grid; gap:14px; }}
  .cv-item {{ position:relative; background:#fff; border:1px solid var(--line); border-radius:18px; padding:18px 22px; max-width:720px; box-shadow:0 14px 34px -26px rgba(11,32,22,.35); }}
  .cv-item h3 {{ font-weight:700; font-size:17px; color:var(--green-900); }}
  .cv-item .org {{ font-size:13.5px; color:var(--muted); margin-top:2px; }}
  .cv-item p {{ font-size:15px; line-height:1.6; color:var(--body); margin-top:8px; }}
  .chips {{ display:flex; flex-wrap:wrap; gap:10px; max-width:720px; }}
  .chip {{ font-family:'Sora',sans-serif; font-weight:600; font-size:13.5px; color:var(--green-900); background:var(--green-100); border:1px solid rgba(34,166,90,.25); border-radius:999px; padding:8px 16px; }}
  .back {{ display:inline-flex; align-items:center; gap:8px; margin-top:clamp(40px,5vw,56px); font-family:'Sora',sans-serif; font-weight:600; font-size:15px; color:var(--green-700); text-decoration:none; }}
  .back:hover {{ color:var(--green-600); }}

  footer {{ border-top:1px solid var(--line); background:var(--paper-2); }}
  footer .wrap {{ display:flex; align-items:center; justify-content:space-between; gap:18px; padding-top:26px; padding-bottom:26px; }}
  .f-brand {{ display:flex; align-items:center; gap:12px; font-size:14px; color:var(--muted); }}
  .f-brand img {{ height:40px; }}
  footer a {{ font-size:14px; color:var(--green-900); }}
</style>
</head>
<body>

  <header class="nav">
    <a class="brand" href="../../" aria-label="ProsperGenics · home"><img src="../../assets/logo.png" alt="ProsperGenics" /></a>
    <nav class="nav-links" aria-label="Main">
      <a href="../../">Home</a>
      <a href="../../about/">About</a>
    </nav>
  </header>

  <main>
    <section class="p-hero">
      <div class="wrap">
        <div class="p-portrait{initial_class}">{portrait}</div>
        <div>
          <p class="p-eyebrow"><span class="dot"></span>ProsperGenics team</p>
          <h1>{name}</h1>
          <p class="p-role">{role}</p>
          <blockquote class="p-quote">"{quote}"</blockquote>
        </div>
      </div>
    </section>

    <section class="cv">
      <div class="wrap">
        <div class="cv-block">
          <h2>Personal <span class="accent">profile</span></h2>
          <div class="prose"><p>{profile}</p></div>
        </div>
        <div class="cv-block">
          <h2>Work <span class="accent">history</span></h2>
          <ul class="cv-list">
{work_items}
          </ul>
        </div>
        <div class="cv-block">
          <h2>Education &amp; <span class="accent">training</span></h2>
          <ul class="cv-list">
{edu_items}
          </ul>
        </div>
        <div class="cv-block">
          <h2>Skills &amp; <span class="accent">focus</span></h2>
          <div class="chips">
{chips}
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


def item(title, org, desc):
    h = f"              <li class=\"cv-item\">\n"
    if title:
        h += f"                <h3>{title}</h3>\n"
    if org:
        h += f"                <div class=\"org\">{org}</div>\n"
    if desc:
        h += f"                <p>{desc}</p>\n"
    h += "              </li>"
    return h


def build():
    for m in PROFILES:
        if m.get("img"):
            portrait = f"<img src=\"../../assets/{m['img']}\" alt=\"{m['name']}\" style=\"object-position: {m['pos']}\" />"
            initial_class = ""
        else:
            portrait = m["initial"]
            initial_class = " p-initial"
        html = PAGE.format(
            name=m["name"], role=m["role"], quote=m["quote"], profile=m["profile"],
            portrait=portrait, initial_class=initial_class,
            work_items="\n".join(item(*w) for w in m["work"]),
            edu_items="\n".join(item(*e) for e in m["education"]),
            chips="\n".join(f"            <span class=\"chip\">{s}</span>" for s in m["skills"]),
        )
        out_dir = os.path.join(HERE, m["slug"])
        os.makedirs(out_dir, exist_ok=True)
        with open(os.path.join(out_dir, "index.html"), "w", encoding="utf-8") as f:
            f.write(html)
    print(f"built {len(PROFILES)} profile pages in {HERE}")


if __name__ == "__main__":
    build()
