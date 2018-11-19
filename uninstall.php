<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || die;

require_once __DIR__ . '/konzilo-bb-personalizer.php';

// Delete cached data (stored as transients).
\Konzilo\BB_Personalizer\Plugin::instance('Cache')->purge();

// Delete options.
delete_option( 'konzilo-bb-personalizer' );
