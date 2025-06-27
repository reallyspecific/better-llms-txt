import TomSelect from 'tom-select/popular';
import Sortable from 'sortablejs';

class SettingsPage {

	constructor( selector = '.rs-util-settings-form' ) {
		if ( typeof selector === 'object' ) {
			this.form = selector;
		} else {
			this.form = document.querySelector( selector );
		}
		this.form.settingsPage = this;
		this.init();
	}

	init() {

		const allToggledBy = this.form.querySelectorAll( '[data-toggled-by]' );
		allToggledBy.forEach( toggleTarget => {
			const toggle = this.form.querySelector( `#${toggleTarget.dataset.toggledBy}` );
			if ( ! toggle ) {
				return;
			}
			this.showTogglableControls( toggle );
		} );
	
		const groupToggles = this.form.querySelectorAll( '[data-toggles-group]' );
		groupToggles.forEach( toggle => {
	
			let group;
	
			if ( toggle.dataset.togglesGroup === 'self' ) {
	
				group = toggle.closest( '.rs-util-settings-field-group' );
	
				const field = toggle.closest( '.rs-util-settings-field-row' );
				const mainLabel = group.children[0];
				const content = group.children[1];
				content.setAttribute( 'aria-hidden', toggle.checked ? 'false' : 'true' );
				content.setAttribute( 'data-toggled-by', toggle.id );
	
				mainLabel.append( field );
	
			} else {
	
				group = this.form.querySelector( `#${toggle.dataset.togglesGroup}` );
				group.setAttribute( 'aria-hidden', toggle.checked ? 'false' : 'true' );
				group.setAttribute( 'data-toggled-by', toggle.id );
	
			}
	
			toggle.setAttribute( 'aria-expanded', toggle.checked ? 'true' : 'false' );
			toggle.setAttribute( 'aria-controls', `[data-toggled-by="${toggle.id}"]` );
			toggle.setAttribute( 'data-controls', `[data-toggled-by="${toggle.id}"]` );
	
		} );
	
		const orderedFields = this.form.querySelectorAll( '[data-ordered]' );
		orderedFields.forEach( ( field, index ) => {
			const hiddenOrder = field.querySelector( `input[data-ordering-field]` );
			if ( ! ( hiddenOrder?.value ?? false ) ) {
				return;
			}
			field.currentOrder = hiddenOrder.value || ( index + 1 );
			const rowControls = document.createElement( 'div' );
			rowControls.classList.add( 'rs-util-settings-field-row-order-controls' );
			field.append( rowControls );
	
			const upButton = document.createElement( 'button' );
			upButton.type = 'button';
			upButton.textContent = 'Move field up';
			upButton.setAttribute( 'data-action', 'move-up' );
			upButton.classList.add( 'button', 'rs-util-settings-field-row-ordering', 'rs-util-settings-field-row-ordering--up' );
			rowControls.append( upButton );
	
			const downButton = document.createElement( 'button' );
			downButton.type = 'button';
			downButton.textContent = 'Move field down';
			downButton.setAttribute( 'data-action', 'move-down' );
			downButton.classList.add( 'button', 'rs-util-settings-field-row-ordering', 'rs-util-settings-field-row-ordering--down' );
			rowControls.append( downButton );

			const grabButton = document.createElement( 'button' );
			grabButton.type = 'button';
			grabButton.textContent = 'Drag to reorder';
			grabButton.classList.add( 'button', 'rs-util-settings-field-row-ordering', 'rs-util-settings-field-row-ordering--grabber' );
			rowControls.append( grabButton );
	
			field.parentElement.dataset.orderingGroup = field.dataset.ordered;
	
		} );
	
		const orderingGroups = this.form.querySelectorAll( '[data-ordering-group]' );
		orderingGroups.forEach( group => {
			[ ...group.children ]
				.sort( ( a, b ) => ( a.currentOrder - b.currentOrder ) )
				.forEach( node => group.appendChild( node ) );

			SettingsPage.makeSortable( group, {
				handle: '.rs-util-settings-field-row-ordering--grabber',
				onSort: () => this.updateSortingIds( group),
			} );
		} );

		document.querySelectorAll( '[data-use-tom-select]' ).forEach( SettingsPage.makeSelectable );

		SettingsPage.listen( 'change', '.rs-util-settings-form', field => SettingsPage.enableSaveButton( field ) );
		SettingsPage.listen( 'change', '[data-controls]', field => this.showTogglableControls( field ) );
		SettingsPage.listen( 'click', '[data-action="save-rs-util-page"]', () => this.save() );
		SettingsPage.listen( 'click', '.rs-util-settings-page__tab-toggle', button => this.clickTab( button ) );
		SettingsPage.listen( 'click', '.rs-util-settings-field-row-ordering', button => this.clickReorderButton( button ) );
		SettingsPage.listen( 'click', '.rs-util-settings-page [data-action]', button => this.handleAction( button ) );

		window.addEventListener( 'popstate', this.onPopState );
	}

