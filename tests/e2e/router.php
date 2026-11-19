<?php
// Router for `php -S` (tests/e2e/run.sh): real files and the static index.html folders
// (people/, experts/, community/) are served as they are, everything else goes to WordPress.
$pg_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$pg_file = __DIR__ . $pg_path;
if ( '/' !== $pg_path && is_file( $pg_file ) ) {
    return false;
}
if ( '/' !== $pg_path && is_dir( $pg_file ) && is_file( rtrim( $pg_file, '/' ) . '/index.html' ) ) {
    header( 'Content-Type: text/html; charset=utf-8' );
    readfile( rtrim( $pg_file, '/' ) . '/index.html' );
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
