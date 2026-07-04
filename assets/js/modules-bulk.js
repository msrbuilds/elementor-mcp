/* global emcpToolsModules */
( function () {
	'use strict';

	// Live value bubble + colored fill for the Image Optimization quality slider.
	document.querySelectorAll( '.emcp-io-range' ).forEach( function ( range ) {
		var out = document.querySelector( '.emcp-io-range-out[for="' + range.id + '"]' );
		function sync() {
			var min = parseFloat( range.min ) || 0;
			var max = parseFloat( range.max ) || 100;
			var pct = max > min ? ( ( parseFloat( range.value ) - min ) / ( max - min ) ) * 100 : 0;
			range.style.setProperty( '--emcp-fill', pct + '%' );
			if ( out ) { out.textContent = range.value; }
		}
		range.addEventListener( 'input', sync );
		sync();
	} );

	// Module settings overlays: open on "Show Settings", close on backdrop /
	// close button / Escape.
	function closeModals() {
		document.querySelectorAll( '.emcp-modal' ).forEach( function ( m ) { m.hidden = true; } );
		document.body.classList.remove( 'emcp-modal-open' );
	}
	document.querySelectorAll( '[data-modal]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var modal = document.getElementById( btn.getAttribute( 'data-modal' ) );
			if ( ! modal ) { return; }
			modal.hidden = false;
			document.body.classList.add( 'emcp-modal-open' );
		} );
	} );
	document.querySelectorAll( '.emcp-modal [data-close]' ).forEach( function ( el ) {
		el.addEventListener( 'click', closeModals );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) { closeModals(); }
	} );

	var cfg = window.emcpToolsModules;
	if ( ! cfg ) { return; }

	var optimizeBtn = document.getElementById( 'emcp-bulk-optimize' );
	var restoreBtn  = document.getElementById( 'emcp-bulk-restore' );
	var progress    = document.querySelector( '.emcp-bulk-progress' );
	var bar         = document.querySelector( '.emcp-bulk-bar span' );
	var status      = document.querySelector( '.emcp-bulk-status' );

	function post( action, extra ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', cfg.nonce );
		if ( extra ) {
			Object.keys( extra ).forEach( function ( k ) { body.set( k, extra[ k ] ); } );
		}
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	function setBar( pct ) {
		if ( bar ) { bar.style.width = pct + '%'; }
	}

	function runBatch() {
		post( cfg.batchAction, { batch: cfg.batchSize } ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				if ( status ) { status.textContent = ( res && res.data && res.data.message ) || 'Error'; }
				optimizeBtn.disabled = false;
				return;
			}
			var d = res.data;
			setBar( d.percent );
			if ( status ) { status.textContent = d.processed + ' / ' + d.total; }
			if ( d.done ) {
				if ( status ) { status.textContent = cfg.done + ' — ' + d.total; }
				optimizeBtn.disabled = false;
			} else {
				runBatch();
			}
		} ).catch( function () {
			optimizeBtn.disabled = false;
		} );
	}

	if ( optimizeBtn ) {
		optimizeBtn.addEventListener( 'click', function () {
			optimizeBtn.disabled = true;
			if ( progress ) { progress.hidden = false; }
			if ( status ) { status.textContent = cfg.optimizing; }
			setBar( 0 );
			runBatch();
		} );
	}

	if ( restoreBtn ) {
		restoreBtn.addEventListener( 'click', function () {
			restoreBtn.disabled = true;
			if ( progress ) { progress.hidden = false; }
			if ( status ) { status.textContent = cfg.restoring; }
			setBar( 0 );
			post( cfg.restoreAction ).then( function ( res ) {
				setBar( 100 );
				if ( status ) {
					status.textContent = ( res && res.success )
						? ( cfg.done + ' — ' + res.data.restored )
						: 'Error';
				}
				restoreBtn.disabled = false;
			} ).catch( function () { restoreBtn.disabled = false; } );
		} );
	}
} )();
