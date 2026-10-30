<?php
/**
 * Search track (card #6393): pure helpers, no database access, so the rules are tested
 * standalone (tests/test-platform-search.php).
 *
 * Query normalisation and terms, the default "describe your problem" keyword extraction, word
 * matching, the evidence weights and ranking, the "why this person matches" explanation, and the
 * SEO decisions (canonical, robots, ItemList). engine.php gathers the evidence; these judge it.
 */

defined( 'ABSPATH' ) || exit;

/** Longest query kept (characters); longer input is cut, and such a page is never indexed. */
const PROSPERGENICS_SEARCH_MAX_QUERY = 100;
/** At most this many terms are matched; the rest of a long query is ignored. */
const PROSPERGENICS_SEARCH_MAX_TERMS = 5;
/** At most this many people are scored per search (the brief's candidate budget). */
const PROSPERGENICS_SEARCH_MAX_CANDIDATES = 200;
/** Results per page on a tab, and per group on the "All" tab. */
const PROSPERGENICS_SEARCH_PER_PAGE   = 20;
const PROSPERGENICS_SEARCH_PER_GROUP  = 5;

/** The result types, in tab order (people first: this is an expertise search). */
function prospergenics_search_types() {
    return array(
        'people'   => 'People',
        'projects' => 'Projects',
        'courses'  => 'Courses',
        'articles' => 'Articles',
    );
}

/** Project kinds, as stored in _pg_project_kind by the projects track (#6388). */
function prospergenics_search_project_kinds() {
    return array(
        'project'  => 'Project',
        'product'  => 'Product',
        'service'  => 'Service',
        'employer' => 'Employer',
    );
}

/**
 * The query as it is matched and as it appears in the canonical URL: tags and control characters
 * removed, whitespace collapsed, lower case, at most PROSPERGENICS_SEARCH_MAX_QUERY characters.
 */
function prospergenics_search_normalize_query( $raw ) {
    $q = is_string( $raw ) ? $raw : '';
    $q = strip_tags( $q );
    $q = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $q );
    $q = trim( preg_replace( '/\s+/u', ' ', (string) $q ) );
    $q = function_exists( 'mb_strtolower' ) ? mb_strtolower( $q, 'UTF-8' ) : strtolower( $q );
    if ( function_exists( 'mb_substr' ) ? mb_strlen( $q, 'UTF-8' ) > PROSPERGENICS_SEARCH_MAX_QUERY : strlen( $q ) > PROSPERGENICS_SEARCH_MAX_QUERY ) {
        $q = trim( function_exists( 'mb_substr' ) ? mb_substr( $q, 0, PROSPERGENICS_SEARCH_MAX_QUERY, 'UTF-8' ) : substr( $q, 0, PROSPERGENICS_SEARCH_MAX_QUERY ) );
    }
    return $q;
}

/** Words that never make a match on their own (English, plus a few Swahili and Dutch ones). */
function prospergenics_search_stopwords() {
    return array(
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'can', 'do', 'for', 'from', 'get', 'has',
        'have', 'help', 'how', 'i', 'in', 'is', 'it', 'its', 'me', 'my', 'need', 'of', 'on', 'or',
        'our', 'so', 'some', 'someone', 'that', 'the', 'their', 'there', 'this', 'to', 'too', 'want',
        'was', 'we', 'what', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'would', 'you',
        'your', 'about', 'into', 'more', 'most', 'not', 'but', 'all', 'any', 'just', 'like', 'find',
        'looking', 'person', 'people', 'expert', 'experts', 'good', 'best', 'very',
        // Swahili and Dutch fillers members type
        'na', 'ya', 'wa', 'kwa', 'ni', 'de', 'het', 'een', 'en', 'van', 'voor', 'met',
    );
}

/**
 * A light stem, so "pricing", "priced" and "prices" find each other: common English endings are
 * dropped while at least four letters remain.
 */
