( function() {

	const wp_inline_edit_function = inlineEditPost.edit;

	// we overwrite the it with our own
	inlineEditPost.edit = function( post_id ) {

		// let's merge arguments of the original function
		wp_inline_edit_function.apply( this, arguments );

		// get the post ID from the argument
		if ( typeof( post_id ) == 'object' ) { // if it is object, get the ID number
			post_id = parseInt( this.getId( post_id ) );
		}

		// add rows to variables
		const edit_row = document.querySelector( '#edit-' + post_id )
		const post_row = document.querySelector( '#post-' + post_id )

		let priority = post_row.querySelector( '.column-llms_priority' )?.textContent ?? '';
		if ( isNaN( parseInt( priority ) ) ) {
			priority = '';
		}

		// populate the inputs with column data
		const priorityField = edit_row?.querySelector( 'input[name="_llms_index_priority"]' );
		if ( priorityField ) {
			priorityField.value = priority;
		}

	}

} )();