	handleAction( button ) {
		switch( button.dataset.action ) {
			case 'remove-item':
				SettingsPage.removeListItem( button.closest( '.rs-util-settings-sortable-list-item' ) );
				SettingsPage.enableSaveButton();
				break;
		}
	}

	save() {
		this.form.classList.add('is-state-saving');
		this.form.submit();
	}

	clickTab( toggle ) {
		history.pushState( null, null, `#${toggle.dataset.section}` );
		SettingsPage.switchTab( toggle.dataset.section, this.form );
	}

	clickReorderButton( orderingButton ) {

		const field = orderingButton.closest( '.rs-util-settings-field-row' );
		const hiddenOrder = field.querySelector( `input[data-ordering-field]` );
		if ( ! hiddenOrder ) {
			return;
		}

		const swapWith = orderingButton.dataset.action === 'move-up' 
			? field.previousElementSibling
			: field.nextElementSibling;

		if ( swapWith ) {
			if ( orderingButton.dataset.action === 'move-up' ) {
				field.insertAdjacentElement( 'afterend', swapWith );
			} else {
				field.insertAdjacentElement( 'beforebegin', swapWith );
			}

			SettingsPage.enableSaveButton();
			this.updateSortingIds( field.closest( '[data-ordering-group]' ) );

		}

	}

	updateSortingIds( group ) {
		const fields = group.querySelectorAll( '.rs-util-settings-field-row' );
		fields.forEach( ( field, index ) => {
			field.querySelector( `input[data-ordering-field]` ).value = index + 1;
			field.currentOrder = index + 1;
		} );
	}

	showTogglableControls( toggle ) {
		const toggled = this.form.querySelectorAll( `[data-toggled-by="${toggle.id}"]` );
		toggled.forEach( fieldRow => {
			fieldRow.setAttribute( 'aria-hidden', ( toggle.checked ?? ( toggle.value || false ) ) ? 'false' : 'true' );
		} );
		if ( toggled.length ) {
			toggle.setAttribute( 'aria-expanded', ( toggle.checked ?? ( toggle.value || false ) ) ? 'true' : 'false' );
			toggle.setAttribute( 'aria-controls', `[data-toggled-by="${toggle.id}"]` );
			toggle.setAttribute( 'data-controls', `[data-toggled-by="${toggle.id}"]` );
		}
	}

	onPopState() {
		const url = new URL( window.location.href );
		const section = url.hash.replace( '#', '' );
		SettingsPage.switchTab( section, this.form );
	}

	static enableSaveButton() {
		document.querySelector( '.rs-util-settings-page .rs-util-settings-page__submit' ).disabled = false;
	}

	static switchTab( section, form ) {

		if ( ! section ) {
			const firstSection = form.querySelector( `.rs-util-settings-section[data-section]` );
			section = firstSection.dataset.section;
		}

		const tabToggles = document.querySelectorAll( '.rs-util-settings-page__tab-toggle' );
		tabToggles.forEach( toggle => {
			toggle.setAttribute( 'aria-expanded', toggle.dataset.section === section ? 'true' : 'false' );
			const target = form.querySelector( `.rs-util-settings-section[data-section="${toggle.dataset.section}"]` );
			target.setAttribute( 'aria-hidden', toggle.dataset.section === section ? 'false' : 'true' );
		} );
	}

	static listen( eventName, selector, callback ) {

		document.addEventListener( eventName, e => {
			const self = e.target.closest( selector );
			if ( ! self ) {
				return;
			}
			e.preventDefault();
			callback( self, e );
		} );

	}

