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
