<?php
/**
 * Prospergenics AI Experts Directory (task #4891).
 *
 *   /experts/                   directory landing page (all categories, all experts)
 *   /experts/category/{slug}/   experts filtered by category
 *   /experts/{slug}/            individual expert profile
 *   /profile/expert/            self-service: apply/edit your own expert listing
 */

defined( 'ABSPATH' ) || exit;

$_pg_experts_dir = __DIR__ . '/experts/';
require $_pg_experts_dir . 'categories.php';
require $_pg_experts_dir . 'data.php';
require $_pg_experts_dir . 'routes.php';
require $_pg_experts_dir . 'render.php';
require $_pg_experts_dir . 'self-service.php';
unset( $_pg_experts_dir );
