<?php

namespace ReallySpecific\BetterLLMStxt\Collections;

use ReallySpecific\BetterLLMStxt\Collection;

use WP_Query;

class Posts extends Collection {

	private WP_Query $wp_query;
	
	private function __construct( array $query_args ) {
		parent::__construct( $query_args['auto_paginate'] ?? true );
		$this->wp_query   = new WP_Query( [ 'posts_per_page' => $this->per_page, ...$query_args ] );
		$this->query_args = $query_args;
	}

	public static function query( array $query_args = [] ): Collection {

		$query_args = wp_parse_args( $query_args, [
			'suppress_filters' => false,
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'orderby'          => 'post_modified',
			'order'            => 'DESC',
		] );
		$query_args = apply_filters( 'llms_txt_generate_get_posts_args', $query_args );

		if ( $query_args['orderby'] === 'priority' ) {
			$query_args['orderby'] = 'meta_value_num';
			$query_args['meta_key'] = '_llms_index_priority';
		}

		if ( isset( $query_args['term'] ) && isset( $query_args['taxonomy'] ) ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => $query_args['taxonomy'],
					'field'    => 'term_id',
					'terms'    => [ $query_args['term'] ],
				],
			];
			unset( $query_args['term'] );
			unset( $query_args['taxonomy'] );
		}

		return new self( $query_args );

	}

	public function collect( $page_number = null ) {

		$this->wp_query->set( 'paged', $page_number ?? $this->current_page );

		if ( $this->query_args['meta_key'] === '_llms_index_priority' && ! has_action( 'get_meta_sql', [ $this, 'leftjoin_priority_meta' ] ) ) {
			add_filter( 'get_meta_sql', [ $this, 'leftjoin_priority_meta' ], 10, 2 );
		}
		if ( $this->query_args['meta_key'] === '_llms_index_priority' && ! has_filter( 'posts_clauses', [ $this, 'sort_null_join' ] ) ) {
			add_filter( 'posts_clauses', [ $this, 'sort_null_join' ], 10, 2 );
		}

		$posts = $this->wp_query->get_posts();

		if ( $this->query_args['meta_key'] === '_llms_index_priority' && has_filter( 'get_meta_sql', [ $this, 'leftjoin_priority_meta' ] ) ) {
			remove_filter( 'get_meta_sql', [ $this, 'leftjoin_priority_meta' ] );
		}
		if ( $this->query_args['meta_key'] === '_llms_index_priority' && has_filter( 'posts_clauses', [ $this, 'sort_null_join' ] ) ) {
			remove_filter( 'posts_clauses', [ $this, 'sort_null_join' ] );
		}

		$this->reset_data( $posts );

	}

	public function leftjoin_priority_meta( $sql, $queries ) {

		if ( empty( $queries ) ) {
			return $sql;
		}

		$query = current( $queries );
		if ( $query['key'] === '_llms_index_priority' ) {
			$sql['join'] = str_replace( 'INNER JOIN', 'LEFT JOIN', $sql['join'] );
			$sql['join'] = str_replace( ')', $sql['where'] . ')', $sql['join'] );
			$sql['where'] = '';
			remove_filter( 'get_meta_sql', [ $this, 'leftjoin_priority_meta' ] );
		}

		return $sql;

	}

	public function sort_null_join( $clauses, $query ) {

		global $wpdb;

		remove_filter( 'posts_clauses', [ $this, 'sort_null_join' ] );

		if ( $query->get('meta_key') === '_llms_index_priority' && $query->get('orderby') === 'meta_value_num' ) {
			// put NULLs at the bottom:
			$clauses['orderby'] = $query->get('order') === 'DESC' 
				? "{$wpdb->postmeta}.meta_value+0 DESC"
				: "-{$wpdb->postmeta}.meta_value DESC";
		}

		return $clauses;

	}

	public function current_id(): string {
		$item = $this->current();
		return "post:{$item->ID}";
	}

	public function current_item_link(): string {
		$item = $this->current();
		return get_permalink( $item->ID );
	}

	public function current_item_text(): string {
		$item = $this->current();
		return $item->post_title;
	}

}