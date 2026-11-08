/**
 * Ukrposhta Pro admin: licence buttons on the settings screen and the shipment
 * box on the order screen. Plain fetch to admin-ajax, no framework.
 */
( function () {
	'use strict';

	const cfg = window.upwcPro || {};
	const i18n = cfg.i18n || {};

	function msgBox( from ) {
		const scope = from.closest( '[data-order]' ) || document.getElementById( 'upwc-lic' );
		return scope ? scope.querySelector( '.upwc-msg' ) : null;
	}

	function say( box, text, isError ) {
		if ( ! box ) {
			return;
		}
		box.textContent = text || '';
		box.className = 'upwc-msg ' + ( isError ? 'is-error' : 'is-ok' );
	}

	function val( id ) {
		const el = document.getElementById( id );
		return el ? el.value : '';
	}

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '[data-upwc]' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		const op = btn.getAttribute( 'data-upwc' );
		if ( op === 'ship_delete' && ! window.confirm( i18n.confirmDelete ) ) {
			return;
		}
		const body = new URLSearchParams();
		body.set( 'action', 'upwc_pro' );
		body.set( 'nonce', cfg.nonce || '' );
		body.set( 'op', op );
		if ( op === 'lic_activate' ) {
			body.set( 'key', val( 'upwc-lic-key' ).trim() );
		}
		if ( op === 'lic_trial' ) {
			body.set( 'email', val( 'upwc-trial-email' ).trim() );
		}
		const holder = btn.closest( '[data-order]' );
		if ( holder ) {
			body.set( 'order', holder.getAttribute( 'data-order' ) );
		}
		const box = msgBox( btn );
		btn.disabled = true;
		say( box, i18n.working, false );
		fetch( cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				btn.disabled = false;
				const data = res && res.data ? res.data : {};
				say( box, data.message || '', ! res.success );
				if ( res.success && data.reload ) {
					window.setTimeout( function () {
						window.location.reload();
					}, 1200 );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				say( box, i18n.network, true );
			} );
	} );
}() );
