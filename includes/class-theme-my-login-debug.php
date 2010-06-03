<?php

if ( !class_exists( 'Theme_My_Login_Debug' ) ) :
/**
 * Theme My Login Debug class
 *
 * @since 6.0
 */
class Theme_My_Login_Debug {
	/**
	 * Holds memory usage at construct
	 *
	 * @since 6.0
	 * @access public
	 * @var int
	 */
	var $initial_memory = 0;
	
	/**
	 * Holds memory usage at each major hook
	 *
	 * @since 6.0
	 * @access public
	 * @var array
	 */
	var $hook_usage = array();

	/**
	 * Holds array of which hooks to record
	 *
	 * @since 6.0
	 * @access public
	 * @var array
	 */
	var $core_hooks = array();
	
	/**
	 * Records current memory usage at specific hooks
	 *
	 * @since 6.0
	 * @access public
	 */
	function record_memory_usage() {
		$current_filter = current_filter();
		if ( in_array( $current_filter, $this->core_hooks ) || substr( $current_filter, 0, 4 ) == 'tml_' )
			$this->core_hook_usage[$current_filter] = memory_get_usage();
	}
	
	function dump_memory_usage() {
		echo '<h2>Memory Usage Summary</h2>' . "\n";
		echo '<ul>' . "\n";
		echo '<li><strong>Initial Usage</strong>: ' . $this->num_bytes_to_string( $this->initial_memory ) . '</li>' . "\n";
		echo '<li><strong>Peak Usage</strong>: ' . $this->num_bytes_to_string( memory_get_peak_usage() ) . '</li>' . "\n";
		echo '<li><strong>End Usage</strong>: ' . $this->num_bytes_to_string( memory_get_usage() ) . '</li>' . "\n";
		echo '</ul>';
		
		echo '<h2>Memory Usage by Hook</h2>' . "\n";
		echo '<ul>' . "\n";
		foreach ( $this->core_hook_usage as $hook => $memory ) {
			echo '<li><strong>' . $hook . '</strong>: ' . $this->num_bytes_to_string( $memory ) . '</li>' . "\n";
		}
		echo '</ul>' . "\n";
	}

	function string_to_num_bytes( $value ) {
		if ( is_numeric( $value ) ) {
			return $value;
		} else {
			$qty = substr( $value, 0, strlen( $value ) - 1 );
			$unit = strtolower( substr( $value, -1 ) );
			switch ( $unit ) {
				case 'k':
					$qty *= 1024;
					break;
				case 'm':
					$qty *= pow( 1024, 2 );
					break;
				case 'g':
					$qty *= pow( 1024, 3 );
					break;
				default:
					$qty = intval( $qty );
			}
			return $qty;
		}
	}
	
	function num_bytes_to_string( $value, $unit = 'm' ) {
		if ( !is_numeric( $value ) )
			$value = $this->string_to_num_bytes( $value );
		switch ( $unit ) {
			case 'k':
				$value /=  1024;
				break;
			case 'm':
				$value /= pow( 1024, 2 );
				break;
			case 'g':
				$value /= pow( 1024, 3);
				break;
		}
		$value = number_format( $value, 2 );
		$unit = strtoupper( $unit );
		return $value . $unit;
	}
	
	function Theme_My_Login_Debug() {
		$this->__construct();
	}
	
	function __construct() {
		$this->initial_memory = memory_get_usage();
		$this->core_hooks = array(
			'muplugins_loaded',
			'load_textdomain',
			'update_option',
			'plugins_loaded',
			'load_textdomain',
			'sanitize_comment_cookies',
			'setup_theme',
			'load_textdomain',
			'auth_cookie_malformed',
			'set_current_user',
			'init',
			'widgets_init',
			'load_textdomain',
			'parse_request',
			'send_headers',
			'pre_get_posts',
			'posts_selection',
			'wp',
			'template_redirect',
			'get_header',
			'wp_head',
			'wp_enqueue_scripts',
			'wp_print_styles',
			'wp_print_scripts',
			'loop_start',
			'the_post',
			'loop_end',
			'get_footer',
			'wp_footer',
			'wp_print_footer_scripts'
			);
		add_action( 'plugins_loaded', array( &$this, 'record_memory_usage' ) );
		add_action( 'all', array( &$this, 'record_memory_usage' ) );
		add_action( 'wp_footer', array( &$this, 'dump_memory_usage' ) );
	}
}
endif;

$Theme_My_Login_Debug =& new Theme_My_Login_Debug();

?>