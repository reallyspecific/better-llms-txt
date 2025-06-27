( function() {

	/*
	const setupStickies = () => {

		const stickySelectors = document.querySelectorAll( `select[id*="add_sticky"]` );
		stickySelectors.forEach( el => {

			const group = el.closest( '.rs-util-settings-field-row__group' );

			const hiddenSelection = group.querySelector(`[name*="[stickies]"]`);
			el.hiddenValue = hiddenSelection;

			const stickySubgroup = document.createElement('div');
			stickySubgroup.classList.add( 'rs-util-settings-field-subgroup' );
			stickyGroupList = document.createElement('div');
			stickyGroupList.classList.add('rs-util-settings-field-row__group','rs-util-settings-sortable-list');
			stickySubgroup.append(stickyGroupList);

			if ( hiddenSelection.dataset.defaultValues ) {
				hiddenSelection.value = '';
				const values = JSON.parse( el.dataset.defaultValues );
				if ( values ) {
					values.forEach( value => {
						addSticky( el, value.id, value.title );
					} );
				}
			}

			stickySubgroup.append( hiddenSelection );
			group.append( stickySubgroup );
			el.attachedStickies = stickyGroupList;

			stickySubgroup.append( el.tomselect );

		} );

	}

	document.addEventListener( 'DOMContentLoaded', setupStickies );
	*/

} )();