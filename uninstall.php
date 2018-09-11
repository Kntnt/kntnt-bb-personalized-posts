<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || die;

require_once __DIR__ . '/kntnt-bb-personalized-posts.php';

// Delete cached data (stored as transients).
\Kntnt\BB_Personalized_Posts\Plugin::instance('Cache')->purge();

// Delete options.
delete_option( 'kntnt-bb-personalized-posts' );
