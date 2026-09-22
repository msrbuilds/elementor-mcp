/**
 * EMCP Themer — step-wise cascading condition builder.
 *
 * Renders rows of [Relation] [Group] [Sub-type] [Object (Pro)] [×] into the metabox
 * mount and serialises them to the hidden JSON field. The available groups follow
 * the selected template type (type-aware); Include/broad leaves are free, while the
 * Exclude relation + per-object search come from the Pro-extended schema.
 *
 * Config: window.emcpThemerCond = { schemasByType, isPro, ajax:{url,action,nonce}, i18n }.
 *
 * @package EMCP_Tools
 */
( function ( $ ) {
	'use strict';

	var cfg = window.emcpThemerCond || {};
	var i18n = cfg.i18n || {};
	var $app, $json, $type;
	var state = { type: '', priority: 0, rows: [] };

	function schema() {
		return ( cfg.schemasByType || {} )[ state.type ] || null;
	}

	function selectorKey( object ) {
		var i = ( object || '' ).indexOf( ':' );
		return i === -1 ? ( object || '' ) : object.substring( 0, i );
	}

	/** Find the {group, sub} whose selector matches (broad) or whose object pattern matches (specific). */
	function locate( object ) {
		var sc = schema();
		if ( ! sc ) { return null; }
		var key = selectorKey( object );
		for ( var g = 0; g < sc.groups.length; g++ ) {
			var group = sc.groups[ g ];
			for ( var s = 0; s < group.subs.length; s++ ) {
				var sub = group.subs[ s ];
				if ( sub.selector === object ) {
					return { group: group.value, sub: sub.value, objectValue: '', objectLabel: '' };
				}
				if ( sub.object && sub.object.specificSelector ) {
					var prefix = sub.object.specificSelector.split( '%d' )[ 0 ];
					if ( prefix && object.indexOf( prefix ) === 0 ) {
						return { group: group.value, sub: sub.value, objectValue: object, objectLabel: object };
					}
				}
			}
		}
		return null;
	}

	function subNode( groupVal, subVal ) {
		var sc = schema();
		if ( ! sc ) { return null; }
		for ( var g = 0; g < sc.groups.length; g++ ) {
			if ( sc.groups[ g ].value !== groupVal ) { continue; }
			for ( var s = 0; s < sc.groups[ g ].subs.length; s++ ) {
				if ( sc.groups[ g ].subs[ s ].value === subVal ) { return sc.groups[ g ].subs[ s ]; }
			}
		}
		return null;
	}

	function addRowFromObject( relation, object ) {
		var loc = locate( object );
		if ( ! loc ) { return; } // unknown/invalid on this type/tier — silently dropped.
		state.rows.push( { relation: relation, group: loc.group, sub: loc.sub, objectValue: loc.objectValue, objectLabel: loc.objectLabel } );
	}

	function blankRow() {
		var sc = schema();
		var firstGroup = sc && sc.groups.length ? sc.groups[ 0 ] : null;
		return {
			relation: 'include',
			group: firstGroup ? firstGroup.value : '',
			sub: firstGroup && firstGroup.subs.length ? firstGroup.subs[ 0 ].value : '',
			objectValue: '',
			objectLabel: ''
		};
	}

	function loadFromJson() {
		var data = {};
		try { data = JSON.parse( $json.val() || '{}' ); } catch ( e ) { data = {}; }
		state.priority = parseInt( data.priority, 10 ) || 0;
		state.rows = [];
		( data.include || [] ).forEach( function ( r ) { addRowFromObject( 'include', r && r.object ); } );
		( data.exclude || [] ).forEach( function ( r ) { addRowFromObject( 'exclude', r && r.object ); } );
		if ( ! state.rows.length ) { state.rows.push( blankRow() ); }
	}

	/** After a type change, drop rows whose group no longer exists. */
	function reconcileRows() {
		var sc = schema();
		if ( ! sc ) { state.rows = []; return; }
		var groups = sc.groups.map( function ( g ) { return g.value; } );
		state.rows = state.rows.filter( function ( r ) { return groups.indexOf( r.group ) !== -1; } );
		if ( ! state.rows.length ) { state.rows.push( blankRow() ); }
	}

	function objectSelector( node, row ) {
		if ( row.objectValue ) { return row.objectValue; }         // specific (Pro)
		return node ? node.selector : '';                          // broad
	}

	function serialize() {
		var include = [], exclude = [];
		state.rows.forEach( function ( r ) {
			var node = subNode( r.group, r.sub );
			var object = objectSelector( node, r );
			if ( ! object ) { return; }
			( r.relation === 'exclude' ? exclude : include ).push( { object: object } );
		} );
		$json.val( JSON.stringify( { include: include, exclude: exclude, priority: state.priority } ) );
	}

	function opt( value, label, selected ) {
		return $( '<option>' ).val( value ).text( label ).prop( 'selected', !! selected );
	}

	function renderRow( row, idx ) {
		var sc = schema();
		var $row = $( '<div class="emcp-cond-row" data-idx="' + idx + '"></div>' );

		// Relation (Include / [Pro] Exclude)
		var $rel = $( '<select class="emcp-cond-relation"></select>' );
		( sc.relations || [] ).forEach( function ( r ) { $rel.append( opt( r.value, r.label, r.value === row.relation ) ); } );
		$row.append( $( '<span class="emcp-cond-cell emcp-cond-cell--rel"></span>' ).append( $rel ) );

		// Group
		var $grp = $( '<select class="emcp-cond-group"></select>' );
		sc.groups.forEach( function ( g ) { $grp.append( opt( g.value, g.label, g.value === row.group ) ); } );
		$row.append( $( '<span class="emcp-cond-cell"></span>' ).append( $grp ) );

		// Sub-type
		var $sub = $( '<select class="emcp-cond-sub"></select>' );
		var group = sc.groups.filter( function ( g ) { return g.value === row.group; } )[ 0 ];
		( group ? group.subs : [] ).forEach( function ( s ) { $sub.append( opt( s.value, s.label, s.value === row.sub ) ); } );
		$row.append( $( '<span class="emcp-cond-cell"></span>' ).append( $sub ) );

		// Object (Pro): only when the chosen node carries an "object" descriptor.
		var node = subNode( row.group, row.sub );
		if ( node && node.object ) {
			$row.append( $( '<span class="emcp-cond-cell emcp-cond-cell--obj"></span>' ).append( buildObjectControl( node, row ) ) );
		}

		// Remove
		var $rm = $( '<button type="button" class="button-link emcp-cond-remove" title="' + ( i18n.remove || 'Remove' ) + '">&times;</button>' );
		$row.append( $rm );

		return $row;
	}

	/** A broad "All …" default + an async search box that appends specific results. */
	function buildObjectControl( node, row ) {
		var $wrap = $( '<span class="emcp-cond-object"></span>' );
		var $sel = $( '<select class="emcp-cond-object-select"></select>' );
		// Offer a broad "All …" only when the node has a broad selector; nodes that
		// exist only as specific targets (e.g. Author archive) require a pick.
		if ( node.selector ) {
			$sel.append( opt( '', ( i18n.all || 'All' ) + ' ' + node.label, ! row.objectValue ) );
		} else {
			$sel.append( opt( '', '— ' + node.label + ' —', ! row.objectValue ).prop( 'disabled', true ) );
		}
		if ( row.objectValue ) { $sel.append( opt( row.objectValue, row.objectLabel || row.objectValue, true ) ); }
		var $search = $( '<input type="text" class="emcp-cond-object-search" placeholder="' + ( i18n.searchType || '' ) + '">' );
		var $results = $( '<div class="emcp-cond-object-results"></div>' );
		$wrap.append( $sel ).append( $search ).append( $results );

		var timer = null;
		$search.on( 'input', function () {
			var q = $.trim( $search.val() );
			clearTimeout( timer );
			if ( q.length < 1 ) { $results.empty(); return; }
			timer = setTimeout( function () { doSearch( node, q, $results, $sel, row ); }, 250 );
		} );
		return $wrap;
	}

	function doSearch( node, q, $results, $sel, row ) {
		if ( ! cfg.ajax ) { return; }
		$results.html( '<span class="emcp-cond-searching">…</span>' );
		$.post( cfg.ajax.url, {
			action: cfg.ajax.action,
			nonce: cfg.ajax.nonce,
			object: JSON.stringify( node.object ),
			q: q
		} ).done( function ( resp ) {
			$results.empty();
			var items = ( resp && resp.data && resp.data.items ) || [];
			items.forEach( function ( it ) {
				$( '<a href="#" class="emcp-cond-result"></a>' ).text( it.label ).on( 'click', function ( e ) {
					e.preventDefault();
					var selector = node.object.specificSelector.replace( '%d', it.id );
					row.objectValue = selector;
					row.objectLabel = it.label;
					$sel.append( opt( selector, it.label, true ) );
					$results.empty();
					serialize();
				} ).appendTo( $results );
			} );
			if ( ! items.length ) { $results.html( '<span class="emcp-cond-searching">—</span>' ); }
		} );
	}

	function render() {
		if ( ! $app.length ) { return; }
		$app.empty();

		if ( ! schema() ) {
			$app.append( $( '<p class="description"></p>' ).text( i18n.noBuilder || '' ) );
			return;
		}

		var $rows = $( '<div class="emcp-cond-rows"></div>' );
		state.rows.forEach( function ( row, idx ) { $rows.append( renderRow( row, idx ) ); } );
		$app.append( $rows );

		var $add = $( '<button type="button" class="button emcp-cond-add"></button>' ).text( '+ ' + ( i18n.addCondition || 'Add condition' ) );
		$app.append( $( '<p></p>' ).append( $add ) );

		// Priority (Pro only).
		if ( cfg.isPro ) {
			var $pr = $( '<span class="emcp-cond-priority"></span>' );
			$pr.append( '<label>Priority </label>' );
			$pr.append( $( '<input type="number" class="small-text emcp-cond-priority-input">' ).val( state.priority ) );
			$app.append( $( '<p class="description"></p>' ).append( $pr ) );
		}
	}

	function bind() {
		$app.on( 'change', '.emcp-cond-relation', function () {
			state.rows[ rowIdx( this ) ].relation = $( this ).val();
			serialize();
		} );
		$app.on( 'change', '.emcp-cond-group', function () {
			var r = state.rows[ rowIdx( this ) ];
			r.group = $( this ).val();
			var group = schema().groups.filter( function ( g ) { return g.value === r.group; } )[ 0 ];
			r.sub = group && group.subs.length ? group.subs[ 0 ].value : '';
			r.objectValue = ''; r.objectLabel = '';
			render(); serialize();
		} );
		$app.on( 'change', '.emcp-cond-sub', function () {
			var r = state.rows[ rowIdx( this ) ];
			r.sub = $( this ).val();
			r.objectValue = ''; r.objectLabel = '';
			render(); serialize();
		} );
		$app.on( 'change', '.emcp-cond-object-select', function () {
			var r = state.rows[ rowIdx( this ) ];
			r.objectValue = $( this ).val();
			r.objectLabel = $( this ).find( 'option:selected' ).text();
			serialize();
		} );
		$app.on( 'click', '.emcp-cond-remove', function () {
			state.rows.splice( rowIdx( this ), 1 );
			if ( ! state.rows.length ) { state.rows.push( blankRow() ); }
			render(); serialize();
		} );
		$app.on( 'click', '.emcp-cond-add', function () {
			state.rows.push( blankRow() );
			render(); serialize();
		} );
		$app.on( 'input', '.emcp-cond-priority-input', function () {
			state.priority = parseInt( $( this ).val(), 10 ) || 0;
			serialize();
		} );
	}

	function rowIdx( el ) {
		return parseInt( $( el ).closest( '.emcp-cond-row' ).data( 'idx' ), 10 ) || 0;
	}

	function init() {
		$app = $( '#emcp-themer-conditions-app' );
		$json = $( '#emcp-themer-conditions-json' );
		$type = $( '#emcp-themer-type' );
		if ( ! $app.length || ! $json.length ) { return; }
		function togglePart() {
			var isPart = $type.val() === 'loop';
			$( '#emcp-themer-loop-preview' ).toggle( isPart );
			$( '#emcp-themer-conditions-wrap' ).toggle( ! isPart );
		}
		togglePart();
		state.type = $type.val();
		loadFromJson();
		render();
		bind();
		serialize();
		$type.on( 'change', function () {
			state.type = $type.val();
			reconcileRows();
			render();
			serialize();
			togglePart();
		} );
	}

	$( init );
}( jQuery ) );
