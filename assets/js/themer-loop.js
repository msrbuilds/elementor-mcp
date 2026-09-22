/**
 * EMCP Themer Loop Grid and Loop Carousel: masonry, entrance animation,
 * load more, infinite scroll, AJAX pagination and the Swiper carousel.
 *
 * No build step. Works with or without Elementor; inside the Elementor editor
 * preview it re-initialises on every settings change, tearing down first.
 *
 * Every query an element makes of its own structure goes through direct
 * children (:scope > ...), because a card can contain a nested Loop Grid or
 * Carousel with the same class names.
 *
 * Contract with the PHP side: data-emcp-loop, data-emcp-sig, data-emcp-page,
 * data-emcp-pages, data-emcp-mode, data-emcp-ajax, data-emcp-url,
 * data-emcp-url-first, data-emcp-carousel on the root; data-kind, data-end,
 * data-mid, data-prev, data-next on the pagination nav; window.emcpThemerLoop
 * (rest, nonce, rest_nonce_url, i18n). Dispatches emcp:loop:appended,
 * emcp:loop:replaced and emcp:loop:carousel-ready on the root.
 */
( function () {
	'use strict';

	var win = window;
	var doc = win.document;
	var cfg = win.emcpThemerLoop || { rest: '', nonce: '', rest_nonce_url: '', i18n: {} };
	var NONCE_RE = /^[a-f0-9]{10}$/;
	var PAGE_TOKEN = 'EMCPPAGENUMBER';

	/** Asset chunks already applied on this page, keyed by handle, kind and text. */
	var applied = new Set();
	/** Every element this script has wired, so detached ones can be torn down. */
	var live = new Set();
	var warned = {};

	function warnOnce( key, msg ) {
		if ( warned[ key ] ) {
			return;
		}
		warned[ key ] = true;
		if ( win.console && win.console.warn ) {
			win.console.warn( msg );
		}
	}

	function toArray( list ) {
		return Array.prototype.slice.call( list || [] );
	}

	function own( el, sel ) {
		return el.querySelector( ':scope > ' + sel );
	}

	function ownAll( el, sel ) {
		return toArray( el.querySelectorAll( ':scope > ' + sel ) );
	}

	function ownItems( el ) {
		return ownAll( el, '.emcp-loop__items > .emcp-loop__item' ).concat( ownAll( el, '.swiper > .swiper-wrapper > .emcp-loop__item' ) );
	}

	function itemsContainer( el ) {
		return own( el, '.emcp-loop__items' ) || own( el, '.swiper > .swiper-wrapper' );
	}

	function intAttr( el, name, fallback ) {
		var n = parseInt( el.getAttribute( name ), 10 );
		return isNaN( n ) ? fallback : n;
	}

	/* ---- pure helpers (exported for the node test) ---------------------- */

	/**
	 * The items paginate_links() would print, as data: {type, page}. type is
	 * prev, page, current, dots or next. Same windowing as core: end_size
	 * pages at each end, mid_size either side of the current page, one dots
	 * marker per gap, prev omitted on page 1 and next on the last page.
	 */
	function navItems( current, total, kind, endSize, midSize ) {
		var out = [];
		total = parseInt( total, 10 ) || 0;
		current = parseInt( current, 10 ) || 0;
		if ( total < 2 ) {
			return out;
		}
		endSize = parseInt( endSize, 10 );
		midSize = parseInt( midSize, 10 );
		if ( isNaN( endSize ) || endSize < 1 ) {
			endSize = 1;
		}
		if ( isNaN( midSize ) || midSize < 0 ) {
			midSize = 2;
		}
		var prevNext = 'numbers' !== kind;
		if ( prevNext && current && 1 < current ) {
			out.push( { type: 'prev', page: current - 1 } );
		}
		if ( 'prev_next' !== kind ) {
			var dots = false;
			for ( var n = 1; n <= total; n++ ) {
				if ( n === current ) {
					out.push( { type: 'current', page: n } );
					dots = true;
				} else if ( n <= endSize || ( current && n >= current - midSize && n <= current + midSize ) || n > total - endSize ) {
					out.push( { type: 'page', page: n } );
					dots = true;
				} else if ( dots ) {
					out.push( { type: 'dots' } );
					dots = false;
				}
			}
		}
		if ( prevNext && current && current < total ) {
			out.push( { type: 'next', page: current + 1 } );
		}
		return out;
	}

	function stripHash( url ) {
		var i = String( url ).indexOf( '#' );
		return -1 === i ? String( url ) : String( url ).slice( 0, i );
	}

	function normalize( url ) {
		url = String( url );
		if ( 'function' === typeof win.URL ) {
			try {
				return stripHash( new win.URL( url, ( win.location && win.location.href ) || undefined ).href );
			} catch ( e ) {
				return stripHash( url );
			}
		}
		return stripHash( url );
	}

	/** The URL of page n from the template (%#% = number) and the page 1 URL. */
	function urlForPage( tpl, first, n ) {
		n = parseInt( n, 10 );
		return 1 === n ? String( first ) : String( tpl ).split( '%#%' ).join( String( n ) );
	}

	/** The page a link points to, or null when it is not one of this loop's pages. */
	function pageFromUrl( href, tpl, first ) {
		if ( ! href || ! tpl ) {
			return null;
		}
		var target = normalize( href );
		if ( first && target === normalize( first ) ) {
			return 1;
		}
		var pattern = normalize( String( tpl ).split( '%#%' ).join( PAGE_TOKEN ) );
		var escaped = pattern.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ).split( PAGE_TOKEN ).join( '(\\d+)' );
		var m = new RegExp( '^' + escaped + '$' ).exec( target );
		if ( ! m ) {
			return null;
		}
		var page = parseInt( m[ 1 ], 10 );
		return page >= 1 ? page : null;
	}

	win.emcpThemerLoopInternals = {
		navItems: navItems,
		urlForPage: urlForPage,
		pageFromUrl: pageFromUrl,
	};

	if ( ! doc || ! doc.querySelectorAll ) {
		return;
	}

	/* ---- assets ---------------------------------------------------------- */

	function chunkKey( handle, kind, chunk ) {
		return handle + '\u0000' + kind + '\u0000' + chunk;
	}

	function printedContains( ids, chunk ) {
		for ( var i = 0; i < ids.length; i++ ) {
			var n = doc.getElementById( ids[ i ] );
			if ( n && -1 !== n.textContent.indexOf( chunk ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Apply a chunk once per page. Deduped by content, never by id: a chunk
	 * counts as applied when this script already applied it, or when the
	 * page printed it under that handle's own node.
	 */
	function applyChunk( handle, kind, chunk, printedIds, apply ) {
		var key = chunkKey( handle, kind, chunk );
		if ( applied.has( key ) || printedContains( printedIds, chunk ) ) {
			return;
		}
		applied.add( key );
		apply( chunk );
	}

	function runInline( code, id ) {
		var s = doc.createElement( 'script' );
		if ( id ) {
			s.id = id;
		}
		s.textContent = code;
		( doc.body || doc.head ).appendChild( s );
	}

	function loadScript( src, id ) {
		return new Promise( function ( resolve ) {
			var s = doc.createElement( 'script' );
			s.id = id;
			s.src = src;
			s.async = false;
			s.onload = resolve;
			s.onerror = resolve;
			( doc.body || doc.head ).appendChild( s );
		} );
	}

	function loadStyle( href, id, media ) {
		return new Promise( function ( resolve ) {
			var l = doc.createElement( 'link' );
			l.id = id;
			l.rel = 'stylesheet';
			l.href = href;
			l.media = media || 'all';
			l.onload = resolve;
			l.onerror = resolve;
			doc.head.appendChild( l );
		} );
	}

	function parseNodes( html ) {
		var tpl = doc.createElement( 'template' );
		tpl.innerHTML = html || '';
		return toArray( tpl.content.children );
	}

	/**
	 * Phase 1, before the cards go in: stylesheets, then per script handle
	 * (in the dependency order the route returns) its config chunks, its
	 * translations and its file, each awaited before the next handle.
	 */
	function applyBefore( assets ) {
		var styles = [];
		var chain = Promise.resolve();
		( assets || [] ).forEach( function ( a ) {
			if ( ! a || ! a.handle ) {
				return;
			}
			if ( 'style' === a.type ) {
				if ( a.external && ! doc.getElementById( a.handle + '-css' ) ) {
					parseNodes( a.external ).forEach( function ( n ) {
						if ( 'LINK' === n.tagName && n.getAttribute( 'href' ) ) {
							styles.push( loadStyle( n.getAttribute( 'href' ), a.handle + '-css', n.getAttribute( 'media' ) ) );
						}
					} );
				}
				return;
			}
			if ( 'script' !== a.type ) {
				return;
			}
			chain = chain.then( function () {
				( a.config || [] ).forEach( function ( chunk ) {
					applyChunk( a.handle, 'config', chunk, [ a.handle + '-js-extra', a.handle + '-js-before' ], function ( c ) {
						runInline( c );
					} );
				} );
				if ( ! a.external ) {
					return null;
				}
				var nodes = parseNodes( a.external ).filter( function ( n ) {
					return 'SCRIPT' === n.tagName;
				} );
				var file = null;
				nodes.forEach( function ( n ) {
					if ( n.getAttribute( 'src' ) ) {
						file = file || n;
						return;
					}
					// The inline translations script runs before the file.
					var id = n.id || a.handle + '-js-translations';
					if ( ! doc.getElementById( id ) ) {
						runInline( n.textContent, id );
					}
				} );
				if ( file && ! doc.getElementById( a.handle + '-js' ) ) {
					return loadScript( file.getAttribute( 'src' ), a.handle + '-js' );
				}
				return null;
			} );
		} );
		return Promise.all( styles.concat( [ chain ] ) );
	}

	/** Phase 2, after the cards are in the DOM: init chunks. */
	function applyAfter( assets ) {
		( assets || [] ).forEach( function ( a ) {
			if ( ! a || ! a.handle ) {
				return;
			}
			( a.init || [] ).forEach( function ( chunk ) {
				if ( 'style' === a.type ) {
					applyChunk( a.handle, 'style-init', chunk, [ a.handle + '-inline-css' ], function ( c ) {
						var st = doc.createElement( 'style' );
						st.textContent = c;
						doc.head.appendChild( st );
					} );
				} else if ( 'script' === a.type ) {
					applyChunk( a.handle, 'init', chunk, [ a.handle + '-js-after' ], function ( c ) {
						runInline( c );
					} );
				}
			} );
		} );
	}

	/* ---- fetch ----------------------------------------------------------- */

	function restUrl( el, page, mode ) {
		var base = String( cfg.rest || '' );
		return base + ( -1 === base.indexOf( '?' ) ? '?' : '&' )
			+ 'config=' + encodeURIComponent( el.getAttribute( 'data-emcp-loop' ) || '' )
			+ '&sig=' + encodeURIComponent( el.getAttribute( 'data-emcp-sig' ) || '' )
			+ '&page=' + page + '&mode=' + mode;
	}

	function send( el, page, mode ) {
		var headers = { Accept: 'application/json' };
		if ( cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}
		return win.fetch( restUrl( el, page, mode ), { credentials: 'same-origin', headers: headers } );
	}

	/**
	 * Refresh the REST nonce. A logged-in visitor served an anonymously
	 * cached page has no nonce at all, so this runs whether or not one was
	 * set. Core answers 0 for a logged-out visitor: that is not accepted.
	 */
	function refreshNonce() {
		if ( ! cfg.rest_nonce_url ) {
			return Promise.resolve( false );
		}
		return win.fetch( cfg.rest_nonce_url, { credentials: 'same-origin' } ).then( function ( r ) {
			return r.ok ? r.text() : '';
		} ).then( function ( text ) {
			text = String( text || '' ).trim();
			if ( NONCE_RE.test( text ) ) {
				cfg.nonce = text;
				return true;
			}
			return false;
		}, function () {
			return false;
		} );
	}

	function fetchPage( el, page, mode ) {
		return send( el, page, mode ).then( function ( res ) {
			if ( 403 !== res.status ) {
				return res;
			}
			return res.json().then( function ( body ) {
				return body && 'rest_cookie_invalid_nonce' === body.code;
			}, function () {
				return false;
			} ).then( function ( isNonce ) {
				if ( ! isNonce ) {
					return res;
				}
				return refreshNonce().then( function ( ok ) {
					return ok ? send( el, page, mode ) : res;
				} );
			} );
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'HTTP ' + res.status );
			}
			return res.json();
		} );
	}

	function pageUrl( el, page ) {
		var tpl = el.getAttribute( 'data-emcp-url' );
		var first = el.getAttribute( 'data-emcp-url-first' );
		if ( ! tpl ) {
			return '';
		}
		return urlForPage( tpl, first || tpl.split( '%#%' ).join( '1' ), page );
	}

	/* ---- state and teardown --------------------------------------------- */

	function makeState( el ) {
		var st = {
			el: el,
			ac: 'function' === typeof win.AbortController ? new win.AbortController() : null,
			manual: [],
			observers: [],
			swiper: null,
			raf: 0,
		};
		el._emcpLoop = st;
		live.add( el );
		return st;
	}

	function listen( st, target, type, fn, opts ) {
		var o = opts || {};
		if ( st.ac ) {
			o.signal = st.ac.signal;
		} else {
			st.manual.push( [ target, type, fn, o ] );
		}
		target.addEventListener( type, fn, o );
	}

	function teardown( el ) {
		var st = el._emcpLoop;
		if ( ! st ) {
			return;
		}
		if ( st.ac ) {
			st.ac.abort();
		}
		st.manual.forEach( function ( l ) {
			l[ 0 ].removeEventListener( l[ 1 ], l[ 2 ], l[ 3 ] );
		} );
		st.observers.forEach( function ( o ) {
			o.disconnect();
		} );
		if ( st.raf && win.cancelAnimationFrame ) {
			win.cancelAnimationFrame( st.raf );
		}
		if ( st.swiper && st.swiper.destroy ) {
			try {
				st.swiper.destroy( true, true );
			} catch ( e ) {}
		}
		el.classList.remove( 'is-ready' );
		st.dead = true;
		delete el._emcpLoop;
		live.delete( el );
	}

	function teardownTree( node ) {
		if ( ! node || ! node.querySelectorAll ) {
			return;
		}
		if ( node._emcpLoop ) {
			teardown( node );
		}
		toArray( node.querySelectorAll( '.emcp-loop' ) ).forEach( teardown );
	}

	function pruneDetached() {
		live.forEach( function ( el ) {
			if ( ! el.isConnected ) {
				teardown( el );
			}
		} );
	}

	/* ---- grid: masonry and animation ------------------------------------ */

	function masonry( el ) {
		if ( ! el.classList.contains( 'is-masonry' ) ) {
			return;
		}
		ownAll( el, '.emcp-loop__items > .emcp-loop__item' ).forEach( function ( item ) {
			// The item's own bottom margin is the row gap the stylesheet gives it.
			var gap = parseFloat( win.getComputedStyle( item ).marginBottom ) || 0;
			item.style.gridRowEnd = 'span ' + Math.max( 1, Math.ceil( item.getBoundingClientRect().height + gap ) );
		} );
	}

	function scheduleMasonry( st ) {
		if ( st.raf || st.dead ) {
			return;
		}
		var run = function () {
			st.raf = 0;
			if ( ! st.dead ) {
				masonry( st.el );
			}
		};
		st.raf = win.requestAnimationFrame ? win.requestAnimationFrame( run ) : win.setTimeout( run, 16 );
	}

	function watchMasonry( st, nodes ) {
		var el = st.el;
		if ( ! el.classList.contains( 'is-masonry' ) ) {
			return;
		}
		if ( 'function' === typeof win.ResizeObserver ) {
			if ( ! st.ro ) {
				st.ro = new win.ResizeObserver( function () {
					scheduleMasonry( st );
				} );
				st.observers.push( st.ro );
				var box = own( el, '.emcp-loop__items' );
				if ( box ) {
					st.ro.observe( box );
				}
			}
			// Items too: an image or web font changes an item's height
			// without changing the container's.
			( nodes || ownAll( el, '.emcp-loop__items > .emcp-loop__item' ) ).forEach( function ( n ) {
				st.ro.observe( n );
			} );
			return;
		}
		if ( ! st.resizeBound ) {
			st.resizeBound = true;
			listen( st, win, 'resize', function () {
				scheduleMasonry( st );
			} );
		}
		( nodes || ownAll( el, '.emcp-loop__items > .emcp-loop__item' ) ).forEach( function ( n ) {
			toArray( n.querySelectorAll( 'img' ) ).forEach( function ( img ) {
				if ( ! img.complete ) {
					listen( st, img, 'load', function () {
						scheduleMasonry( st );
					} );
					listen( st, img, 'error', function () {
						scheduleMasonry( st );
					} );
				}
			} );
		} );
	}

	/**
	 * Reveal one item. The stagger delay is cleared on the item's first
	 * transitionend (or a timer, for reduced motion), and .is-anim-done
	 * hands the transition back to the hover rules, so a later hover is
	 * never delayed.
	 */
	function reveal( item, delay, duration ) {
		var finished = false;
		var onEnd;
		var finish = function () {
			if ( finished ) {
				return;
			}
			finished = true;
			item.removeEventListener( 'transitionend', onEnd );
			item.style.removeProperty( '--emcp-anim-delay' );
			item.classList.add( 'is-anim-done' );
		};
		onEnd = function ( e ) {
			if ( e.target === item ) {
				finish();
			}
		};
		item.style.setProperty( '--emcp-anim-delay', delay + 'ms' );
		item.addEventListener( 'transitionend', onEnd );
		item.classList.add( 'is-visible' );
		win.setTimeout( finish, delay + duration + 200 );
	}

	function animate( st, items ) {
		var el = st.el;
		if ( ! /(^|\s)has-anim-/.test( el.className ) ) {
			return;
		}
		// Items are hidden only from here on, so a page whose script never
		// runs shows every card.
		el.classList.add( 'is-anim-ready' );
		items = ( items || ownItems( el ) ).filter( function ( i ) {
			return ! i.classList.contains( 'is-visible' );
		} );
		var cs = win.getComputedStyle( el );
		var step = parseInt( cs.getPropertyValue( '--emcp-anim-step' ), 10 ) || 0;
		var duration = parseInt( cs.getPropertyValue( '--emcp-anim-duration' ), 10 ) || 500;
		if ( 'function' !== typeof win.IntersectionObserver ) {
			items.forEach( function ( i ) {
				reveal( i, 0, duration );
			} );
			return;
		}
		if ( ! st.animIo ) {
			st.animIo = new win.IntersectionObserver( function ( entries ) {
				entries.filter( function ( e ) {
					return e.isIntersecting;
				} ).forEach( function ( e, i ) {
					st.animIo.unobserve( e.target );
					reveal( e.target, i * step, duration );
				} );
			}, { rootMargin: '0px 0px -10% 0px' } );
			st.observers.push( st.animIo );
		}
		items.forEach( function ( i ) {
			st.animIo.observe( i );
		} );
	}

	/* ---- grid: loading pages -------------------------------------------- */

	function elementorReady( nodes ) {
		var ef = win.elementorFrontend;
		if ( ! ef || ! ef.elementsHandler || 'function' !== typeof ef.elementsHandler.runReadyTrigger ) {
			return;
		}
		nodes.forEach( function ( n ) {
			var list = toArray( n.querySelectorAll( '.elementor-element' ) );
			if ( n.classList && n.classList.contains( 'elementor-element' ) ) {
				list.unshift( n );
			}
			list.forEach( function ( e ) {
				try {
					ef.elementsHandler.runReadyTrigger( e );
				} catch ( err ) {}
			} );
		} );
	}

	function insert( el, data, mode ) {
		var box = itemsContainer( el );
		var nodes = parseNodes( data.html );
		if ( 'replace' === mode ) {
			var st = el._emcpLoop;
			toArray( box.children ).forEach( function ( c ) {
				if ( st && st.ro ) {
					st.ro.unobserve( c );
				}
				if ( st && st.animIo ) {
					st.animIo.unobserve( c );
				}
				teardownTree( c );
				box.removeChild( c );
			} );
		}
		nodes.forEach( function ( n ) {
			box.appendChild( n );
		} );
		el.setAttribute( 'data-emcp-page', String( data.page ) );
		el.setAttribute( 'data-emcp-pages', String( data.max_pages ) );
		return nodes;
	}

	function focusCard( card ) {
		if ( ! card ) {
			return;
		}
		var target = card.querySelector( 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' );
		if ( ! target ) {
			if ( ! card.hasAttribute( 'tabindex' ) ) {
				card.setAttribute( 'tabindex', '-1' );
			}
			target = card;
		}
		try {
			target.focus( { preventScroll: true } );
		} catch ( e ) {
			target.focus();
		}
	}

	function focusElement( el ) {
		if ( ! el.hasAttribute( 'tabindex' ) ) {
			el.setAttribute( 'tabindex', '-1' );
		}
		try {
			el.focus( { preventScroll: true } );
		} catch ( e ) {
			el.focus();
		}
	}

	function afterInsert( st, nodes, data, mode ) {
		var el = st.el;
		applyAfter( data.assets );
		elementorReady( nodes );
		// Wire any nested grid or carousel inside the new cards.
		nodes.forEach( function ( n ) {
			initAll( n );
		} );
		masonry( el );
		watchMasonry( st, nodes );
		animate( st, nodes );
		if ( st.swiper && st.swiper.update ) {
			st.swiper.update();
		}
		el.dispatchEvent( new win.CustomEvent( 'replace' === mode ? 'emcp:loop:replaced' : 'emcp:loop:appended', { detail: { nodes: nodes, page: data.page } } ) );
	}

	function endReached( st, data ) {
		return data.page >= data.max_pages || 0 === data.count;
	}

	function removeAppendControls( st ) {
		var more = own( st.el, '.emcp-loop__more' );
		if ( more ) {
			more.parentNode.removeChild( more );
		}
		var sentinel = own( st.el, '.emcp-loop__sentinel' );
		if ( sentinel ) {
			if ( st.infIo ) {
				st.infIo.disconnect();
			}
			sentinel.parentNode.removeChild( sentinel );
		}
	}

	/**
	 * Load one page. trigger is nav, more or infinite; it decides the
	 * fallback. Resolves true when the page went in.
	 */
	function loadPage( st, page, mode, trigger ) {
		var el = st.el;
		if ( st.loading ) {
			return Promise.resolve( false );
		}
		st.loading = true;
		el.classList.add( 'is-loading' );
		el.setAttribute( 'aria-busy', 'true' );
		var btn = own( el, '.emcp-loop__more > .emcp-loop__more-btn' );
		if ( btn ) {
			btn.disabled = true;
			btn.classList.add( 'is-loading' );
		}
		var done = function () {
			st.loading = false;
			el.classList.remove( 'is-loading' );
			el.removeAttribute( 'aria-busy' );
			if ( btn ) {
				btn.disabled = false;
				btn.classList.remove( 'is-loading' );
			}
		};
		return fetchPage( el, page, mode ).then( function ( data ) {
			return applyBefore( data.assets ).then( function () {
				if ( st.dead ) {
					done();
					return false;
				}
				var nodes = insert( el, data, mode );
				// The page is in. Anything failing from here on is logged, never
				// answered with the reload fallback, which would throw the
				// inserted cards away.
				try {
					afterInsert( st, nodes, data, mode );
					if ( 'append' === mode && endReached( st, data ) ) {
						removeAppendControls( st );
					}
				} catch ( err ) {
					if ( win.console && win.console.error ) {
						win.console.error( 'EMCP Loop Grid: after-insert step failed.', err );
					}
				}
				done();
				if ( 'more' === trigger ) {
					focusCard( nodes[ 0 ] );
				}
				return data;
			} );
		} ).catch( function () {
			done();
			if ( st.dead ) {
				return false;
			}
			if ( 'infinite' === trigger ) {
				// Never navigate on a failure the visitor did not click for.
				if ( st.infIo ) {
					st.infIo.disconnect();
				}
				var sentinel = own( el, '.emcp-loop__sentinel' );
				if ( sentinel ) {
					sentinel.parentNode.removeChild( sentinel );
				}
				warnOnce( 'infinite', 'EMCP Loop Grid: could not load more posts; infinite scroll stopped.' );
				return false;
			}
			var url = pageUrl( el, page );
			if ( url ) {
				win.location.href = url;
			}
			return false;
		} );
	}

	/* ---- grid: numbers nav ----------------------------------------------- */

	function renderNav( el, nav, current, total ) {
		var tpl = el.getAttribute( 'data-emcp-url' ) || '';
		var first = el.getAttribute( 'data-emcp-url-first' ) || '';
		var items = navItems( current, total, nav.getAttribute( 'data-kind' ) || 'numbers', nav.getAttribute( 'data-end' ), nav.getAttribute( 'data-mid' ) );
		while ( nav.firstChild ) {
			nav.removeChild( nav.firstChild );
		}
		items.forEach( function ( it ) {
			var node;
			if ( 'current' === it.type ) {
				node = doc.createElement( 'span' );
				node.setAttribute( 'aria-current', 'page' );
				node.className = 'page-numbers current';
				node.textContent = String( it.page );
			} else if ( 'dots' === it.type ) {
				node = doc.createElement( 'span' );
				node.className = 'page-numbers dots';
				node.textContent = '…';
			} else {
				node = doc.createElement( 'a' );
				node.className = 'prev' === it.type ? 'prev page-numbers' : ( 'next' === it.type ? 'next page-numbers' : 'page-numbers' );
				node.setAttribute( 'href', urlForPage( tpl, first, it.page ) );
				node.textContent = 'prev' === it.type ? ( nav.getAttribute( 'data-prev' ) || '' ) : ( 'next' === it.type ? ( nav.getAttribute( 'data-next' ) || '' ) : String( it.page ) );
			}
			nav.appendChild( node );
		} );
		nav.hidden = 0 === items.length;
	}

	/**
	 * Second guard for replaceState: a per-element query-var URL only ever
	 * changes the query, so its path must be this document's path; one
	 * that is not (a URL built from some other request) is left out of the
	 * address bar. The current source paginates by path (/page/N/), so its
	 * path legitimately changes and only the origin is checked.
	 */
	function sameDocumentPath( el, href ) {
		if ( 'function' !== typeof win.URL || ! win.location ) {
			return false;
		}
		var target;
		try {
			target = new win.URL( href, win.location.href );
		} catch ( e ) {
			return false;
		}
		if ( target.origin !== win.location.origin ) {
			return false;
		}
		var pageVar = el.getAttribute( 'data-emcp-page-var' ) || '';
		var tpl = el.getAttribute( 'data-emcp-url' ) || '';
		if ( pageVar && -1 !== tpl.indexOf( pageVar + '=' ) ) {
			return target.pathname === win.location.pathname;
		}
		return true;
	}

	function wireNav( st ) {
		var el = st.el;
		var nav = own( el, '.emcp-loop__pagination' );
		if ( ! nav || '1' !== el.getAttribute( 'data-emcp-ajax' ) || 'replace' !== el.getAttribute( 'data-emcp-mode' ) || ! el.getAttribute( 'data-emcp-url' ) ) {
			return;
		}
		// Delegated, so the links of a rebuilt nav work too.
		listen( st, nav, 'click', function ( ev ) {
			if ( ev.defaultPrevented || 0 !== ev.button || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey ) {
				return;
			}
			var a = ev.target && ev.target.closest ? ev.target.closest( 'a' ) : null;
			if ( ! a || ! nav.contains( a ) ) {
				return;
			}
			var href = a.getAttribute( 'href' ) || '';
			var page = pageFromUrl( href, el.getAttribute( 'data-emcp-url' ), el.getAttribute( 'data-emcp-url-first' ) );
			if ( null === page || st.loading ) {
				// Not one of this loop's pages, or a load is already running:
				// let the browser follow the link.
				return;
			}
			ev.preventDefault();
			loadPage( st, page, 'replace', 'nav' ).then( function ( data ) {
				if ( ! data ) {
					return;
				}
				if ( win.history && win.history.replaceState && sameDocumentPath( el, href ) ) {
					try {
						win.history.replaceState( win.history.state, '', normalize( href ) );
					} catch ( e ) {}
				}
				renderNav( el, nav, data.page, data.max_pages );
				if ( el.getBoundingClientRect().top < 0 ) {
					el.scrollIntoView( { block: 'start' } );
				}
				focusElement( el );
			} );
		} );
	}

	/* ---- grid: load more and infinite scroll ----------------------------- */

	function nextPage( el ) {
		return intAttr( el, 'data-emcp-page', 1 ) + 1;
	}

	function wireAppend( st ) {
		var el = st.el;
		var btn = own( el, '.emcp-loop__more > .emcp-loop__more-btn' );
		if ( btn ) {
			listen( st, btn, 'click', function () {
				loadPage( st, nextPage( el ), 'append', 'more' );
			} );
		}
		var sentinel = own( el, '.emcp-loop__sentinel' );
		if ( ! sentinel ) {
			return;
		}
		if ( 'function' !== typeof win.IntersectionObserver ) {
			return;
		}
		var offset = intAttr( sentinel, 'data-offset', 0 );
		st.infIo = new win.IntersectionObserver( function ( entries ) {
			if ( ! entries.some( function ( e ) {
				return e.isIntersecting;
			} ) ) {
				return;
			}
			var next = nextPage( el );
			if ( next > intAttr( el, 'data-emcp-pages', 1 ) ) {
				removeAppendControls( st );
				return;
			}
			loadPage( st, next, 'append', 'infinite' ).then( function ( data ) {
				// Still in view after a short page: observe again so the
				// observer reports the current state and loads the next one.
				var s = own( el, '.emcp-loop__sentinel' );
				if ( data && s && st.infIo && ! st.dead ) {
					st.infIo.unobserve( s );
					st.infIo.observe( s );
				}
			} );
		}, { rootMargin: '0px 0px ' + offset + 'px 0px' } );
		st.observers.push( st.infIo );
		sentinel.hidden = false;
		sentinel.style.height = '1px';
		st.infIo.observe( sentinel );
	}

	/* ---- carousel -------------------------------------------------------- */

	function startSwiper( st ) {
		var el = st.el;
		var opts;
		try {
			opts = JSON.parse( el.getAttribute( 'data-emcp-carousel' ) || '{}' );
		} catch ( e ) {
			opts = {};
		}
		if ( opts.navigation ) {
			opts.navigation = { prevEl: own( el, '.emcp-loop__arrow--prev' ), nextEl: own( el, '.emcp-loop__arrow--next' ) };
		}
		if ( opts.pagination ) {
			opts.pagination.el = own( el, '.emcp-loop__dots' );
		}
		var container = own( el, '.swiper' );
		if ( ! container ) {
			return;
		}
		st.swiper = new win.Swiper( container, opts );
		el.classList.add( 'is-ready' );
		el.dispatchEvent( new win.CustomEvent( 'emcp:loop:carousel-ready', { detail: { swiper: st.swiper } } ) );
	}

	function wireCarousel( st ) {
		if ( 'function' === typeof win.Swiper ) {
			startSwiper( st );
			return;
		}
		var giveUp = function () {
			warnOnce( 'swiper', 'EMCP Loop Carousel: Swiper is not loaded, showing a static row.' );
		};
		if ( 'complete' === doc.readyState ) {
			giveUp();
			return;
		}
		// Optimisation plugins defer scripts: try once more on window load.
		listen( st, win, 'load', function () {
			if ( st.dead ) {
				return;
			}
			if ( 'function' === typeof win.Swiper ) {
				startSwiper( st );
			} else {
				giveUp();
			}
		}, { once: true } );
	}

	/* ---- init ------------------------------------------------------------ */

	function initOne( el, force ) {
		if ( el._emcpLoop ) {
			if ( ! force ) {
				return;
			}
			teardown( el );
		}
		var st = makeState( el );
		if ( el.classList.contains( 'emcp-loop--carousel' ) ) {
			wireCarousel( st );
		} else {
			wireNav( st );
			wireAppend( st );
			masonry( el );
			watchMasonry( st, null );
		}
		animate( st, null );
	}

	function initAll( root ) {
		pruneDetached();
		root = root || doc;
		var list = toArray( root.querySelectorAll( '.emcp-loop' ) );
		if ( root.classList && root.classList.contains( 'emcp-loop' ) ) {
			list.unshift( root );
		}
		list.forEach( function ( el ) {
			initOne( el, false );
		} );
	}

	/** The loops a widget scope owns itself, not ones nested in its cards. */
	function topLevelLoops( scope ) {
		var list = toArray( scope.querySelectorAll( '.emcp-loop' ) );
		if ( scope.classList && scope.classList.contains( 'emcp-loop' ) ) {
			list.unshift( scope );
		}
		return list.filter( function ( el ) {
			var outer = el.parentElement ? el.parentElement.closest( '.emcp-loop' ) : null;
			return ! outer || ! scope.contains( outer );
		} );
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	} else {
		initAll();
	}

	// Elementor editor preview only: re-init on every settings change.
	function hookElementor() {
		var ef = win.elementorFrontend;
		if ( ! ef || ! ef.hooks || hookElementor.done ) {
			return;
		}
		if ( 'function' !== typeof ef.isEditMode || ! ef.isEditMode() ) {
			return;
		}
		hookElementor.done = true;
		[ 'emcp-loop-grid.default', 'emcp-loop-carousel.default' ].forEach( function ( name ) {
			ef.hooks.addAction( 'frontend/element_ready/' + name, function ( $scope ) {
				var scope = $scope && $scope[ 0 ] ? $scope[ 0 ] : $scope;
				if ( ! scope || ! scope.querySelectorAll ) {
					return;
				}
				pruneDetached();
				topLevelLoops( scope ).forEach( function ( el ) {
					initOne( el, true );
				} );
				initAll( scope );
			} );
		} );
	}
	hookElementor();
	win.addEventListener( 'elementor/frontend/init', hookElementor );

	win.emcpThemerLoopInit = initAll;
}() );
