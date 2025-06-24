<?php

namespace ReallySpecific\BetterLLMStxt;

use ReallySpecific\BetterLLMStxt\Generator;

use function ReallySpecific\BetterLLMStxt\plugin;

use Exception;
use WP_Error;

class Index {

	private $filename;

	private $output_path;

	private $file_url;

	private $sections = [];

	private $post_types = [];

	private $posts_indexed = [];

	private $taxonomies = [];

	private $issues = [];

	private string $template = '';

	protected string $settings_index = '';

	protected string $section_name = 'llms-txt-output';

	private string $index_slug = 'index';

	public function __construct( $args = [] ) {

		$args = wp_parse_args( $args, [
			'filename'    => null,
			'output_path' => null,
			'post_types'  => null,
			'index_slug'  => null,
		] );

		$this->filename    = $args['filename'] ?? 'llms.txt';
		$this->output_path = trailingslashit( $args['output_path'] ?? ABSPATH );
		$this->file_url    = trailingslashit( str_replace( ABSPATH, get_bloginfo('url'), $this->output_path ) ) . $this->filename;
		$this->post_types  = $args['post_types'] ?? get_post_types( [ 'public' => true ] );
		$this->taxonomies  = $args['taxonomies'] ?? [];

		$this->section_name = sanitize_title( $this->filename . '-output' );

		if ( empty( $args['index_slug'] ) ) {
			$args['index_slug'] = end( explode( "\\", __CLASS__ ) );
		}

		$this->index_slug = sanitize_title( $args['index_slug'] );
		$this->settings_index = 'index.' . $this->index_slug;

	}

	public function __get( $key ) {
		switch( $key ) {
			case 'filename':
				return $this->filename;
			case 'output_dir':
				return $this->output_path;
			case 'output_file':
				return $this->output_path . $this->filename;
			case 'url':
				return $this->file_url;
			case 'post_types':
				return $this->post_types;
		}
		return null;
	}

	

	public function install_settings() {

		try {
			$local_template = file_get_contents( plugin()->get_path( 'templates/default.md' ) );
		} catch ( Exception $e ) {
			$local_template = '';
		}

		$template_path = "llms-txt/{$this->index_slug}.md";
		// Note to translators: %s is the path to the template file
		$output_description = __( "In your theme, you can override this template by creating a file at `%s`." );
		$template_override = locate_template( $template_path, false, false );
		if ( ! empty( $template_override ) ) {
			// Note to translators: %s is the path to the template file
			$output_description = __( "This template is currently overridden by your theme at `%s`", 'better-llms-txt' );
		}

		$output_description .= __( ' See [documentation](#) for more information on template tags.', 'better-llms-txt' );

		plugin()->settings()->add_section(
			id: $this->section_name,
			props: [
				'title'       => $this->filename,
				'description' => sprintf( 
					__( "[View current file](%s)", 'better-llms-txt' ), 
					$this->url,
					//add_query_arg( 'regenerate', $this->index_slug, admin_url( 'options-general.php?page=llms-txt' ) )
				),
				'fields' => [
					[
						'name'        => $this->settings_index . '.template',
						'type'        => 'textarea',
						'label'       => __( 'Output template', 'better-llms-txt' ),
						'description' => sprintf( $output_description, 'templates/' . $template_path ),
						'default'     => $local_template,
						'rows'        => 10,
						'class'       => [ 'is-style-code-text' ],
					],[
						'name'        => $this->settings_index . '.no_duplicates',
						'type'        => 'checkbox',
						'label'       => __( 'No duplicate links', 'better-llms-txt' ),
						'value_label' => __( 'Only allow posts and terms to appear once on the index', 'better-llms-txt' ),
						'default'     => false,
					],[
						'name'        => $this->settings_index . '.schedule',
						'type'        => 'select',
						'options'     => [ $this, 'get_cron_schedule_options' ],
						'label'       => __( 'Generate on schedule', 'better-llms-txt' ),
						'description' => __( 'This index will be generated on the schedule specified.', 'better-llms-txt' ),
					],
				],
			]
		);

	}

