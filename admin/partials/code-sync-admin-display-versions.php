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
    <h1>Code Sync Settings</h1>
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
    </div>
   
