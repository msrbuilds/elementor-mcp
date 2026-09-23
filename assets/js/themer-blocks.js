/**
 * EMCP Themer: dynamic block editor registration (no build step).
 *
 * Registers each server-rendered block (attributes + supports + controls come
 * from PHP via window.emcpThemerBlocks) with a shared edit() that shows a
 * ServerSideRender live preview and an InspectorControls panel built from the
 * per-block control descriptors. save() returns null: output is dynamic.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.serverSideRender ) {
		return;
	}

	var blocks = wp.blocks;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor || wp.editor;
	var comp = wp.components;
	var SSR = wp.serverSideRender;
	var data = window.emcpThemerBlocks || {};
	data.blocks = data.blocks || {};
	data.category = data.category || 'emcp-themer';

	var InspectorControls = be.InspectorControls;
	var useBlockProps = be.useBlockProps;
	var PanelBody = comp.PanelBody;
	var SelectControl = comp.SelectControl;
	var ToggleControl = comp.ToggleControl;
	var TextControl = comp.TextControl;
	var RangeControl = comp.RangeControl;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	var BaseControl = comp.BaseControl;
	var Disabled = comp.Disabled;
	var useInstanceId = wp.compose && wp.compose.useInstanceId;

	/** Blocks whose preview holds links and buttons that must not be clickable. */
	var DISABLED_PREVIEW = { 'loop-grid': true, 'loop-carousel': true };

	function numberMax( key ) {
		if ( key === 'maxWidth' ) { return 600; }
		if ( key === 'columns' ) { return 6; }
		if ( key === 'length' ) { return 100; }
		return 12;
	}

	/**
	 * A descriptor's options as [{ value, label }]. A descriptor names a
	 * shared list (optionsKey, from data.loopOptions) or carries its own
	 * plain values (options). Saved values missing from the list are added,
	 * labelled by their value, so a saved selection always shows and
	 * survives an edit. Labels are plain text; React escapes them.
	 */
	function optionsFor( def, val ) {
		var list;
		if ( def.optionsKey ) {
			list = ( data.loopOptions && data.loopOptions[ def.optionsKey ] ) || [];
		} else {
			list = ( def.options || [] ).map( function ( o ) { return { label: String( o ), value: String( o ) }; } );
		}
		var saved = Array.isArray( val ) ? val : ( val === undefined || val === null || val === '' ? [] : [ val ] );
		var known = {};
		list.forEach( function ( o ) { known[ String( o.value ) ] = true; } );
		var extra = [];
		saved.forEach( function ( v ) {
			var s = String( v );
			if ( s !== '' && ! known[ s ] ) {
				known[ s ] = true;
				extra.push( { value: s, label: s } );
			}
		} );
		return extra.length ? list.concat( extra ) : list;
	}

	/**
	 * Whether a descriptor's `when` holds for these attributes: one
	 * condition or a list that must all hold; `in` (the value is one of
	 * these) or `notIn` (none of these), compared strictly. The server
	 * applies the same rule (EMCP_Tools_Themer_Loop_Block_Map::is_shown())
	 * and leaves a hidden control's attribute out of the render.
	 */
	function isShown( def, attributes ) {
		var w = def.when;
		if ( ! w ) {
			return true;
		}
		var list = Array.isArray( w ) ? w : [ w ];
		for ( var i = 0; i < list.length; i++ ) {
			var c = list[ i ];
			if ( ! c || ! c.attr ) {
				continue;
			}
			var v = attributes[ c.attr ];
			if ( Array.isArray( c.in ) && c.in.indexOf( v ) === -1 ) {
				return false;
			}
			if ( Array.isArray( c.notIn ) && c.notIn.indexOf( v ) !== -1 ) {
				return false;
			}
		}
		return true;
	}

	/** A list as the text the control shows. */
	function listText( v ) {
		return Array.isArray( v ) ? v.join( ', ' ) : String( v === undefined || v === null ? '' : v );
	}

	/** Comma-separated text as a trimmed list without empties. */
	function parseList( text ) {
		return String( text ).split( ',' ).map( function ( x ) { return x.trim(); } ).filter( function ( x ) { return x !== ''; } );
	}

	/**
	 * A list attribute edited as comma-separated text. The draft is local
	 * while typing (a trailing comma must survive) and is saved on blur or
	 * Enter. When the saved value changes from elsewhere (undo, another
	 * control, a pasted block), the draft follows it.
	 */
	function TextListControl( p ) {
		var st = useState( listText( p.value ) );
		var draft = st[ 0 ];
		var setDraft = st[ 1 ];
		var committed = useRef( listText( p.value ) );
		useEffect( function () {
			var now = listText( p.value );
			if ( now !== committed.current ) {
				committed.current = now;
				setDraft( now );
			}
		}, [ listText( p.value ) ] );
		function commit() {
			var parts = parseList( draft );
			var current = Array.isArray( p.value ) ? p.value.map( String ) : parseList( listText( p.value ) );
			committed.current = parts.join( ', ' );
			if ( parts.join( ',' ) === current.join( ',' ) ) {
				return;
			}
			p.onChange( parts );
		}
		return el( TextControl, {
			label: p.label,
			value: draft,
			onChange: setDraft,
			onBlur: commit,
			onKeyDown: function ( ev ) {
				if ( ev && ev.key === 'Enter' ) {
					commit();
				}
			}
		} );
	}

	/** A labelled native multiple select. */
	function MultiSelectControl( p ) {
		var id = 'emcp-multiselect-' + ( useInstanceId ? useInstanceId( MultiSelectControl ) : p.name );
		var current = Array.isArray( p.value ) ? p.value.map( String ) : [];
		return el( BaseControl, { id: id, label: p.label, className: 'emcp-multiselect' },
			el( 'select', {
				id: id,
				multiple: true,
				size: Math.min( 8, Math.max( 3, p.options.length ) ),
				style: { width: '100%' },
				value: current,
				onChange: function ( ev ) {
					var picked = Array.prototype.filter.call( ev.target.options, function ( o ) { return o.selected; } )
						.map( function ( o ) { return o.value; } );
					p.onChange( picked );
				}
			}, p.options.map( function ( o ) {
				return el( 'option', { key: String( o.value ), value: String( o.value ) }, o.label );
			} ) )
		);
	}

	function renderControl( def, props ) {
		var key = def.key;
		var val = props.attributes[ key ];
		function set( v ) {
			var a = {};
			a[ key ] = v;
			props.setAttributes( a );
		}
		if ( ! isShown( def, props.attributes ) ) {
			return null;
		}
		if ( def.type === 'select' ) {
			var opts = optionsFor( def, val );
			// A number attribute (a Loop Item id) is stored as a number; the
			// select itself works on strings.
			var numeric = typeof val === 'number';
			return el( SelectControl, {
				key: key,
				label: def.label,
				value: val === undefined || val === null ? '' : String( val ),
				options: opts,
				onChange: function ( v ) { set( numeric ? ( parseInt( v, 10 ) || 0 ) : v ); }
			} );
		}
		if ( def.type === 'multiselect' ) {
			return el( MultiSelectControl, { key: key, name: key, label: def.label, value: val, options: optionsFor( def, val ), onChange: set } );
		}
		if ( def.type === 'text-list' ) {
			return el( TextListControl, { key: key, label: def.label, value: val, onChange: set } );
		}
		if ( def.type === 'toggle' ) {
			return el( ToggleControl, { key: key, label: def.label, checked: !! val, onChange: set } );
		}
		if ( def.type === 'text' ) {
			return el( TextControl, { key: key, label: def.label, value: val || '', onChange: set } );
		}
		if ( def.type === 'number' ) {
			return el( RangeControl, {
				key: key,
				label: def.label,
				value: typeof val === 'number' ? val : 0,
				min: typeof def.min === 'number' ? def.min : 0,
				max: typeof def.max === 'number' ? def.max : numberMax( key ),
				onChange: function ( v ) { set( typeof v === 'number' ? v : 0 ); }
			} );
		}
		if ( def.type === 'menu' ) {
			var menuOpts = ( data.menus || [] ).map( function ( m ) { return { label: m.label, value: String( m.value ) }; } );
			return el( SelectControl, {
				key: key,
				label: def.label,
				value: String( val || 0 ),
				options: menuOpts,
				onChange: function ( v ) { set( parseInt( v, 10 ) || 0 ); }
			} );
		}
		return null;
	}

	/**
	 * Inspector panels: descriptors with a `panel` are grouped under it (in
	 * first-seen order); the rest share one panel named after the block.
	 */
	function renderPanels( cfg, props ) {
		var order = [];
		var groups = {};
		cfg.controls.forEach( function ( d ) {
			var title = d.panel || cfg.title;
			if ( ! groups[ title ] ) {
				groups[ title ] = [];
				order.push( title );
			}
			groups[ title ].push( d );
		} );
		return order.map( function ( title, i ) {
			return el(
				PanelBody,
				{ key: title, title: title, initialOpen: i === 0 },
				groups[ title ].map( function ( d ) { return renderControl( d, props ); } )
			);
		} );
	}

	Object.keys( data.blocks ).forEach( function ( key ) {
		var cfg = data.blocks[ key ];
		var name = 'emcp/' + key;
		if ( blocks.getBlockType && blocks.getBlockType( name ) ) {
			return;
		}
		blocks.registerBlockType( name, {
			apiVersion: 2,
			title: cfg.title,
			category: data.category,
			icon: cfg.icon || 'admin-generic',
			attributes: cfg.attributes || {},
			supports: cfg.supports || {},
			edit: function ( props ) {
				var blockProps = useBlockProps ? useBlockProps() : {};
				var panel = null;
				if ( cfg.controls && cfg.controls.length ) {
					panel = el(
						InspectorControls,
						{},
						renderPanels( cfg, props )
					);
				}
				var preview = el( SSR, {
					block: name,
					attributes: props.attributes,
					className: 'emcp-dyn-ssr'
				} );
				if ( DISABLED_PREVIEW[ key ] && Disabled ) {
					preview = el( Disabled, {}, preview );
				}
				return el( Fragment, {}, panel, el( 'div', blockProps, preview ) );
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
