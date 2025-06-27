<?php

namespace ReallySpecific\BetterLLMStxt\Indexes;

use ReallySpecific\BetterLLMStxt\Index;

use function ReallySpecific\BetterLLMStxt\plugin;

use Exception;
use WP_Error;

class Abbreviated extends Index {

	protected $sections = [];

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

	/**
	 * Writes the current Index buffer to the output path
	 *
	 * @return string|WP_Error returns the output path on success or a WP_Error on failure
	 */
	protected function write() : string|WP_Error {
		$section_order = $this->get_setting( 'ordering' );
		if ( ! empty( $section_order ) ) {

			$ordering = [];

			foreach( $this->sections as $name => $section ) {
				$name_fixed = str_replace( '.', '_', $name );
				$ordering[ $name ] = $section_order[ $name_fixed ] ?? null;
			}
			asort( $ordering );
			
			$sorted = [];
			foreach( $ordering as $name => $order ) {
				$sorted[ $name ] = $this->sections[ $name ];
			}
			$this->sections = $sorted;
		}

		return parent::write();
	}

	public function install_settings() {

		parent::install_settings();

		plugin()->settings()->add_field( [
			'name'         => $this->settings_index . '.generate_on_save',
			'type'         => 'checkbox',
			'label'        => __( 'Generate on save', 'better-llms-txt' ),
			'value_label'  => __( 'Enable', 'better-llms-txt' ),
			'description'  => __( 'An abbreviated index will be generated when an enabled post is saved', 'better-llms-txt' ),
			'default'      => true,
		], $this->section_name );

		plugin()->settings()->add_field( [
			'name'        => $this->settings_index . '.max_links',
			'type'        => 'number',
			'label'       => __( 'Max Links', 'better-llms-txt' ),
			'description' => __( 'The maximum number of links per post type to include in the abbreviated index', 'better-llms-txt' ),
			'default'     => 5,
		], $this->section_name );

		$all_post_types = get_post_types( [], 'objects' );

		$post_types = plugin()->get_setting( key: 'post_types' );
		$taxonomies = plugin()->get_setting( key: 'taxonomies' );

		$ordering = 0;

		foreach( $all_post_types as $post_type ) {
			$post_type_name = $post_type->name;

			if ( $post_types[ $post_type_name ]['enabled'] ?? false ) {
				$label = $post_types[ $post_type_name ]['label'] ?: $post_type->label;
				plugin()->settings()->add_field( [
					'name'        => "{$this->settings_index}.ordering.{$post_type_name}",
					'group'       => 'Contents',
					'subgroup'    => $label,
					'type'        => 'hidden',
					'default'     => ++ $ordering,
					'ordering'    => "{$this->settings_index}.ordering",
				], $this->section_name );

				if ( $post_type->rest_base ) {
					$posts_search_url = get_rest_url( null, 'wp/v2/' . $post_type->rest_base );
					$posts_search_url = add_query_arg( 'ignore_sticky', true, $posts_search_url );
					$posts_search_url = add_query_arg( 'search_columns', [ 'post_title' ], $posts_search_url );
					$posts_search_url = add_query_arg( 'search', '@query', $posts_search_url );

					plugin()->settings()->add_field( [
						'name'          => "{$this->settings_index}.stickies.post_type_{$post_type_name}",
						'group'       => 'Contents',
						'subgroup'    => $label,
						'type'        => 'sortable',
						'multiple'    => true,
						'data'        => $posts_search_url,
						'data_value'  => 'id',
						'data_label'  => 'title.rendered',
						'enable_tom'  => true,
						'placeholder' => 'Add ' . $post_type->labels->singular_name,
						'label'       => __( 'Pinned', 'better-llms-txt' ),
						'description' => __( 'These posts will always appear at the top of the section. (And ignore max links limit.)', 'better-llms-txt' ),
					], $this->section_name );
				}
			}

			$all_taxonomies = get_object_taxonomies( $post_type_name, 'objects' );
			foreach( $all_taxonomies as $taxonomy ) {
				$taxonomy_name = $taxonomy->name;

				if ( $taxonomies[ $taxonomy_name ]['enabled'] ?? false ) {
					$label = $taxonomies[ $taxonomy_name ]['label'] ?: $taxonomy->label;
					plugin()->settings()->add_field( [
						'name'         => "{$this->settings_index}.ordering.{$post_type_name}_{$taxonomy_name}",
						'group'        => 'Contents',
						'subgroup'     => $label,
						'type'         => 'hidden',
						'style'        => 'table',
						'default'      => ++ $ordering,
						'ordering'     => "{$this->settings_index}.ordering",
					], $this->section_name );

					if ( $taxonomy->rest_base ) {
						$terms_search_url = get_rest_url( null, 'wp/v2/' . $taxonomy->rest_base );
						$terms_search_url = add_query_arg( 'ignore_sticky', true, $terms_search_url );
						$terms_search_url = add_query_arg( 'search_columns', [ 'name', 'slug' ], $terms_search_url );
						$terms_search_url = add_query_arg( 'search', '@query', $terms_search_url );
					
						plugin()->settings()->add_field( [
							'name'          => "{$this->settings_index}.stickies.tax_{$taxonomy_name}",
							'group'       => 'Contents',
							'subgroup'    => $label,
							'type'        => 'sortable',
							'enable_tom'  => true,
							'placeholder' => 'Add ' . $taxonomy->labels->singular_name,
							'label'       => __( 'Pinned', 'better-llms-txt' ),
							'description' => __( 'These terms will always appear at the top of the section. (And ignore max links limit.)', 'better-llms-txt' ),
							'data'        => $terms_search_url,
							'data_value'  => 'id',
							'data_label'  => 'name',
						], $this->section_name );
					}

					$terms = $taxonomies[ $taxonomy_name ]['terms'] ?? [];
					foreach( $terms as $term_id ) {
						$term = get_term_by( 'term_id', $term_id, $taxonomy_name );
						if ( empty( $term ) ) {
							continue;
						}
						plugin()->settings()->add_field( [
							'name'        => "{$this->settings_index}.ordering.{$post_type_name}_{$taxonomy_name}_{$term->slug}",
							'group'       => 'Contents',
							'subgroup'    => $term->name,
							'type'        => 'hidden',
							'default'     => ++ $ordering,
							'ordering'    => "{$this->settings_index}.ordering",
						], $this->section_name );

						if ( $post_type->rest_base ) {
							$posts_search_url = get_rest_url( null, 'wp/v2/' . $post_type->rest_base );
							$posts_search_url = add_query_arg( 'ignore_sticky', true, $posts_search_url );
							$posts_search_url = add_query_arg( $taxonomy_name, $term_id, $posts_search_url );
							$posts_search_url = add_query_arg( 'search_columns', [ 'post_title' ], $posts_search_url );
							$posts_search_url = add_query_arg( 's', '@query', $posts_search_url );

							plugin()->settings()->add_field( [
								'name'        => "{$this->settings_index}.stickies.term_{$term_id}",
								'group'       => 'Contents',
								'subgroup'    => $term->name,
								'type'        => 'sortable',
								'enable_tom'  => true,
								'data'        => $posts_search_url,
								'data_value'  => 'id',
								'data_label'  => 'title.rendered',
								'placeholder' => 'Add ' . $post_type->labels->singular_name,
								'label'       => __( 'Pinned', 'better-llms-txt' ),
								'description' => __( 'These posts will always appear at the top of the section. (And ignore max links limit.)', 'better-llms-txt' ),
							], $this->section_name );
						}
						
					}
				}
			}

		}

	}

	public function build( array $args = [], array $generate_args = [] ) : string|WP_Error {

		$max_links = $this->get_setting( 'max_links' );
		$sticky = $this->get_setting( 'stickies' );
		if ( is_array( $sticky ) ) {
			foreach( $sticky as $type => $ids ) {
				$generate_args[ $type ] = [
					'include' => $ids,
					...( $generate_args[ $type ] ?? [] )
				];
			}
		}

		return parent::build( [
			'max_links' => $max_links,
			...$args
		], $generate_args );

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

