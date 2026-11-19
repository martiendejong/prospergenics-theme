<?php
/**
 * Expert category definitions (task #4891).
 */

defined( 'ABSPATH' ) || exit;

/** All defined expertise categories, ordered for display. */
function prospergenics_expert_categories() {
	return array(
		array( 'slug' => 'ai-agents',    'label' => 'AI Agents & LLM Systems',  'description' => 'Building and deploying AI agents, LLM pipelines, and autonomous systems.' ),
		array( 'slug' => 'software-dev', 'label' => 'Software Development',      'description' => 'Full-stack and back-end development, architecture, and system design.' ),
		array( 'slug' => 'web-design',   'label' => 'Web Design & Front-end',    'description' => 'UI/UX design, front-end development, and accessible interfaces.' ),
		array( 'slug' => 'wordpress',    'label' => 'WordPress & Platforms',     'description' => 'WordPress development, plugin authoring, and platform configuration.' ),
		array( 'slug' => 'marketing',    'label' => 'Marketing & Brand',         'description' => 'Brand strategy, SEO, content marketing, and storytelling.' ),
		array( 'slug' => 'training',     'label' => 'Training & Education',      'description' => 'Teaching, curriculum design, and skill development.' ),
		array( 'slug' => 'business',     'label' => 'Business Development',      'description' => 'Entrepreneurship, partnerships, and business strategy.' ),
		array( 'slug' => 'art',          'label' => 'Art & Curation',            'description' => 'Art curation, provenance research, and collection management.' ),
	);
}

/** One category by slug, or null. */
function prospergenics_expert_category( $slug ) {
	foreach ( prospergenics_expert_categories() as $cat ) {
		if ( $cat['slug'] === $slug ) {
			return $cat;
		}
	}
	return null;
}
