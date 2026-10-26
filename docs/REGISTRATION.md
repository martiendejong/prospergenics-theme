# Member registration (/register/)

Card #6338. A visitor gives a name, an email address and a password; we create the member and send
them to the onboarding wizard (`/profile/welcome/`, card #6340). The confirmation email (card #6339),
the wizard state and the profile slug come from WordPress' own `user_register` hook
(`inc/profiles/verify.php`, `inc/profiles/data.php`), so there is one path for every new account.

Code: `inc/profiles/register.php` (rules, IAM call, member creation), `pg-register.php` (the page),
`inc/profiles/routes.php` (URL and template). Tests: `tests/test-platform-register.php` (87 checks, a
fake WordPress) and section 10 of `tests/e2e/role-matrix.js` (a real WordPress).

## Switching it on

Registration is **off** until an administrator ticks Settings, General, Membership, **Anyone can
register**. Off, `/register/` says registration is closed and nobody can be created; the header's
Join link then opens the contact form as before. On, Join opens `/register/`.

WordPress' own `wp-login.php?action=register` is redirected to `/register/` (and `register_url` points
there), because core's form would create accounts without the checks below and without IAM.

## What is checked

| Check | Rule |
|---|---|
| Name | required, markup stripped, at most 80 characters |
| Email | valid, at most 100 characters, not used by an account yet (any letter case) |
| Password | 10 to 128 characters; not one of the most common passwords, not one repeated character or a run like `1234567890`, not built from the name or email |
| Spam | nonce, hidden honeypot field, signed form time (at least 2 seconds, at most a day) |
| Rate limit | 30 attempts per hour per IP (failed attempts count, so the form is no email-guessing tool; high enough for a class registering from one shared connection) |

Passwords are used as typed (no text sanitiser), never logged, never refilled into the form.

The member gets the role **subscriber**: that is the platform's plain "member" (the role matrix in
`tests/e2e/` treats it that way). Filter `prospergenics_register_role` can change it, but never to a
role that can edit, upload, publish or manage anything.

## IAM (Jengo IAM) and WordPress

Order: validate, then **IAM**, then WordPress. When IAM refuses or is down nothing is created in
WordPress, so there are no half-registered members and the visitor can simply try again.

IAM provisioning is configured in `wp-config.php` (never in the repository):

```php
define( 'PROSPERGENICS_IAM_PROVISION_URL',   'https://<iam host>/<provisioning endpoint>' );
define( 'PROSPERGENICS_IAM_PROVISION_TOKEN', '<service credential>' );
```

Without both constants IAM provisioning is off: the member is created in WordPress only and flagged
`_pg_register_iam = pending` (find them with
`wp user list --meta_key=_pg_register_iam --meta_value=pending`). With them, a successful answer stores
IAM's subject in `jengo_iam_sub` (the key the `jengo-iam-sso` plugin matches on first) and the flag is
`created`.

### The contract the WordPress side expects

`POST <PROSPERGENICS_IAM_PROVISION_URL>`, `Authorization: Bearer <token>`, `Content-Type: application/json`,
over https (plain http only for localhost), no redirects followed:

```json
{ "email": "amina@example.com", "password": "as typed", "firstName": "Amina", "lastName": "Wanjiku", "source": "prospergenics.com" }
```

| IAM answers | Meaning here |
|---|---|
| `200` / `201` with `{ "subject": "<id>" }` (also read: `sub`, `userId`, `id`) | account created; the id is linked to the WordPress member |
| `200` / `201` without an id | treated as a failure: an account we cannot link is not "created" |
| `409` | the email already has an IAM account: the form says so on the email field |
| `400` / `422` | details rejected: "check your name, email address and password" |
| anything else, a timeout, a transport error | "try again in a few minutes"; the status code (never the body or the password) goes to the PHP error log |

**This endpoint does not exist in IAM yet.** Today IAM has public registration switched off on purpose
(`Registration:PublicRegistrationEnabled = false`: accounts come from invitations or an admin), and the
site-registration pieces that exist are the OIDC sign-in plugin (`wordpress-plugin/jengo-iam-sso`,
iam-system PR #207) and the app-role catalogue per WordPress site. The provisioning endpoint has to be
built on the IAM side as a service-account call (same pattern as `invitations:send`, iam-system task
1496): create the user, give them the site's `{ClientId}:subscriber` app role so the SSO plugin admits
them, return the subject. Until then IAM stays off here and members are flagged `pending`.

`prospergenics_register_iam_provisioner` (filter) replaces the whole call, for another transport or a test.

### Known edge

If IAM answers "created" and WordPress then fails to insert the user (very unlikely: the email and login
were checked a moment earlier), the IAM account exists without a WordPress member. A retry meets `409`
and is told to log in or reset the password; an administrator removes the IAM account or lets the member
in through the SSO plugin (auto-provision on). There is no IAM delete in the contract on purpose.

## Hooks

- `prospergenics_register_open` (filter): override the on/off switch.
- `prospergenics_register_role` (filter): the member role (unprivileged roles only).
- `prospergenics_register_iam_provisioner` (filter): replace the IAM call; return `array( 'status' => 'created'|'disabled', 'subject' => '' )` or a `WP_Error`.
- `prospergenics_member_registered` (action, `$user_id`, `$iam`): after the member exists and is logged in.
