<?php

namespace ReallySpecific\BetterLLMStxt\Collections;

use ReallySpecific\BetterLLMStxt\Collection;

use WP_Query;

class Posts extends Collection {

	private WP_Query $wp_query;

	private WP_Query $stickies;

	private function __construct( array $query_args ) {
		parent::__construct( $query_args['auto_paginate'] ?? true );
		if ( isset( $query_args['include'] ) ) {
			$this->stickies   = new WP_Query( [ 'orderby' => 'post__in', 'post_type' => $query_args['post_type'] ?? 'post', 'post__in' => $query_args['include'], 'nopaging' => true, 'per_page' => -1 ] );
			unset( $query_args['include'] );
			$this->current_page = 0;
		}
		$this->wp_query   = new WP_Query( [ 'posts_per_page' => $this->per_page, ...$query_args ] );
		$this->query_args = $query_args;
	}

	public function __get( $key ) {
		switch( $key ) {
			case 'sticky_count':
				return $this->stickies?->found_posts ?? 0;
			case 'query_count':
				return $this->wp_query?->found_posts ?? 0;
			case 'total_count':
				return $this->sticky_count + $this->query_count;
			default:
				return parent::__get( $key );
		}
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

		if ( isset( $this->stickies ) && empty( $page_number ?? $this->current_page ) ) {
			$posts = $this->stickies->get_posts();
		} else {
			$this->wp_query->set( 'paged', $page_number ?? $this->current_page );
			$posts = $this->wp_query->get_posts();
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