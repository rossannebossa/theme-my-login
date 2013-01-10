<?php
/**
 * Holds the Theme My Login Admin class
 *
 * @package Theme_My_Login
 * @since 6.0
 */

if ( ! class_exists( 'Theme_My_Login_Admin' ) ) :
/**
 * Theme My Login Admin class
 *
 * @since 6.0
 */
class Theme_My_Login_Admin extends Theme_My_Login_Abstract {
	/**
	 * Holds options key
	 *
	 * @since 6.3
	 * @access protected
	 * @var string
	 */
	protected $options_key = 'theme_my_login';

	/**
	 * Returns singleton instance
	 *
	 * @since 6.3
	 * @access public
	 * @return Theme_My_Login
	 */
	public static function get_object() {
		return parent::get_object( __CLASS__ );
	}

	/**
	 * Returns default options
	 *
	 * @since 6.3
	 * @access public
	 */
	public static function default_options() {
		return Theme_My_Login::default_options();
	}

	/**
	 * Loads object
	 *
	 * @since 6.3
	 * @access public
	 */
	protected function load() {
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 8 );

		add_action( 'wp_trash_post',      array( &$this, 'wp_trash_post' ) );
		add_action( 'before_delete_post', array( &$this, 'wp_trash_post' ) );

		register_activation_hook( WP_PLUGIN_DIR . '/theme-my-login/theme-my-login.php', array( &$this, 'install' ) );
		register_uninstall_hook( WP_PLUGIN_DIR . '/theme-my-login/theme-my-login.php', array( 'Theme_My_Login_Admin', 'uninstall' ) );
	}

	/**
	 * Builds plugin admin menu and pages
	 *
	 * @since 6.0
	 * @access public
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'Theme My Login Settings', 'theme-my-login' ),
			__( 'TML', 'theme-my-login' ),
			'manage_options',
			'theme_my_login',
			array( 'Theme_My_Login_Admin', 'settings_page' )
		);

		add_submenu_page(
			'theme_my_login',
			__( 'General', 'theme-my-login' ),
			__( 'General', 'theme-my-login' ),
			'manage_options',
			'theme_my_login',
			array( 'Theme_My_Login_Admin', 'settings_page' )
		);

		// General section
		add_settings_section( 'general',    __( 'General', 'theme-my-login'    ), '__return_false', $this->options_key );
		add_settings_section( 'modules',    __( 'Modules', 'theme-my-login'    ), '__return_false', $this->options_key );

		// General fields
		add_settings_field( 'enable_css',  __( 'Stylesheet',   'theme-my-login' ), array( &$this, 'settings_field_enable_css'  ), $this->options_key, 'general' );
		add_settings_field( 'email_login', __( 'E-mail Login', 'theme-my-login' ), array( &$this, 'settings_field_email_login' ), $this->options_key, 'general' );
		add_settings_field( 'modules',     __( 'Modules',      'theme-my-login' ), array( &$this, 'settings_field_modules'     ), $this->options_key, 'modules' );
	}

	/**
	 * Registers TML settings
	 *
	 * This is used because register_setting() isn't available until the "admin_init" hook.
	 *
	 * @since 6.0
	 * @access public
	 */
	public function admin_init() {
		register_setting( 'theme_my_login', 'theme_my_login',  array( &$this, 'save_settings' ) );
	}

	/**
	 * Don't allow deletion of Login page
	 *
	 * @since 6.3
	 * @access public
	 *
	 * @param int $post_id Post ID
	 */
	public function wp_trash_post( $post_id ) {
		if ( Theme_My_Login::get_page_action( $post_id ) )
			wp_die( __( 'Deleting this page will cause Theme My Login to malfunction. If you really want to delete it, please deactivate Theme My Login.', 'theme-my-login' ), '', array( 'back_link' => true ) );
	}

	/**
	 * Renders the settings page
	 *
	 * @since 6.0
	 * @access public
	 */
	public static function settings_page( $args = '' ) {
		extract( wp_parse_args( $args, array(
			'title'       => __( 'Theme My Login Settings', 'theme-my-login' ),
			'options_key' => 'theme_my_login'
		) ) );
		?>
		<div id="<?php echo $options_key; ?>" class="wrap">
			<?php screen_icon( 'options-general' ); ?>
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( $options_key );
					do_settings_sections( $options_key );
					submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders Stylesheet settings field
	 *
	 * @since 6.3
	 * @access public
	 */
	public function settings_field_enable_css() {
		?>
		<input name="theme_my_login[enable_css]" type="checkbox" id="theme_my_login_enable_css" value="1"<?php checked( 1, $this->get_option( 'enable_css' ) ); ?> />
		<label for="theme_my_login_enable_css"><?php _e( 'Enable "theme-my-login.css"', 'theme-my-login' ); ?></label>
		<p class="description"><?php _e( 'In order to keep changes between upgrades, you can store your customized "theme-my-login.css" in your current theme directory.', 'theme-my-login' ); ?></p>
        <?php
	}

	/**
	 * Renders E-mail Login settings field
	 *
	 * @since 6.3
	 * @access public
	 */
	public function settings_field_email_login() {
		?>
		<input name="theme_my_login[email_login]" type="checkbox" id="theme_my_login_email_login" value="1"<?php checked( 1, $this->get_option( 'email_login' ) ); ?> />
		<label for="theme_my_login_email_login"><?php _e( 'Enable e-mail address login', 'theme-my-login' ); ?></label>
		<p class="description"><?php _e( 'Allows users to login using their e-mail address in place of their username.', 'theme-my-login' ); ?></p>
    	<?php
	}

	/**
	 * Renders Modules settings field
	 *
	 * @since 6.3
	 * @access public
	 */
	public function settings_field_modules() {
		foreach ( get_plugins( '/theme-my-login/modules' ) as $path => $data ) {
			$id = sanitize_key( $data['Name'] );
		?>
		<input name="theme_my_login[active_modules][]" type="checkbox" id="theme_my_login_active_modules_<?php echo $id; ?>" value="<?php echo $path; ?>"<?php checked( in_array( $path, (array) $this->get_option( 'active_modules' ) ) ); ?> />
		<label for="theme_my_login_active_modules_<?php echo $id; ?>"><?php printf( __( 'Enable %s', 'theme-my-login' ), $data['Name'] ); ?></label><br />
		<?php if ( $data['Description'] ) : ?>
		<p class="description"><?php echo $data['Description']; ?></p>
		<?php endif;
		}
	}

	/**
	 * Sanitizes TML settings
	 *
	 * This is the callback for register_setting()
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param string|array $settings Settings passed in from filter
	 * @return string|array Sanitized settings
	 */
	public function save_settings( $settings ) {
		$settings['enable_css']     = isset( $settings['enable_css']     );
		$settings['email_login']    = isset( $settings['email_login']    );
		$settings['active_modules'] = isset( $settings['active_modules'] ) ? (array) $settings['active_modules'] : array();

		// If we have modules to activate
		if ( $activate = array_diff( $settings['active_modules'], $this->get_option( 'active_modules', array() ) ) ) {
			foreach ( $activate as $module ) {
				if ( file_exists( WP_PLUGIN_DIR . '/theme-my-login/modules/' . $module ) )
					include_once( WP_PLUGIN_DIR . '/theme-my-login/modules/' . $module );
				do_action( 'tml_activate_' . $module );
			}
		}

		// If we have modules to deactivate
		if ( $deactivate = array_diff( $this->get_option( 'active_modules', array() ), $settings['active_modules'] ) ) {
			foreach ( $deactivate as $module ) {
				do_action( 'tml_deactivate_' . $module );
			}
		}

		$settings = wp_parse_args( $settings, $this->get_options() );

		return $settings;
	}

	/**
	 * Wrapper for multisite installation
	 *
	 * @since 6.1
	 * @access public
	 */
	public function install() {
		global $wpdb;

		if ( is_multisite() ) {
			if ( isset( $_GET['networkwide'] ) && ( $_GET['networkwide'] == 1 ) ) {
				$blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs" ) );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->_install();
				}
				restore_current_blog();
				return;
			}	
		}
		$this->_install();
	}

	/**
	 * Installs TML
	 *
	 * @since 6.0
	 * @access protected
	 */
	protected function _install() {
		global $wpdb;

		// Get plugin data
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/theme-my-login/theme-my-login.php' );

		// Current version
		$version = $this->get_option( 'version', $plugin_data['Version'] );

		// 4.4 upgrade
		if ( version_compare( $version, '4.4', '<' ) ) {
			remove_role( 'denied' );
		}
		// 6.3 upgrade
		if ( version_compare( $version, '6.3', '<' ) ) {
			// Delete obsolete options
			$this->delete_option( 'page_id'          );
			$this->delete_option( 'initial_nag'      );
			$this->delete_option( 'show_in_pagelist' );

			// Move options to their own rows
			foreach ( $this->get_options() as $key => $value ) {
				if ( in_array( $key, array( 'active_modules' ) ) )
					continue;

				if ( is_array( $value ) )
					update_option( "theme_my_login_{$key}", $value );
			}

			// Get existing page ID
			$page_id = $this->get_option( 'page_id' );

			// Check if page exists
			$existing_page = ( $page_id ) ? get_page( $page_id ) : get_page_by_title( 'Login' );

			// Maybe create login page?
			if ( $existing_page ) {
				// Make sure the page is not in the trash
				if ( 'trash' == $page->post_status )
					wp_untrash_post( $page_id );

				// Change to new post type
				$wpdb->update( $wpdb->posts, array( 'post_type' => 'tml_page' ), array( 'ID' => $existing_page->ID ) );

				update_post_meta( $existing_page->ID, '_tml_action', 'login' );
			}
		}

		// Setup default pages
		foreach ( Theme_My_Login::default_pages() as $action => $title ) {
			if ( ! $page_id = $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pmeta ON p.ID = pmeta.post_id WHERE p.post_type = 'tml_page' AND pmeta.meta_key = '_tml_action' AND pmeta.meta_value = %s", $action ) ) ) {
				$page_id = wp_insert_post( array(
					'post_title'     => $title,
					'post_status'    => 'publish',
					'post_type'      => 'tml_page',
					'post_content'   => '[theme-my-login]',
					'comment_status' => 'closed',
					'ping_status'    => 'closed'
				) );
				update_post_meta( $page_id, '_tml_action', $action );
			}
		}

		$this->set_option( 'version', $plugin_data['Version'] );
		$this->save_options();

		// Generate permalinks
		flush_rewrite_rules();
	}

	/**
	 * Wrapper for multisite uninstallation
	 *
	 * @since 6.1
	 * @access public
	 */
	public static function uninstall() {
		global $wpdb;

		if ( is_multisite() ) {
			if ( isset( $_GET['networkwide'] ) && ( $_GET['networkwide'] == 1 ) ) {
				$blogids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs" ) );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::_uninstall();
				}
				restore_current_blog();
				return;
			}	
		}
		self::_uninstall();
	}

	/**
	 * Uninstalls TML
	 *
	 * @since 6.0
	 * @access protected
	 */
	protected static function _uninstall() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Run module uninstall hooks
		$modules = get_plugins( '/theme-my-login/modules' );
		foreach ( array_keys( $modules ) as $module ) {
			$module = plugin_basename( trim( $module ) );

			if ( file_exists( WP_PLUGIN_DIR . '/theme-my-login/modules/' . $module ) )
				@include ( WP_PLUGIN_DIR . '/theme-my-login/modules/' . $module );

			do_action( 'tml_uninstall_' . $module );
		}

		// Remove delete block
		remove_action( 'wp_trash_post', array( self::get_object(), 'wp_trash_post' ) );

		// Delete the pages
		$pages = get_posts( array( 'post_type' => 'tml_page', 'post_status' => 'any', 'posts_per_page' => -1 ) );
		foreach ( $pages as $page ) {
			wp_delete_post( $page->ID );
		}

		// Delete options
		delete_option( 'theme_my_login' );
		delete_option( 'widget_theme-my-login' );
	}
}
endif; // Class exists

