<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://bueroblanko.de
 * @since             0.0.0
 * @package           Code_Sync
 *
 * @wordpress-plugin
 * Plugin Name:       Buero Blanko Basic
 * Plugin URI:        https://bueroblanko.de
 * Description:       to sync all code snippets across client sites
 * Version:           0.0.3
 * Author:            Büro Blanko Medien GmbH
 * Author URI:        https://bueroblanko.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       code-sync-plugin
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CODE_SYNC_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-code-sync-activator.php
 */
function activate_code_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-activator.php';
	Code_Sync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-code-sync-deactivator.php
 */
function deactivate_code_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-deactivator.php';
	Code_Sync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_code_sync' );
register_deactivation_hook( __FILE__, 'deactivate_code_sync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-code-sync.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_code_sync() {

	$plugin = new Code_Sync();
	$plugin->run();

}
run_code_sync();

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/bueroblanko/Buero-Blanko-Basic',
	__FILE__,
	'code-sync-plugin'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('your-token-here');
