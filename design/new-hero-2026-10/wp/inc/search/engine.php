<?php
/**
 * Search track (card #6393): the search itself, its cache and its seams.
 *
 * What is searched (read-only, and only what a logged-out visitor may see, so one cached result
 * serves everyone and the page can be indexed):
 *   - people: verified, indexable, visible profiles (prospergenics_profiles_is_indexable() and
 *     prospergenics_profiles_visibility_for( $id, 0 )), never hidden or suspended members. Each
 *     piece of evidence comes from a section the member shows to everyone: a section they made
 *     private never contributes to a match or an explanation;
 *   - projects: published pg_project posts (projects track #6388), with their contributors;
 *   - courses: published pg_course posts;
 *   - articles: published pg_article posts, never hidden ones (_pg_articles_hidden) or
 *     password-protected ones.
 *
 * The query plan is the same for every search (no query per result): the projects, courses and
 * articles that mention a term; how many matching profile entries each member has; the members
 * whose headline or about text mentions one; the certificates of the matching courses; then, for
 * the chosen candidates only, their matching entries, their accounts and their skills, with one
 * priming read for the post meta involved. At most PROSPERGENICS_SEARCH_MAX_CANDIDATES people,
 * the strongest raw evidence first, are scored. The result is cached in a transient for
 * PROSPERGENICS_SEARCH_CACHE_TTL seconds, keyed by the query and filters; any change to a
 * profile, project, course, article or certificate bumps a generation number, which retires
 * every cached search at once.
 *
 * Seams (each a filter with a docblock below): 'prospergenics_search_index_hits' (Jengo FTS5,
 * #6378), 'prospergenics_search_listings_results' (Jengo Listings), 'prospergenics_search_evidence_provenance'
 * (Knowledge provenance), 'prospergenics_search_intake_terms' (AGI intake), and
 * 'prospergenics_search_person_evidence' (scrutiny #6389, Work review).
 */

defined( 'ABSPATH' ) || exit;

/** Seconds a computed search stays cached (until something changes, whichever comes first). */
const PROSPERGENICS_SEARCH_CACHE_TTL = 600;
/** Option holding the cache generation. */
const PROSPERGENICS_SEARCH_GENERATION = '_pg_search_generation';
/** Post types a search reads. */
const PROSPERGENICS_SEARCH_ENTRY_TYPES   = array( 'pg_skill', 'pg_experience', 'pg_education' );
const PROSPERGENICS_SEARCH_CONTENT_TYPES = array( 'pg_project', 'pg_course', 'pg_article' );

/* ───────────────────────── arguments ───────────────────────── */

/**
 * Clean request arguments: q, problem, type, skill, kind, verified, page.
 *
 * @param array $in Usually wp_unslash( $_GET ).
 */
function prospergenics_search_args( array $in ) {
    $str = function ( $k, $max ) use ( $in ) {
        return isset( $in[ $k ] ) && is_string( $in[ $k ] ) ? mb_substr( sanitize_text_field( $in[ $k ] ), 0, $max ) : '';
    };
    $type    = sanitize_key( $str( 'type', 20 ) );
    $kind    = sanitize_key( $str( 'kind', 20 ) );
    $problem = isset( $in['problem'] ) && is_string( $in['problem'] ) ? trim( mb_substr( sanitize_textarea_field( $in['problem'] ), 0, 600 ) ) : '';
    $q       = prospergenics_search_normalize_query( $str( 'q', 200 ) );
    $terms   = prospergenics_search_terms( $q );
    if ( '' !== $problem ) {
        /**
         * AGI intake seam (#6393): turn a "describe your problem" text into search terms.
         *
         * Default: plain keyword matching (prospergenics_search_intake_keywords(): the most
         * frequent words that are not stopwords). The AGI intake service will plug in here and
         * return the skills / problem categories it recognises in the text; it must return a
         * list of short lowercase strings and must not store the text. When the visitor also
         * typed a query, its terms come first.
         *
         * @param string[] $terms   Default keywords.
         * @param string   $problem The visitor's text (sanitised, at most 600 characters).
         * @param string[] $q_terms Terms of the typed query, if any.
         */
        $intake = (array) apply_filters( 'prospergenics_search_intake_terms', prospergenics_search_intake_keywords( $problem ), $problem, $terms );
        $intake = array_values( array_filter( array_map( 'prospergenics_search_normalize_query', array_filter( $intake, 'is_string' ) ) ) );
        $terms  = array_slice( array_values( array_unique( array_merge( $terms, $intake ) ) ), 0, PROSPERGENICS_SEARCH_MAX_TERMS );
        if ( '' === $q ) {
            $q = implode( ' ', $terms );
        }
    }
    $skill = trim( $str( 'skill', 60 ) );
    if ( '' === $q && '' !== $skill ) {
        // browsing by skill alone: the skill is the query
        $q     = prospergenics_search_normalize_query( $skill );
        $terms = prospergenics_search_terms( $q );
    }
    return array(
        'q'        => $q,
        'terms'    => $terms,
        'problem'  => $problem,
        'type'     => isset( prospergenics_search_types()[ $type ] ) || 'listings' === $type ? $type : 'all',
        'skill'    => $skill,
        'kind'     => isset( prospergenics_search_project_kinds()[ $kind ] ) ? $kind : '',
        'verified' => ! empty( $in['verified'] ),
        'page'     => isset( $in['pg'] ) ? max( 1, min( 50, absint( $in['pg'] ) ) ) : 1,
    );
}

