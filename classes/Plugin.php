<?php

namespace ReallySpecific\BetterLLMStxt;

use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Plugin as RS_Plugin;

final class Plugin extends RS_Plugin {

	protected static $self = null;

	private array $indexes = [];

	public function setup(): void {
		$index_args = [
			'post_types' => array_keys(
				array_filter(
					$this->get_setting( key: 'post_types' ) ?: [],
					fn( $post_type ) => $post_type['enabled']
				)
			),
		];
		$this->register_index( new Indexes\Abbreviated( $index_args ) );
		$this->register_index( new Indexes\Full( $index_args ) );

		$this->attach_service( 'init', 'cli',         __NAMESPACE__ . '\\CLI' );
		$this->attach_service( 'init', 'integration', __NAMESPACE__ . '\\Integration' );
	}

	public function register_settings( $namespaces = [] ): void {

		add_action( 'rs_util_settings_enqueue_admin_scripts', [ $this, 'enqueue_assets' ] );

 		parent::register_settings( [
			'default' => [
				'slug'        => 'llms-txt',
				'capability'  => 'manage_options',
				'parent'      => 'options-general.php',
				'option_name' => 'llms-txt-config',
			],
			...$namespaces,
		] );
	}

	public function delete_current_indexes() {
		foreach( $this->indexes as $index ) {
			$index->delete_file();
		}
	}

	public function enqueue_assets() {

		//$file = $this->get_url( 'assets/llms-settings.js' );
		//wp_enqueue_script( 'llms-settings', $file, [], plugin()->version );

	}

