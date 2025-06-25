import TomSelect from 'tom-select/popular';

const thisForm = () => {
	return document.querySelector( '.rs-util-settings-form' );
}

const initForm = () => {

	const allToggledBy = thisForm().querySelectorAll( '[data-toggled-by]' );
	allToggledBy.forEach( toggleTarget => {
		const toggle = thisForm().querySelector( `#${toggleTarget.dataset.toggledBy}` );
		if ( ! toggle ) {
			return;
		}
		showTogglableControls( toggle );
	} );

	const groupToggles = thisForm().querySelectorAll( '[data-toggles-group]' );
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

			group = thisForm().querySelector( `#${toggle.dataset.togglesGroup}` );
			group.setAttribute( 'aria-hidden', toggle.checked ? 'false' : 'true' );
			group.setAttribute( 'data-toggled-by', toggle.id );

		}

		toggle.setAttribute( 'aria-expanded', toggle.checked ? 'true' : 'false' );
		toggle.setAttribute( 'aria-controls', `[data-toggled-by="${toggle.id}"]` );
		toggle.setAttribute( 'data-controls', `[data-toggled-by="${toggle.id}"]` );

	} );

}

const onReady = async () => {

	const settings = rsUtil_settingsPageENV();

	if ( settings.svg_iconset && ! document.querySelector( '#rs-util-svg-iconset' ) ) {
		const svgIcons = document.createElement( 'div' );
		try {
			const svg = await fetch( settings.svg_iconset );
			const svgText = await svg.text();
			svgIcons.innerHTML = svgText;
		} catch ( e ) {
			console.error( e );
		}
		document.body.appendChild( svgIcons.firstElementChild );
	}

	document.querySelectorAll( '[data-use-tom-select]' ).forEach( el => {
		el.tom = new TomSelect( el, {} );
	} );

}

const switchTab = ( section ) => {

	if ( section === '' ) {
		const firstSection = thisForm().querySelector( `.rs-util-settings-section[data-section]` );
		section = firstSection.dataset.section;
	}

	const tabToggles = document.querySelectorAll( '.rs-util-settings-page__tab-toggle' );
	tabToggles.forEach( toggle => {
		toggle.setAttribute( 'aria-expanded', toggle.dataset.section === section ? 'true' : 'false' );
		const target = thisForm().querySelector( `.rs-util-settings-section[data-section="${toggle.dataset.section}"]` );
		target.setAttribute( 'aria-hidden', toggle.dataset.section === section ? 'false' : 'true' );
	} );
}


const showTogglableControls = ( toggle ) => {
	const toggled = thisForm().querySelectorAll( `[data-toggled-by="${toggle.id}"]` );
	toggled.forEach( fieldRow => {
		fieldRow.setAttribute( 'aria-hidden', ( toggle.checked ?? ( toggle.value || false ) ) ? 'false' : 'true' );
	} );
	if ( toggled.length ) {
		toggle.setAttribute( 'aria-expanded', ( toggle.checked ?? ( toggle.value || false ) ) ? 'true' : 'false' );
		toggle.setAttribute( 'aria-controls', `[data-toggled-by="${toggle.id}"]` );
		toggle.setAttribute( 'data-controls', `[data-toggled-by="${toggle.id}"]` );
	}
}

const onPopState = () => {
	const url = new URL( window.location.href );
	const section = url.hash.replace( '#', '' );
	switchTab( section );
}

const enableSaveButton = () => {
	document.querySelector( '.rs-util-settings-page .rs-util-settings-page__submit' ).disabled = false;
}

const initListeners = () => {

	document.addEventListener( 'change', e => {
		if ( e.target.closest( '.rs-util-settings-form' ) ) {
			enableSaveButton();
		}
	} );

	document.addEventListener( 'click', e => {
		const saveButton = e.target.closest( '[data-action="save-rs-util-page"]' );
		if ( ! saveButton ) {
			return;
		}
		e.preventDefault();
		thisForm().classList.add('is-state-saving');
		thisForm().submit();
	} );

	document.addEventListener( 'click', e => {
		const tabToggle = e.target.closest( '.rs-util-settings-page__tab-toggle' );
		if ( ! tabToggle ) {
			return;
		}
		e.preventDefault();

		history.pushState( null, null, `#${tabToggle.dataset.section}` );
		switchTab( tabToggle.dataset.section );
	} );

	document.addEventListener( 'change', e => {
		const toggle = e.target.closest( '[data-controls]' );
		if ( ! toggle ) {
			return;
		}
		e.preventDefault();

		showTogglableControls( toggle );
	} );

	window.addEventListener( 'popstate', onPopState );
}

onPopState();
initListeners();
initForm();

if ( typeof rsUtil_settingsPageENV === 'function' ) {
	onReady();
} else {
	document.addEventListener( 'rsUtil_settingsPageENV|ready', onReady );
}