/* ───────────────────────── cache ───────────────────────── */

function prospergenics_search_generation() {
    return max( 1, (int) get_option( PROSPERGENICS_SEARCH_GENERATION, 1 ) );
}

/** Retire every cached search (once per request is enough). */
function prospergenics_search_flush() {
    static $done = false;
    if ( $done ) {
        return;
    }
    $done = true;
    update_option( PROSPERGENICS_SEARCH_GENERATION, prospergenics_search_generation() + 1, true );
}

// Anything that changes who or what can be found retires the cache.
add_action( 'prospergenics_platform_content_saved', 'prospergenics_search_flush' );
add_action( 'prospergenics_platform_content_deleted', 'prospergenics_search_flush' );
add_action( 'prospergenics_profiles_suspended', 'prospergenics_search_flush' );
add_action( 'prospergenics_courses_certificate_issued', 'prospergenics_search_flush' );
add_action( 'prospergenics_courses_certificate_revoked', 'prospergenics_search_flush' );
add_action( 'profile_update', 'prospergenics_search_flush' );
add_action( 'delete_user', 'prospergenics_search_flush' );
/** Other modules (scrutiny approvals, Work review, an index rebuild) can fire this to retire the cache. */
add_action( 'prospergenics_search_invalidate', 'prospergenics_search_flush' );

function prospergenics_search_flush_for_post( $post_id ) {
    $type = get_post_type( (int) $post_id );
    if ( in_array( $type, array_merge( PROSPERGENICS_SEARCH_ENTRY_TYPES, PROSPERGENICS_SEARCH_CONTENT_TYPES, array( 'pg_certificate', 'pg_scrutiny' ) ), true ) ) {
        prospergenics_search_flush();
    }
}
foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $prospergenics_search_hook ) {
    add_action( $prospergenics_search_hook, 'prospergenics_search_flush_for_post' );
}
unset( $prospergenics_search_hook );

/** Post meta that changes visibility or evidence: hidden articles, contributions, revoked certificates, endorsements. */
function prospergenics_search_flush_for_post_meta( $meta_id, $post_id, $meta_key ) {
    if ( 0 === strpos( (string) $meta_key, '_pg_' ) ) {
        prospergenics_search_flush_for_post( $post_id );
    }
}
/** User meta that changes who can be found: headline, about, privacy, hidden, suspended, verification, slug, photo. */
function prospergenics_search_flush_for_user_meta( $meta_id, $user_id, $meta_key ) {
    $keys = array( 'headline', 'about', 'privacy', 'hidden', 'suspended', 'verify_pending', 'slug', 'photo_id', 'onboarding' );
    foreach ( $keys as $k ) {
        if ( '_pg_profiles_' . $k === $meta_key ) {
            prospergenics_search_flush();
            return;
        }
    }
}
foreach ( array( 'added', 'updated', 'deleted' ) as $prospergenics_search_op ) {
    add_action( $prospergenics_search_op . '_post_meta', 'prospergenics_search_flush_for_post_meta', 10, 3 );
    add_action( $prospergenics_search_op . '_user_meta', 'prospergenics_search_flush_for_user_meta', 10, 3 );
}
unset( $prospergenics_search_op );

/**
 * Run a search (cached). The result does not depend on who is looking.
 *
 * @param array $args prospergenics_search_args() output.
 * @return array people, projects, courses, articles, listings, skills (facet name => count),
 *               candidates, ms, queries, cached
 */
function prospergenics_search( array $args ) {
    $key_args = array_intersect_key( $args, array_flip( array( 'terms', 'skill', 'kind', 'verified' ) ) );
    $key_args['skill'] = mb_strtolower( (string) $key_args['skill'] );
    $key = prospergenics_search_cache_key( prospergenics_search_generation(), $key_args );
    $hit = get_transient( $key );
    if ( is_array( $hit ) && isset( $hit['people'] ) ) {
        $hit['cached'] = true;
        return $hit;
    }
    global $wpdb;
    $t0      = microtime( true );
    $q0      = (int) $wpdb->num_queries;
    $result  = prospergenics_search_compute( $args );
    $result['ms']      = round( ( microtime( true ) - $t0 ) * 1000, 1 );
    $result['queries'] = (int) $wpdb->num_queries - $q0;
    $result['cached']  = false;
    set_transient( $key, $result, PROSPERGENICS_SEARCH_CACHE_TTL );
    return $result;
}

/* ───────────────────────── the default index: SQL LIKE ───────────────────────── */

/** WHERE fragment: any of the columns contains any of the stems (prepared). */
function prospergenics_search_like_sql( array $columns, array $terms ) {
    global $wpdb;
    $or = array();
    foreach ( $terms as $t ) {
        $like = '%' . $wpdb->esc_like( prospergenics_search_stem( $t ) ) . '%';
        foreach ( $columns as $c ) {
            $or[] = $wpdb->prepare( "$c LIKE %s", $like ); // phpcs:ignore WordPress.DB.PreparedSQL -- column names are constants
        }
    }
    return '(' . implode( ' OR ', $or ) . ')';
}

