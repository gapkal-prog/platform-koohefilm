/**
 * Koohe Film — پیش‌نمایش زنده‌ی سفارشی‌ساز.
 *
 * تنها گزینه‌هایی که transport آن‌ها postMessage است اینجا مدیریت می‌شوند؛
 * بقیه با بارگذاری دوباره‌ی پیش‌نمایش اعمال می‌گردند.
 *
 * وابستگی: wp.customize (customize-preview)
 *
 * @package KooheFilm
 */

( function ( api ) {
	'use strict';

	if ( ! api ) {
		return;
	}

	var root = document.documentElement;

	/**
	 * نوشتن متن در همه‌ی عنصرهای یک انتخاب‌گر.
	 *
	 * @param {string} selector انتخاب‌گر CSS.
	 * @param {string} value    متن تازه.
	 */
	function setText( selector, value ) {
		var nodes = document.querySelectorAll( selector );
		var i;

		for ( i = 0; i < nodes.length; i++ ) {
			nodes[ i ].textContent = value;
		}
	}

	/* عنوان سایت. */
	api( 'blogname', function ( setting ) {
		setting.bind( function ( value ) {
			setText( '.wp-block-site-title a, .wp-block-site-title', value );
		} );
	} );

	/* توضیح کوتاه سایت. */
	api( 'blogdescription', function ( setting ) {
		setting.bind( function ( value ) {
			setText( '.wp-block-site-tagline', value );
		} );
	} );

	/*
	 * نسبت پوستر: مقدار روی متغیر CSS نوشته می‌شود تا همه‌ی کارت‌ها
	 * بی‌درنگ و بدون بارگذاری دوباره به‌روز شوند.
	 */
	api( 'koohe_poster_ratio', function ( setting ) {
		setting.bind( function ( value ) {
			var safe = /^\d+\s*\/\s*\d+$/.test( String( value ) ) ? String( value ) : '2/3';

			root.style.setProperty( '--koohe-poster-ratio', safe.replace( '/', ' / ' ) );

			/* همگام‌سازی کلاس بدنه برای انتخاب‌گرهای وابسته. */
			var body = document.body;

			body.className = body.className.replace( /\bkoohe-poster-[\w-]+/g, '' ).trim();
			body.classList.add( 'koohe-poster-' + safe.replace( /\s/g, '' ).replace( '/', '-' ) );
		} );
	} );
} )( window.wp && window.wp.customize );