function prospergenics_search_stem( $term ) {
    $term = (string) $term;
    foreach ( array( 'ings', 'ing', 'ers', 'ies', 'ed', 'es', 'er', 's' ) as $end ) {
        $len = strlen( $end );
        if ( strlen( $term ) - $len >= 4 && substr( $term, -$len ) === $end ) {
            return 'ies' === $end ? substr( $term, 0, -3 ) . 'y' : substr( $term, 0, -$len );
        }
    }
    return $term;
}

/**
 * The terms of a normalised query: words of two or more characters (letters, digits, + and #, so
 * "c++" and "c#" survive), stopwords dropped, duplicates removed, at most
 * PROSPERGENICS_SEARCH_MAX_TERMS. A query of only stopwords keeps its first real word.
 *
 * @return string[]
 */
function prospergenics_search_terms( $q ) {
    preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}+#\'’-]*/u', (string) $q, $m );
    $stop  = array_flip( prospergenics_search_stopwords() );
    $words = array();
    foreach ( $m[0] as $w ) {
        $w = trim( str_replace( '’', "'", $w ), "-'" );
        if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $w, 'UTF-8' ) : strlen( $w ) ) < 2 || isset( $stop[ $w ] ) ) {
            continue;
        }
        $words[ $w ] = true;
    }
    return array_slice( array_keys( $words ), 0, PROSPERGENICS_SEARCH_MAX_TERMS );
}

/**
 * The default AGI intake (#6393 seam 'prospergenics_search_intake_terms'): plain keyword
 * extraction from a "describe your problem" text. The most frequent non-stopwords, ties in the
 * order they appear, at most $max.
 *
 * @return string[]
 */
function prospergenics_search_intake_keywords( $text, $max = PROSPERGENICS_SEARCH_MAX_TERMS ) {
    $text = prospergenics_search_normalize_query( function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, 600, 'UTF-8' ) : substr( (string) $text, 0, 600 ) );
    preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}+#-]*/u', $text, $m );
    $stop  = array_flip( prospergenics_search_stopwords() );
    $count = array();
    $first = array();
    foreach ( $m[0] as $i => $w ) {
        if ( strlen( $w ) < 3 || isset( $stop[ $w ] ) || ctype_digit( $w ) ) {
            continue;
        }
        $count[ $w ] = isset( $count[ $w ] ) ? $count[ $w ] + 1 : 1;
        if ( ! isset( $first[ $w ] ) ) {
            $first[ $w ] = $i;
        }
    }
    $words = array_keys( $count );
    usort( $words, function ( $a, $b ) use ( $count, $first ) {
        return $count[ $b ] !== $count[ $a ] ? $count[ $b ] - $count[ $a ] : $first[ $a ] - $first[ $b ];
    } );
    return array_slice( $words, 0, max( 1, (int) $max ) );
}

/**
 * Which of the terms a text mentions, matched at the start of a word on the stem: "pric" finds
 * "Pricing" and "price list" but "ai" does not find "maintain".
 *
 * @param string   $text
 * @param string[] $terms
 * @return string[] the terms found
 */
function prospergenics_search_text_matches( $text, array $terms ) {
    $text  = (string) $text;
    $found = array();
    if ( '' === $text ) {
        return $found;
    }
    foreach ( $terms as $term ) {
        $stem = prospergenics_search_stem( $term );
        if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $stem, '/' ) . '/iu', $text ) ) {
            $found[] = $term;
        }
    }
    return $found;
}

/**
 * Evidence kinds with their weight (strength of evidence) and whether they count as verified.
 * Confirmed contributions beat claimed ones; earned certificates, taught courses and approved
 * scrutiny evidence are checked by someone other than the member; a skill or a headline is the
 * member's own word. Filter 'prospergenics_search_weights' to tune.
 *
 * @return array<string,array{weight:float,verified:bool}>
 */
