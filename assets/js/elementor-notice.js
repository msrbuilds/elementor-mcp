( function () {
	var notice = document.querySelector( '[data-emcp-elementor-notice]' );
	if ( ! notice ) return;
	notice.addEventListener( 'click', function ( event ) {
		if ( ! event.target.closest( '.notice-dismiss' ) ) return;
		var body = new URLSearchParams();
		body.append( 'action', 'emcp_tools_dismiss_elementor_notice' );
		body.append( 'nonce', notice.getAttribute( 'data-emcp-nonce' ) || '' );
		fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} );
	} );
} )();
