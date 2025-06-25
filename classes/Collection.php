<?php

namespace ReallySpecific\BetterLLMStxt;

use ArrayIterator;

abstract class Collection extends ArrayIterator {

	protected int $current_page = 1;
	protected int $per_page = 100;
	protected array $query_args = [];
	protected bool $auto_paginate = true;

	public function __construct( $auto_paginate = true ) {
		$this->auto_paginate = $auto_paginate;
		$this->reset_data();
	}

	public function __get( $key ) {
		return match( $key ) {
			'current_page' => $this->current_page,
			'per_page'     => $this->per_page,
			default        => $this->query_args[$key],
		};
	}

	public function __set( $key, $value ) {
		match( $key ) {
			'current_page' => $this->current_page = $value,
			'per_page'     => $this->per_page = $value,
			default        => $this->query_args[$key] = $value,
		};
	}

	abstract public static function query( array $query_args = [] ): Collection;

	abstract public function collect( $page_number = null );

	public function reset_data( $data = [] ) {
		parent::__construct( $data );
		$this->rewind();
	}

	public function next(): void {
		parent::next();
		if ( $this->key() === null && $this->auto_paginate ) {
			$this->next_page();
		}
	}

	public function next_page(): void {
		$this->current_page++;
		$this->collect();
	}

	public function to_array(): array {
		return $this->getArrayCopy();
	}

	abstract public function current_id(): string;

	abstract public function current_item_link(): string;

	abstract public function current_item_text(): string;

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
}