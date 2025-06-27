<?php

namespace ReallySpecific\BetterLLMStxt\Indexes;

use ReallySpecific\BetterLLMStxt\Index;

use Exception;
use WP_Error;

class Full extends Index {

	public function __construct( $args = [] ) {

		return parent::__construct( [
			'filename'    => 'llms-full.txt',
			'index_slug'  => 'full',
			...$args
		] );

	}

	public function build( array $args = [], array $generate_args = [] ) : string|WP_Error {

		return parent::build( [
			'max_links' => 0,
			...$args
		], $generate_args );

	}

}

