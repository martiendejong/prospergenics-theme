<?php
/**
 * Staff area at /manage/ (card #6457): the everyday tasks of the ProsperGenics team in the site's
 * own design, so nobody has to go into wp-admin for them. wp-admin stays for everything else.
 *
 *   /manage/            overview: new members, verified members, enrolments, completions and
 *                       certificates over the last 7 and 30 days; what is waiting (articles for
 *                       review, unread contact messages, open reports, writer/instructor requests)
 *   /manage/members/    search (name or email), filters, paging, and per member: role (subscriber or
 *                       writer), mark verified, resend the confirmation link, hide or show the
 *                       profile, suspend or unsuspend
 *   /manage/messages/   the contact messages (pg_contact_msg): unread, open, handled, spam; reply by
 *                       email; mark read, unread, handled
 *   /manage/articles/   articles waiting for review (the "Waiting for review" queue of
 *                       /articles/mine/ and its "Publish now" action), plus links to the Reports
 *                       queue, Users → Requests and the instructor dashboard
 *
 * Who gets in (checked on the server: visitors get a 302 to the login page, everyone else without
 * the capability a 403): the capability pg_manage, mapped at runtime (user_has_cap, nothing written
 * to the roles table) to everyone who can moderate comments and edit others' posts, i.e. editors and
 * administrators; the same people as the Reports queue (pg_moderate, inc/profiles/moderation.php).
 * A capability of its own, so a future "community manager" role can get the area without editor
 * rights. The contact inbox also needs the contact-message capabilities and the review queue
 * edit_others_pg_articles, which editors and administrators have.
 *
 * Who may change which member (prospergenics_manage_can_manage_member(), helpers.php):
 *   - administrators: what wp-admin → Users allows them (prospergenics_profiles_can_manage_member():
 *     edit_users + edit_user for that member);
 *   - editors (pg_manage, no edit_users): only members who are not staff, i.e. who cannot edit
 *     others' posts, manage users or open /manage/ themselves. Subscribers, writers, contributors,
 *     authors and instructors, never administrators or other editors;
 *   - nobody acts on their own account here (no marking yourself verified, unhiding or demoting
 *     yourself): that is /profile/settings/ and wp-admin.
 * Role changes: editors only switch a member between subscriber and pg_writer, and only when the
 * member has no other role (an editor cannot demote an author). Administrators (promote_user for
 * that member) can also make a member a contributor, author or editor. Nobody grants or takes away
 * the administrator role here: that stays in wp-admin → Users.
 *
 * Every action is a POST to admin-post.php with its own nonce (per action and member/message) and
 * a capability check for that member/message; the actions reuse the platform's helpers
 * (prospergenics_profiles_mark_verified(), _send_verification(), _set_hidden(), _suspend(),
 * _unsuspend(), and pg_articles_publish of inc/articles/mine.php).
 *
 * Styles: assets/platform/manage.css, only on these pages.
 */

defined( 'ABSPATH' ) || exit;

define( 'PROSPERGENICS_MANAGE_VERSION', '1.0.0' );

require __DIR__ . '/manage/helpers.php';  // pure rules: who may change whom, roles, list queries, message state
require __DIR__ . '/manage/stats.php';    // the overview's COUNT queries
require __DIR__ . '/manage/routes.php';   // /manage/… URLs, access (302 / 403), templates, styles
require __DIR__ . '/manage/actions.php';  // the member and contact-message actions (admin-post.php)