	static makeSelectable( el, moreArgs = {} ) {

		const fireTomEvent = ( event, el, ...args ) => {
			document.dispatchEvent( new CustomEvent( `rs-util-select-${event}`, { detail: { el, args } } ) );
			return true;
		}

		const tomArgs = {
			onChange:        ( ...args ) => fireTomEvent( 'change', el, ...args ),
			onItemAdd:       ( ...args ) => fireTomEvent( 'item-add', el, ...args ),
			onItemRemove:    ( ...args ) => fireTomEvent( 'item-remove', el, ...args ),
			onOptionAdd:     ( ...args ) => fireTomEvent( 'option-add', el, ...args ),
			onOptionRemove:  ( ...args ) => fireTomEvent( 'option-remove', el, ...args ),
			onDropdownOpen:  ( ...args ) => fireTomEvent( 'open', el, ...args ),
			onDropdownClose: ( ...args ) => fireTomEvent( 'close', el, ...args ),
			onFocus:         ( ...args ) => fireTomEvent( 'focus', el, ...args ),
			onBlur:          ( ...args ) => fireTomEvent( 'blur', el, ...args ),
			onInitialize:    ( ...args ) => fireTomEvent( 'initialize', el, ...args ),
			...moreArgs,
		};

		if ( el.dataset.action === 'add-item' ) {
			const group = el.closest( '.rs-util-settings-field-value' );
			const list  = group.querySelector( '.rs-util-settings-sortable-list' );
			SettingsPage.makeSortable( list, {
				onSort: () => {
					SettingsPage.updateListValues( group );
				}
			} );
			tomArgs.onItemAdd = ( value, ...args ) => {
				const label = el.currentResults?.[ value ] ?? value;
				SettingsPage.addListItem( group, value, label );
				el.tom.clear();
				el.tom.blur();
			};
		}

		if ( el.dataset.source ) {
			const source = JSON.parse( el.dataset.source );
			const valueField = source.value.split('.');
			const labelField = source.label.split('.');
			tomArgs.load = ( query, callback ) => {
				const url = source.url.replace( '@query', encodeURIComponent( query ) );
				fetch( url )
					.then( response => response.json() )
					.then( data => {
						const results = [];
						el.currentResults ??= {};
						data.forEach( item => {
							let value = item;
							valueField.forEach( field => {
								value = value[field];
							} );
							let label = item;
							labelField.forEach( field => {
								label = label[field];
							} );
							results.push( { value, label } );
							el.currentResults[value] = label;
						} );
						callback( results );
					} )
					.catch( error => console.error( error ) );
			};
			tomArgs.valueField  = 'value';
			tomArgs.labelField  = 'label';
			tomArgs.searchField = 'label';
		}

		el.tom = new TomSelect( el, tomArgs );
	}

	static makeSortable( el, args = {} ) {

		Sortable.create( el, {
			animation: 150,
			ghostClass: 'sortable-ghost',
			chosenClass: 'sortable-chosen',
			handle: '.rs-util-settings-draggable-handle',
			...args
		} );
	}

	static addListItem = ( group, value, label ) => {

		const values = ( group.querySelector( 'input[type="hidden"]' ).value
			? JSON.parse( group.querySelector( 'input[type="hidden"]' ).value )
			: null ) || [];
		
		values.push( value );
		group.querySelector( 'input[type="hidden"]' ).value = JSON.stringify( values );


		const newItem = document.createElement('div');
		newItem.classList.add( 'rs-util-settings-sortable-list-item' );
		newItem.dataset.value = value;
		newItem.innerHTML = `
			<span class="rs-util-settings-draggable-handle"></span>
			<span>${label}</span>
			<button type="button" class="rs-util-settings-trash-btn" data-action="remove-item">Remove Item</button>
		`;

		const list = group.querySelector( '.rs-util-settings-sortable-list' );
		list.append( newItem );

		SettingsPage.enableSaveButton();

	}

	static removeListItem = ( item ) => {

		const group = item.closest( '.rs-util-settings-field-value' );

		item.remove();

		SettingsPage.updateListValues( group );
		SettingsPage.enableSaveButton();
	}

	static updateListValues( group ) {

		const hiddenValueField = group.querySelector( 'input[type="hidden"]' );

		const list = group.querySelector( '.rs-util-settings-sortable-list' );
		const values = [];
		list.querySelectorAll( '.rs-util-settings-sortable-list-item' ).forEach( item => {
			values.push( item.dataset.value );
		} );
		hiddenValueField.value = JSON.stringify( values );

		SettingsPage.enableSaveButton();

	}

	static install() {
		new SettingsPage();
	}

}

window.rsUtil_SettingsPage = SettingsPage;

export default SettingsPage;
