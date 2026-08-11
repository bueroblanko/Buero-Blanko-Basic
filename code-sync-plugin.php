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
 * Description:       to sync all code snippets across client sites
 * Version:           0.0.9
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

// Version aus dem Plugin-Header oben — wird ans Cockpit gemeldet, damit man
// dort sieht, welche Seite auf welchem Stand steht
define( 'CODE_SYNC_PLUGIN_VERSION', '0.0.9' );

// Anmeldung beim BB Cockpit (siehe includes/class-code-sync-anmeldung.php).
// Leer lassen schaltet die Meldung ab.
define( 'CODE_SYNC_COCKPIT_TOKEN', 'aa72f3a3cb3ca28fa394e72cae70ac0f078af921011ab3a3' );

// check if there are some plugins installed if yes define the ADD_META_TAGS variable as false , true otherwise
$plugs = ['wpmu-dev-seo/wpmu-dev-seo.php','smartcrawl-seo/wpmu-dev-seo.php', 'wordpress-seo/wp-seo.php', 'all-in-one-seo-pack/all_in_one_seo_pack.php'];
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



/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-code-sync-activator.php
 */
function activate_code_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-activator.php';
	Code_Sync_Activator::activate();

	// beim Aktivieren sofort einmal melden und den taeglichen Lauf einplanen
	Code_Sync_Anmeldung::planen();
	Code_Sync_Anmeldung::melden();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-code-sync-deactivator.php
 */
function deactivate_code_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-deactivator.php';
	Code_Sync_Deactivator::deactivate();

	Code_Sync_Anmeldung::abmelden();
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-anmeldung.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-code-sync-screenshot.php';
// spaet einhaengen, damit das CSS nach dem der Banner-Plugins steht
add_action( 'wp_head', array( 'Code_Sync_Screenshot', 'kopf' ), 999 );
add_action( Code_Sync_Anmeldung::HOOK, array( 'Code_Sync_Anmeldung', 'melden' ) );
// falls das Plugin schon vor dieser Version aktiv war, gibt es noch keinen
// Cron-Eintrag — der wird hier nachgeholt
add_action( 'init', array( 'Code_Sync_Anmeldung', 'planen' ) );

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
add_filter( 'attachment_fields_to_edit', 'codesync_yw_add_media_custom_field', null, 11 );


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
            $ret .= '<td>'. $resource .'</td>';
            
            if ( !empty( $url ) )
            {
                $ret .= '<td><a href="'. $url .'" target="_blank" rel="nofollow noopener noreferrer">Quelle öffnen</a></td>';
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
            $return .= '<a href="'. $url .'" rel="nofollow noopener noreferrer" target="_blank">';
        }
        
        $return .= $rescource;
        
        if ( !empty( $url ) )
        {
            $return .= '</a>';
        }
        
        $return .= '</div>';
    }

    return $return;
}