/** "IN (%s, %s …)" for a list of strings, prepared. */
function prospergenics_search_in_sql( array $values ) {
    global $wpdb;
    return $wpdb->prepare( implode( ', ', array_fill( 0, count( $values ), '%s' ) ), $values ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** WHERE fragment for posts (alias p) whose title, content, excerpt or describing meta mention a term. */
function prospergenics_search_match_sql( array $terms ) {
    global $wpdb;
    $meta_in = prospergenics_search_in_sql( array( '_pg_profiles_company', '_pg_profiles_degree', '_pg_profiles_field', '_pg_project_skills', '_pg_project_contributions', '_pg_courses_outcomes' ) );
    return '(' . prospergenics_search_like_sql( array( 'p.post_title', 'p.post_content', 'p.post_excerpt' ), $terms )
        . " OR p.ID IN (SELECT m.post_id FROM {$wpdb->postmeta} m WHERE m.meta_key IN ($meta_in) AND " . prospergenics_search_like_sql( array( 'm.meta_value' ), $terms ) . '))';
}

/**
 * Which posts and members mention the terms, from the WordPress tables. A coarse filter (substring
 * LIKE on the stem); the engine then checks each hit at word starts.
 *
 * @return array{posts:array<int,object>,entry_authors:array<int,int>,users:int[]}
 */
function prospergenics_search_wp_hits( array $terms ) {
    global $wpdb;
    $match = prospergenics_search_match_sql( $terms );
    $posts = array();
    // 1. projects, courses, articles
    $types = array_values( array_filter( PROSPERGENICS_SEARCH_CONTENT_TYPES, 'post_type_exists' ) );
    if ( $types ) {
        $posts = (array) $wpdb->get_results( "SELECT p.* FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN (" . prospergenics_search_in_sql( $types ) . ") AND $match ORDER BY p.ID DESC LIMIT 300" ); // phpcs:ignore WordPress.DB.PreparedSQL -- built from prepared parts
    }
    // 2. members with matching profile entries (skills, experience, education) and how many; the
    // entries themselves are read for the chosen candidates only (prospergenics_search_entries())
    $rows    = (array) $wpdb->get_results( "SELECT p.post_author AS uid, COUNT(*) AS n FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN (" . prospergenics_search_in_sql( PROSPERGENICS_SEARCH_ENTRY_TYPES ) . ") AND $match GROUP BY p.post_author ORDER BY COUNT(*) DESC, p.post_author ASC LIMIT 1000" ); // phpcs:ignore WordPress.DB.PreparedSQL
    $authors = array();
    foreach ( $rows as $r ) {
        $authors[ (int) $r->uid ] = (int) $r->n;
    }
    // 3. members whose headline or about text mentions a term
    $users = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ('_pg_profiles_headline', '_pg_profiles_about') AND " . prospergenics_search_like_sql( array( 'meta_value' ), $terms ) . ' LIMIT 1000' ); // phpcs:ignore WordPress.DB.PreparedSQL
    return array( 'posts' => $posts, 'entry_authors' => $authors, 'users' => array_map( 'intval', (array) $users ) );
}

/**
 * The candidates' profile entries that mention a term, in one read (at most 200 members × 50
 * entries each).
 *
 * @param int[] $user_ids
 * @return object[] full wp_posts rows
 */
function prospergenics_search_entries( array $terms, array $user_ids ) {
    if ( ! $user_ids ) {
        return array();
    }
    global $wpdb;
    return (array) $wpdb->get_results( "SELECT p.* FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN (" . prospergenics_search_in_sql( PROSPERGENICS_SEARCH_ENTRY_TYPES ) . ') AND p.post_author IN (' . implode( ',', array_map( 'intval', $user_ids ) ) . ') AND ' . prospergenics_search_match_sql( $terms ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- built from prepared parts and integers
}

/* ───────────────────────── computing a search ───────────────────────── */

/** Put full post rows and their meta in the object cache (two reads at most, none per post later). */
function prospergenics_search_prime_posts( array $rows ) {
    if ( ! $rows ) {
        return;
    }
    update_post_cache( $rows );
    update_meta_cache( 'post', array_map( 'intval', wp_list_pluck( $rows, 'ID' ) ) );
}

/** Text of a post a term may be found in (title, content, excerpt and the meta that describes it). */
function prospergenics_search_post_text( $p ) {
    $text = $p->post_title . ' ' . $p->post_excerpt . ' ' . wp_strip_all_tags( $p->post_content );
    switch ( $p->post_type ) {
        case 'pg_experience':
            $text .= ' ' . get_post_meta( $p->ID, '_pg_profiles_company', true );
            break;
        case 'pg_education':
            $text .= ' ' . get_post_meta( $p->ID, '_pg_profiles_degree', true ) . ' ' . get_post_meta( $p->ID, '_pg_profiles_field', true );
            break;
        case 'pg_project':
            $text .= ' ' . implode( ' ', prospergenics_search_project_skills( $p->ID ) );
            break;
        case 'pg_course':
            $text .= ' ' . get_post_meta( $p->ID, '_pg_courses_outcomes', true );
            break;
    }
    return $text;
}

/** A project's skills (_pg_project_skills, per the shared contract). */
function prospergenics_search_project_skills( $project_id ) {
    $skills = get_post_meta( (int) $project_id, '_pg_project_skills', true );
    return is_array( $skills ) ? array_values( array_filter( array_map( 'strval', $skills ) ) ) : array();
}

/** A project's contributors as visitors may see them (projects track #6388), or none without it. */
function prospergenics_search_project_contributors( $project_id ) {
    if ( ! function_exists( 'prospergenics_projects_contributors' ) ) {
        return array();
    }
    $out = array();
    foreach ( (array) prospergenics_projects_contributors( (int) $project_id, 0 ) as $c ) {
        if ( is_array( $c ) && ! empty( $c['user_id'] ) ) {
            $out[] = array(
                'user_id'      => (int) $c['user_id'],
                'role'         => isset( $c['role'] ) ? (string) $c['role'] : '',
                'contribution' => isset( $c['contribution'] ) ? (string) $c['contribution'] : '',
                'status'       => isset( $c['status'] ) && in_array( $c['status'], array( 'claimed', 'confirmed', 'disputed' ), true ) ? $c['status'] : 'claimed',
            );
        }
    }
    return $out;
}

function prospergenics_search_project_url( $project_id ) {
    return function_exists( 'prospergenics_projects_url' ) ? (string) prospergenics_projects_url( (int) $project_id ) : (string) get_permalink( (int) $project_id );
}

/**
 * Whether a member may be found, and the sections everyone may see: verified, indexable, not
 * hidden or suspended, and the profile shown to visitors.
 *
 * @return array<string,bool>|null visibility for a logged-out visitor, or null
 */
function prospergenics_search_person_visibility( $user_id ) {
    if ( ! function_exists( 'prospergenics_profiles_is_indexable' ) || ! function_exists( 'prospergenics_profiles_visibility_for' ) ) {
        return null;
    }
    if ( function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $user_id ) ) {
        return null;
    }
    if ( ! prospergenics_profiles_is_indexable( $user_id ) ) {
        return null;
    }
    $vis = prospergenics_profiles_visibility_for( $user_id, 0 );
    return empty( $vis['profile'] ) ? null : $vis;
}

/**
 * Compute a search (uncached). See the file header for the query plan.
 */
function prospergenics_search_compute( array $args ) {
    $terms = $args['terms'];
    $empty = array( 'people' => array(), 'projects' => array(), 'courses' => array(), 'articles' => array(), 'listings' => array(), 'skills' => array(), 'candidates' => 0 );
    if ( ! $terms ) {
        return $empty;
    }

    /**
     * Jengo FTS5 index seam (#6378): which posts and members mention the terms.
     *
     * Return null (the default) to use the WordPress tables (SQL LIKE, prospergenics_search_wp_hits()).
     * The global FTS5 index (#6378-#6380) will return the same shape from its own index:
     * 'posts' => full wp_posts rows (objects) of the published projects, courses and articles
     * that match (e.g. read back with one "SELECT * … WHERE ID IN (…)" for the IDs the index
     * found), 'entry_authors' => member ID => number of matching profile entries, and 'users' =>
     * member IDs whose headline/about matches. The chosen candidates' entries, privacy, evidence,
     * ranking and explanations stay in WordPress, so swapping the index never changes who may be
     * found.
     *
     * @param array|null $hits
     * @param string[]   $terms
     * @param array      $args  The cleaned search arguments.
     */
    $hits = apply_filters( 'prospergenics_search_index_hits', null, $terms, $args );
    if ( ! is_array( $hits ) || ! isset( $hits['posts'], $hits['users'] ) ) {
        $hits = prospergenics_search_wp_hits( $terms );
    }
    $hits += array( 'entry_authors' => array() );
    prospergenics_search_prime_posts( $hits['posts'] );

    // Check each hit at word starts; sort into evidence by member and into result groups.
    $by_user  = array(); // user_id => list of [kind, post, terms]
    $projects = array();
    $courses  = array();
    $articles = array();
    foreach ( $hits['posts'] as $p ) {
        if ( '' !== (string) $p->post_password ) {
            continue;
        }
        $found = prospergenics_search_text_matches( prospergenics_search_post_text( $p ), $terms );
        if ( ! $found ) {
            continue;
        }
        switch ( $p->post_type ) {
            case 'pg_project':
                // hidden by staff, its creator hidden or suspended, or no contribution a visitor
                // may see: the project does not exist for visitors (projects track #6388)
                if ( function_exists( 'prospergenics_projects_can_view' ) && ! prospergenics_projects_can_view( $p, 0 ) ) {
                    break;
                }
                $projects[ (int) $p->ID ] = array( 'post' => $p, 'terms' => $found );
                break;
            case 'pg_course':
                $courses[ (int) $p->ID ] = array( 'post' => $p, 'terms' => $found );
                $by_user[ (int) $p->post_author ][] = array( 'course', $p, $found );
                break;
            case 'pg_article':
                if ( function_exists( 'prospergenics_articles_is_hidden' ) ? prospergenics_articles_is_hidden( $p->ID ) : get_post_meta( $p->ID, '_pg_articles_hidden', true ) ) {
                    break; // hidden by moderation (#6472): gone for everyone
                }
                $articles[ (int) $p->ID ] = array( 'post' => $p, 'terms' => $found );
                $by_user[ (int) $p->post_author ][] = array( 'article', $p, $found );
                break;
        }
    }

    // Contributors of matching projects (the contributions' own text was matched in SQL too). Their
    // accounts are read in one go first: the projects track checks each one's privacy.
    $contributor_ids = array();
    foreach ( array_keys( $projects ) as $pid ) {
        foreach ( (array) get_post_meta( $pid, '_pg_project_contributions', true ) as $c ) {
            if ( is_array( $c ) && ! empty( $c['user_id'] ) ) {
                $contributor_ids[] = (int) $c['user_id'];
            }
        }
    }
    if ( $contributor_ids ) {
        cache_users( array_slice( array_unique( $contributor_ids ), 0, 1000 ) );
    }
    foreach ( $projects as $pid => &$proj ) {
        $proj['contributors'] = prospergenics_search_project_contributors( $pid );
        $proj['kind']         = (string) get_post_meta( $pid, '_pg_project_kind', true );
        foreach ( $proj['contributors'] as $c ) {
            if ( 'disputed' !== $c['status'] ) {
                $by_user[ $c['user_id'] ][] = array( 'project', $proj['post'], $proj['terms'], $c );
            }
        }
    }
    unset( $proj );

    // Certificates earned for matching courses (author = learner, parent = course).
    if ( $courses && post_type_exists( 'pg_certificate' ) ) {
        global $wpdb;
        $ids   = implode( ',', array_map( 'intval', array_keys( $courses ) ) );
        $certs = (array) $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'pg_certificate' AND post_status = 'publish' AND post_parent IN ($ids) LIMIT 1000" ); // phpcs:ignore WordPress.DB.PreparedSQL -- integers
        if ( $certs ) {
            update_post_cache( $certs );
            update_meta_cache( 'post', wp_list_pluck( $certs, 'ID' ) );
        }
        foreach ( $certs as $cert ) {
            if ( ! get_post_meta( $cert->ID, '_pg_courses_revoked_at', true ) ) {
                $by_user[ (int) $cert->post_author ][] = array( 'certificate', $cert, $courses[ (int) $cert->post_parent ]['terms'], (int) $cert->post_parent );
            }
        }
    }
    foreach ( $hits['users'] as $uid ) {
        if ( ! isset( $by_user[ $uid ] ) ) {
            $by_user[ $uid ] = array();
        }
        $by_user[ $uid ][] = array( 'headline', null, array() );
    }
    foreach ( array_keys( $hits['entry_authors'] ) as $uid ) {
        if ( ! isset( $by_user[ (int) $uid ] ) ) {
            $by_user[ (int) $uid ] = array();
        }
    }
    unset( $by_user[0] );

    // The candidate budget: the members with the strongest raw evidence first, at most 200
    // (a matching profile entry counts like a piece of experience until it is read).
    $raw = array();
    foreach ( $by_user as $uid => $list ) {
        $raw[ $uid ] = prospergenics_search_raw_weight( $list ) + 3 * ( isset( $hits['entry_authors'][ $uid ] ) ? (int) $hits['entry_authors'][ $uid ] : 0 );
    }
    uksort( $by_user, function ( $a, $b ) use ( $raw ) {
        return $raw[ $b ] <=> $raw[ $a ] ?: $a - $b;
    } );
    $by_user    = array_slice( $by_user, 0, PROSPERGENICS_SEARCH_MAX_CANDIDATES, true );
    $candidates = count( $by_user );
    cache_users( array_keys( $by_user ) );

    // The candidates' matching profile entries, in one read.
    $entries = prospergenics_search_entries( $terms, array_values( array_intersect( array_keys( $by_user ), array_map( 'intval', array_keys( $hits['entry_authors'] ) ) ) ) );
    prospergenics_search_prime_posts( $entries );
    foreach ( $entries as $p ) {
        $found = prospergenics_search_text_matches( prospergenics_search_post_text( $p ), $terms );
        if ( $found && isset( $by_user[ (int) $p->post_author ] ) ) {
            $by_user[ (int) $p->post_author ][] = array( substr( $p->post_type, 3 ), $p, $found );
        }
    }

    // Who may be found, and which of their sections everyone may see.
    $vis_of = array();
    foreach ( array_keys( $by_user ) as $uid ) {
        $vis = prospergenics_search_person_visibility( $uid );
        if ( $vis ) {
            $vis_of[ $uid ] = $vis;
        }
    }

    // Every visible skill of those members (skill facet and filter), one read.
    $skills_of = prospergenics_search_skills_of( array_keys( array_filter( $vis_of, function ( $v ) { return ! empty( $v['skills'] ); } ) ) );

    // Approved scrutiny evidence about them, their matching entries and projects (#6389), one read.
    $scrutiny = prospergenics_search_scrutiny_evidence( $vis_of, $by_user, $terms );

    $kinds  = (array) apply_filters( 'prospergenics_search_weights', prospergenics_search_evidence_kinds() );
    $people = array();
    $facets = array();
    foreach ( $vis_of as $uid => $vis ) {
        $evidence = prospergenics_search_person_evidence( $uid, $by_user[ $uid ], $vis, $terms, $projects );
        if ( isset( $scrutiny[ $uid ] ) ) {
            $evidence = array_merge( $evidence, $scrutiny[ $uid ] );
        }
        /**
         * More evidence for a person (Django Work review, other sources; approved scrutiny
         * evidence (#6389) is already in, from prospergenics_search_scrutiny_evidence()). Add only items a logged-out
         * visitor may see (approved, public), each: kind ('scrutiny' or another key of
         * prospergenics_search_evidence_kinds()), label, url, terms (the query terms it matches),
         * verified (bool). Called only for members who may be found at all.
         *
         * @param array    $evidence
         * @param int      $user_id
         * @param string[] $terms
         * @param array    $visibility Sections a logged-out visitor may see.
         */
        $evidence = (array) apply_filters( 'prospergenics_search_person_evidence', $evidence, $uid, $terms, $vis );
        $evidence = array_values( array_filter( array_map( 'prospergenics_search_with_provenance', $evidence ) ) );
        if ( ! $evidence ) {
            continue;
        }
        $user  = get_userdata( $uid );
        $s     = prospergenics_search_score( $evidence, $terms, $kinds );
        $mine  = isset( $skills_of[ $uid ] ) ? $skills_of[ $uid ] : array();
        if ( '' !== $args['skill'] && ! in_array( mb_strtolower( $args['skill'] ), array_map( 'mb_strtolower', $mine ), true ) ) {
            continue;
        }
        if ( '' !== $args['kind'] && ! array_filter( $evidence, function ( $e ) use ( $args ) { return isset( $e['project_kind'] ) && $e['project_kind'] === $args['kind']; } ) ) {
            continue;
        }
        if ( $args['verified'] && ! $s['verified'] ) {
            continue;
        }
        foreach ( $mine as $name ) {
            $facets[ $name ] = isset( $facets[ $name ] ) ? $facets[ $name ] + 1 : 1;
        }
        $people[] = array(
            'id'       => $uid,
            'name'     => $user ? $user->display_name : '',
            'url'      => prospergenics_profiles_url( $uid ),
            'headline' => (string) prospergenics_profiles_meta( $uid, 'headline' ),
            'evidence' => $evidence,
        ) + $s;
    }
    usort( $people, 'prospergenics_search_compare_people' );
    arsort( $facets );

    return array(
        'people'     => $people,
        'projects'   => prospergenics_search_project_results( $projects, $terms, $args ),
        'courses'    => '' === $args['kind'] && ! $args['verified'] ? prospergenics_search_post_results( $courses, $terms, $args['skill'] ) : array(),
        'articles'   => '' === $args['kind'] && ! $args['verified'] ? prospergenics_search_post_results( $articles, $terms, $args['skill'] ) : array(),
        /**
         * Jengo Listings seam: companies, AI tools and other directory entries (#6371-#6377) that
         * match. Default none (jengo-listings is not reachable from the theme yet). Return items
         * with title, url, type (e.g. "Company", "Tool") and excerpt; they get their own tab.
         *
         * @param array    $items
         * @param string[] $terms
         * @param array    $args
         */
        'listings'   => array_values( array_filter( (array) apply_filters( 'prospergenics_search_listings_results', array(), $terms, $args ), function ( $i ) { return is_array( $i ) && ! empty( $i['title'] ) && ! empty( $i['url'] ); } ) ),
        'skills'     => array_slice( $facets, 0, 15, true ),
        'candidates' => $candidates,
    );
}

/**
 * Approved scrutiny evidence (card #6389) for the members who may be found, in one read: items of
 * kind "evidence" that the ProsperGenics team approved, about
 *   - the person, when the item's text mentions a query term,
 *   - one of their matching profile entries (a "claim"), when that section is public, or
 *   - a matching project they contributed to (not disputed, portfolio public).
 * Corrections, questions, disputes and reviews (opinions) never add weight.
 *
 * @param array $vis_of  user ID => visibility for a logged-out visitor.
 * @param array $by_user user ID => raw hits [kind, post, terms, extra].
 * @return array<int,array> user ID => evidence items
 */
function prospergenics_search_scrutiny_evidence( array $vis_of, array $by_user, array $terms ) {
    if ( ! $vis_of || ! post_type_exists( 'pg_scrutiny' ) || ! function_exists( 'prospergenics_scrutiny_record' ) ) {
        return array();
    }
    $section = array( 'skill' => 'skills', 'experience' => 'experience', 'education' => 'education' );
    $targets = array(); // key => [ [uid, terms|null (null: the item text must match), url] ]
    foreach ( $vis_of as $uid => $vis ) {
        $profile                       = prospergenics_profiles_url( $uid );
        $targets[ 'person:' . $uid ][] = array( $uid, null, $profile );
        foreach ( $by_user[ $uid ] as $h ) {
            if ( isset( $section[ $h[0] ] ) && ! empty( $vis[ $section[ $h[0] ] ] ) ) {
                $targets[ 'claim:' . $h[1]->ID ][] = array( $uid, $h[2], $profile );
            } elseif ( 'project' === $h[0] && ! empty( $vis['portfolio'] ) ) {
                $targets[ 'project:' . $h[1]->ID ][] = array( $uid, $h[2], prospergenics_search_project_url( $h[1]->ID ) );
            }
        }
    }
    $posts = get_posts( array(
        'post_type'        => 'pg_scrutiny',
        'post_status'      => 'publish',
        'posts_per_page'   => 500,
        'orderby'          => 'ID',
        'order'            => 'DESC',
        'meta_query'       => array(
            array( 'key' => '_pg_scrutiny_target', 'value' => array_keys( $targets ), 'compare' => 'IN' ),
            array( 'key' => '_pg_scrutiny_status', 'value' => 'approved' ),
            array( 'key' => '_pg_scrutiny_kind', 'value' => 'evidence' ),
        ),
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    $out  = array();
    $seen = array();
    foreach ( $posts as $post ) {
        $r = prospergenics_scrutiny_record( $post );
        if ( ! $r || 'approved' !== $r['status'] || 'evidence' !== $r['kind'] || ! isset( $targets[ $r['target'] ] ) ) {
            continue;
        }
        $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $r['body'] ) ) );
        foreach ( $targets[ $r['target'] ] as $t ) {
            list( $uid, $found, $url ) = $t;
            $found = null === $found ? prospergenics_search_text_matches( $text, $terms ) : $found;
            if ( ! $found || isset( $seen[ $uid . ':' . $r['id'] ] ) ) {
                continue;
            }
            $seen[ $uid . ':' . $r['id'] ] = true;
            $out[ $uid ][] = array(
                'kind'  => 'scrutiny',
                'label' => '“' . prospergenics_search_snippet( $text, $terms, 70 ) . '”' . ( '' !== $r['name'] ? ' by ' . $r['name'] : '' ),
                'url'   => preg_replace( '/#.*$/', '', $url ) . '#pgs-item-' . (int) $r['id'],
                'terms' => $found,
            );
        }
    }
    return $out;
}

/**
 * A member's raw evidence weight before any privacy check, to choose which candidates are scored
 * when there are more than PROSPERGENICS_SEARCH_MAX_CANDIDATES.
 *
 * @param array $hits [kind, post, terms, extra] items.
 */
function prospergenics_search_raw_weight( array $hits ) {
    $kinds = prospergenics_search_evidence_kinds();
    $w     = 0;
    foreach ( $hits as $h ) {
        $kind = $h[0];
        if ( 'project' === $kind ) {
            $kind = isset( $h[3]['status'] ) && 'confirmed' === $h[3]['status'] ? 'project_confirmed' : 'project_claimed';
        }
        $w += isset( $kinds[ $kind ] ) ? $kinds[ $kind ]['weight'] : 1;
    }
    return $w;
}

/**
 * Knowledge provenance seam: where a piece of evidence comes from and who vouched for it.
 *
 * Default: the WordPress record itself ('source' => 'prospergenics-wp'). The Knowledge
 * provenance service will attach its own record here (source, verified_by, verified_at, a link to
 * the provenance trail) and may set 'verified' when it has checked the item. Return null to drop
 * an item. Applied to every evidence item before scoring.
 *
 * @param array $evidence kind, label, url, terms, verified, …
 */
function prospergenics_search_with_provenance( $evidence ) {
    if ( ! is_array( $evidence ) || empty( $evidence['kind'] ) || ! isset( $evidence['label'] ) ) {
        return null;
    }
    $evidence += array( 'url' => '', 'terms' => array(), 'provenance' => array( 'source' => 'prospergenics-wp' ) );
    $out = apply_filters( 'prospergenics_search_evidence_provenance', $evidence );
    return is_array( $out ) ? $out : null;
}

/**
 * The visible skills (names) of members, in one read.
 *
 * @param int[] $user_ids Members whose skills section everyone may see.
 * @return array<int,string[]>
 */
function prospergenics_search_skills_of( array $user_ids ) {
    if ( ! $user_ids ) {
        return array();
    }
    global $wpdb;
    $ids  = implode( ',', array_map( 'intval', $user_ids ) );
    $rows = (array) $wpdb->get_results( "SELECT post_author, post_title FROM {$wpdb->posts} WHERE post_type = 'pg_skill' AND post_status = 'publish' AND post_author IN ($ids)" ); // phpcs:ignore WordPress.DB.PreparedSQL -- integers
    $out  = array();
    foreach ( $rows as $r ) {
        $out[ (int) $r->post_author ][] = (string) $r->post_title;
    }
    return $out;
}

/**
 * One member's evidence, only from the sections a logged-out visitor may see.
 *
 * @param array $hits     [kind, post, terms, extra] from prospergenics_search_compute().
 * @param array $vis      Visibility for a logged-out visitor.
 * @param array $projects Matching projects (post, terms, contributors, kind).
 */
function prospergenics_search_person_evidence( $uid, array $hits, array $vis, array $terms, array $projects ) {
    $profile  = prospergenics_profiles_url( $uid );
    $evidence = array();
    $seen     = array();
    // a section the member made private never contributes (projects: the "portfolio" section of #6388)
    $section  = array( 'skill' => 'skills', 'experience' => 'experience', 'education' => 'education', 'certificate' => 'certificates', 'project' => 'portfolio', 'scrutiny' => 'profile' );
    foreach ( $hits as $h ) {
        list( $kind, $p ) = $h;
        $found = $h[2];
        if ( isset( $section[ $kind ] ) && isset( $vis[ $section[ $kind ] ] ) && ! $vis[ $section[ $kind ] ] ) {
            continue;
        }
        switch ( $kind ) {
            case 'skill':
                $evidence[] = array( 'kind' => 'skill', 'label' => $p->post_title, 'url' => $profile . '#pgp-skills', 'terms' => $found, 'endorsements' => count( get_post_meta( $p->ID, '_pg_profiles_endorser', false ) ) );
                break;
            case 'experience':
                $company    = (string) get_post_meta( $p->ID, '_pg_profiles_company', true );
                $evidence[] = array( 'kind' => 'experience', 'label' => $p->post_title . ( '' !== $company ? ' at ' . $company : '' ), 'url' => $profile . '#pgp-experience', 'terms' => $found );
                break;
            case 'education':
                $field      = trim( get_post_meta( $p->ID, '_pg_profiles_degree', true ) . ' ' . get_post_meta( $p->ID, '_pg_profiles_field', true ) );
                $evidence[] = array( 'kind' => 'education', 'label' => $p->post_title . ( '' !== $field ? ' (' . $field . ')' : '' ), 'url' => $profile . '#pgp-education', 'terms' => $found );
                break;
            case 'course':
                $evidence[] = array( 'kind' => 'course', 'label' => $p->post_title, 'url' => (string) get_permalink( $p->ID ), 'terms' => $found );
                break;
            case 'article':
                $evidence[] = array( 'kind' => 'article', 'label' => $p->post_title, 'url' => (string) get_permalink( $p->ID ), 'terms' => $found );
                break;
            case 'certificate':
                $course = $h[3];
                if ( isset( $seen[ 'cert' . $course ] ) ) {
                    break;
                }
                $seen[ 'cert' . $course ] = true;
                $verify = function_exists( 'prospergenics_courses_certificate_data' ) ? prospergenics_courses_certificate_data( get_post( $p->ID ) ) : array();
                $evidence[] = array( 'kind' => 'certificate', 'label' => get_the_title( $course ), 'url' => isset( $verify['verify_url'] ) ? (string) $verify['verify_url'] : (string) get_permalink( $course ), 'terms' => $found );
                break;
            case 'project':
                $c = $h[3];
                if ( isset( $seen[ 'project' . $p->ID ] ) ) {
                    break;
                }
                $seen[ 'project' . $p->ID ] = true;
                $evidence[] = array(
                    'kind'         => 'confirmed' === $c['status'] ? 'project_confirmed' : 'project_claimed',
                    'label'        => $p->post_title,
                    'url'          => prospergenics_search_project_url( $p->ID ),
                    'terms'        => $found,
                    'role'         => $c['role'],
                    'project_kind' => isset( $projects[ $p->ID ] ) ? $projects[ $p->ID ]['kind'] : '',
                );
                break;
            case 'headline':
                foreach ( array( 'headline', 'about' ) as $field ) {
                    $text  = (string) prospergenics_profiles_meta( $uid, $field );
                    $found = prospergenics_search_text_matches( $text, $terms );
                    if ( $found ) {
                        $evidence[] = array( 'kind' => 'headline', 'label' => 'headline' === $field ? $text : prospergenics_search_snippet( $text, $terms, 90 ), 'url' => $profile . ( 'about' === $field ? '#pgp-about-h' : '' ), 'terms' => $found );
                        break;
                    }
                }
                break;
        }
    }
    return $evidence;
}

/** Project results: title, kind, period, snippet, confirmed contributors, why it matches. */
function prospergenics_search_project_results( array $projects, array $terms, array $args ) {
    $out = array();
    foreach ( $projects as $pid => $proj ) {
        $p      = $proj['post'];
        $skills = prospergenics_search_project_skills( $pid );
        if ( '' !== $args['kind'] && $proj['kind'] !== $args['kind'] ) {
            continue;
        }
        if ( '' !== $args['skill'] && ! in_array( mb_strtolower( $args['skill'] ), array_map( 'mb_strtolower', $skills ), true ) ) {
            continue;
        }
        $confirmed = count( array_filter( $proj['contributors'], function ( $c ) { return 'confirmed' === $c['status']; } ) );
        if ( $args['verified'] && ! $confirmed ) {
            continue;
        }
        $why = array();
        if ( prospergenics_search_text_matches( $p->post_title, $terms ) ) {
            $why[] = 'title';
        }
        $matched_skills = array_values( array_filter( $skills, function ( $s ) use ( $terms ) { return (bool) prospergenics_search_text_matches( $s, $terms ); } ) );
        if ( $matched_skills ) {
            $why[] = ( count( $matched_skills ) > 1 ? 'skills ' : 'skill ' ) . implode( ', ', $matched_skills );
        }
        if ( ! $why ) {
            $why[] = 'description';
        }
        $out[] = array(
            'id'        => $pid,
            'title'     => $p->post_title,
            'url'       => prospergenics_search_project_url( $pid ),
            'kind'      => $proj['kind'],
            'period'    => (string) get_post_meta( $pid, '_pg_project_period', true ),
            'snippet'   => prospergenics_search_snippet( '' !== $p->post_excerpt ? $p->post_excerpt : $p->post_content, $terms ),
            'confirmed' => $confirmed,
            'people'    => count( array_filter( $proj['contributors'], function ( $c ) { return 'disputed' !== $c['status']; } ) ),
            'why'       => $why,
            'coverage'  => count( $proj['terms'] ),
        );
    }
    usort( $out, function ( $a, $b ) {
        return array( $b['coverage'], $b['confirmed'], $b['people'] ) <=> array( $a['coverage'], $a['confirmed'], $a['people'] ) ?: strcasecmp( $a['title'], $b['title'] );
    } );
    return $out;
}

/** Course and article results: title, author, date, snippet; title matches first. */
function prospergenics_search_post_results( array $items, array $terms, $skill ) {
    $out = array();
    foreach ( $items as $id => $it ) {
        $p = $it['post'];
        if ( '' !== $skill && ! prospergenics_search_text_matches( prospergenics_search_post_text( $p ), array( mb_strtolower( $skill ) ) ) ) {
            continue;
        }
        $author = get_userdata( (int) $p->post_author );
        $out[]  = array(
            'id'       => $id,
            'title'    => $p->post_title,
            'url'      => (string) get_permalink( $id ),
            'author'   => $author ? $author->display_name : '',
            'date'     => mysql2date( 'j M Y', $p->post_date ),
            'snippet'  => prospergenics_search_snippet( '' !== $p->post_excerpt ? $p->post_excerpt : $p->post_content, $terms ),
            'in_title' => count( prospergenics_search_text_matches( $p->post_title, $terms ) ),
            'coverage' => count( $it['terms'] ),
        );
    }
    usort( $out, function ( $a, $b ) {
        return array( $b['coverage'], $b['in_title'] ) <=> array( $a['coverage'], $a['in_title'] ) ?: $b['id'] - $a['id'];
    } );
    return $out;
}
