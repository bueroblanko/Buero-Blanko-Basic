<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/admin/partials
 */
?>

<div class="wrap">
    <h1>BB Basic</h1>
    <div class="plugin-version-info">
        <div class="version-card">
            <h3>PHP Version</h3>
            <p class="version-number"><?php echo PHP_VERSION; ?></p>
        </div>
        <div class="version-card">
            <h3>WordPress Version</h3>
            <p class="version-number"><?php echo esc_html( get_bloginfo('version')); ?></p>
        </div>
        <div class="version-card">
            <h3>Divi Theme</h3>
            <p class="version-number"><?php echo esc_html($theme_version); ?></p>
        </div>
        <div class="version-card">
            <h3>BB Basic</h3>
            <?php $code_sync_header = get_file_data( CODE_SYNC_PLUGIN_FILE, array( 'Version' => 'Version' ) ); ?>
            <p class="version-number"><?php echo esc_html( $code_sync_header['Version'] ); ?></p>
            <p>Update-Kanal: <?php echo 'main' === CODE_SYNC_UPDATE_BRANCH ? 'Test (main)' : 'Live (live)'; ?></p>
        </div>
    </div>
   
