<?php

namespace ReallySpecific\BetterLLMStxt\Indexes;

use ReallySpecific\BetterLLMStxt\Index;

use function ReallySpecific\BetterLLMStxt\plugin;

use Exception;
use WP_Error;

class Abbreviated extends Index {

	public function __construct( $args = [] ) {

		parent::__construct( [
			'filename'    => 'llms.txt',
			'index_slug'  => 'abbreviated',
			...$args
		] );

		if ( $this->get_setting( 'generate_on_save' ) ) {
			add_action( 'save_post', [ $this, 'maybe_generate_llms_text' ], 10, 2 );
		}

	}

	public function install_settings() {

		parent::install_settings();

		plugin()->settings()->add_field( [
			'name'        => $this->settings_index . '.generate_on_save',
			'type'        => 'checkbox',
			'label'       => __( 'Generate on save', 'better-llms-txt' ),
			'value_label' => __( 'Enable', 'better-llms-txt' ),
			'description' => __( 'An abbreviated index will be generated when an enabled post is saved', 'better-llms-txt' ),
			'default'     => true,
		], $this->section_name );

		plugin()->settings()->add_field( [
			'name'        => $this->settings_index . '.max_links',
			'type'        => 'number',
			'label'       => __( 'Max Links', 'better-llms-txt' ),
			'description' => __( 'The maximum number of links per post type to include in the abbreviated index', 'better-llms-txt' ),
			'default'     => 5,
		], $this->section_name );

		

	}

	public function build( array $args = [] ) : string|WP_Error {

		$max_links = $this->get_setting( 'max_links' );

		return parent::build( [
			'max_links' => $max_links,
			...$args
		] );

	}
	public function maybe_generate_llms_text( $post_id, $post ) {

		if ( defined( 'DOING_AUTOSAVE' ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		$post_type = get_post_type( $post );

		if ( ! in_array( $post_type, $this->post_types ) ) {
			return;
		}

		$this->build();

	}

}

