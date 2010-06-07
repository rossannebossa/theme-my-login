<?php
/**
 * Holds the Theme My Login class
 *
 * @package Theme My Login
 */
 
if ( !class_exists( 'Theme_My_Login' ) ) :
/*
 * Theme My Login class
 *
 * This class contains properties and methods common to the front-end.
 *
 * @since 6.0
 */
class Theme_My_Login extends Theme_My_Login_Base {
	/**
	 * Total instances of TML
	 *
	 * @since 6.0
	 * @access public
	 * @var int
	 */
	var $count = 0;
	
	/**
	 * Current instance being requested via HTTP GET or POST
	 *
	 * @since 6.0
	 * @access public
	 * @var int
	 */
	var $request_instance;
	
	/**
	 * Current action being requested via HTTP GET or POST
	 *
	 * @since 6.0
	 * @access public
	 * @var string
	 */
	var $request_action;
	
	/**
	 * URL to redirect to
	 *
	 * @since 6.0
	 * @access public
	 * @var string
	 */
	var $redirect_to;
	
	/**
	 * Flag used within wp_list_pages() to make the_title() filter work properly
	 *
	 * @since 6.0
	 * @access public
	 * @var bool
	 */
	var $doing_pagelist = false;
	
	/**
	 * Proccesses the request
	 *
	 * Callback for 'parse_request' hook in WP::parse_request()
	 *
	 * @see WP::parse_request()
	 * @since 6.0
	 * @access public
	 */
	function the_request() {
		$errors =& $this->errors;
		$action =& $this->request_action;
		$instance =& $this->request_instance;
		
		do_action_ref_array( 'tml_request', array( &$this ) );
		
		if ( $this->options['enable_css'] )
			wp_enqueue_style( 'theme-my-login', $this->get_stylesheet(), false, $this->options['version'] );

		// Set a cookie now to see if they are supported by the browser.
		setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN );
		if ( SITECOOKIEPATH != COOKIEPATH )
			setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN );
			
		// allow plugins to override the default actions, and to add extra actions if they want
		if ( has_filter( 'login_action_' . $action ) ) {
			do_action_ref_array( 'login_action_' . $action, array( &$this ) );
		} else {
			$http_post = ( 'POST' == $_SERVER['REQUEST_METHOD'] );
			switch ( $action ) {
				case 'logout' :
					check_admin_referer( 'log-out' );
					
					$redirect_to = apply_filters( 'logout_redirect', site_url( 'wp-login.php?loggedout=true' ), isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '' );

					wp_logout();

					wp_safe_redirect( $redirect_to );
					exit();
					break;
				case 'lostpassword' :
				case 'retrievepassword' :
					if ( $http_post ) {
						$errors = $this->retrieve_password();
						if ( !is_wp_error( $errors ) ) {
							$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : $this->get_current_url( 'checkemail=confirm' );
							if ( !empty( $instance ) )
								$redirect_to = add_query_arg( 'instance', $instance, $redirect_to );
							wp_safe_redirect( $redirect_to );
							exit();
						}
					}

					if ( isset( $_REQUEST['error'] ) && 'invalidkey' == $_REQUEST['error'] )
						$errors->add( 'invalidkey', __( 'Sorry, that key does not appear to be valid.', 'theme-my-login' ) );
					break;
				case 'resetpass' :
				case 'rp' :
					$errors = $this->reset_password( $_GET['key'], $_GET['login'] );

					if ( !is_wp_error( $errors ) ) {
						$redirect_to = apply_filters( 'resetpass_redirect', $this->get_current_url( 'checkemail=newpass' ) );
						if ( !empty( $instance ) )
							$redirect_to = add_query_arg( 'instance', $instance, $redirect_to );
						wp_safe_redirect( $redirect_to );
						exit();
					}

					$redirect_to = $this->get_current_url( 'action=lostpassword&error=invalidkey' );
					if ( !empty( $instance ) )
						$redirect_to = add_query_arg( 'instance', $instance, $redirect_to );
					wp_redirect( $redirect_to );
					exit();
					break;
				case 'register' :
					if ( function_exists( 'is_multisite' ) && is_multisite() ) {
						// Multisite uses wp-signup.php
						wp_redirect( apply_filters( 'wp_signup_location', get_bloginfo('wpurl') . '/wp-signup.php' ) );
						exit;
					}
					
					if ( !get_option( 'users_can_register' ) ) {
						wp_redirect( $this->get_current_url( 'registration=disabled' ) );
						exit();
					}

					$user_login = '';
					$user_email = '';
					if ( $http_post ) {
						require_once( ABSPATH . WPINC . '/registration.php' );

						$user_login = $_POST['user_login'];
						$user_email = $_POST['user_email'];
						
						$errors = $this->register_new_user( $user_login, $user_email );
						if ( !is_wp_error( $errors ) ) {
							$redirect_to = !empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : $this->get_current_url('checkemail=registered' );
							if ( !empty( $instance ) )
								$redirect_to = add_query_arg( 'instance', $instance, $redirect_to );
							$redirect_to = apply_filters( 'register_redirect', $redirect_to );
							wp_redirect( $redirect_to );
							exit();
						}
					}
					break;
				case 'login' :
				default:
					$secure_cookie = '';
					$interim_login = isset( $_REQUEST['interim-login'] );

					// If the user wants ssl but the session is not ssl, force a secure cookie.
					if ( !empty( $_POST['log'] ) && !force_ssl_admin() ) {
						$user_name = sanitize_user( $_POST['log'] );
						if ( $user = get_userdatabylogin( $user_name ) ) {
							if ( get_user_option( 'use_ssl', $user->ID ) ) {
								$secure_cookie = true;
								force_ssl_admin( true );
							}
						}
					}

					if ( isset( $_REQUEST['redirect_to'] ) && !empty( $_REQUEST['redirect_to'] ) ) {
						$redirect_to = $_REQUEST['redirect_to'];
						// Redirect to https if user wants ssl
						if ( $secure_cookie && false !== strpos( $redirect_to, 'wp-admin' ) )
							$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
					} else {
						$redirect_to = admin_url();
					}
					
					$reauth = empty( $_REQUEST['reauth'] ) ? false : true;

					// If the user was redirected to a secure login form from a non-secure admin page, and secure login is required but secure admin is not, then don't use a secure
					// cookie and redirect back to the referring non-secure admin page.  This allows logins to always be POSTed over SSL while allowing the user to choose visiting
					// the admin via http or https.
					if ( !$secure_cookie && is_ssl() && force_ssl_login() && !force_ssl_admin() && ( 0 !== strpos( $redirect_to, 'https' ) ) && ( 0 === strpos( $redirect_to, 'http' ) ) )
						$secure_cookie = false;
						
					if ( $http_post ) {
						$user = wp_signon( '', $secure_cookie );

						$this->redirect_to = apply_filters( 'login_redirect', $redirect_to, isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '', $user );

						if ( $http_post && !is_wp_error( $user ) && !$reauth ) {
							// If the user can't edit posts, send them to their profile.
							if ( !$user->has_cap( 'edit_posts' ) && ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) )
								$redirect_to = admin_url( 'profile.php' );
							wp_safe_redirect( $redirect_to );
							exit();
						}
						
						$errors = $user;
					}
					
					// Clear errors if loggedout is set.
					if ( !empty( $_GET['loggedout'] ) || $reauth )
						$errors = new WP_Error();

					// If cookies are disabled we can't log in even with a valid user+pass
					if ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[TEST_COOKIE] ) )
						$errors->add( 'test_cookie', __( '<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href="http://www.google.com/cookies.html">enable cookies</a> to use WordPress.', 'theme-my-login' ) );

					// Some parts of this script use the main login form to display a message
					if		( isset( $_GET['loggedout'] ) && TRUE == $_GET['loggedout'] )
						$errors->add( 'loggedout', __( 'You are now logged out.', 'theme-my-login' ), 'message' );
					elseif	( isset( $_GET['registration'] ) && 'disabled' == $_GET['registration'] )
						$errors->add( 'registerdisabled', __( 'User registration is currently not allowed.', 'theme-my-login' ) );
					elseif	( isset( $_GET['checkemail'] ) && 'confirm' == $_GET['checkemail'] )
						$errors->add( 'confirm', __( 'Check your e-mail for the confirmation link.', 'theme-my-login' ), 'message' );
					elseif	( isset( $_GET['checkemail'] ) && 'newpass' == $_GET['checkemail'] )
						$errors->add( 'newpass', __( 'Check your e-mail for your new password.', 'theme-my-login' ), 'message' );
					elseif	( isset( $_GET['checkemail'] ) && 'registered' == $_GET['checkemail'] )
						$errors->add( 'registered', __( 'Registration complete. Please check your e-mail.', 'theme-my-login' ), 'message' );
					elseif	( $interim_login )
						$errors->add( 'expired', __( 'Your session has expired. Please log-in again.', 'theme-my-login' ), 'message' );
						
					// Clear any stale cookies.
					if ( $reauth )
						wp_clear_auth_cookie();
					break;
			} // end switch
		} // endif has_filter()
	}

	/**
	 * Changes the_title() to reflect the current action
	 *
	 * Callback for 'the_title' hook in the_title()
	 *
	 * @see the_title()
	 * @since 6.0
	 * @acess public
	 *
	 * @param string $title The current post title
	 * @param int $post_id The current post ID
	 * @return string The modified post title
	 */
	function the_title( $title, $post_id = '' ) {
		if ( is_admin() && !defined( 'IS_PROFILE_PAGE' ) )
			return $title;
			
		// No post ID until WP 3.0!
		if ( empty( $post_id ) ) {
			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s", $title ) );
		}
		
		if ( $this->options['page_id'] == $post_id ) {
			if ( $this->doing_pagelist ) {
				$title = is_user_logged_in() ? __( 'Log Out', 'theme-my-login' ) : __( 'Log In', 'theme-my-login' );
			} else {
				$action = empty( $this->request_instance ) ? $this->request_action : 'login';
				$title = Theme_My_Login_Template::get_title( $action );
			}
		}
		return $title;
	}
	
	/**
	 * Changes single_post_title() to reflect the current action
	 *
	 * Callback for 'single_post_title' hook in single_post_title()
	 *
	 * @see single_post_title()
	 * @since 6.0
	 * @access public
	 *
	 * @param string $title The current post title
	 * @return string The modified post title
	 */
	function single_post_title( $title ) {
		if ( is_page( $this->options['page_id'] ) ) {
			$action = empty( $this->request_instance ) ? $this->request_action : 'login';
			$title = Theme_My_Login_Template::get_title( $action );
		}
		return $title;
	}
	
	/**
	 * Excludes TML page if set in the admin
	 *
	 * Callback for 'wp_list_pages_excludes' hook in wp_list_pages()
	 *
	 * @see wp_list_pages()
	 * @since 6.0
	 * @access public
	 *
	 * @param array $exclude_array Array of excluded pages
	 * @return array Modified array of excluded pages
	 */
	function list_pages_excludes( $exclude_array ) {
		// This makes the_title() filter work properly
		$this->doing_pagelist = true;
		
		$exclude_array = (array) $exclude_array;
		if ( !$this->options['show_page'] )
			$exclude_array[] = $this->options['page_id'];
		return $exclude_array;
	}
	
	/**
	 * Filters the output of wp_list_pages()
	 *
	 * Callback for 'wp_list_pages' hook in wp_list_pages()
	 *
	 * @see wp_list_pages()
	 * @since 6.0
	 * @access public
	 *
	 * @param string $output The generated HTML output
	 * @return string The modified HTML output
	 */
	function wp_list_pages( $output ) {
		// The second part to make the_title() filter work properly
		$this->doing_pagelist = false;
		return $output;
	}
	
	/**
	 * Changes permalink to logout link if user is logged in
	 *
	 * Callback for 'page_link' hook in get_page_link()
	 *
	 * @see get_page_link()
	 * @since 6.0
	 * @access public
	 *
	 * @param string $link The link
	 * @param int $id The current post ID
	 * @return string The modified link
	 */
	function page_link( $link, $id ) {
		if ( !$this->doing_pagelist )
			return $link;
		if ( $id == $this->options['page_id'] ) {
			if ( is_user_logged_in() && ( !isset( $_REQUEST['action'] ) || 'logout' != $_REQUEST['action'] ) )
				$link = wp_nonce_url( add_query_arg( 'action', 'logout', $link ), 'log-out' );
		}
		return $link;
	}

	/**
	 * Handler for 'theme-my-login' shortcode
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param string|array $atts Attributes passed from the shortcode
	 * @return string HTML output from Theme_My_Login_Template->display()
	 */
	function shortcode( $atts = '' ) {
	
		if ( isset( $atts['instance_id'] ) ) {
			$atts['instance'] = $atts['instance_id'];
			unset( $atts['instance_id'] );
		}
		
		if ( !isset( $atts['instance'] ) )
			$atts['instance'] = $this->get_new_instance();
		elseif ( 'page' == $atts['instance'] || 'tml-page' == $atts['instance'] )
			$atts['instance'] = '';
			
		if ( $this->request_instance == $atts['instance'] )
			$atts['is_active'] = 1;
			
		if ( !isset( $atts['redirect_to'] ) )
			$atts['redirect_to'] = $this->redirect_to;
		
		$template =& new Theme_My_Login_Template( $atts, $this->errors );
		
		return $template->display();
	}
	
	/**
	 * Handler for 'theme-my-login-page' shortcode
	 *
	 * Essentially a wrapper for the 'theme-my-login' shortcode.
	 * Works by automatically setting some attributes to make the shortcode work properly for the main login page
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param string|array $atts Attributes passed from the shortcode
	 * @return string HTML output from Theme_My_Login_Template->display()
	 */
	function page_shortcode( $atts = '' ) {
		if ( !is_array( $atts ) )
			$atts = array();
			
		$atts['instance'] = 'page';
		
		if ( !isset( $atts['show_title'] ) )
			$atts['show_title'] = 0;
		if ( !isset( $atts['before_widget'] ) )
			$atts['before_widget'] = '';
		if ( !isset( $atts['after_widget'] ) )
			$atts['after_widget'] = '';
			
		return $this->shortcode( $atts );
	}

	/**
	 * Incremenets $this->count and returns it
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @return int New value of $this->count
	 */
	function get_new_instance() {
		$this->count++;
		return $this->count;
	}
	
	/**
	 * Returns current URL
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param string $path Optionally append path to the current URL
	 * @return string URL with optional path appended
	 */
	function get_current_url( $path = '' ) {
		$url = remove_query_arg( array( 'instance', 'action', 'checkemail', 'error', 'loggedout', 'registered', 'redirect_to', 'updated', 'key', '_wpnonce' ) );
		if ( !empty( $path ) ) {
			$path = wp_parse_args( $path );
			$url = add_query_arg( $path, $url );
		}
		return $url;
	}
	
	/**
	 * Enqueues the specified sylesheet
	 *
	 * First looks in theme/template directories for the stylesheet, falling back to plugin directory
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param string $file Filename of stylesheet to load
	 * @return string Path to stylesheet
	 */
	function get_stylesheet( $file = 'theme-my-login.css' ) {
		if ( file_exists( get_stylesheet_directory() . '/' . $file ) )
			$stylesheet = get_stylesheet_directory_uri() . '/' . $file;
		elseif ( file_exists( get_template_directory() . '/' . $file ) )
			$stylesheet = get_template_directory_uri() . '/' . $file;
		else
			$stylesheet = plugins_url( '/theme-my-login/' . $file );
		return $stylesheet;
	}
	
	/**
	 * Attaches class methods to WordPress hooks
	 *
	 * @since 6.0
	 * @access public
	 */
	function load() {
		$this->request_action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'login';
		$this->request_instance = isset( $_REQUEST['instance'] ) ? $_REQUEST['instance'] : '';
		
		add_action( 'parse_request', array( &$this, 'the_request' ) );
		
		add_filter( 'the_title', array( &$this, 'the_title' ), 10, 2 );
		add_filter( 'single_post_title', array( &$this, 'single_post_title' ) );
		
		add_filter( 'page_link', array( &$this, 'page_link' ), 10, 2 );
		
		add_filter( 'wp_list_pages_excludes', array( &$this, 'list_pages_excludes' ) );
		add_filter( 'wp_list_pages', array( &$this, 'wp_list_pages' ) );
		
		add_shortcode( 'theme-my-login-page', array( &$this, 'page_shortcode' ) );
		add_shortcode( 'theme-my-login', array( &$this, 'shortcode' ) );
	}
	
	/**
	 * Handles sending password retrieval email to user.
	 *
	 * @since 6.0
	 * @access public
	 * @uses $wpdb WordPress Database object
	 *
	 * @return bool|WP_Error True: when finish. WP_Error on error
	 */
	function retrieve_password() {
		global $wpdb;

		$errors = new WP_Error();

		if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) )
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Enter a username or e-mail address.', 'theme-my-login' ) );

		if ( strpos( $_POST['user_login'], '@' ) ) {
			$user_data = get_user_by_email( trim( $_POST['user_login'] ) );
			if ( empty( $user_data ) )
				$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: There is no user registered with that email address.', 'theme-my-login' ) );
		} else {
			$login = trim( $_POST['user_login'] );
			$user_data = get_userdatabylogin( $login );
		}

		do_action( 'lostpassword_post' );

		if ( $errors->get_error_code() )
			return $errors;

		if ( !$user_data ) {
			$errors->add( 'invalidcombo', __( '<strong>ERROR</strong>: Invalid username or e-mail.', 'theme-my-login' ) );
			return $errors;
		}

		// redefining user_login ensures we return the right case in the email
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;

		do_action( 'retreive_password', $user_login );  // Misspelled and deprecated
		do_action( 'retrieve_password', $user_login );

		$allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

		if ( !$allow )
			return new WP_Error( 'no_password_reset', __( 'Password reset is not allowed for this user', 'theme-my-login' ) );
		else if ( is_wp_error( $allow ) )
			return $allow;

		$key = $wpdb->get_var( $wpdb->prepare( "SELECT user_activation_key FROM $wpdb->users WHERE user_login = %s", $user_login ) );
		if ( empty( $key ) ) {
			// Generate something random for a key...
			$key = wp_generate_password( 20, false );
			do_action( 'retrieve_password_key', $user_login, $key );
			// Now insert the new md5 key into the db
			$wpdb->update( $wpdb->users, array( 'user_activation_key' => $key ), array( 'user_login' => $user_login ) );
		}
		$site_url = ( function_exists( 'network_site_url' ) ) ? 'network_site_url' : 'site_url'; // Pre 3.0 compatibility
		$message = __( 'Someone has asked to reset the password for the following site and username.', 'theme-my-login' ) . "\r\n\r\n";
		$message .= $site_url() . "\r\n\r\n";
		$message .= sprintf( __( 'Username: %s', 'theme-my-login' ), $user_login ) . "\r\n\r\n";
		$message .= __( 'To reset your password visit the following address, otherwise just ignore this email and nothing will happen.', 'theme-my-login' ) . "\r\n\r\n";
		$message .= $site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . "\r\n";

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$blogname = $GLOBALS['current_site']->site_name;
		} else {
			// The blogname option is escaped with esc_html on the way into the database in sanitize_option
			// we want to reverse this for the plain text arena of emails.
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}
		
		$title = sprintf( __( '[%s] Password Reset', 'theme-my-login' ), $blogname );

		$title = apply_filters( 'retrieve_password_title', $title );
		$message = apply_filters( 'retrieve_password_message', $message, $key );

		if ( $message && !wp_mail( $user_email, $title, $message ) )
			wp_die( __( 'The e-mail could not be sent.', 'theme-my-login' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function...', 'theme-my-login' ) );

		return true;
	}

	/**
	 * Handles resetting the user's password.
	 *
	 * @since 6.0
	 * @access public
	 * @uses $wpdb WordPress Database object
	 *
	 * @param string $key Hash to validate sending user's password
	 * @return bool|WP_Error
	 */
	function reset_password( $key, $login ) {
		global $wpdb;

		$key = preg_replace( '/[^a-z0-9]/i', '', $key );

		if ( empty( $key ) || !is_string( $key ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'theme-my-login' ) );

		if ( empty( $login ) || !is_string( $login ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'theme-my-login' ) );

		$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE user_activation_key = %s AND user_login = %s", $key, $login ) );
		if ( empty( $user ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'theme-my-login' ) );

		// Generate something random for a password...
		$new_pass = wp_generate_password();

		do_action( 'password_reset', $user, $new_pass );
		
		$site_url = ( function_exists( 'network_site_url' ) ) ? 'network_site_url' : 'site_url'; // Pre 3.0 compatibility

		wp_set_password( $new_pass, $user->ID );
		update_user_option( $user->ID, 'default_password_nag', true, true ); //Set up the Password change nag.
		$message  = sprintf( __( 'Username: %s', 'theme-my-login' ), $user->user_login ) . "\r\n";
		$message .= sprintf( __( 'Password: %s', 'theme-my-login' ), $new_pass ) . "\r\n";
		$message .= $site_url( 'wp-login.php', 'login' ) . "\r\n";

		if ( function_exists( 'is_multisite') && is_multisite() ) {
			$blogname = $GLOBALS['current_site']->site_name;
		} else {
			// The blogname option is escaped with esc_html on the way into the database in sanitize_option
			// we want to reverse this for the plain text arena of emails.
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}
		
		$title = sprintf( __( '[%s] Your new password', 'theme-my-login' ), $blogname );

		$title = apply_filters( 'password_reset_title', $title );
		$message = apply_filters( 'password_reset_message', $message, $new_pass );

		if ( $message && !wp_mail( $user->user_email, $title, $message ) )
			wp_die( __( 'The e-mail could not be sent.', 'theme-my-login' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function...', 'theme-my-login' ) );

		wp_password_change_notification( $user );

		return true;
	}

	/**
	 * Handles registering a new user.
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param string $user_login User's username for logging in
	 * @param string $user_email User's email address to send password and add
	 * @return int|WP_Error Either user's ID or error on failure.
	 */
	function register_new_user( $user_login, $user_email ) {
		$errors = new WP_Error();

		$sanitized_user_login = sanitize_user( $user_login );
		$user_email = apply_filters( 'user_registration_email', $user_email );

		// Check the username
		if ( $sanitized_user_login == '' ) {
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Please enter a username.', 'theme-my-login' ) );
		} elseif ( !validate_username( $user_login ) ) {
			$errors->add( 'invalid_username', __( '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.', 'theme-my-login' ) );
			$sanitized_user_login = '';
		} elseif ( username_exists( $sanitized_user_login ) ) {
			$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.', 'theme-my-login' ) );
		}

		// Check the e-mail address
		if ( '' == $user_email ) {
			$errors->add( 'empty_email', __( '<strong>ERROR</strong>: Please type your e-mail address.', 'theme-my-login' ) );
		} elseif ( !is_email( $user_email ) ) {
			$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.', 'theme-my-login' ) );
			$user_email = '';
		} elseif ( email_exists( $user_email ) ) {
			$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.', 'theme-my-login' ) );
		}

		do_action( 'register_post', $sanitized_user_login, $user_email, $errors );

		$errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );

		if ( $errors->get_error_code() )
			return $errors;

		$user_pass = apply_filters( 'user_registration_pass', wp_generate_password() );
		$user_id = wp_create_user( $sanitized_user_login, $user_pass, $user_email );
		if ( !$user_id ) {
			$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !', 'theme-my-login' ), get_option( 'admin_email' ) ) );
			return $errors;
		}

		update_user_option( $user_id, 'default_password_nag', true, true ); //Set up the Password change nag.

		$this->new_user_notification( $user_id, $user_pass );

		return $user_id;
	}

	/**
	 * Notify the blog admin of a new user, normally via email.
	 *
	 * @since 6.0
	 * @access public
	 *
	 * @param int $user_id User ID
	 * @param string $plaintext_pass Optional. The user's plaintext password
	 */
	function new_user_notification( $user_id, $plaintext_pass = '' ) {
		$user = new WP_User( $user_id );
		
		do_action( 'new_user_notification', $user_id, $plaintext_pass );

		$user_login = stripslashes( $user->user_login );
		$user_email = stripslashes( $user->user_email );

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$blogname = $GLOBALS['current_site']->site_name;
		} else {
			// The blogname option is escaped with esc_html on the way into the database in sanitize_option
			// we want to reverse this for the plain text arena of emails.
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		if ( apply_filters( 'send_admin_new_user_notification', true ) ) {
			$message  = sprintf( __( 'New user registration on your site %s:', 'theme-my-login' ), $blogname ) . "\r\n\r\n";
			$message .= sprintf( __( 'Username: %s', 'theme-my-login' ), $user_login ) . "\r\n\r\n";
			$message .= sprintf( __( 'E-mail: %s', 'theme-my-login' ), $user_email ) . "\r\n";
		
			$title = sprintf( __( '[%s] New User Registration', 'theme-my-login' ), $blogname );
		
			$title = apply_filters( 'admin_new_user_notification_title', $title, $user_id );
			$message = apply_filters( 'admin_new_user_notification_message', $message, $user_id );

			@wp_mail( get_option( 'admin_email' ), $title, $message );		
		}

		if ( empty( $plaintext_pass ) )
			return;
			
		if ( apply_filters( 'send_new_user_notification', true ) ) {
			$message  = sprintf( __( 'Username: %s', 'theme-my-login' ), $user_login ) . "\r\n";
			$message .= sprintf( __( 'Password: %s', 'theme-my-login' ), $plaintext_pass ) . "\r\n";
			$message .= wp_login_url() . "\r\n";
		
			$title = sprintf( __( '[%s] Your username and password', 'theme-my-login' ), $blogname);

			$title = apply_filters( 'new_user_notification_title', $title, $user_id );
			$message = apply_filters( 'new_user_notification_message', $message, $plaintext_pass, $user_id );
		
			wp_mail( $user_email, $title, $message );
		}
	}
}
endif;

?>