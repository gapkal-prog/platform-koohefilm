/**
 * Koohe Film — افزودنی‌های ویرایشگر بلوک.
 *
 * وابستگی‌ها (از inc/block-styles.php): wp-blocks, wp-dom-ready, wp-i18n
 *
 * وظایف:
 *   ۱. ثبت تنوع‌های آماده (Block Variations) برای بلوک‌های ManaCore تا افزودن
 *      بخش‌های پرکاربرد (تازه‌ها، پرامتیازها، ترندها…) با یک کلیک انجام شود.
 *   ۲. تنوع‌های چیدمانی برای core/query و core/columns هم‌راستا با سبک‌های قالب.
 *
 * نکته: همه‌ی ثبت‌ها با بررسی وجود بلوک انجام می‌شود تا در نبود افزونه‌ی
 * ManaCore Core خطایی رخ ندهد.
 *
 * @package KooheFilm
 */

( function ( blocks, domReady, i18n ) {
	'use strict';

	if ( ! blocks || ! domReady ) {
		return;
	}

	var __ = i18n && i18n.__ ? i18n.__ : function ( text ) {
		return text;
	};

	var DOMAIN = 'koohe-film';

	/**
	 * آیا بلوک ثبت شده است؟
	 *
	 * @param {string} name نام بلوک.
	 * @return {boolean} نتیجه.
	 */
	function hasBlock( name ) {
		return !! ( blocks.getBlockType && blocks.getBlockType( name ) );
	}

	/**
	 * ثبت گروهی تنوع‌ها برای یک بلوک.
	 *
	 * @param {string} blockName  نام بلوک.
	 * @param {Array}  variations فهرست تنوع‌ها.
	 */
	function registerVariations( blockName, variations ) {
		if ( ! hasBlock( blockName ) || ! blocks.registerBlockVariation ) {
			return;
		}

		variations.forEach( function ( variation ) {
			blocks.registerBlockVariation( blockName, variation );
		} );
	}

	/**
	 * تنوع‌های شبکه‌ی آثار.
	 */
	function titlesGridVariations() {
		registerVariations( 'manacore/titles-grid', [
			{
				name:       'koohe-latest-movies',
				title:      __( 'آخرین فیلم‌ها (کوه فیلم)', DOMAIN ),
				description: __( 'شبکه‌ی ۶ ستونی از تازه‌ترین فیلم‌ها.', DOMAIN ),
				icon:       'video-alt2',
				attributes: {
					heading:       __( 'آخرین فیلم‌ها', DOMAIN ),
					source:        'latest',
					postTypes:     [ 'movie' ],
					count:         12,
					columns:       6,
					columnsTablet: 3,
					columnsMobile: 2,
					layout:        'grid',
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-latest-series',
				title:      __( 'آخرین سریال‌ها (کوه فیلم)', DOMAIN ),
				icon:       'welcome-view-site',
				attributes: {
					heading:       __( 'آخرین سریال‌ها', DOMAIN ),
					source:        'latest',
					postTypes:     [ 'series' ],
					count:         12,
					columns:       6,
					columnsTablet: 3,
					columnsMobile: 2,
					layout:        'grid',
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-latest-anime',
				title:      __( 'آخرین انیمه‌ها (کوه فیلم)', DOMAIN ),
				icon:       'format-video',
				attributes: {
					heading:       __( 'آخرین انیمه‌ها', DOMAIN ),
					source:        'latest',
					postTypes:     [ 'anime' ],
					count:         12,
					columns:       6,
					columnsTablet: 3,
					columnsMobile: 2,
					layout:        'grid',
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-trending-row',
				title:      __( 'ترند هفته — ردیف افقی', DOMAIN ),
				description: __( 'پرطرفدارترین آثار هفت روز گذشته در یک ردیف اسکرول‌شونده.', DOMAIN ),
				icon:       'chart-line',
				attributes: {
					heading:       __( 'ترند هفته', DOMAIN ),
					source:        'trending',
					count:         14,
					columns:       7,
					columnsTablet: 4,
					columnsMobile: 2,
					layout:        'carousel',
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-top-rated',
				title:      __( 'بالاترین امتیازها', DOMAIN ),
				icon:       'star-filled',
				attributes: {
					heading:      __( 'بالاترین امتیازها', DOMAIN ),
					source:       'top_rated',
					count:        12,
					columns:      6,
					layout:       'grid',
					headingIcon:  'star',
					showRating:   true,
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-most-viewed',
				title:      __( 'پربازدیدترین‌ها', DOMAIN ),
				icon:       'visibility',
				attributes: {
					heading:     __( 'پربازدیدترین‌ها', DOMAIN ),
					source:      'most_viewed',
					count:       12,
					columns:     6,
					layout:      'grid',
					headingIcon: 'eye',
					showViews:   true,
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-sidebar-list',
				title:      __( 'فهرست کنار‌ستون', DOMAIN ),
				description: __( 'چیدمان یک‌ستونی مناسب کنارستون.', DOMAIN ),
				icon:       'list-view',
				attributes: {
					heading:       __( 'پیشنهاد ویژه', DOMAIN ),
					source:        'trending',
					count:         6,
					columns:       1,
					columnsTablet: 1,
					columnsMobile: 1,
					layout:        'list',
					showMore:      false,
					cardStyle:     'wide',
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-random-picks',
				title:      __( 'انتخاب تصادفی', DOMAIN ),
				icon:       'randomize',
				attributes: {
					heading:     __( 'شاید بپسندید', DOMAIN ),
					source:      'random',
					count:       6,
					columns:     6,
					layout:      'grid',
					headingIcon: 'compass',
				},
				scope: [ 'inserter', 'transform' ],
			},
		] );
	}

	/**
	 * تنوع‌های اسلایدر ویژه.
	 */
	function heroVariations() {
		registerVariations( 'manacore/hero-slider', [
			{
				name:       'koohe-hero-featured',
				title:      __( 'اسلایدر ویژه — بلند', DOMAIN ),
				icon:       'slides',
				attributes: {
					source:     'featured',
					count:      6,
					height:     'large',
					autoplay:   true,
					interval:   8,
					showArrows: true,
					showDots:   true,
				},
				scope: [ 'inserter', 'transform' ],
			},
			{
				name:       'koohe-hero-trending',
				title:      __( 'اسلایدر ترندها — متوسط', DOMAIN ),
				icon:       'slides',
				attributes: {
					source:     'trending',
					count:      5,
					height:     'medium',
					autoplay:   true,
					interval:   6,
					showArrows: true,
				},
				scope: [ 'inserter', 'transform' ],
			},
		] );
	}

	/**
	 * تنوع‌های بلوک‌های هسته هم‌راستا با سبک‌های قالب.
	 */
	function coreVariations() {
		registerVariations( 'core/columns', [
			{
				name:       'koohe-content-sidebar',
				title:      __( 'محتوا + کنارستون (۶۸/۳۲)', DOMAIN ),
				description: __( 'چیدمان دوستونی استاندارد کوه فیلم.', DOMAIN ),
				icon:       'columns',
				attributes: { align: 'wide' },
				innerBlocks: [
					[ 'core/column', { width: '68%' }, [ [ 'core/paragraph', { placeholder: __( 'محتوای اصلی…', DOMAIN ) } ] ] ],
					[ 'core/column', { width: '32%' }, [ [ 'core/paragraph', { placeholder: __( 'کنارستون…', DOMAIN ) } ] ] ],
				],
				scope: [ 'inserter' ],
			},
		] );
	}

	domReady( function () {
		titlesGridVariations();
		heroVariations();
		coreVariations();
	} );
} )( window.wp && window.wp.blocks, window.wp && window.wp.domReady, window.wp && window.wp.i18n );
