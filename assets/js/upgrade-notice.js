/* EMCP Tools: dismisses the upgrade banner (class-upgrade-notice.php). */
( function () {
	var banner = document.querySelector( '.emcp-upgrade-banner, .emcp-sale' );
	if ( ! banner ) return;
	// Sale countdown: data-emcp-end is the end of the sale in Unix seconds.
	var end = parseInt( banner.getAttribute( 'data-emcp-end' ) || '0', 10 );
	if ( end > 0 ) {
		var pad = function ( n ) { return ( n < 10 ? '0' : '' ) + n; };
		var tick = function () {
			var left = Math.max( 0, end - Math.floor( Date.now() / 1000 ) );
			var parts = {
				d: Math.floor( left / 86400 ),
				h: Math.floor( ( left % 86400 ) / 3600 ),
				m: Math.floor( ( left % 3600 ) / 60 ),
				s: left % 60,
			};
			Object.keys( parts ).forEach( function ( k ) {
				var el = banner.querySelector( '[data-emcp-t="' + k + '"]' );
				if ( el ) el.textContent = pad( parts[ k ] );
			} );
			if ( 0 === left ) {
				banner.parentNode && banner.parentNode.removeChild( banner );
				return false;
			}
			return true;
		};
		if ( tick() ) {
			var timer = setInterval( function () {
				if ( ! tick() ) clearInterval( timer );
			}, 1000 );
		}
	}
	var dismissBtn = banner.querySelector( '.emcp-upgrade-banner__dismiss' );
	if ( ! dismissBtn ) return;
	dismissBtn.addEventListener( 'click', function () {
		banner.classList.add( 'is-dismissed' );
		var body = new URLSearchParams();
		body.append( 'action', 'emcp_tools_dismiss_upgrade_notice' );
		body.append( 'nonce', banner.getAttribute( 'data-emcp-nonce' ) || '' );
		body.append( 'banner', banner.getAttribute( 'data-emcp-banner' ) || 'upgrade' );
		fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).finally( function () {
			setTimeout( function () {
				if ( banner.parentNode ) banner.parentNode.removeChild( banner );
			}, 280 );
		} );
	} );
} )();
