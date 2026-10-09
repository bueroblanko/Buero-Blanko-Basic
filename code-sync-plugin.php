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
 * Plugin Name:       Büro Blanko Basic
 * Plugin URI:        https://bueroblanko.de
 * Description:       Grundeinstellungen, Branding und Code-Snippets von Büro Blanko für alle Kundenseiten. Optionaler Developer-Modus lädt Novamira (AGPL-3.0, Ovation S.r.l.) nach.
 * Version:           0.0.17.2
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
define('CODE_SYNC_ALLOWED_MAIL', 'bueroblanko.de');
define( 'CODE_SYNC_PLUGIN_FILE', __FILE__ );

// check if there are some plugins installed if yes define the ADD_META_TAGS variable as false , true otherwise
$plugs = [
	'wpmu-dev-seo/wpmu-dev-seo.php',                      // SmartCrawl (alt)
	'smartcrawl-seo/wpmu-dev-seo.php',                    // SmartCrawl
	'wordpress-seo/wp-seo.php',                           // Yoast
	'all-in-one-seo-pack/all_in_one_seo_pack.php',        // AIOSEO
	'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',    // AIOSEO Pro
	'seo-by-rank-math/rank-math.php',                     // Rank Math
	'wp-seopress/seopress.php',                           // SEOPress
	'autodescription/autodescription.php',                // The SEO Framework
];
if ( ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$exists = false;
foreach ($plugs as $p ) {
	if ( is_plugin_active( $p ) ) {
    	$exists = true;
		break;
	}
}
define( 'CODE_SYNC_ADD_META_TAGS', !$exists );

// Developer-Modus (Novamira). Tut nichts, solange keine Schalter-Datei
// wp-content/bb-developer-modus.php per FTP abgelegt ist.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-developer-modus.php';
Code_Sync_Developer_Modus::start();
// Freigabeweg fuer Aenderungen auf Live-Seiten, nur mit Developer-Modus.
if ( 'clear' !== Code_Sync_Developer_Modus::stufe() ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-freigabe.php';
	Code_Sync_Freigabe::start( Code_Sync_Developer_Modus::stufe() );
}



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

// Die Meta-Tag-Tabelle wird nur beim Aktivieren angelegt. Bei Updates per
// Auto-Update oder ZIP-Austausch laeuft der Aktivierungs-Hook nicht, deshalb
// hier einmalig nachholen.
function code_sync_maybe_create_table() {
	if ( get_option( 'code_sync_db_version' ) === '1' ) {
		return;
	}
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-activator.php';
	Code_Sync_Activator::activate();
	update_option( 'code_sync_db_version', '1' );
}
add_action( 'plugins_loaded', 'code_sync_maybe_create_table' );
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

// Update-Kanal: Alle Seiten folgen dem Branch "live". Testseiten folgen "main",
// wenn in ihrer wp-config.php steht: define( 'BB_BASIC_UPDATE_KANAL', 'test' );
// Eine Testseite kann stattdessen einem Entwicklungs-Branch folgen:
// define( 'BB_BASIC_TEST_ZWEIG', 'name-des-branches' );
// Ablauf siehe NEWUPDATE.md.
$code_sync_zweig = 'live';
if ( defined( 'BB_BASIC_UPDATE_KANAL' ) && 'test' === BB_BASIC_UPDATE_KANAL ) {
	$code_sync_zweig = 'main';
	// Eine Test-ZIP kann eine Datei zweig.txt mitbringen. Sie wird gemerkt, weil das
	// naechste Update von GitHub sie nicht mehr enthaelt.
	$code_sync_zweig_datei = plugin_dir_path( __FILE__ ) . 'zweig.txt';
	if ( file_exists( $code_sync_zweig_datei ) ) {
		update_option( 'code_sync_test_zweig', trim( (string) file_get_contents( $code_sync_zweig_datei ) ) );
		@unlink( $code_sync_zweig_datei );
	}
	// Eine per zweig.txt gemerkte Wahl geht vor die Konstante, damit sich der Branch
	// ohne Eingriff in die wp-config.php umstellen laesst.
	$code_sync_test_zweig = (string) get_option( 'code_sync_test_zweig', '' );
	if ( '' === $code_sync_test_zweig && defined( 'BB_BASIC_TEST_ZWEIG' ) ) {
		$code_sync_test_zweig = (string) BB_BASIC_TEST_ZWEIG;
	}
	if ( preg_match( '#^[A-Za-z0-9._/-]{1,100}$#', $code_sync_test_zweig ) ) {
		$code_sync_zweig = $code_sync_test_zweig;
	}
}
define( 'CODE_SYNC_UPDATE_BRANCH', $code_sync_zweig );
$myUpdateChecker->setBranch( CODE_SYNC_UPDATE_BRANCH );

// Testseiten sehen alle 10 Minuten nach und spielen eine neue Version sofort ein,
// statt auf die WordPress-Updates (bis zu 12 Stunden) zu warten.
if ( 'live' !== CODE_SYNC_UPDATE_BRANCH ) {
	add_filter( 'cron_schedules', function ( $plaene ) {
		$plaene['bb_basic_10min'] = array( 'interval' => 10 * MINUTE_IN_SECONDS, 'display' => 'Alle 10 Minuten (BB Basic Test)' );
		return $plaene;
	} );
	add_action( 'bb_basic_test_update', function () use ( $myUpdateChecker ) {
		$myUpdateChecker->checkForUpdates();
		if ( ! $myUpdateChecker->getUpdate() ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$upgrader->upgrade( plugin_basename( __FILE__ ) );
		// Das Upgrade deaktiviert nicht; zur Sicherheit wieder aktivieren.
		if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			activate_plugin( plugin_basename( __FILE__ ) );
		}
	} );
	if ( ! wp_next_scheduled( 'bb_basic_test_update' ) ) {
		wp_schedule_event( time() + 60, 'bb_basic_10min', 'bb_basic_test_update' );
	}
	// Falls WP-Cron auf dem Server nicht von selbst laeuft: beim Oeffnen des Backends.
	add_action( 'admin_init', function () {
		if ( wp_doing_ajax() || get_transient( 'bb_basic_test_update_lief' ) ) {
			return;
		}
		set_transient( 'bb_basic_test_update_lief', 1, 10 * MINUTE_IN_SECONDS );
		do_action( 'bb_basic_test_update' );
	} );
} elseif ( wp_next_scheduled( 'bb_basic_test_update' ) ) {
	wp_clear_scheduled_hook( 'bb_basic_test_update' );
}

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('your-token-here');

if (is_plugin_active('yw-photoresources/yw-photoresources.php')) {
    deactivate_plugins('yw-photoresources/yw-photoresources.php');
}




function codesync_yw_pr_add_scripts() {
    wp_enqueue_style( 'yw_photoresources', plugins_url('public/css/min/yw-photoresources.min.css', __FILE__ ) );
}

add_action( 'wp_enqueue_scripts', 'codesync_yw_pr_add_scripts' );


/* Add CSS and JS to Backend */
function codesync_yw_pr_add_admin_scripts()
{
    wp_enqueue_style( 'yw-photoresources-admin', plugins_url('admin/css/min/yw-photoresources-admin.min.css', __FILE__ ) );
}
add_action('admin_enqueue_scripts', 'codesync_yw_pr_add_admin_scripts');

/**
 * Adding a custom field to Attachment Edit Fields
 * @param  array $form_fields 
 * @param  WP_POST $post        
 * @return array              
 */
function codesync_yw_add_media_custom_field( $form_fields, $post ) 
{
    $field_value = get_post_meta( $post->ID, 'yw_stock_resource', true );
    $url_value = get_post_meta( $post->ID, 'yw_stock_url', true );

    $form_fields["yw_stock_title"]["tr"] = " 
        <tr> 
        <th scope='row' class='yw_stock_title'>Bildquelle</th>
        </tr>";

    $form_fields['yw_stock_resource'] = array(
        'value' => $field_value ? $field_value : '',
        'label' => __( 'Quelle', 'yw-photoresources' ),
        'input'  => 'text'
    );
    
    $form_fields['yw_stock_url'] = array(
        'value' => $url_value ? $url_value : '',
        'label' => __( 'Quell-URL', 'yw-photoresources' ),
        'input'  => 'url'
    );

    $form_fields["yw_stock_end"]["tr"] = " 
        <tr> 
        <th scope='row' class='yw_stock_end'></th>
        </tr>";

    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'codesync_yw_add_media_custom_field', 10, 2 );


/**
 * Saving the attachment data
 * @param  integer $attachment_id 
 * @return void                
 */
function codesync_yw_save_attachment( $attachment_id ) {
    
    if ( !empty( $_REQUEST['attachments'][ $attachment_id ]['yw_stock_resource'] ) ) 
    {
        $resource = sanitize_text_field($_REQUEST['attachments'][ $attachment_id ]['yw_stock_resource']);
        update_post_meta( $attachment_id, 'yw_stock_resource', $resource );
    }
    else
    {
        delete_post_meta( $attachment_id, 'yw_stock_resource' );
    }
    
    if ( !empty( $_REQUEST['attachments'][ $attachment_id ]['yw_stock_url'] ) ) 
    {
        $url = esc_url($_REQUEST['attachments'][ $attachment_id ]['yw_stock_url']);
        update_post_meta( $attachment_id, 'yw_stock_url', $url );
    }
    else
    {
        delete_post_meta( $attachment_id, 'yw_stock_url' );
    }
}

add_action( 'edit_attachment', 'codesync_yw_save_attachment' );

add_shortcode( 'stock_resources', 'codesync_yw_show_stockresources' );

function codesync_yw_show_stockresources()
{
    global $wpdb;
    

    $args = array(
        'limit'       => -1,
        'posts_per_page' => -1,
        'post_type'   => 'attachment',
        'meta_query'  => array(
            array(
                'key'     => 'yw_stock_resource',
                'compare' => 'EXISTS'
            )
        )
    );

    $stockphotos = get_posts( $args );

    
    $ret = '';

    if ( $stockphotos )
    {
        $ret = '<table class="yw_stockresources">';
        
        $ret .= '<thead>';
        $ret .= '<tr>';
        $ret .= '<th class="yw_thumb">'. __( 'Foto', 'yw-photorescources' ) .'</th>';
        $ret .= '<th>'. __( 'Quelle', 'yw-photorescources' ) .'</th>';
        $ret .= '<th>'. __( 'Link', 'yw-photorescources' ) .'</th>';
        $ret .= '</tr>';
        $ret .= '</thead>';

        $ret .= '<tbody>';

        foreach ( $stockphotos AS $stock )
        {
            // Get photo

            // Get source
            $resource = get_post_meta( $stock->ID, 'yw_stock_resource', true );
            $url = get_post_meta( $stock->ID, 'yw_stock_url', true );

            $ret .= '<tr>';
            $ret .= '<td>'. wp_get_attachment_image( $stock->ID ) .'</td>';
            $ret .= '<td>'. esc_html( $resource ) .'</td>';
            
            if ( !empty( $url ) )
            {
                $ret .= '<td><a href="'. esc_url( $url ) .'" target="_blank" rel="nofollow noopener noreferrer">Quelle öffnen</a></td>';
            }
            else
            {
                $ret .= '<td></td>';
            }

            $ret .= '</tr>';
        }

        $ret .= '</tbody>';
        $ret .= '</table>';
    }
    else
    {
        $ret = '<p class="yw_sr_empty">Keine Stockfotos gefunden</p>';
    }
    
    
    return $ret;
}


add_shortcode( 'image_resource', 'codesync_yw_show_single_resource' );

function codesync_yw_show_single_resource( $atts )
{
    $atts = shortcode_atts( array(
        'id' => null,
    ), $atts );

    $return = '';

    if ( isset($atts['id']) && $atts['id'] > 0 ) 
    {
        $rescource = get_post_meta( $atts['id'], 'yw_stock_resource', true );
        $url = get_post_meta( $atts['id'], 'yw_stock_url', true );

        $return = '<div class="yw_single_resource">'. __( 'Bildquelle', 'yw-photoresources' ) .': ';
        
        if ( !empty( $url ) )
        {
            $return .= '<a href="'. esc_url( $url ) .'" rel="nofollow noopener noreferrer" target="_blank">';
        }
        
        $return .= esc_html( $rescource );
        
        if ( !empty( $url ) )
        {
            $return .= '</a>';
        }
        
        $return .= '</div>';
    }

    return $return;
}

