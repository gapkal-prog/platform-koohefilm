// شبیه‌سازی محیط ویرایشگر برای اطمینان از ثبت شدن بلوک‌ها.
var registered = {};
var els = 0;
function mkEl( type, props ) {
	els++;
	var kids = Array.prototype.slice.call( arguments, 2 );
	return { type: type, props: props, children: kids };
}
global.window = global;
var stub = function ( n ) { return { __name: n }; };
global.wp = {
	blocks: {
		registerBlockType: function ( name, cfg ) { registered[ name ] = cfg; },
		getBlockType: function () { return null; },
		unregisterBlockType: function () {},
	},
	element: { createElement: mkEl, Fragment: stub( 'Fragment' ) },
	i18n: { __: function ( s ) { return s; } },
	blockEditor: { InspectorControls: stub( 'Inspector' ), useBlockProps: function () { return { className: 'wp-block' }; } },
	components: {
		PanelBody: stub('PanelBody'), PanelRow: stub('PanelRow'), TextControl: stub('TextControl'),
		TextareaControl: stub('TextareaControl'), SelectControl: stub('SelectControl'),
		RangeControl: stub('RangeControl'), ToggleControl: stub('ToggleControl'),
		Button: stub('Button'), FormTokenField: stub('FormTokenField'), Notice: stub('Notice'),
	},
	serverSideRender: stub( 'SSR' ),
};
global.manaCoreBlocks = JSON.parse( require('fs').readFileSync( require('path').join( __dirname, 'fixtures', 'payload.json' ), 'utf8' ) );
global.manaCoreBlockRegistry = JSON.parse( require('fs').readFileSync( require('path').join( __dirname, 'fixtures', 'registry.json' ), 'utf8' ) );

require( require('path').join( __dirname, '..', 'plugins', 'manacore-core', 'assets', 'js', 'blocks.js' ) );

var names = Object.keys( registered );
console.log( 'registered blocks: ' + names.length );
names.forEach(function(n){ console.log('  - ' + n + '  attrs=' + Object.keys(registered[n].attributes).length); });

// اجرای edit هر بلوک با ویژگی‌های پیش‌فرض تا خطاهای زمان اجرا آشکار شود.
var fails = 0;
names.forEach( function ( n ) {
	var cfg = registered[ n ];
	var attrs = {};
	Object.keys( cfg.attributes ).forEach( function ( k ) {
		var d = cfg.attributes[ k ].default;
		attrs[ k ] = typeof d === 'undefined' ? undefined : d;
	} );
	var props = { attributes: attrs, setAttributes: function () {} };
	try {
		cfg.edit( props );
	} catch ( e ) {
		fails++;
		console.log( 'EDIT FAIL ' + n + ': ' + e.message );
	}
	// حالت دوم: نمایش شرطی فعال با یک قاعده از هر نوع.
	if ( attrs.visibility ) {
		var types = Object.keys( global.manaCoreBlocks.visibility.ruleTypes );
		types.forEach( function ( t ) {
			var p2 = { attributes: Object.assign( {}, attrs, {
				visibility: { enabled: true, action: 'show', relation: 'AND',
					rules: [ { type: t, operator: 'is', taxonomy: 'genre', values: [], from: '', to: '', key: '' } ],
					devices: [ 'mobile' ] } } ), setAttributes: function () {} };
			try { cfg.edit( p2 ); } catch ( e ) { fails++; console.log( 'VIS FAIL ' + n + '/' + t + ': ' + e.message ); }
		} );
	}
	// حالت سوم: یک قاعده‌ی تاکسونومی در کوئری.
	if ( typeof attrs.taxQuery !== 'undefined' ) {
		var p3 = { attributes: Object.assign( {}, attrs, { taxQuery: [ { taxonomy: 'genre', terms: ['action'], operator: 'IN' }, { taxonomy: 'country', terms: [], operator: 'NOT IN' } ], orderBy: 'meta_num', showExcerpt: true } ), setAttributes: function () {} };
		try { cfg.edit( p3 ); } catch ( e ) { fails++; console.log( 'TAX FAIL ' + n + ': ' + e.message ); }
	}
} );
console.log( fails === 0 ? 'RUNTIME: all edit() calls OK (elements built: ' + els + ')' : 'RUNTIME FAILURES: ' + fails );

// بازرسی عمیق: شمارش پنل‌ها و کنترل‌های واقعی هر بلوک.
function walk( node, out ) {
	if ( ! node || typeof node !== 'object' ) { return out; }
	if ( Array.isArray( node ) ) { node.forEach( function ( n ) { walk( n, out ); } ); return out; }
	if ( node.type && node.type.__name ) {
		var nm = node.type.__name;
		out.counts[ nm ] = ( out.counts[ nm ] || 0 ) + 1;
		if ( 'PanelBody' === nm && node.props && node.props.title ) { out.panels.push( node.props.title ); }
	}
	if ( node.children ) { walk( node.children, out ); }
	if ( node.props ) { Object.keys( node.props ).forEach( function ( k ) {
		if ( 'object' === typeof node.props[ k ] ) { walk( node.props[ k ], out ); }
	} ); }
	return out;
}

console.log( '\n=== PANEL / CONTROL INVENTORY (default attrs) ===' );
names.forEach( function ( n ) {
	var cfg = registered[ n ];
	var attrs = {};
	Object.keys( cfg.attributes ).forEach( function ( k ) { attrs[ k ] = cfg.attributes[ k ].default; } );
	var tree = cfg.edit( { attributes: attrs, setAttributes: function () {} } );
	var out = walk( tree, { counts: {}, panels: [] } );
	console.log( n + '  panels=' + out.panels.length );
	console.log( '   ' + out.panels.join( ' | ' ) );
	var ctl = Object.keys( out.counts ).filter( function ( k ) { return /Control|Field|Notice|Button/.test( k ); } )
		.map( function ( k ) { return k + ':' + out.counts[ k ]; } ).join( ', ' );
	console.log( '   controls -> ' + ctl );
} );

// بازرسی پنل نمایش شرطی در حالت فعال.
console.log( '\n=== VISIBILITY PANEL (enabled, 12 rule types) ===' );
var g = registered['manacore/titles-grid'];
var base = {}; Object.keys( g.attributes ).forEach( function ( k ) { base[ k ] = g.attributes[ k ].default; } );
Object.keys( global.manaCoreBlocks.visibility.ruleTypes ).forEach( function ( t ) {
	var tree = g.edit( { attributes: Object.assign( {}, base, { visibility: { enabled: true, action: 'hide', relation: 'OR',
		rules: [ { type: t, operator: 'is', taxonomy: 'genre', values: ['action'], from: '2024-01-01', to: '2025-01-01', key: 'foo' } ],
		devices: [ 'mobile', 'tablet' ] } } ), setAttributes: function () {} } );
	var out = walk( tree, { counts: {}, panels: [] } );
	var ctl = Object.keys( out.counts ).filter( function ( k ) { return /Control|Field/.test( k ); } )
		.map( function ( k ) { return k + ':' + out.counts[ k ]; } ).join( ',' );
	console.log( '  ' + t.padEnd( 14 ) + ' -> ' + ctl );
} );
