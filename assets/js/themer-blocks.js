/**
 * EMCP Themer — dynamic block editor registration (no build step).
 *
 * Registers each server-rendered block (attributes + supports + controls come
 * from PHP via window.emcpThemerBlocks) with a shared edit() that shows a
 * ServerSideRender live preview and an InspectorControls panel built from the
 * per-block control descriptors. save() returns null — output is dynamic.
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
	var data = window.emcpThemerBlocks || { blocks: {}, menus: [], category: 'emcp-themer' };

	var InspectorControls = be.InspectorControls;
	var useBlockProps = be.useBlockProps;
	var PanelBody = comp.PanelBody;
	var SelectControl = comp.SelectControl;
	var ToggleControl = comp.ToggleControl;
	var TextControl = comp.TextControl;
	var RangeControl = comp.RangeControl;

	function numberMax( key ) {
		if ( key === 'maxWidth' ) { return 600; }
		if ( key === 'columns' ) { return 6; }
		if ( key === 'length' ) { return 100; }
		return 12;
	}

	function renderControl( def, props ) {
		var key = def.key;
		var val = props.attributes[ key ];
		function set( v ) {
			var a = {};
			a[ key ] = v;
			props.setAttributes( a );
		}
		if ( def.type === 'select' ) {
			var opts = ( def.options || [] ).map( function ( o ) { return { label: o, value: o }; } );
			return el( SelectControl, { key: key, label: def.label, value: val, options: opts, onChange: set } );
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
				min: 0,
				max: numberMax( key ),
				onChange: function ( v ) { set( typeof v === 'number' ? v : 0 ); }
			} );
		}
		if ( def.type === 'menu' ) {
			var mopts = ( data.menus || [] ).map( function ( m ) { return { label: m.label, value: String( m.value ) }; } );
			return el( SelectControl, {
				key: key,
				label: def.label,
				value: String( val || 0 ),
				options: mopts,
				onChange: function ( v ) { set( parseInt( v, 10 ) || 0 ); }
			} );
		}
		return null;
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
						el(
							PanelBody,
							{ title: cfg.title, initialOpen: true },
							cfg.controls.map( function ( d ) { return renderControl( d, props ); } )
						)
					);
				}
				var preview = el( SSR, {
					block: name,
					attributes: props.attributes,
					className: 'emcp-dyn-ssr'
				} );
				return el( Fragment, {}, panel, el( 'div', blockProps, preview ) );
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
