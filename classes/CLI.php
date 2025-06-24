<?php

namespace ReallySpecific\BetterLLMStxt;

use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils as Utils;
use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Service;
use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Filesystem as FS;

use WP_CLI;

class CLI extends Service {

	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		if ( ! class_exists( 'WP_CLI' ) ) {
			$this->disable();
			return;
		}
		WP_CLI::add_command( 'llms.txt generate', [ $this, 'run' ] );
	}

	public function run( $args, $assoc_args ) {

	}

}