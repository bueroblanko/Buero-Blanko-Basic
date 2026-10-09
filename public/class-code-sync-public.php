<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/public
 * @author     Ilyes <test@test.com>
 */

if (! defined('CODE_SYNC_ALLOWED_MAIL')) {
	die('Bruh!');
}
class Code_Sync_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Code_Sync_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Code_Sync_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		// add_action('wp_head', 'my_custom_wp_head_function');

		// function my_custom_wp_head_function() {
		// 	$f  =  plugin_dir_path(__FILE__) . 'code-snippets/';
		// 	$snippets_dir =  plugin_dir_path(__FILE__) . 'code-snippets/';
		// 	foreach (glob($snippets_dir . "*.php") as $file) {
		// 			$f.=  $file . "\n";
		// 		}
		// 	echo '<!-- ' . $f . ' -->';
		// }
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/code-sync-public.css', array(), $this->version, 'all' );
		// Divi-Layout-Sperre nur fuer angemeldete Nutzer ausserhalb von Buero Blanko,
		// Besucher ohne Login koennen den Builder ohnehin nicht oeffnen
		if ( is_user_logged_in() && ! $this->is_bueroblanko_user() ) {
			wp_enqueue_style( 'code-sync-public-disable-divi-layouts', plugin_dir_url( __FILE__ ) . 'css/code-sync-public-disable-divi-layouts.css', array( ), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Code_Sync_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Code_Sync_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/code-sync-public.js', array( 'jquery' ), $this->version, false );
		// check if the user email ends with buerobronko , if not enqueue a script
		if ( is_user_logged_in() && ! $this->is_bueroblanko_user() ) {
			wp_enqueue_script( 'code-sync-public-disable-divi-layouts', plugin_dir_url( __FILE__ ) . 'js/code-sync-public-disable-divi-layouts.js', array( 'jquery' ), $this->version, true );
		}
	
	}

	/** Ist der angemeldete Nutzer ein Buero-Blanko-Konto (Mail endet auf @bueroblanko.de)? */
	public function is_bueroblanko_user() {
		$email = strtolower( (string) $this->get_current_user_email() );
		$suffix = '@' . CODE_SYNC_ALLOWED_MAIL;
		return $email !== '' && substr( $email, -strlen( $suffix ) ) === $suffix;
	}

	public function get_current_user_email() {
		$current_user = wp_get_current_user(); // Retrieve the current user object
		if ($current_user && $current_user->exists()) {
			return $current_user->get('user_email'); // Return the user's email
		}
		return null; // No user is logged in
	}

	public function execute_code_snippets() {
		$snippets_dir = plugin_dir_path(__FILE__) . '../code-snippets/php/';

		if (!is_dir($snippets_dir) || !is_readable($snippets_dir)) {
			return;
		}

		$snippets = glob($snippets_dir . "*.php");
		if (empty($snippets)) {
			return;
		}

		foreach ($snippets as $file) {
			if (basename($file) === 'index.php') {
				continue;
			}

			$code = file_get_contents($file);
			if ($code === false) {
				continue;
			}
			$code = preg_replace('/^<\?php/', '', $code);

			// Throwable faengt auch Laufzeitfehler (Error, TypeError) ab, damit ein
			// defektes Snippet nicht die ganze Seite lahmlegt. Doppelt deklarierte
			// Funktionen lassen sich so nicht abfangen, deshalb tragen alle
			// Snippet-Funktionen das Praefix bb_.
			ob_start();
			try {
				eval($code);
			} catch (Throwable $e) {
				error_log('BB Basic: Snippet ' . basename($file) . ' fehlgeschlagen: ' . $e->getMessage());
			} finally {
				ob_end_clean();
			}
		}
	}

	public function add_meta_tags () {
		if (!defined('CODE_SYNC_ADD_META_TAGS') || !CODE_SYNC_ADD_META_TAGS){
			return;
		}
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'code_sync_meta_tags';
		$old_row = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
		// og:url ist die Adresse der aktuellen Seite, nicht immer die Startseite
		global $wp;
		if ( is_singular() ) {
			$site_url = get_permalink();
		} else {
			$site_url = home_url( empty( $wp->request ) ? '/' : user_trailingslashit( $wp->request ) );
		}
		$site_url = esc_url( $site_url );
		$domain = esc_attr( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		$title = isset($old_row->title) ? esc_attr($old_row->title) : '';
		$keywords = isset($old_row->keywords) ? esc_attr($old_row->keywords) : '';
		$description = isset($old_row->description) ? esc_attr($old_row->description) : '';
		$og_image = isset($old_row->og_image) ? esc_attr($old_row->og_image) : '';

		$result = "";

		if (!empty($description)){
			$result .= '<meta name="description" content="'. $description. '">';
		}
		if (!empty($keywords)){
			$result .= '<meta name="keywords" content="'. $keywords. '">';
		}
		
		$result .= <<<EOT
<!-- Facebook Meta Tags -->
<meta property="og:url" content="$site_url">
<meta property="og:type" content="website">
EOT;
		if (!empty($title)) {
			$result .= '<meta property="og:title" content="'.$title.'">';
		}
		if (!empty($description)) {
			$result .= '<meta property="og:description" content="'.$description.'">';
		
		}
		if (!empty($og_image)){
			$result .= '<meta property="og:image" content="'.$og_image.'">';
		}

		$result .= <<<EOT
<!-- Twitter Meta Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="$site_url">
<meta property="twitter:domain" content="$domain">
EOT;
		if (!empty($title)) {
			$result .= '<meta name="twitter:title" content="'.$title.'">';
		}
		if (!empty($description)) {
			$result .= '<meta name="twitter:description" content="'.$description.'">';
		
		}
		if (!empty($og_image)){
			$result .= '<meta name="twitter:image" content="'.$og_image.'">';
		}


		echo $result;
	}

	public function load_js_snippets() {
		$snippets_dir = plugin_dir_path( __FILE__ ) . '../code-snippets/js/';
		$snippets = glob( $snippets_dir . '*.js' );
		if ( empty( $snippets ) ) {
			return;
		}
		foreach ( $snippets as $file ) {
			$filename = basename( $file );
			wp_enqueue_script( $filename, plugins_url( 'code-snippets/js/' . $filename, CODE_SYNC_PLUGIN_FILE ), array( 'jquery' ), null, true );
		}
	}

	public function add_body_class ($classes) {
		$classes[] = 'no-et-layouts';

		return $classes;

		
	}
}
