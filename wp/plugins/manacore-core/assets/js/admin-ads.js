/**
 * برگزیننده‌ی تصویر بنر تبلیغاتی.
 *
 * فقط یک کار می‌کند: دکمه‌ی «انتخاب از کتابخانه» را به `wp.media` وصل
 * می‌کند و نشانی تصویر انتخابی را در فیلد متنی می‌ریزد. نگه‌داشتن نشانی
 * در یک فیلد متنی (به‌جای شناسه‌ی پیوست) این مزیت را دارد که مدیر می‌تواند
 * بنر بیرونی (CDN) هم بدهد.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.querySelector( '[data-manacore-ad-media]' );
		var field = document.getElementById( 'manacore_ad_image' );

		if ( ! button || ! field || ! window.wp || ! window.wp.media ) {
			return;
		}

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var frame = window.wp.media( {
				title: 'انتخاب تصویر بنر',
				button: { text: 'استفاده از این تصویر' },
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = attachment && attachment.url ? attachment.url : '';

				field.value = url;
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );

			frame.open();
		} );
	} );
} )();