	public function get_cron_schedule_options() {

		$cron_schedules = wp_get_schedules();
		$cron_options = [
			'' => __( 'Never', 'better-llms-txt' ),
		];
		foreach ( $cron_schedules as $interval => $schedule ) {
			$cron_options[ $interval ] = $schedule['display'];
		}
		$cron_options = array_unique( $cron_options );

		return $cron_options;

	}

	public static $actions_registered = false;

	public function register() {

		if ( did_action( 'llms_txt_settings_ready' ) ) {
			$this->install_settings();
		} else {
			add_action( 'llms_txt_settings_ready', [ $this, 'install_settings' ] );
		}

		if ( $this->get_setting( 'schedule' ) && ! wp_next_scheduled( 'llms_txt_generate_' . $this->index_slug ) ) {
			wp_schedule_event( time(), $this->get_setting( 'schedule' ), 'llms_txt_generate_' . $this->index_slug );
		}

		if ( ! self::$actions_registered ) {
			self::$actions_registered = true;
			add_action( 'llms_txt_generate_' . $this->index_slug, [ $this, 'build' ] );
		}

	}

	/**
	 * Returns the filename or string buffer of the generated index.
	 * Returns WP_Error object on failure.
	 *
	 * @param array $args
	 * @return string|WP_Error
	 */
	public function build( array $args = [] ) : string|WP_Error {

		$args = wp_parse_args( $args, [
			'tmp_path'  => null,
			'folder'    => null,
		] );

		$all_post_types = get_post_types();

		foreach( $all_post_types as $post_type ) {

			$post_type_obj = get_post_type_object( $post_type );

			$generator_args = [
				'post_type' => $post_type,
				'tmp_path'  => $args['tmp_path'],
				'folder'    => $args['folder'],
				...$args,
			];

			$level = 2;
			
			$orderby = plugin()->get_setting( key: "post_types.{$post_type}.orderby" );
			$order   = plugin()->get_setting( key: "post_types.{$post_type}.order" );
			$post_args = [
				'orderby' => $orderby,
				'order'   => $order ?: 'DESC',
			];

			$post_is_enabled = in_array( $post_type, $this->post_types );

			if ( $post_is_enabled ) {

				$generator = new Generator( $generator_args );
				
				switch( $orderby ) {
					case 'priority':
						$post_args['orderby'] = 'priority';
						break;
					case 'title':
						$post_args['orderby'] = 'post_title';
						break;
					default:
						$post_args['orderby'] = 'post_modified';
						break;
				}
				$post_args += ( $args['post_args'] ?? [] );

				$this->sections[ $post_type ] = $generator->generate( $post_args, [
					'section_title'    => plugin()->get_setting( key: "post_types.{$post_type}.label" ) ?: $post_type->label,
					'indexed'          => &$this->posts_indexed,
					'allow_duplicates' => ! $this->get_setting( 'no_duplicates' ),
				] );

				$level++;

			}

			$type_taxonomies = get_object_taxonomies( $post_type, 'names' );
			foreach ( $type_taxonomies as $tax_name ) {

				$taxonomy         = get_taxonomy( $tax_name );
				$taxonomy_enabled = plugin()->get_setting( key: "taxonomies.{$tax_name}.enabled" );

				if ( $taxonomy_enabled ) {

					$tax_orderby = plugin()->get_setting( key: "taxonomies.{$tax_name}.orderby" );
					$tax_order   = plugin()->get_setting( key: "taxonomies.{$tax_name}.order" );

					$generator = new Generator( [
						'taxonomy' => $taxonomy,
						...$generator_args,
					] );
					$this->sections[ "{$post_type}.{$tax_name}" ] = $generator->generate( [
						'children' => $this->taxonomies[$tax_name]['show_children'] ?? false,
						'orderby'  => $tax_orderby ?: 'name',
						'order'    => $tax_order   ?: 'ASC',
						...( $args['tax_args'] ?? [] ),
					],[
						'level'            => $level,
						'section_title'    => plugin()->get_setting( key: "taxonomies.{$tax_name}.label" ) ?: ucwords( $post_type_obj->labels->singular_name . ' ' . $taxonomy->label ),
						'indexed'          => &$this->posts_indexed,
						'allow_duplicates' => ! $this->get_setting( 'no_duplicates' ),
					] );

					$level ++;
				}

				$terms = plugin()->get_setting( key: "taxonomies.{$tax_name}.terms" );
				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$term = get_term( (int) $term, $tax_name );
						$generator = new Generator( [
							'taxonomy' => $taxonomy,
							'term'     => $term,
							...$generator_args,
						] );
						$this->sections[ "{$post_type}.{$tax_name}.{$term->slug}" ] = $generator->generate( [
							'orderby'  => $orderby, // inherit from post type
							'order'    => $order,   // inherit from post type
							...( $args['term_args'] ?? [] ),
						],[
							'level'            => $level,
							'section_title'    => $term->name,
							'indexed'          => &$this->posts_indexed,
							'allow_duplicates' => ! $this->get_setting( 'no_duplicates' ),
						] );
					}
				}


			}
		}

		return $this->write();

	}

	public function delete_file() {

		if ( file_exists( trailingslashit( $this->output_path ) . $this->filename ) ) {
			unlink( trailingslashit( $this->output_path ) . $this->filename );
		}

	}
	public function get_setting( string $key ) {

		return plugin()->get_setting( key: $this->settings_index . '.' . $key );

	}

	public function get_template() {

		$template = plugin()->get_template_part( $this->index_slug, null, [
			'extension_type' => '.md',
			'theme_folder'   => 'llms-txt',
		] );

		if ( ! empty( $template ) ) {
			return $template;
		}

		$template = $this->get_setting( 'template' );
		if ( ! empty( $template ) ) {
			return $template;
		}

		$default = plugin()->get_path( 'templates/default.md' );
		if ( file_exists( $default ) ) {
			return file_get_contents( $default );
		}

		return '';

	}

	/**
	 * TODO: move this to the Util libarary
	 *
	 * @param string $template
	 * @return string
	 */
	private function process_template( string $template ) : string {

		$vars = [
			'sitemap_url'      => home_url( '/sitemap.xml' ),
			'home_url'         => home_url(),
			'publish_datetime' => date('c'),
		];

		foreach ( $vars as $key => $value ) {
			$template = str_replace( "\${vars.$key}", $value, $template );
		}

		preg_match_all( '/\$\{([a-z0-9._-]+)\}/', $template, $matches );
		foreach ( $matches[1] as $match ) {
			$var = explode( '.', $match );
			if ( $var[0] === 'bloginfo' && isset( $var[1] ) ) {
				$template = str_replace( "\${{$match}}", get_bloginfo( $var[1] ), $template );
			}
		}

		return $template;

	}

	/**
	 * Writes the current Index buffer to the output path
	 *
	 * @return string|WP_Error returns the output path on success or a WP_Error on failure
	 */
	private function write() : string|WP_Error {

		$template = $this->get_template();
		$template = $this->process_template( $template );

		$handle = fopen( $this->output_path . $this->filename, 'w' );
		if ( ! $handle ) {
			return new WP_Error( 'file_open_failed', __( 'Failed to open file for writing', 'better-llms-txt' ) );
		}

		$section_location = strpos( $template, '${sections}' );
		if ( $section_location === false ) {
			return $template;
		}

		$template = explode( '${sections}', $template );
		$intro = array_shift( $template );

		try {

			fwrite( $handle, $intro );

			while ( ! empty( $template ) ) {

				foreach( $this->sections as $section_slug => $section ) {
					if ( is_file( $section ) ) {
						$section_content = file_get_contents( $section );
						if ( empty( $section_content ) ) {
							$this->issues[] = new WP_Error( 'empty_section', __( "Section `{$section_slug}` is empty from file `{$section}`", 'better-llms-txt' ) );
						}
						$section = $section_content;
					}
					fwrite( $handle, $section );
				}
				$content = array_shift( $template );
				fwrite( $handle, $content );

			}

		} catch ( Exception $e ) {
			fclose( $handle );
			return new WP_Error( 'file_write_failed', __( 'Failed to write file', 'better-llms-txt' ), $e );
		}

		fclose( $handle );

		return $this->output_path . $this->filename;

	}


}

