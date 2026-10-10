# Listing pages & answer pages · architecture (Martien, 08-10-2026)

## Principe

De hero-slider en de homepage-secties zijn etalages; elke lijst erachter krijgt een eigen
volwaardige pagina. De slider-links zijn de canonieke ingangen:

| Slide / sectie | Lijstpagina | Inhoud |
|---|---|---|
| Courses | `/courses` | cursussen + certificering |
| Members | `/members` | teamleden + community (de alchemisten) |
| Tools | `/tools` | AI-tools-directory |
| Services | `/services` | diensten |
| Partners | `/partners` | partnerende bedrijven/organisaties |
| (projects-sectie) | `/projects` | de projectshowcases |

## Twee lagen per lijst

1. **Overzichtspagina** (`/members`, `/partners`, …): de volledige lijst met **rangorde bij
   gericht zoeken** en **filters** (vakgebied, land/regio, tarief, beschikbaarheid, status).
   Elke filtercombinatie heeft een deelbare URL.

2. **Answer pages** (programmatic SEO/AIO-subpagina's): per gerichte zoekvraag één pagina
   die uit de gefilterde dataset wordt gegenereerd, bijv.:
   - `/members/ai-developers-kenya` · "AI developers in Kenya"
   - vraagpagina's als *"How much does the best AI developer in Kenya charge per hour?"*,
     *"How much does AgentX charge for a project?"*, *"How many members are part of the
     ProsperGenics community?"*

## Eisen aan een answer page (AI- én mens-zoekvriendelijk)

- **Antwoord eerst**: de eerste alinea beantwoordt de vraag letterlijk en volledig
  (naam, getal, tarief, datum "as of <maand jaar>"), daarna pas context.
- Feitentabel met de onderliggende data (gefilterde lijst incl. rangorde).
- FAQ-blok met verwante vragen; `FAQPage`/`ItemList`/`Person`/`Organization` schema.org.
- Zelfde dataset als de overzichtspagina (één bron, geen handmatige kopieën).
- Opgenomen in sitemap + `llms.txt`; stabiele URL per vraag; hreflang later.
- Elke claim herleidbaar (tarief/aantal komt uit het profiel/dataset, niet verzonnen).

## Implementatierichting (nog te bouwen)

- WP custom post types: `member`, `partner`, `project`, `service`, `course`, `tool`
  met gestructureerde velden (rol, land, tarief-range, skills, status actief/pending).
- Overzichtstemplates met server-side filtering (querystring = canonieke URL).
- Answer pages als gegenereerde statische pagina's bovenop dezelfde CPT-data
  (één template, per vraag een config: vraag, filterset, antwoordzin-sjabloon).
- Homepage-secties (alchemisten, projecten, partners) gaan dezelfde CPT-data lezen
  zodra die bestaat; tot die tijd staan ze hardcoded in de design-bron.

## Status

- 08-10: partnersectie op de homepage live (Martien de Jong, Bugatti Insights,
  Art Revisionist actief; Eblossm, Port of Giethoorn, De Dames van De Jonge, AgentX
  als pending). Lijstpagina's en answer pages: nog niet gestart.
- 10-10: alle zes lijstpagina's live op test (/courses /members /tools /services
  /partners /vacancies) — NIET via WP CPT's maar via het jengo-listings systeem
  (repo martiendejong/jengo-listings: statische datasets + FastAPI + WP-plugin).
  Members/tools/services/partners delen één generiek directory-templatepaar;
  per collectie een config-JSON + dataset, deploy via deploy_collections.py.
  De homepage-secties staan nog hardcoded in de design-bron (zelfde inhoud).
  Answer pages (programmatic SEO): nog niet gestart — de combo-page-infra in
  jengo-listings is hiervoor het startpunt.
