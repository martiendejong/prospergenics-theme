<?php
/**
 * Baseline security response headers
 *
 * Adds Strict-Transport-Security, Content-Security-Policy, X-Frame-Options
 * and X-Content-Type-Options to every front-end response. Confirmed missing
 * via curl on 2026-09-05 and WebSearch-validated as a live 2026 GEO/AEO
 * signal: automated retrieval/AI-citation crawlers treat their absence as a
 * sign the domain is "structurally unverified", independent of content
 * quality. JengoWork task 1669.
 *
 * Unlike the other 3 sites in this task, prospergenics.com had NO HSTS at
 * all (not just missing CSP/X-Frame-Options/X-Content-Type-Options), so it
 * is added here too.
 *
 * The CSP is deliberately permissive (allows any https: origin, plus
 * 'unsafe-inline'/'unsafe-eval') rather than a per-plugin allowlist, so it
 * doesn't need constant upkeep as plugins/embeds change — it still blocks
 * plugin objects (Flash) and cross-origin framing (clickjacking).
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('send_headers', function () {
	if (is_admin() || headers_sent()) {
		return;
	}

	if (is_ssl()) {
		header('Strict-Transport-Security: max-age=15552000');
	}
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	header(
		"Content-Security-Policy: default-src 'self' https: data: blob:; " .
		"script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
		"style-src 'self' 'unsafe-inline' https:; " .
		"img-src 'self' https: data: blob:; " .
		"font-src 'self' https: data:; " .
		"connect-src 'self' https:; " .
		"frame-ancestors 'self'; " .
		"base-uri 'self'; " .
		"object-src 'none'"
	);
});
