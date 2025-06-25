<?php

namespace ReallySpecific\BetterLLMStxt\Collections;

use ReallySpecific\BetterLLMStxt\Collection;

use WP_Term_Query;

class Terms extends Collection {

	private WP_Term_Query $wp_query;
	
	private function __construct( array $query_args ) {
		parent::__construct( $query_args['auto_paginate'] ?? true );
		$this->wp_query   = new WP_Term_Query( [ 'number' => $this->per_page, ...$query_args ] );
		$this->query_args = $query_args;
	}

	public static function query( array $query_args = [] ): Collection {

		$query_args = wp_parse_args( $query_args, [
			'suppress_filters' => false,
			'orderby'          => 'name',
			'order'            => 'ASC',
		] );
		$query_args = apply_filters( 'llms_txt_generate_get_terms_args', $query_args );

		if ( $query_args['orderby'] === 'priority' ) {
			$query_args['orderby'] = 'meta_value_num';
			$query_args['meta_key'] = '_llms_index_priority';
		}

		return new self( $query_args );

	}

	public function collect( $page_number = null ) {

		$this->wp_query->query_vars['offset'] = ( $page_number ?? $this->current_page - 1 ) * ( $this->per_page );

		if ( $this->query_args['meta_key'] === '_llms_index_priority' && ! has_action( 'terms_clauses', [ $this, 'leftjoin_priority_meta' ] ) ) {
			add_filter( 'terms_clauses', [ $this, 'join_and_sort_term_meta' ], 10, 3 );
		}
		/*if ( $this->query_args['meta_key'] === '_llms_index_priority' && ! has_filter( 'posts_clauses', [ $this, 'sort_null_join' ] ) ) {
			add_filter( 'posts_clauses', [ $this, 'sort_null_join' ], 10, 2 );
		}*/

		$terms = $this->wp_query->get_terms();

		if ( $this->query_args['meta_key'] === '_llms_index_priority' && has_filter( 'terms_clauses', [ $this, 'leftjoin_priority_meta' ] ) ) {
			remove_filter( 'terms_clauses', [ $this, 'join_and_sort_term_meta' ] );
		}
		/*if ( $this->query_args['meta_key'] === '_llms_index_priority' && has_filter( 'posts_clauses', [ $this, 'sort_null_join' ] ) ) {
			remove_filter( 'posts_clauses', [ $this, 'sort_null_join' ] );
		}*/

		$this->reset_data( $terms );

	}

	public function join_and_sort_term_meta( $clauses, $taxonomies, $args ) {

		global $wpdb;

		if ( $args['meta_key'] === '_llms_index_priority' && $args['orderby'] === 'meta_value_num' ) {

			// left join meta table:
			$clauses['join'] = str_replace( "INNER JOIN {$wpdb->termmeta}", "LEFT JOIN {$wpdb->termmeta}", $clauses['join'] );
			if ( preg_match( "/AND \(\\s*{$wpdb->termmeta}.meta_key = '_llms_index_priority'\\s*\)/", $clauses['where'], $and_where ) ) {
				$clauses['where'] = str_replace( $and_where[0], '', $clauses['where'] );
				$clauses['join'] = str_replace( "{$wpdb->termmeta}.term_id", "{$wpdb->termmeta}.term_id {$and_where[0]}", $clauses['join'] );
			}

			// put NULLs at the bottom:
			$clauses['orderby'] = $args['order'] === 'DESC' 
				? "ORDER BY {$wpdb->termmeta}.meta_value+0"
				: "ORDER BY -{$wpdb->termmeta}.meta_value";

			$clauses['order'] = "DESC";

		}

		return $clauses;

	}

	public function current_id(): string {
		$item = $this->current();
		return 'term:' . $item->term_id;
	}

	public function current_item_link(): string {
		$item = $this->current();
		return get_term_link( $item->term_id );
	}

	public function current_item_text(): string {
		$item = $this->current();
		return $item->name;
	}

}