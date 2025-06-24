<?php

namespace ReallySpecific\BetterLLMStxt;

use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Filesystem as FS;

use WP_Post_Type;
use WP_Query;

use Exception;

final class Generator {

	private $post_type;

	private $taxonomy;

	private $term;

	private $buffer;

	private string $output_type;

	private ?int $max_links;

	private string $filename;

	private string $tmp_path;

	public function __construct( array $args = [] ) {
		$args = wp_parse_args( $args, [
			'max_links'  => 5,
			'folder'     => null,
			'tmp_path'   => null,
			'filename'   => null,
			'return'     => 'filepath',
			'post_type'  => null,
			'taxonomy'   => null,
			'term'       => null,
			'children'   => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'level'      => 2,
		] );
		if ( is_string( $args['post_type'] ) ) {
			$args['post_type'] = get_post_type_object( $args['post_type'] );
		}
		if ( is_string( $args['taxonomy'] ) ) {
			$args['taxonomy'] = get_taxonomy( $args['taxonomy'] );
		}
		if ( ! $args['post_type'] instanceof WP_Post_Type && ! $args['taxonomy'] instanceof WP_Taxonomy ) {
			throw new Exception( 'Post type or taxonomy is required' );
		}
		if ( ! in_array( $args['return'], [ 'string', 'filepath' ] ) ) {
			throw new Exception( 'Return type must be `string` or `filepath`' );
		}
		$this->post_type = $args['post_type'];
		$this->taxonomy  = $args['taxonomy'];
		$this->term      = $args['term'];

		$this->max_links = $args['max_links'] ?: null;
		$this->tmp_path = $args['tmp_path'] ?? '';
		if ( empty( $this->tmp_path ) ) {
			$this->tmp_path = trailingslashit( trailingslashit( wp_upload_dir()['basedir'] ) . 'llms-tmp' );
		}
		if ( $args['folder'] ) {
			$this->tmp_path = trailingslashit( trailingslashit( $this->tmp_path ) . $args['folder'] );
		}
		$filename = [ 'llms' ];
		if ( isset( $this->post_type ) ) {
			$filename[] = $this->post_type->name;
		}
		if ( isset( $this->taxonomy ) ) {
			$filename[] = $this->taxonomy->name;
		}
		if ( isset( $this->term ) ) {
			$filename[] = $this->term->slug;
		}
		$filename = implode( "-", $filename );

		$this->filename = $args['filename'] ?? $filename . '.md';
		
		$this->output_type = $args['return'];
	}

	/**
	 * Summary of generate
	 * @return bool|string
	 */
	public function generate( $get_query_args = [], $template_args = [] ) {


		if ( isset( $this->term ) ) {
			$collection = Collections\Posts::query( [ 'taxonomy' => $this->taxonomy->name, 'term' => $this->term->term_id, ...$get_query_args ] );
		} elseif ( isset( $this->taxonomy ) ) {
			$collection = Collections\Terms::query( [ 'taxonomy' => $this->taxonomy->name, ...$get_query_args ] );
		} elseif ( isset( $this->post_type ) ) {
			$collection = Collections\Posts::query( [ 'post_type' => $this->post_type->name, ...$get_query_args ] );
		}

		if ( empty( $this->max_links ) || $this->max_links > 100 ) {
			$collection->per_page = 100;
		} else {
			$collection->per_page = $this->max_links;
		}

		$this->open_buffer();

		$title = str_replace( '${section_title}', $template_args['section_title'] ?? $this->post_type->label, $this->section_title_template( $template_args['level'] ?? 2 ) );
		$this->add_line( $title );

		$template = $this->link_line_template();

		$item_count = 0;
		$collection->collect();
		foreach( $collection as $item ) {

 			if ( empty( $item ) ) {
				break;
			}

			$item_id = $collection->current_id();

			if ( empty( $template_args['allow_duplicates'] ) && isset( $template_args['indexed'][ $item_id ] ) ) {
				continue;
			}

			$link = apply_filters( 'llms_txt_generate_item_link', $collection->current_item_link(), $item, $this );
			$text = apply_filters( 'llms_txt_generate_item_text', $collection->current_item_text(), $item, $this );

			$line = sprintf( $template, $text, $link );
			$line = apply_filters( 'llms_txt_generate_item_link', $line, $item, $this );
			$this->add_line( $line );

			$template_args['indexed'][ $item_id ] = true;

			$item_count += 1;
			if ( ! empty( $this->max_links ) && $item_count >= $this->max_links ) {
				break;
			}

		}

		$this->add_line( "\n" );

		return $this->close_buffer();

	}

	private function section_title_template( $level = 2 ) {

		$level = max( 1, min( 6, $level ) );
		$hashes = str_repeat( '#', $level );

		return apply_filters( 'llms_txt_generate_section_title_template', "{$hashes} \${section_title}\n\n", $this );
	}

	private function link_line_template( $indent = 0 ) {

		$indentation = str_repeat( '   ', $indent );

		return apply_filters( 'llms_txt_generate_link_line_template', "{$indentation}- [%s](%s)\n", $this );

	}

	private function open_buffer( $clear = true ) {


		if ( $this->output_type === 'string' ) {
			if ( $clear || empty( $this->buffer ) ) {
				$this->buffer = '';
			}
			return;
		}


		if ( ! file_exists( $this->tmp_path ) ) {
			FS\recursive_mk_dir( $this->tmp_path );
		}

		$filename = apply_filters( 'llms_txt_tmp_filename', $this->filename, $this );
		$filepath = trailingslashit( $this->tmp_path ) . $filename;

		$this->buffer = fopen( $filepath, $clear ? 'w' : 'a' );

	}

	private function add_line( string $line ) {

		if ( $this->output_type === 'string' ) {
			$this->buffer .= $line;
			return;
		}

		fwrite( $this->buffer, $line );

	}

	private function close_buffer() {

		if ( $this->output_type === 'string' ) {
			return $this->buffer;
		}

		fclose( $this->buffer );

		return $this->tmp_path . $this->filename;
	}


}