function prospergenics_search_evidence_kinds() {
    return array(
        'project_confirmed' => array( 'weight' => 10, 'verified' => true ),
        'scrutiny'          => array( 'weight' => 8, 'verified' => true ),
        'certificate'       => array( 'weight' => 6, 'verified' => true ),
        'course'            => array( 'weight' => 6, 'verified' => true ),
        'skill'             => array( 'weight' => 4, 'verified' => false ),
        'project_claimed'   => array( 'weight' => 3, 'verified' => false ),
        'article'           => array( 'weight' => 3, 'verified' => false ),
        'experience'        => array( 'weight' => 3, 'verified' => false ),
        'education'         => array( 'weight' => 2, 'verified' => false ),
        'headline'          => array( 'weight' => 1, 'verified' => false ),
    );
}

/**
 * Score one person's evidence.
 *
 * Every item adds its kind's weight; a skill gains up to 3 more for endorsements (one each);
 * articles count for at most three. Coverage is how many of the query's terms the evidence
 * covers together: people who match every word rank above people who match one.
 *
 * @param array    $evidence Items: kind, terms (matched), endorsements (skills), verified (optional override).
 * @param string[] $terms    The query's terms.
 * @param array    $kinds    prospergenics_search_evidence_kinds() (filtered).
 * @return array{score:float,coverage:int,verified:int}
 */
function prospergenics_search_score( array $evidence, array $terms, array $kinds = array() ) {
    $kinds    = $kinds ? $kinds : prospergenics_search_evidence_kinds();
    $score    = 0.0;
    $verified = 0;
    $covered  = array();
    $articles = 0;
    foreach ( $evidence as $e ) {
        $kind = isset( $e['kind'] ) ? $e['kind'] : '';
        if ( ! isset( $kinds[ $kind ] ) ) {
            continue;
        }
        if ( 'article' === $kind && ++$articles > 3 ) {
            continue;
        }
        $score += (float) $kinds[ $kind ]['weight'];
        if ( 'skill' === $kind && ! empty( $e['endorsements'] ) ) {
            $score += min( 3, (int) $e['endorsements'] );
        }
        $is_verified = isset( $e['verified'] ) ? (bool) $e['verified'] : $kinds[ $kind ]['verified'];
        if ( $is_verified ) {
            $verified++;
        }
        foreach ( isset( $e['terms'] ) ? (array) $e['terms'] : array() as $t ) {
            $covered[ $t ] = true;
        }
    }
    return array(
        'score'    => $score,
        'coverage' => count( array_intersect_key( $covered, array_flip( $terms ) ) ),
        'verified' => $verified,
    );
}

/** Order people: more of the query covered, then stronger evidence, then more verified items, then name. */
function prospergenics_search_compare_people( array $a, array $b ) {
    foreach ( array( 'coverage', 'score', 'verified' ) as $k ) {
        if ( $a[ $k ] != $b[ $k ] ) { // phpcs:ignore Universal.Operators.StrictComparisons -- int/float mix
            return $a[ $k ] < $b[ $k ] ? 1 : -1;
        }
    }
    return strcasecmp( (string) $a['name'], (string) $b['name'] );
}

/**
 * The parts of a person's explanation, strongest kind first, each with the evidence it links to:
 *
 *   skill Pricing; contributed to First paying customer (confirmed); teaches Pricing your
 *   services; earned the certificate for …; wrote 2 articles
 *
 * @param array $evidence Items: kind, label, url, status (projects), role (projects).
 * @return array<int,array{text:string,links:array<int,array{label:string,url:string}>,suffix:string}>
 */
