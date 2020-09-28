<?php
/**
* Plugin Name: Blaze Online Hosting
* Plugin URI: #
* Description: Manage site hosting feature
* Version: 1.0.1
* Author: Blaze Online
* Author URI: https://blaze.online/
* License: GPLv2+
*/

if ( !defined( 'ABSPATH' ) ) exit;

define( 'BLAZE_URL', plugin_dir_url( __FILE__ ) );
define( 'BLAZE_DIR', plugin_dir_path( __FILE__ ) );

add_action('plugins_loaded', 'blaze_init', 0);

function blaze_init()
{
	$blazeHooks = new BlazeHooks();
}

// Register CLI cmd
if ( method_exists( 'WP_CLI', 'add_command' ) ) {
	WP_CLI::add_command( 'blaze-admin', array( new BlazeHooks,'blz_run_enable_disable_options' ) );
}

class BlazeHooks
{
	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array($this, 'blz_load_scripts') );

		add_action( 'admin_menu', array($this, 'blz_admin_menu') );
		add_action( 'admin_head', array($this, 'blz_admin_head') );

		add_filter( 'gettext', array($this, 'change_post_to_article') );
		add_filter( 'ngettext', array($this, 'change_post_to_article') );

	  	add_action( 'admin_init', array($this, 'blz_settings') );

	  	add_action( 'pre_current_active_plugins', array($this, 'hide_blz_plugin_to_list') );
	}

	public function blz_load_scripts()
	{
		global $wp;
		wp_enqueue_style( 'blz-hook-plugin-css', BLAZE_URL . 'assets/css/blz-hook.css', false, '1.0.0' );

		wp_enqueue_script( 'blz-hook-plugin-js', BLAZE_URL . 'assets/js/blz-hook.js', array(), '1.0.0', true );
		wp_localize_script('blz-hook-plugin-js', 'blz_object', array( 
				'ajax_url' => admin_url('admin-ajax.php'), 
				'homeurl' => home_url()
			) 
		);

		$cartfragment = get_option('cartfragment');
		if (is_front_page() && !empty($cartfragment)) {
			wp_dequeue_script('wc-cart-fragments');
		}
	}

	public function blz_admin_head()
	{
		?>
		<script type="text/javascript">
			jQuery(function($) {
				// Rename Closte
				jQuery('#toplevel_page_closte_main_menu').find('.wp-menu-name').html('Blaze Online');

				// Rename title
				if(jQuery('.wp-heading-inline').length > 0) {
					var heading = jQuery('.wp-heading-inline').text();
					if(heading == 'Closte Settings') {
						jQuery('.wp-heading-inline').text('Blaze Online Settings');
					}
				}

				// Remove domain
				jQuery('#toplevel_page_closte_main_menu').find('.wp-submenu li').last().remove();

				// Remove CDN
				jQuery('#wp-admin-bar-closte_top_bar_flush_cdn').remove();	
				jQuery('#toplevel_page_closte_main_menu').find('.wp-submenu li.wp-first-item').next().remove();		

				// Hide unnecessary functions
				if($('.nav-tab-wrapper').find(".nav-tab")) {
					$('.nav-tab-wrapper').find(".nav-tab").each(function(){
						var hrf = $(this).attr('href');
						if(hrf == '#closte_plugin_config-cdn' || hrf == '#closte_plugin_email') {
							jQuery(this).hide();
						}
					});
				}
			});
		</script>
		<?php
	}

	public function change_post_to_article( $translated ) 
	{  
	    $translated = str_replace( 'Closte', 'Blaze Online', $translated );
	    $translated = str_replace( 'closte_main_menu', 'blazeonline', $translated );
	    return $translated;
	}

	public function blz_admin_menu()
	{
		global $menu, $submenu;
		add_submenu_page( 'options-general.php', 'Blaze Online', 'Blaze Online', 'manage_options', 'blz-fragments', array($this, 'blz_fragments'));
	}

	public function blz_settings()
	{
		register_setting( 'blz-settings-group', 'cartfragment' );

		if ( class_exists( 'wpMandrill' ) ) {
			remove_action( 'wp_dashboard_setup', array( 'wpMandrill' , 'addDashboardWidgets' ) );
		}
	}

	public function blz_fragments()
	{
		?>
		<div class="wrap">
		<h1>Blaze Online Options</h1>

		<form method="post" action="options.php">
		    <?php settings_fields( 'blz-settings-group' ); ?>
		    <?php do_settings_sections( 'blz-settings-group' ); ?>
		    <?php $cartfragment = get_option('cartfragment'); ?>
		    <table class="form-table">
		        <tr valign="top">
		        <th scope="row">Disable cart fragments</th>
		        <td><input type="checkbox" name="cartfragment" value="1" <?php echo !empty($cartfragment)?'checked="checked"':''; ?> /></td>
		        </tr>
		    </table>
		    
		    <?php submit_button(); ?>

		</form>
		</div>
		<?php
	}

	public function hide_blz_plugin_to_list() {

		if ( is_user_logged_in() ) {

			$user = wp_get_current_user();

			global $wp_list_table;

			$plugins_list   = $wp_list_table->items;

			$hidden_plugins = array( 'blaze-online-hosting/blaze-online-hosting.php' );

			foreach ( $plugins_list as $key => $val ) {
				if ( in_array( $key, $hidden_plugins ) ) {
					unset( $wp_list_table->items[ $key ] );
				}
			}
		}
	}

	public function blz_run_enable_disable_options($args, $assoc_args)
	{
		// Run option commands
		if(!empty($args[0]) && $args[0] == 'set-option') {
			// Enable/Disable Cart Fragments
			// Command: wp blaze-admin set-option --cart-fragment=true/false
			if(!empty($assoc_args['cart-fragment'])) {
				if($assoc_args['cart-fragment'] == 'true') {
					update_option( 'cartfragment', 0 );

					WP_CLI::success( "Cart fragment enabled" );
				} elseif($assoc_args['cart-fragment'] == 'false') {
					update_option( 'cartfragment', 1 );

					WP_CLI::success( "Cart fragment disabled" );
				} else {
					WP_CLI::error( "Wrong command please try again!" );
				}
			}
		}

		// Run plugin updater
		if(!empty($args[0]) && $args[0] == 'update-plugin') {
			// Command: wp blaze-admin update-plugin

			include_once BLAZE_DIR .'library/updater.php';
			define( 'WP_GITHUB_FORCE_UPDATE', true );

			$config = array(
				'slug' => plugin_basename( __FILE__ ),
				'proper_folder_name' => 'blaze-online-hosting',
				'api_url' => 'https://api.github.com/melberthbontilao/Blaze-Online-Hosting',
				'raw_url' => 'https://raw.github.com/melberthbontilao/Blaze-Online-Hosting/master',
				'github_url' => 'https://github.com/melberthbontilao/Blaze-Online-Hosting',
				'zip_url' => 'https://github.com/melberthbontilao/Blaze-Online-Hosting/archive/master.zip',
				'sslverify' => true,
				'requires' => '3.0',
				'tested' => '3.3',
				'readme' => 'README.md',
				'access_token' => 'f1069fa8684afc6660b700f2187db853af35651f',
			);

			new WP_GitHub_Updater( $config );

			WP_CLI::success( "Plugin is now updated to latest version" );

		}
	}
}