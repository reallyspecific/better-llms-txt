<?php

namespace ReallySpecific\BetterLLMStxt;

use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Service;
use ReallySpecific\BetterLLMStxt\Dependencies\RS_Utils\Settings;
class Integration extends Service {

	public function __construct( $plugin ) {
		parent::__construct( $plugin );

		add_action( 'template_redirect', [ $this, 'maybe_handle_404' ] );
		//add_filter( 'manage_posts_columns', [ $this, 'maybe_add_priority_column' ], 10, 2 );
		//add_filter( 'manage_pages_columns', [ $this, 'maybe_add_priority_column' ], 10, 1 );
		//add_action( 'manage_posts_custom_column',  [ $this, 'display_priority_column' ], 10, 2 );
		//add_action( 'manage_pages_custom_column',  [ $this, 'display_priority_column' ], 10, 2 );
		//add_action( 'quick_edit_custom_box',  [ $this, 'quick_edit_fields' ], 10, 2 );
		//add_action( 'save_post', [ $this, 'quick_edit_save' ] );
		//add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_scripts' ] );
		add_action( 'rs_util_settings_saved', [ $this, 'settings_saved' ] );

		add_filter( 'rs_util_settings_render_sortable_values', [ $this, 'return_sortable_value_labels' ], 10, 2 );

		/*$taxonomies_enabled = $plugin->get_setting( key: 'taxonomies' );
		foreach ( $taxonomies_enabled as $taxonomy => $options ) {
			if ( ! $options['enabled'] ) {
				continue;
			}
			add_action( "{$taxonomy}_add_form_fields", [ $this, 'add_term_priority_field' ], 10, 1 );
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_term_priority_field' ], 10, 2 );
			add_action( "saved_{$taxonomy}", [ $this, 'save_term_priority' ], 10, 2 );
		}*/
	}

	public function return_sortable_value_labels( $values, $field ) {

		if ( str_contains( $field['name'], '.post_' ) || str_contains( $field['name'], '.term_' ) ) {
			foreach( $values as &$value ) {
				$title = get_the_title( $value );
				$value = [
					'value' => $value,
					'label' => $title,
				];
			}
		}

		if ( str_contains( $field['name'], '.tax_' ) ) {
			$taxonomy = end( explode( '_', $field['name'] ) );
			foreach( $values as &$value ) {
				$term = get_term_by( 'term_id', $value, $taxonomy );
				$value = [
					'value' => $value,
					'label' => $term->name,
				];
			}
		}

		return $values;

	}

	public function maybe_handle_404() {

		if ( ! is_404() ) {
			return;
		}

		$index = plugin()->find_index( untrailingslashit( ABSPATH ) . $_SERVER['REQUEST_URI'] );
		if ( $index ) {
			if ( $index->build() ) {
				wp_redirect( home_url( $index->filename ), 301 );
			}
		}

		return;

	}

	public function settings_saved( $settings ) {

		if ( $settings instanceof Settings ) {
			// this verifies we're not saving some other plugin that uses rs_util
			plugin()->delete_current_indexes();
		}

	}


	public function register_admin_scripts() {

		wp_register_script( 'llms-txt-posts-admin', plugin()->get_url( 'assets/posts-admin.js' ), [], null, true );

	}

	public function maybe_add_priority_column( $columns, $post_type = 'page' ) {

		$enabled = plugin()->get_setting( key: 'post_types.' . $post_type . '.enabled' );
		$sorting = plugin()->get_setting( key: 'post_types.' . $post_type . '.orderby' );

		if ( ! $enabled || $sorting !== 'priority' ) {
			return $columns;
		}

		wp_enqueue_script( 'llms-txt-posts-admin' );

		$columns['llms_priority'] = __( 'LLMS Index Priority', 'better-llms-txt' );
		return $columns;

	}

	public function display_priority_column( $column_name, $post_id ) {

		if ( $column_name !== 'llms_priority' ) {
			return;
		}

		$priority = get_post_meta( $post_id, '_llms_index_priority', true );
		if ( empty( $priority ) ) {
			$priority = __( 'Not Set', 'better-llms-txt' );
		}

		echo $priority;

	}

	public function quick_edit_fields( $column_name ) {

		global $post;

		switch( $column_name ) {
			case 'llms_priority': {
				?>
					<fieldset class="inline-edit-col">
						<div class="inline-edit-col">
							<label>
								<span class="title"><?php _e( 'Priority', 'better-llms-txt' ); ?></span>
								<input value="<?php echo get_post_meta( $post->ID, '_llms_index_priority', true ); ?>" type="number" min="0" size="5" name="_llms_index_priority">
							</label>
						</div>
					</fieldset>
				<?php
				break;
			}
		}
	}

	public function quick_edit_save( $post_id ){

		// check inlint edit nonce
		if ( ! wp_verify_nonce( $_POST[ '_inline_edit' ], 'inlineeditnonce' ) ) {
			return;
		}

		// update the price
		$priority = isset( $_POST[ '_llms_index_priority' ] ) ? sanitize_text_field( $_POST[ '_llms_index_priority' ] ) : '';
		update_post_meta( $post_id, '_llms_index_priority', $priority );

	}

	public function add_term_priority_field( $taxonomy, $term = null ) {
		?>
			<div class="form-field">
				<label for="_llms_index_priority">LLMS Index Priority</label>
				<input class="postform" type="number" size="5" min="0" increment="1" value="" name="_llms_index_priority" id="_llms_index_priority" />
				<p>Terms can be sorted in the LLMS index by this number. Terms without a value will always be sortred at the end.</p>
			</div>
		<?php
	}

	public function edit_term_priority_field( $term, $taxonomy ) {
		$value = get_term_meta( $term->term_id, '_llms_index_priority', true );

		?>
		<tr class="form-field">
			<th><label for="_llms_index_priority">LLMS Index Priority</label></th>
			<td>
				<input name="_llms_index_priority" id="_llms_index_priority" type="number" size="5" min="0" increment="1" value="<?php echo esc_attr( $value ); ?>" />
				<p class="description">Terms can be sorted in the LLMS index by this number. Terms without a value will always be sortred at the end.</p>
			</td>
		</tr>
		<?php
	}

	public function save_term_priority( $term_id ) {
		$priority = isset( $_POST[ '_llms_index_priority' ] ) ? sanitize_text_field( $_POST[ '_llms_index_priority' ] ) : '';
		update_term_meta( $term_id, '_llms_index_priority', $priority );
	}
}