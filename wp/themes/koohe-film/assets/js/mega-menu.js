/**
 * Koohe Film — کنترل‌های «مگامنو» در ویرایشگر فهرست راهبری.
 *
 * مدیر بدون نوشتن کد، از همان پنل تنظیمات بلوک (کنار «ظاهر» و «تنظیمات
 * پیوند») نقش هر آیتم را تعیین می‌کند:
 *
 *   - زیرمنوی اصلی «دسته‌بندی‌ها» → پنل مگامنو
 *   - زیرمنوهای داخلی پنل → ستون ژانرها (با انتخاب تعداد ستون) یا ستون دسترسی سریع
 *   - پیوندهای داخل پنل → سرستون، تیتر، ژانر، ردیف دسترسی سریع یا کارت ویژه
 *
 * نقش فقط با کلاس‌های CSS ذخیره می‌شود (`koohe-mega*`) تا ظاهر همان چیزی
 * باشد که قالب از پیش داشت و فهرست‌های موجود بی‌تغییر بمانند. کلاس‌های
 * دیگر آیتم (از جمله «Additional CSS class» دستی) دست‌نخورده می‌مانند.
 *
 * وابستگی‌ها (از inc/blocks.php): wp-blocks, wp-compose, wp-block-editor,
 * wp-components, wp-element, wp-hooks, wp-i18n. بدون JSX و بدون بیلد.
 *
 * @package KooheFilm
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.blockEditor || ! wp.components || ! wp.element ) {
		return;
	}

	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var __                = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var ToggleControl     = wp.components.ToggleControl;
	var DOMAIN            = 'koohe-film';

	/* نقش‌های مجاز برای هر نوع بلوک؛ هر نقش کلاس‌های خودش را دارد. */
	var ROLES = {
		'core/navigation-submenu': [
			{ value: '', label: __( 'زیرمنوی معمولی', DOMAIN ), classes: [] },
			{ value: 'mega', label: __( 'پنل مگامنو', DOMAIN ), classes: [ 'koohe-mega' ] },
			{ value: 'genres', label: __( 'ستون ژانرها (شبکه)', DOMAIN ), classes: [ 'koohe-mega-group', 'koohe-mega-genres' ] },
			{ value: 'quick', label: __( 'ستون دسترسی سریع', DOMAIN ), classes: [ 'koohe-mega-group', 'koohe-mega-quick' ] },
		],
		'core/navigation-link': [
			{ value: '', label: __( 'پیوند معمولی', DOMAIN ), classes: [] },
			{ value: 'eyebrow', label: __( 'سرستون پنل', DOMAIN ), classes: [ 'koohe-mega-eyebrow' ] },
			{ value: 'title', label: __( 'تیتر پنل', DOMAIN ), classes: [ 'koohe-mega-title' ] },
			{ value: 'genre', label: __( 'ژانر (چیپ)', DOMAIN ), classes: [ 'koohe-mega-genre' ] },
			{ value: 'quick', label: __( 'ردیف دسترسی سریع', DOMAIN ), classes: [ 'koohe-mega-quick' ] },
			{ value: 'rating', label: __( 'ردیف امتیازها (آیکن ستاره)', DOMAIN ), classes: [ 'koohe-mega-quick', 'koohe-mega-quick--rating' ] },
			{ value: 'newest', label: __( 'ردیف تازه‌ها (آیکن فیلم)', DOMAIN ), classes: [ 'koohe-mega-quick', 'koohe-mega-quick--newest' ] },
			{ value: 'korean', label: __( 'ردیف کره‌ای (آیکن پرچم)', DOMAIN ), classes: [ 'koohe-mega-quick', 'koohe-mega-quick--korean' ] },
			{ value: 'cast', label: __( 'ردیف بازیگران (آیکن فرد)', DOMAIN ), classes: [ 'koohe-mega-quick', 'koohe-mega-quick--cast' ] },
			{ value: 'feature', label: __( 'کارت ویژه', DOMAIN ), classes: [ 'koohe-mega-feature' ] },
		],
	};

	/* تعداد ستون‌های شبکه‌ی ژانرها؛ پیش‌فرض ۳ (بدون کلاس). */
	var COLUMN_CLASSES = [ 'koohe-mega-cols-2', 'koohe-mega-cols-3', 'koohe-mega-cols-4' ];

	/* کلاس‌هایی که این کنترل‌ها مالک‌شان هستند و هنگام تغییر نقش پاک می‌شوند. */
	function managedClasses() {
		var list = [ 'koohe-nav-dot' ].concat( COLUMN_CLASSES );

		Object.keys( ROLES ).forEach( function ( name ) {
			ROLES[ name ].forEach( function ( role ) {
				role.classes.forEach( function ( cls ) {
					if ( -1 === list.indexOf( cls ) ) {
						list.push( cls );
					}
				} );
			} );
		} );

		return list;
	}

	function splitClasses( value ) {
		return String( value || '' ).split( /\s+/ ).filter( Boolean );
	}

	/**
	 * نقش فعلی از روی کلاس‌ها: بیشترین تطابق کلاس برنده است، تا مثلاً
	 * «ردیف امتیازها» بر «ردیف دسترسی سریع» ترجیح داشته باشد.
	 */
	function currentRole( name, classes ) {
		var best = '';
		var bestScore = 0;

		ROLES[ name ].forEach( function ( role ) {
			var matches = role.classes.every( function ( cls ) {
				return -1 !== classes.indexOf( cls );
			} );

			if ( matches && role.classes.length > bestScore ) {
				best = role.value;
				bestScore = role.classes.length;
			}
		} );

		return best;
	}

	/** کلاس‌های مدیریت‌شده را برمی‌دارد و کلاس‌های جدید را می‌افزاید. */
	function replaceManaged( classes, add ) {
		var managed = managedClasses();
		var kept    = classes.filter( function ( cls ) {
			return -1 === managed.indexOf( cls );
		} );

		return kept.concat( add ).join( ' ' );
	}

	function roleOptions( name ) {
		return ROLES[ name ].map( function ( role ) {
			return { value: role.value, label: role.label };
		} );
	}

	var withMegaControls = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! ROLES[ props.name ] || ! props.setAttributes ) {
				return el( BlockEdit, props );
			}

			var attributes = props.attributes || {};
			var classes    = splitClasses( attributes.className );
			var role       = currentRole( props.name, classes );
			var isLink     = 'core/navigation-link' === props.name;
			var controls   = [];

			controls.push(
				el( SelectControl, {
					key: 'role',
					label: __( 'نقش در مگامنو', DOMAIN ),
					help: __( 'برای ساخت پنل، زیرمنوی «دسته‌بندی‌ها» را «پنل مگامنو» کنید و ستون‌ها و پیوندها را در داخلش بسازید.', DOMAIN ),
					value: role,
					options: roleOptions( props.name ),
					onChange: function ( value ) {
						var chosen = ROLES[ props.name ].filter( function ( item ) {
							return item.value === value;
						} )[ 0 ];

						props.setAttributes( {
							className: replaceManaged( classes, chosen ? chosen.classes : [] ) || undefined,
						} );
					},
				} )
			);

			if ( 'genres' === role ) {
				var columns = 3;

				COLUMN_CLASSES.forEach( function ( cls, index ) {
					if ( -1 !== classes.indexOf( cls ) ) {
						columns = index + 2;
					}
				} );

				controls.push(
					el( SelectControl, {
						key: 'columns',
						label: __( 'تعداد ستون‌های ژانرها', DOMAIN ),
						value: String( columns ),
						options: [
							{ value: '2', label: '2' },
							{ value: '3', label: '3' },
							{ value: '4', label: '4' },
						],
						onChange: function ( value ) {
							var add = [ 'koohe-mega-group', 'koohe-mega-genres' ];

							if ( '3' !== value ) {
								add.push( 'koohe-mega-cols-' + value );
							}

							props.setAttributes( {
								className: replaceManaged( classes, add ) || undefined,
							} );
						},
					} )
				);
			}

			if ( isLink ) {
				controls.push(
					el( ToggleControl, {
						key: 'dot',
						label: __( 'نقطه‌ی تزئینی کنار پیوند', DOMAIN ),
						help: __( 'مثل نقطه‌ی کنار «برنامه پخش» در طرح مرجع.', DOMAIN ),
						checked: -1 !== classes.indexOf( 'koohe-nav-dot' ),
						onChange: function ( checked ) {
							var next = classes.filter( function ( cls ) {
								return 'koohe-nav-dot' !== cls;
							} );

							if ( checked ) {
								next.push( 'koohe-nav-dot' );
							}

							props.setAttributes( { className: next.join( ' ' ) || undefined } );
						},
					} )
				);
			}

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'مگامنو', DOMAIN ), initialOpen: true },
						controls
					)
				)
			);
		};
	}, 'withKooheMegaMenuControls' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'koohe-film/mega-menu-controls', withMegaControls );
} )( window.wp );
