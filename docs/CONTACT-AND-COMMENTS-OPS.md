# Contact form & comments — what broke, what the code now does, what a human still has to do

_Written 2026-10-18, in response to: "the contact form and comments aren't working properly …
everything gets lost in a blackhole, even the proper contact requests … people effectively
can't reach us by email."_

## What was actually wrong

Diagnosed against the live production site, not from memory:

1. **Contact Form 7 is not installed.** The theme prefers CF7 and falls back to its own
   form when the plugin is absent (`front-page.php`). Production is running the fallback.
   The only plugin on the live front end is `seo-god`.
2. **The fallback form had no persistence.** `inc/contact-form-handler.php` validated the
   input and called `wp_mail()`. That was the entire storage strategy. If mail failed, or
   was filtered as spam at the receiving end, the enquiry was gone — no database row, no
   log, no retry. **That is the blackhole.** It also means the existing backlog of genuine
   enquiries is not recoverable from WordPress; they were never written down.
3. **No spam protection anywhere.** A nonce was the only gate, and a nonce stops no bot —
   it is served in the page like every other field. No honeypot, no timing check, no rate
   limit, no Akismet, no CAPTCHA.
4. **Comments open site-wide with the same lack of protection.** The REST API reports
   exactly **one approved comment** (Martien, 2025). The "thousands of comments" are an
   unmoderated pending/spam queue.
5. **Moderation email went to the same unattended address**, so the queue notification was
   itself part of the blackhole.

## What the code now does (this branch)

| File | Change |
|---|---|
| `inc/contact-messages-store.php` | **new** — private `pg_contact_msg` post type. Every submission is written here *before* any mail is attempted. |
| `inc/spam-protection.php` | **new** — honeypot, signed timing token, link-stuffing check, per-IP rate limit, optional Akismet. Shared by both forms. |
| `inc/contact-messages-admin.php` | **new** — admin list (sender, reply-to link, delivery status, spam reason) + an error notice when a notification fails. |
| `inc/contact-form-handler.php` | rewritten: validate → classify → **store** → mail, recording the delivery outcome. |
| `inc/comment-spam-protection.php` | **new** — same checks on the comment form; comments closed on pages. |
| `front-page.php` | contact form renders the protection fields. |

Two deliberate design rules:

- **Nothing is ever discarded.** Spam is *flagged and stored*, not dropped, so a false
  positive stays recoverable. This is the direct answer to "even the proper contact
  requests get lost".
- **The stored record is the source of truth; email is only a notification.** A broken
  mailer can no longer lose an enquiry, and it now surfaces as a red admin notice instead
  of silence.

Verified: 11/11 test files pass, including `tests/test-contact-spam-and-storage.php`
(32 assertions covering each bot signal, the rate limit, storage-before-mail ordering,
and that the enquiry store is never publicly queryable).

## What still needs a human — this is not finished by deploying

### 1. Somebody has to own the inbox
This is the part no code change can solve, and it was the actual ask.

- Decide the destination address. It should be a **shared mailbox**, not a personal one.
- Point the theme at it: Appearance → Customize → the Prospergenics email setting, or
  filter `prospergenics_contact_notification_address` — no code edit needed.
- Assign a named person and a response SLA.
- **JengoMail is already in your own stack** (`scp-jengo/jengomail`, listed on
  `/tools/` as "shared inboxes, rules and AI assistance"). A shared inbox with rules is
  exactly this problem, and using your own product here is the obvious fit.

### 2. Verify mail actually leaves the server
The live `functions.php` carries a **hardcoded SMTP block that exists only on the server
and has never been in git** (noted repeatedly in `AGENT_PROGRESS.md`). Nobody has
confirmed it still authenticates. After deploying, send one test enquiry and check the
new **Notification** column reads `Sent`. If it reads `FAILED`, the error is shown
inline — fix the SMTP credentials before anything else, because every other improvement
depends on mail working.

### 3. Clear the existing backlog (admin job, not theme code)
Theme code must not bulk-delete comments. With WP-CLI on the server:

```bash
wp comment list --status=spam --format=count
wp comment delete $(wp comment list --status=spam --format=ids) --force

# Review the pending queue BEFORE deleting - real comments may be in there.
wp comment list --status=hold --format=count
```

Then decide the policy for the blog: given one genuine comment in two years, closing
comments on older posts is reasonable —
`wp post list --post_type=post --format=ids | xargs -n1 -I{} wp post update {} --comment_status=closed`
— but that is Martien's call, not mine.

### 4. Consider Akismet
The new checks are dependency-free and catch the bulk of automated spam. If spam still
gets through after this, activating Akismet needs **no code change**: `inc/spam-protection.php`
detects it and folds it in as an extra signal automatically.

## Deploy notes

Not deployed. Both `prospergenics.com` and `test.prospergenics.com` must stay live, and
this repo's live `functions.php` has drifted from git (task 797's meta description, the
SMTP block), so this must be a **surgical FTP patch with the live files backed up first**,
never a full-file overwrite. New files in `inc/` can be uploaded as-is; `functions.php`
and `front-page.php` need the changed blocks inserted into the live copies.

After deploying, the `pg_contact_msg` post type appears automatically — no activation
step, no database migration.