function prospergenics_search_explanation_parts( array $evidence ) {
    $groups = array();
    foreach ( $evidence as $e ) {
        $groups[ $e['kind'] ][] = $e;
    }
    $link  = function ( $e ) {
        return array( 'label' => (string) $e['label'], 'url' => (string) $e['url'] );
    };
    $parts = array();
    $order = array( 'skill', 'project_confirmed', 'project_claimed', 'scrutiny', 'course', 'certificate', 'article', 'experience', 'education', 'headline' );
    foreach ( $order as $kind ) {
        if ( empty( $groups[ $kind ] ) ) {
            continue;
        }
        $items = $groups[ $kind ];
        switch ( $kind ) {
            case 'skill':
                $parts[] = array( 'text' => count( $items ) > 1 ? 'skills' : 'skill', 'links' => array_map( $link, $items ), 'suffix' => '' );
                break;
            case 'project_confirmed':
            case 'project_claimed':
                foreach ( $items as $e ) {
                    $parts[] = array( 'text' => 'contributed to', 'links' => array( $link( $e ) ), 'suffix' => 'project_confirmed' === $kind ? '(confirmed)' : '(claimed)' );
                }
                break;
            case 'scrutiny':
                foreach ( $items as $e ) {
                    $parts[] = array( 'text' => 'reviewed evidence:', 'links' => array( $link( $e ) ), 'suffix' => '(approved)' );
                }
                break;
            case 'course':
                $parts[] = array( 'text' => 'teaches', 'links' => array_map( $link, $items ), 'suffix' => '' );
                break;
            case 'certificate':
                $parts[] = array( 'text' => 'completed', 'links' => array_map( $link, $items ), 'suffix' => count( $items ) > 1 ? '(certificates)' : '(certificate)' );
                break;
            case 'article':
                $n       = count( $items );
                $parts[] = array( 'text' => 1 === $n ? 'wrote' : sprintf( 'wrote %d articles:', $n ), 'links' => array_map( $link, array_slice( $items, 0, 3 ) ), 'suffix' => $n > 3 ? sprintf( 'and %d more', $n - 3 ) : '' );
                break;
            case 'experience':
                $parts[] = array( 'text' => 'worked as', 'links' => array_map( $link, $items ), 'suffix' => '' );
                break;
            case 'education':
                $parts[] = array( 'text' => 'studied at', 'links' => array_map( $link, $items ), 'suffix' => '' );
                break;
            case 'headline':
                $parts[] = array( 'text' => 'profile:', 'links' => array_map( $link, array_slice( $items, 0, 1 ) ), 'suffix' => '' );
                break;
        }
    }
    return $parts;
}

/**
 * The explanation as HTML: "Matches ‘pricing’: skill <a>Pricing</a>; contributed to <a>…</a>
 * (confirmed); …". Every value is escaped here.
 *
 * @param string $query    The query as the visitor typed it (normalised).
 * @param array  $evidence See prospergenics_search_explanation_parts().
 */
function prospergenics_search_explanation_html( $query, array $evidence ) {
    $out = array();
    foreach ( prospergenics_search_explanation_parts( $evidence ) as $p ) {
        $links = array();
        foreach ( $p['links'] as $l ) {
            $links[] = '' !== $l['url']
                ? '<a href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['label'] ) . '</a>'
                : '<span>' . esc_html( $l['label'] ) . '</span>';
        }
        $last  = array_pop( $links );
        $list  = $links ? implode( ', ', $links ) . ' and ' . $last : $last;
        $out[] = esc_html( $p['text'] ) . ' ' . $list . ( '' !== $p['suffix'] ? ' ' . esc_html( $p['suffix'] ) : '' );
    }
    if ( ! $out ) {
        return '';
    }
    return 'Matches ‘' . esc_html( $query ) . '’: ' . implode( '; ', $out );
}

/**
 * Whether a query is too odd to be indexed: empty, too long, too many words, mostly not words,
 * a URL or an email address, or only digits.
 */
function prospergenics_search_query_is_odd( $q, array $terms ) {
    $q = (string) $q;
    if ( '' === $q || ! $terms ) {
        return true;
    }
    $len = function_exists( 'mb_strlen' ) ? mb_strlen( $q, 'UTF-8' ) : strlen( $q );
    if ( $len > 60 || $len < 2 || count( preg_split( '/\s+/u', $q ) ) > 5 ) {
        return true;
    }
    if ( preg_match( '#https?:|www\.|@|\.(com|net|org|ke|nl)\b#i', $q ) ) {
        return true;
    }
    $letters = preg_match_all( '/\p{L}/u', $q );
    return $letters < 2 || $letters < 0.6 * preg_match_all( '/\S/u', $q );
}