	public function install_settings( array $settings = [] ): void {

		parent::install_settings( [
			'default' => [
				'menu_title' => __( 'LLMS.txt', 'better-llms-txt' ),
				'page_title' => __( 'LLMS Index Settings', 'better-llms-txt' ),
				'form_title' => null,
			],
			...$settings,
		] );

		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		$this->settings()->add_section( 'post_type_options',
			props: [
				//'title'     => __( 'Indexed Post Types', 'better-llms-txt' ),
				'tab_label' => 'Post Types',
				'fields'    => []
			]
		);

		$this->settings()->add_section( 'taxonomy_options',
			props: [
				//'title'     => __( 'Indexed Taxonomies', 'better-llms-txt' ),
				'tab_label' => 'Taxonomies',
				'fields'    => []
			]
		);

		$index = 0;
		foreach( $post_types as $post_type ) {
			$index += 1;
			
			$this->settings()->add_field( [
				'name'          => "post_types.{$post_type->name}.enabled",
				'group'         => $post_type->label,
				'type'          => 'checkbox',
				'label'         => __( 'Enable this post type', 'better-llms-txt' ),
				'default'       => true,
				'toggles_group' => true,
			], 'post_type_options' );
			$this->settings()->add_field( [
				'name'                 => "post_types.{$post_type->name}.label",
				'group'                => $post_type->label,
				'subgroup_toggled_by'  => "post_type_options__post_types.{$post_type->name}.enabled",
				'type'                 => 'text',
				'label'                => __( 'Override Label', 'better-llms-txt' ),
				'description'          => __( 'Override the label used in the index.', 'better-llms-txt' ),
				'default'              => '',
				'placeholder'          => $post_type->label,
			], 'post_type_options' );
			$this->settings()->add_field( [
				'name'                 => "post_types.{$post_type->name}.orderby",
				'group'                => $post_type->label,
				'subgroup'             => __( 'Sort by', 'better-llms-txt' ),
				'type'                 => 'select',
				'label'                => '',
				'options' => [
					'publish_date' => __( 'Last Modified', 'better-llms-txt' ),
					'title'        => __( 'Title', 'better-llms-txt' ),
				],
				'default'     => 'publish_date',
				
			], 'post_type_options' );
			$this->settings()->add_field( [
				'name'        => "post_types.{$post_type->name}.order",
				'group'       => $post_type->label,
				'subgroup'    => __( 'Sort by', 'better-llms-txt' ),
				'type'        => 'select',
				'options'     => [
					'desc' => __( 'Descending', 'better-llms-txt' ),
					'asc' => __( 'Ascending', 'better-llms-txt' ),
				],
				'label'       => '',
				'default'     => 'desc',
			], 'post_type_options' );
			$this->settings()->add_field( [
				'name'          => "post_types.{$post_type->name}.ordering",
				'group'         => $post_type->label,
				'type'          => 'hidden',
				'label'         => '',
				'default'       => $index,
			], 'post_type_options' );

		}

		$public_taxonomies = get_taxonomies(
			[ 'public' => true ],
			'objects'
		);
		uasort( $public_taxonomies, fn( $a, $b ) => strcmp( $a->label, $b->label ) );
		uasort( $public_taxonomies, fn( $a, $b ) => strcmp( $a->object_type[0], $b->object_type[0] ) );
		foreach( $public_taxonomies as $taxonomy ) {
			$this->settings()->add_field( [
				'name'        => "taxonomies.{$taxonomy->name}.enabled",
				'group'       => $taxonomy->label,
				'group_desc'  => 'Used by `' . implode( '`, `', explode( ' ', $taxonomy->object_type[0] ) ) . '`',
				'type'        => 'checkbox',
				'label'       => 'Show in index',
				'value_label' => __( 'Show list of ', 'better-llms-txt' ) . strtolower( $taxonomy->labels->singular_name . ' terms' ),
				'default'     => false,
			], 'taxonomy_options' );
			$this->settings()->add_field( [
				'name'                 => "taxonomies.{$taxonomy->name}.label",
				'group'                => $taxonomy->label,
				'subgroup_toggled_by'  => "taxonomy_options__taxonomies.{$taxonomy->name}.enabled",
				'type'                 => 'text',
				'label'                => __( 'Override Label', 'better-llms-txt' ),
				'description'          => __( 'Override the label used in the index.', 'better-llms-txt' ),
				'default'              => '',
				'placeholder'          => $taxonomy->label,
			], 'taxonomy_options' );
			$this->settings()->add_field( [
				'name'                => "taxonomies.{$taxonomy->name}.orderby",
				'group'               => $taxonomy->label,
				'subgroup'            => 'Sorting',
				'subgroup_toggled_by' => "taxonomy_options__taxonomies.{$taxonomy->name}.enabled",
				'type'                => 'select',
				'label'               => '',
				'options' => [
					''         => __( 'Default ordering', 'better-llms-txt' ),
					'name'     => __( 'Order by name', 'better-llms-txt' ),
				],
			], 'taxonomy_options' );
			$this->settings()->add_field( [
				'name'        => "taxonomies.{$taxonomy->name}.order",
				'group'       => $taxonomy->label,
				'subgroup'    => 'Sorting',
				'type'        => 'select',
				'default'     => 'asc',
				'label'       => '',
				'options'     => [
					'asc'  => __( 'Ascending', 'better-llms-txt' ),
					'desc' => __( 'Descending', 'better-llms-txt' ),
				],
				'toggled_by'  => "taxonomy_options__taxonomies.{$taxonomy->name}.orderby",
			], 'taxonomy_options' );
			if ( $taxonomy->hierarchical ) {
				$this->settings()->add_field( [
					'name'        => "taxonomies.{$taxonomy->name}.show_children",
					'group'       => $taxonomy->label,
					'type'        => 'checkbox',
					'default'     => false,
					'label'       => 'Show term children',
					'toggled_by'  => "taxonomy_options__taxonomies.{$taxonomy->name}.enabled",
				], 'taxonomy_options' );
			}
			$terms = get_terms( [ 'taxonomy' => $taxonomy->name, 'hide_empty' => false, 'fields' => 'id=>name' ] );
			$this->settings()->add_field( [
				'name'        => "taxonomies.{$taxonomy->name}.terms",
				'label'       => __( 'Show posts from these terms', 'better-llms-txt' ),
				'group'       => $taxonomy->label,
				'type'        => 'select',
				'multiple'    => true,
				'enable_tom'  => true,
				'options'     => $terms,
			], 'taxonomy_options' );
		}

		do_action( 'llms_txt_settings_ready', $this );

	}

	public function register_index( $index ) {

		$this->indexes[ $index->filename ] = $index;

		$index->register( $this );

	}

	public function find_index( string $filepath ) {
		foreach ( $this->indexes as $index ) {
			if ( $index->output_file === $filepath ) {
				return $index;
			}
		}
		return null;
	}

}