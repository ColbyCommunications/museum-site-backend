<?php
if ( 'master' == getenv( 'PLATFORM_BRANCH' ) ) {
    define( 'ALGOLIA_INDEX_NAME_PREFIX', 'wp_museum_redesign_');
} else {
    define( 'ALGOLIA_INDEX_NAME_PREFIX', 'platform_wp_museum_redesign_' );
}