/**
 * Robots for a results page: only a plain, sensible query with results is indexed. Filters,
 * tabs, paging and "describe your problem" texts are noindex (they repeat the query's page),
 * and so is an empty or odd query, or one without results. Links are always followed.
 *
 * @param array $f q, terms, total, filtered (bool), paged (int), problem (bool)
 * @return bool true = may be indexed
 */
function prospergenics_search_indexable( array $f ) {
    $f = array_merge( array( 'q' => '', 'terms' => array(), 'total' => 0, 'filtered' => false, 'paged' => 1, 'problem' => false ), $f );
    return ! $f['filtered'] && ! $f['problem'] && (int) $f['paged'] < 2 && (int) $f['total'] > 0
        && ! prospergenics_search_query_is_odd( $f['q'], (array) $f['terms'] );
}

/**
 * The one canonical URL per query: the search page with only the normalised query (filters, tab
 * and paging left out), or the bare search page when there is no query.
 */
function prospergenics_search_canonical( $base, $q ) {
    $q = prospergenics_search_normalize_query( $q );
    return '' === $q ? $base : $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . 'q=' . rawurlencode( $q );
}

/**
 * schema.org ItemList for the results on the page, in their order.
 *
 * @param array  $items Each: url, name, type (schema type: Person, CreativeWork, Course, Article).
 * @param string $url   The page's canonical URL.
 * @param string $name  The list's name.
 */
function prospergenics_search_itemlist( array $items, $url, $name ) {
    $list = array();
    $pos  = 0;
    foreach ( $items as $it ) {
        if ( empty( $it['url'] ) ) {
            continue;
        }
        $list[] = array(
            '@type'    => 'ListItem',
            'position' => ++$pos,
            'url'      => (string) $it['url'],
            'name'     => (string) $it['name'],
        );
    }
    return array(
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => (string) $name,
        'url'             => (string) $url,
        'numberOfItems'   => count( $list ),
        'itemListElement' => $list,
    );
}

/** The transient key for a search: the cache generation plus everything that changes the result. */
function prospergenics_search_cache_key( $generation, array $args ) {
    ksort( $args );
    return 'pg_search_' . (int) $generation . '_' . md5( prospergenics_search_json( $args ) );
}

/** json_encode that works without WordPress loaded (tests), and is stable for the cache key. */
function prospergenics_search_json( $data ) {
    return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $data ) : (string) json_encode( $data );
}

/**
 * A plain-text snippet of at most $max characters around the first term found (or the start of
 * the text when none is), cut on word boundaries.
 */
function prospergenics_search_snippet( $text, array $terms, $max = 160 ) {
    $text = trim( preg_replace( '/\s+/u', ' ', strip_tags( preg_replace( '#<(/p|br\s*/?|/h[1-6]|/li)>#i', ' ', (string) $text ) ) ) );
    $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
    $len  = mb_strlen( $text, 'UTF-8' );
    if ( $len <= $max ) {
        return $text;
    }
    $at = 0;
    foreach ( $terms as $t ) {
        if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( prospergenics_search_stem( $t ), '/' ) . '/iu', $text, $m, PREG_OFFSET_CAPTURE ) ) {
            $at = mb_strlen( substr( $text, 0, $m[0][1] ), 'UTF-8' );
            break;
        }
    }
    $start = max( 0, min( $at - (int) ( $max / 3 ), $len - $max ) );
    $cut   = mb_substr( $text, $start, $max, 'UTF-8' );
    if ( $start > 0 ) {
        $sp  = mb_strpos( $cut, ' ', 0, 'UTF-8' );
        $cut = '…' . ( false !== $sp ? mb_substr( $cut, $sp + 1, null, 'UTF-8' ) : $cut );
    }
    if ( $start + $max < $len ) {
        $sp  = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
        $cut = rtrim( false !== $sp ? mb_substr( $cut, 0, $sp, 'UTF-8' ) : $cut, ' ,;:.-' ) . '…';
    }
    return $cut;
}

/** One page of a list. */
function prospergenics_search_page( array $items, $page, $per_page = PROSPERGENICS_SEARCH_PER_PAGE ) {
    $page = max( 1, (int) $page );
    return array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
}
