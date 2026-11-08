# SEO ops notes for prospergenics.com

## Bump `dateModified` after a code-level SEO fix (JengoWork task 2366)

**The trap:** AI citation engines weight page recency via the WebPage
`dateModified` JSON-LD schema. Yoast derives that date directly from
WordPress's own `post_modified` column for the page. A fix that ships as
theme/plugin code (a `functions.php` filter, a server/WAF header change,
a meta description generator) never touches the page's post row — so
`post_modified`, and therefore the live schema's `dateModified`, silently
stays stuck on whatever date the page was last edited in wp-admin, even
though the page's real SEO signal just improved. This is not a caching bug
and not a Yoast bug: `modified_gmt` from the REST API matches the stale
JSON-LD exactly, because WordPress is correctly reporting what it thinks is
true.

**The fix — every time a code-level change ships that affects a specific
page's SEO signal without a wp-admin content edit on that page:**

1. Note which page(s) the fix affects (homepage = WP page id 423, `/about/`
   = 352, `/trainings/` = 11 — see `scripts/bump-modified-date.py` for the
   current id list).
2. Run the script to re-save each page's own existing content through the
   REST API — WordPress accepts this as a real edit and advances
   `post_modified`, with zero actual content change:

   ```bash
   WP_APP_USER=martien WP_APP_PASSWORD="<vault project 4, credential 148>" \
       python scripts/bump-modified-date.py 423 352 11
   ```

3. Verify: `GET /wp-json/wp/v2/pages/<id>` shows a fresh `modified_gmt`, and
   the live page's WebPage JSON-LD `dateModified` matches it.

**When to do this:** any task that changes a page's SEO-relevant output
(schema, meta description, headers, redirects, structured data) via code
rather than a wp-admin edit. Check this doc as part of that task's own
"How to test" / done-when list, not as an afterthought.

**Do NOT** use this to artificially inflate `dateModified` on pages with no
real underlying change — it exists to make the schema honest, not to game
freshness signals for pages that haven't actually changed.

**Gotcha:** the site's Cloudflare WAF (tasks 1668/1669) 403s any REST API
call using the default `python-requests` User-Agent header
("Request forbidden by administrative rules"). `curl`'s default UA is not
blocked. `scripts/bump-modified-date.py` already sends a browser-style UA to
work around this — reuse that header if you write another script against
this site's REST API.

## Legacy pages imported from the old site (cards #6488, #6520)

The theme already sends these URLs to their new home with a 301 (`inc/legacy-pages.txt` and
`inc/person-redirects.txt`, on `init`, also while the page is still published) and leaves them out
of `wp-sitemap.xml` (`inc/sitemap.php`). The pages themselves are content in the database, which a
theme deploy cannot change. Unpublishing them is the clean end state: it also takes their old text
(with "Finehas Mpoe" and "Diko Mohamend") out of the public REST API
(`/wp-json/wp/v2/pages`), and the redirects keep working afterwards.

| ID on test | Slug | Title | Redirects to |
|---|---|---|---|
| 181 | our-team | Our Team | /members/ |
| 183 | frank-2 | Frank | /people/frank/ |
| 184 | lessy-2 | Lessy… | /people/lessy/ |
| 185 | diko-2 | Diko | /people/diko/ |
| 186 | martien-2 | Martien de Jong, Founder | /people/martien/ |
| 187 | sandra-2 | Sandra Simitia Mpoe, Team Lead | /people/simitia/ |
| 188 | kennisbank | Kennisbank | /articles/ |
| 197 | about-legacy | About | /about/ |
| 198 | home-legacy | info | / |
| 2 | sample-page | Sample Page | / |
| 173 | knowledge-base | Knowledge Base | /articles/ |
| 177 | our-programs | Our Programs | /courses/ |
| 179 | colibri-wp | Home | / |
| 180 | join-our-community | Contact | /join/ |
| 190 | trainings | Trainings & Tutorials | /courses/ |

On each site (test first, then production), as an administrator:

1. Before touching anything, check the owners' answer on card #6520: Lesiamon for /lessy-2/,
   Simitia (Sandra) for /sandra-2/. Default without an answer: keep nothing, the /people/ profiles
   are the source of truth. Anything to keep goes into `people/build-profiles.py` PROFILES first.
2. wp-admin → *Pages* → search for the title (or open `post.php?post=<ID>&action=edit`), check the
   slug in the URL matches the table (IDs can differ on production).
3. *Quick Edit* → *Status* → **Draft** → *Update*. (Or tick all of them on the list, *Bulk actions*
   → *Edit* → *Status: Draft* → *Update*.) Draft rather than Trash keeps the text for reference.
4. Check: `curl -sI https://test.prospergenics.com/our-team/` gives `301` with the Location from the
   table, for every row; `curl -s https://test.prospergenics.com/wp-sitemap-posts-page-1.xml` lists
   none of the slugs; `curl -s 'https://test.prospergenics.com/wp-json/wp/v2/pages?search=Finehas'`
   returns `[]`.

Not in these cards and left alone: `entrepreneurship-support`, `community-development`,
`ai-and-technology-training`, `claude-code-cursor-coaching(-nl)` and `privacy-policy-2` (the footer
links `/privacy-policy/`, which WordPress forwards to `/privacy-policy-2/`).
