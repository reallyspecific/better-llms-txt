<?php
/**
 * Plugin Name: Better LLMS.txt
 * Plugin URI: https://github.com/reallyspecific/better-llms-txt
 * Update URI: https://github.com/reallyspecific/better-llms-txt
 * Description: A plugin for making comprehensive index files for LLMs to parse.
 * Version: 1.0.0-rc3
 * Author: Really Specific
 * Author URI: https://github.com/reallyspecific
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

namespace ReallySpecific\BetterLLMStxt;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}


function &plugin() {

	static $instance = null;
	if ( isset( $instance ) ) {
		return $instance;
	}

	if ( ! class_exists( 'ReallySpecific\BetterLLMStxt\Plugin' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	}
	if ( ! function_exists( 'ReallySpecific\BetterLLMStxt\Dependencies\Utils\setup' ) ) {
		require_once __DIR__ . '/dependencies/reallyspecific/wp-utils/load.php';
	}

	$instance = Plugin::new( [
		'name'        => 'Better LLMS.txt',
		'slug'        => 'better-llms-txt',
		'i18n_domain' => 'better-llms-txt',
		'file'        => __FILE__,
	] );

	return $instance;

}

plugin();