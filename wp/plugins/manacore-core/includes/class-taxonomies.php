<?php
/**
 * ثبت تاکسونومی‌ها.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Taxonomies
 */
class Taxonomies {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 6 );
	}

	/**
	 * تعریف تاکسونومی‌ها.
	 *
	 * @return array
	 */
	public function definitions() {
		$titles = manacore_title_post_types();
		$all    = array_merge( $titles, array( 'episode' ) );

		return apply_filters(
			'manacore_taxonomies',
			array(
				'genre'    => array(
					'singular'     => __( 'ژانر', 'manacore' ),
					'plural'       => __( 'ژانرها', 'manacore' ),
					'object_types' => $titles,
					'hierarchical' => true,
					'slug'         => 'genre',
				),
				'country'  => array(
					'singular'     => __( 'کشور', 'manacore' ),
					'plural'       => __( 'کشورها', 'manacore' ),
					'object_types' => $titles,
					'hierarchical' => true,
					'slug'         => 'country',
				),
				'release_year' => array(
					'singular'     => __( 'سال انتشار', 'manacore' ),
					'plural'       => __( 'سال‌های انتشار', 'manacore' ),
					'object_types' => $titles,
					'hierarchical' => true,
					'slug'         => 'year',
				),
				'network'  => array(
					'singular'     => __( 'شبکه', 'manacore' ),
					'plural'       => __( 'شبکه‌ها', 'manacore' ),
					'object_types' => array( 'series', 'anime' ),
					'hierarchical' => true,
					'slug'         => 'network',
				),
				'studio'   => array(
					'singular'     => __( 'استودیو', 'manacore' ),
					'plural'       => __( 'استودیوها', 'manacore' ),
					'object_types' => $titles,
					'hierarchical' => true,
					'slug'         => 'studio',
				),
				'quality'  => array(
					'singular'     => __( 'کیفیت', 'manacore' ),
					'plural'       => __( 'کیفیت‌ها', 'manacore' ),
					'object_types' => $all,
					'hierarchical' => true,
					'slug'         => 'quality',
				),
				'language' => array(
					'singular'     => __( 'زبان', 'manacore' ),
					'plural'       => __( 'زبان‌ها', 'manacore' ),
					'object_types' => $all,
					'hierarchical' => true,
					'slug'         => 'language',
				),
				'age_rating' => array(
					'singular'     => __( 'رده سنی', 'manacore' ),
					'plural'       => __( 'رده‌های سنی', 'manacore' ),
					'object_types' => $titles,
					'hierarchical' => true,
					'slug'         => 'age-rating',
				),
				'mood'     => array(
					'singular'     => __( 'حال و هوا', 'manacore' ),
					'plural'       => __( 'حال و هواها', 'manacore' ),
					'object_types' => $titles,
					'hierarchical' => true,
					'slug'         => 'mood',
				),
				'title_tag' => array(
					'singular'     => __( 'برچسب', 'manacore' ),
					'plural'       => __( 'برچسب‌ها', 'manacore' ),
					'object_types' => $all,
					'hierarchical' => false,
					'slug'         => 'title-tag',
				),
			/*
			 * نقش چهره («بازیگر»/«کارگردان»): دکمه‌های صافی برگه‌ی
			 * «بازیگران و عوامل» از همین ترم‌ها ساخته می‌شوند، پس افزودن
			 * نقش تازه از پیشخوان کافی است — هیچ فهرست سختی در قالب نیست.
			 */
			'person_role' => array(
				'singular'     => __( 'نقش چهره', 'manacore' ),
				'plural'       => __( 'نقش‌های چهره', 'manacore' ),
				'object_types' => array( 'person' ),
				'hierarchical' => false,
				'slug'         => 'role',
			),
			)
		);
	}

	/**
	 * ثبت تاکسونومی‌ها در وردپرس.
	 */
	public function register() {
		foreach ( $this->definitions() as $key => $def ) {
			register_taxonomy(
				$key,
				$def['object_types'],
				array(
					'labels'            => array(
						'name'          => $def['plural'],
						'singular_name' => $def['singular'],
						'menu_name'     => $def['plural'],
						/* translators: %s: نام تاکسونومی */
						'search_items'  => sprintf( __( 'جستجوی %s', 'manacore' ), $def['plural'] ),
						'all_items'     => $def['plural'],
						/* translators: %s: نام تاکسونومی */
						'edit_item'     => sprintf( __( 'ویرایش %s', 'manacore' ), $def['singular'] ),
						/* translators: %s: نام تاکسونومی */
						'add_new_item'  => sprintf( __( 'افزودن %s', 'manacore' ), $def['singular'] ),
						/* translators: %s: نام تاکسونومی */
						'not_found'     => sprintf( __( '%s یافت نشد', 'manacore' ), $def['plural'] ),
					),
					'hierarchical'      => $def['hierarchical'],
					'public'            => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'query_var'         => true,
					'rewrite'           => array(
						'slug'       => $def['slug'],
						'with_front' => false,
					),
				)
			);
		}
	}
}
