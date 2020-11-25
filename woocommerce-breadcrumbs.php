<?php
/*
Plugin Name: WooCommerce Breadcrumbs
Plugin URI: http://maddisondesigns.com/woocommerce-breadcrumbs
Description: A simple plugin to style the WooCommerce Breadcrumbs or disable them altogether
Version: 1.0.7
WC requires at least: 2.6
WC tested up to: 4.7
Author: Anthony Hortin
Author URI: http://maddisondesigns.com
Text Domain: woocommerce-breadcrumbs
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


class Wcb_WooCommerce_Breadcrumbs_plugin {

	private $options;
	private $breadcrumb_defaults;
	private $wootheme_theme;
	private $storefront_theme;

	public function __construct() {

		$this->breadcrumb_defaults = array(
			'wcb_enable_breadcrumbs' => 1,
			'wcb_breadcrumb_delimiter' => ' &#47; ',
			'wcb_wrap_before' => '<nav class="woocommerce-breadcrumb">',
			'wcb_wrap_after' => '</nav>',
			'wcb_before' => '',
			'wcb_after' => '',
			'wcb_home_text' => _x( 'Home', 'breadcrumb', 'woocommerce-breadcrumbs' ),
			'wcb_home_url' => esc_url( home_url( '/' ) )
			);

		add_action( 'admin_menu', array( $this, 'wcb_create_menu_option' ) );
		add_action( 'admin_init', array( $this, 'wcb_admin_init' ) );
		add_action( 'init', array( $this, 'wcb_init' ) );
		add_filter( 'plugin_action_links', array( $this, 'wcb_add_settings_link'), 10, 2);
		add_action( 'head', 'woocommerce_breadcrumb', 20, 0);

		$this->options = ( get_option( 'wcb_breadcrumb_options' ) === false ? $this->breadcrumb_defaults : get_option( 'wcb_breadcrumb_options' ) );

		if( empty( $this->options['wcb_enable_breadcrumbs'] ) ) {
			add_action( 'init', array( $this, 'wcb_remove_woocommerce_breadcrumb' ) );
		}
	}

	/**
	 * Add a new option to the Settings menu
	 */
	public function wcb_create_menu_option() {
		add_options_page( 'WooCommerce Breadcrumbs', 'WC Breadcrumbs', 'manage_options', 'woocommerce-breadcrumbs', array( $this, 'wcb_plugin_settings_page' ) );
	}

	/**
	 * Add a settings link to plugin page
	 */
	public function wcb_add_settings_link( $links, $file ) {
		static $this_plugin;

		if( !$this_plugin ) {
			$this_plugin = plugin_basename( __FILE__ );
		}

		if( $file == $this_plugin ) {
			$settings_link = '<a href="options-general.php?page=woocommerce-breadcrumbs">' . __( 'Settings', 'woocommerce-breadcrumbs' ) . '</a>';
			array_unshift( $links, $settings_link ) ;
		}

		return $links;
	}

	/**
	 * Create our settings page
	 */
	public function wcb_plugin_settings_page() {
		$this->options = ( get_option( 'wcb_breadcrumb_options' ) === false ? $this->breadcrumb_defaults : get_option( 'wcb_breadcrumb_options' ) );

		if( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$message = __('It appears that WooCommerce is not currently activated. To get the most out of WooCommerce Breadcrumbs install & activate the WooCommerce plugin', 'woocommerce-breadcrumbs' );
			add_settings_error( 'woocommerce-breadcrumb-warnings', esc_attr( 'wcb_woocommerce_disabled' ), $message, 'error' );
		}

		if ( $this->wootheme_theme ) {
			$message = esc_html("It looks like you're using a WooThemes theme. If you notice a few less breadcrumb options than you may expect this is because WooThemes disables the WooCommerce breadcrumbs in favour of the WooFramework Breadcrumbs.", 'woocommerce-breadcrumbs' );
			add_settings_error( 'woocommerce-breadcrumb-warnings', esc_attr( 'wcb_woo_framework_breadcrumbs' ), $message, 'updated' );
		}

		settings_errors( 'woocommerce-breadcrumb-warnings' );

		echo '<div class="wrap">';
			echo '<h2>WooCommerce Breadcrumbs</h2>';
			echo '<form action="options.php" method="post">';
				settings_fields( 'wcb_breadcrumb_options' );
				do_settings_sections( 'woocommerce-breadcrumbs' );
				echo '<p>';
					submit_button( _x( 'Save Changes', 'breadcrumb', 'woocommerce-breadcrumbs' ), 'primary', 'submit', false  );
					$other_attributes = array (
						'onclick' => "return confirm( '" . esc_html__( 'Click OK to reset to the default breadcrumb settings!', 'woocommerce-breadcrumbs' ) . "' );"
						);
					submit_button( 'Restore Defaults', 'secondary alignright', 'restore_defaults', false, $other_attributes );
				echo '</p>';
			echo '</form>';
		echo '</div>';

	}

	/**
	 * Register and define the settings
	 */
	public function wcb_admin_init() {
		$settings_args = array(
            'type' => 'array', 
            'sanitize_callback' => array( $this, 'wcb_plugin_sanitize_options' ),
            'show_in_rest' => false,
            'default' => $this->breadcrumb_defaults,
            );

		register_setting( 'wcb_breadcrumb_options', 'wcb_breadcrumb_options', $settings_args );
		add_settings_section( 'wcb_general_settings', 'Breadcrumb Settings', array( $this, 'wcb_plugin_section_callback' ), 'woocommerce-breadcrumbs' );
		add_settings_field( 'wcb_enable_breadcrumbs', 'Enable breadcrumbs', array( $this, 'wcb_enable_breadcrumbs_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		add_settings_field( 'wcb_breadcrumb_delimiter', 'Breadcrumb separator', array( $this, 'wcb_breadcrumb_delimiter_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		add_settings_field( 'wcb_wrap_before', 'Wrap before', array( $this, 'wcb_wrap_before_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		add_settings_field( 'wcb_wrap_after', 'Wrap after', array( $this, 'wcb_wrap_after_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		if ( !$this->wootheme_theme ) {
			// We can't set these if the theme is using the WooFramework Breadcrumbs instead of the WooCommerce Breadcrumbs, so don't show them
			add_settings_field( 'wcb_before', 'Before', array( $this, 'wcb_before_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
			add_settings_field( 'wcb_after', 'After', array( $this, 'wcb_after_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		}
		add_settings_field( 'wcb_home_text', 'Home text', array( $this, 'wcb_home_text_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		if ( !$this->wootheme_theme ) {
			// We can't set this if the theme is using the WooFramework Breadcrumbs instead of the WooCommerce Breadcrumbs, so don't show it
			add_settings_field( 'wcb_home_url', 'Home URL', array( $this, 'wcb_home_url_callback' ), 'woocommerce-breadcrumbs', 'wcb_general_settings' );
		}
	}

	/**
	 * Once the theme is initialised, check to see if it's a WooTheme theme as they typically disable WooCommerce breadcrumbs
	 * in favour of the WooFramework Breadcrumbs (which behave a little differently)
	 * Set the breadcrumbs now that we have all the details
	 */
	public function wcb_init() {
		if ( class_exists( 'Storefront' ) ) {
			$this->storefront_theme = true;
		}
		elseif ( function_exists( 'woo_breadcrumbs' ) ) {
			remove_filter( 'woo_breadcrumbs_args', 'woo_custom_breadcrumbs_args', 10 );
			$this->breadcrumb_defaults['wcb_breadcrumb_delimiter'] = '&gt;';
			$this->breadcrumb_defaults['wcb_wrap_before'] = '<span class="breadcrumb-title">' . __( 'You are here:', 'woocommerce-breadcrumbs' ) . '</span>';
			$this->breadcrumb_defaults['wcb_wrap_after'] = '';
			$this->wootheme_theme = true;
		}
		else {
			$this->wootheme_theme = false;
			$this->storefront_theme = false;
		}

		if( !empty( $this->options['wcb_enable_breadcrumbs'] ) ) {
			if ( $this->wootheme_theme ) {
				add_filter( 'woo_breadcrumbs_args', array( $this, 'wcb_woocommerce_set_breadcrumbs' ), 11 );
			}
			else {
				add_filter( 'woocommerce_breadcrumb_defaults', array( $this, 'wcb_woocommerce_set_breadcrumbs' ) );
				add_filter( 'woocommerce_breadcrumb_home_url', array( $this, 'wcb_woocommerce_breadcrumb_home_url' ) );
			}
		}
	}

	/**
	 * Display a section message
	 */
	public function wcb_plugin_section_callback() {
		printf( '<p>%s</p>', __( 'Customise the look of your WooCommerce breadcrumbs, using the settings below. Alternatively, disable them altogether by unchecking &lsquo;Enable breadcrumbs&rsquo;.', 'woocommerce-breadcrumbs' ) );
	}

	/**
	 * Display and fill the form field for the delimeter setting
	 */
	function wcb_enable_breadcrumbs_callback() {
		$enable_breadcrumbs = ( isset( $this->options['wcb_enable_breadcrumbs'] ) ? $this->options['wcb_enable_breadcrumbs'] : '0' );

		printf( '<input id="wcb_enable_breadcrumbs" type="checkbox" name="wcb_breadcrumb_options[wcb_enable_breadcrumbs]" value="%1$s" %2$s/>',
			$enable_breadcrumbs,
			checked( $enable_breadcrumbs, true, false ) );
	}

	/**
	 * Display and fill the form field for the delimeter setting
	 */
	public function wcb_breadcrumb_delimiter_callback() {
		$breadcrumb_delimiter = ( isset( $this->options['wcb_breadcrumb_delimiter'] ) ? $this->options['wcb_breadcrumb_delimiter'] : '' );

		printf( '<input id="wcb_breadcrumb_delimiter" class="regular-text" name="wcb_breadcrumb_options[wcb_breadcrumb_delimiter]" type="text" value="%s"/>',
			esc_attr( $breadcrumb_delimiter )  );
		printf( '<p class="description">%s</p>', __( 'This is the separator to use between each breadcrumb.', 'woocommerce-breadcrumbs' ) );
	}

	/**
	 * Display and fill the form field for the Wrap Before setting
	 */
	public function wcb_wrap_before_callback() {
		$wrap_before = ( isset( $this->options['wcb_wrap_before'] ) ? $this->options['wcb_wrap_before'] : '' );

		printf( '<input id="wcb_wrap_before" class="regular-text" name="wcb_breadcrumb_options[wcb_wrap_before]" type="text" value="%s"/>',
			esc_attr( $wrap_before ) );
		if ( $this->wootheme_theme ) {
			$msg = __( 'The opening html tag to display before all your breadcrumbs.', 'woocommerce-breadcrumbs' );
		}
		else {
			$msg = __( 'The opening html tag to wrap before all your breadcrumbs.', 'woocommerce-breadcrumbs' );
		}
		printf( '<p class="description">%s</p>', $msg );
	}

	/**
	 * Display and fill the form field for the Wrap After setting
	 */
	public function wcb_wrap_after_callback() {
		$wrap_after = ( isset( $this->options['wcb_wrap_after'] ) ? $this->options['wcb_wrap_after'] : '' );

		printf( '<input id="wcb_wrap_after" class="regular-text" name="wcb_breadcrumb_options[wcb_wrap_after]" type="text" value="%s"/>',
			esc_attr( $wrap_after ) );
		if ( $this->wootheme_theme ) {
			$msg = __( 'The closing html tag to display after all your breadcrumbs.', 'woocommerce-breadcrumbs' );
		}
		else {
			$msg = __( 'The closing html tag to wrap after all your breadcrumbs.', 'woocommerce-breadcrumbs' );
		}
		printf( '<p class="description">%s</p>', $msg );
	}

	/**
	 * Display and fill the form field for the Before setting
	 */
	public function wcb_before_callback() {
		$before = ( isset( $this->options['wcb_before'] ) ? $this->options['wcb_before'] : '' );

		printf( '<input id="wcb_before" class="regular-text" name="wcb_breadcrumb_options[wcb_before]" type="text" value="%s"/>',
			esc_attr( $before ) );
		printf( '<p class="description">%s</p>', __( 'The opening html tag to wrap before each individual breadcrumb.', 'woocommerce-breadcrumbs' ) );
	}

	/**
	 * Display and fill the form field for the After setting
	 */
	public function wcb_after_callback() {
		$after = ( isset( $this->options['wcb_after'] ) ? $this->options['wcb_after'] : '' );

		printf( '<input id="wcb_after" class="regular-text" name="wcb_breadcrumb_options[wcb_after]" type="text" value="%s"/>',
			esc_attr( $after ) );
		printf( '<p class="description">%s</p>', __( 'The closing html tag to wrap after each individual breadcrumb.', 'woocommerce-breadcrumbs' ) );
	}

	/**
	 * Display and fill the form field for the Home Text setting
	 */
	public function wcb_home_text_callback() {
		$home_text = ( isset( $this->options['wcb_home_text'] ) ? $this->options['wcb_home_text'] : '' );

		printf( '<input id="wcb_home_text" class="regular-text" name="wcb_breadcrumb_options[wcb_home_text]" type="text" value="%s"/>',
			$home_text );
		printf( '<p class="description">%s</p>', __( 'The text to use for the &lsquo;Home&rsquo; breadcrumb.', 'woocommerce-breadcrumbs' ) );
	}

	/**
	 * Display and fill the form field for the Home URL setting
	 */
	public function wcb_home_url_callback() {
		$home_url = ( isset( $this->options['wcb_home_url'] ) ? $this->options['wcb_home_url'] : '' );

		printf( '<input id="wcb_home_url" class="regular-text" name="wcb_breadcrumb_options[wcb_home_url]" type="text" value="%s"/>',
			esc_attr( $home_url ) );
		printf( '<p class="description">%s</p>', __( 'The URL that the &lsquo;Home&rsquo; breadcrumb links to.', 'woocommerce-breadcrumbs' ) );
	}

	/**
	 * Validate and sanitize each of our options
	 */
	public function wcb_plugin_sanitize_options( $input ) {
		$valid = array();

		// If the Restore Defaults button is clicked, reset the values to the default values
		if ( isset( $_POST['restore_defaults'] ) ) {
			$message = esc_html('Default options restored', 'woocommerce-breadcrumbs' );
			add_settings_error( 'woocommerce-breadcrumbs', esc_attr( 'wcb_restore_defaults' ), $message, 'updated' );
			return $this->breadcrumb_defaults;
		}

		// Validate the inputs
		$valid['wcb_enable_breadcrumbs'] = ( isset( $input['wcb_enable_breadcrumbs'] ) ? '1' : '0' );

		$valid['wcb_breadcrumb_delimiter'] = wp_kses_post( $input['wcb_breadcrumb_delimiter'] );

		$valid['wcb_wrap_before'] = wp_kses_post( $input['wcb_wrap_before'] );

		$valid['wcb_wrap_after'] = wp_kses_post( $input['wcb_wrap_after'] );

		$valid['wcb_before'] = wp_kses_post( $input['wcb_before'] );

		$valid['wcb_after'] = wp_kses_post( $input['wcb_after'] );

		$valid['wcb_home_text'] = sanitize_text_field( $input['wcb_home_text'] );

		$valid['wcb_home_url'] = esc_url( $input['wcb_home_url'] );

		return $valid;
	}

	/**
	* Remove the WooCommerce Breadcrumbs
	*/
	public function wcb_remove_woocommerce_breadcrumb() {
		if ( $this->storefront_theme ) {
			remove_action( 'storefront_before_content', 'woocommerce_breadcrumb', 10 );
		}
		elseif ( $this->wootheme_theme ) {
			remove_filter( 'woo_main_before', 'woo_display_breadcrumbs', 10 );
		}
		else {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}
	}

	/**
	* Change the Home link for the Breadrumbs
	*/
	public function wcb_woocommerce_breadcrumb_home_url() {
		return $this->options['wcb_home_url'];
	}

	/**
	* Set the breadcrumbs
	*/
	public function wcb_woocommerce_set_breadcrumbs() {

		if ( $this->wootheme_theme ) {
			return array(
				'separator' => $this->options['wcb_breadcrumb_delimiter'],
				'before' => $this->options['wcb_wrap_before'],
				'after' => $this->options['wcb_wrap_after'],
				'show_home' => _x( $this->options['wcb_home_text'], 'breadcrumb', 'woocommerce-breadcrumbs' )
			);
		}
		else {
			return array(
				'delimiter' => $this->options['wcb_breadcrumb_delimiter'],
				'wrap_before' => $this->options['wcb_wrap_before'],
				'wrap_after' => $this->options['wcb_wrap_after'],
				'before' => $this->options['wcb_before'],
				'after' => $this->options['wcb_after'],
				'home' => _x( $this->options['wcb_home_text'], 'breadcrumb', 'woocommerce-breadcrumbs' )
			);
		}
	}
}

$wcb_woocommerce_breadcrumbs = new Wcb_WooCommerce_Breadcrumbs_plugin();
