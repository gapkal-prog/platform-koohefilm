<?php
/**
 * ثبت نوع‌های محتوا.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Post_Types
 */
class Post_Types {

	use Singleton;

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'episode_permalink' ), 10, 2 );
	}

	/**
	 * ثبت همه‌ی نوع‌های محتوا.
	 */
	public function register() {
		$this->register_movie();
		$this->register_series();
		$this->register_anime();
		$this->register_episode();
		$this->register_person();
		$this->register_collection();
	}

	/**
	 * ساخت آرایه‌ی برچسب‌ها.
	 *
	 * @param string $singular مفرد.
	 * @param string $plural   جمع.
	 * @return array
	 */
	private function labels( $singular, $plural ) {
		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			'add_new'               => __( 'افزودن', 'manacore' ),
			/* translators: %s: نام مفرد نوع محتوا */
			'add_new_item'          => sprintf( __( 'افزودن %s جدید', 'manacore' ), $singular ),
			/* translators: %s: نام مفرد نوع محتوا */
			'edit_item'             => sprintf( __( 'ویرایش %s', 'manacore' ), $singular ),
			/* translators: %s: نام مفرد نوع محتوا */
			'new_item'              => sprintf( __( '%s جدید', 'manacore' ), $singular ),
			/* translators: %s: نام مفرد نوع محتوا */
			'view_item'             => sprintf( __( 'نمایش %s', 'manacore' ), $singular ),
			/* translators: %s: نام جمع نوع محتوا */
			'search_items'          => sprintf( __( 'جستجوی %s', 'manacore' ), $plural ),
			/* translators: %s: نام جمع نوع محتوا */
			'not_found'             => sprintf( __( 'هیچ %s یافت نشد', 'manacore' ), $plural ),
			'not_found_in_trash'    => __( 'موردی در زباله‌دان نیست', 'manacore' ),
			'all_items'             => $plural,
			'featured_image'        => __( 'پوستر', 'manacore' ),
			'set_featured_image'    => __( 'انتخاب پوستر', 'manacore' ),
			'remove_featured_image' => __( 'حذف پوستر', 'manacore' ),
			'use_featured_image'    => __( 'استفاده به عنوان پوستر', 'manacore' ),
			'items_list'            => $plural,
			'item_published'        => __( 'منتشر شد.', 'manacore' ),
			'item_updated'          => __( 'به‌روزرسانی شد.', 'manacore' ),
		);
	}

	/**
	 * آرگومان‌های مشترک.
	 *
	 * @param array $args آرگومان‌های اختصاصی.
	 * @return array
	 */
	private function base_args( array $args ) {
		return wp_parse_args(
			$args,
			array(
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => '',
				'query_var'          => true,
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => false,
				'menu_position'      => 5,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'author' ),
				'template_lock'      => false,
			)
		);
	}

	/**
	 * نوع محتوای فیلم.
	 */
	private function register_movie() {
		register_post_type(
			'movie',
			$this->base_args(
				array(
					'labels'       => $this->labels( __( 'فیلم', 'manacore' ), __( 'فیلم‌ها', 'manacore' ) ),
					'menu_icon'    => 'dashicons-format-video',
					'rewrite'      => array(
						'slug'       => manacore_get_option( 'slug_movie', 'movie' ),
						'with_front' => false,
					),
					'rest_base'    => 'movies',
					'menu_position'=> 20,
				)
			)
		);
	}

	/**
	 * نوع محتوای سریال.
	 */
	private function register_series() {
		register_post_type(
			'series',
			$this->base_args(
				array(
					'labels'       => $this->labels( __( 'سریال', 'manacore' ), __( 'سریال‌ها', 'manacore' ) ),
					'menu_icon'    => 'dashicons-editor-ol',
					'rewrite'      => array(
						'slug'       => manacore_get_option( 'slug_series', 'series' ),
						'with_front' => false,
					),
					'rest_base'    => 'series',
					'menu_position'=> 21,
				)
			)
		);
	}

	/**
	 * نوع محتوای انیمه.
	 */
	private function register_anime() {
		register_post_type(
			'anime',
			$this->base_args(
				array(
					'labels'       => $this->labels( __( 'انیمه', 'manacore' ), __( 'انیمه‌ها', 'manacore' ) ),
					'menu_icon'    => 'dashicons-buddicons-activity',
					'rewrite'      => array(
						'slug'       => manacore_get_option( 'slug_anime', 'anime' ),
						'with_front' => false,
					),
					'rest_base'    => 'anime',
					'menu_position'=> 22,
				)
			)
		);
	}

	/**
	 * نوع محتوای قسمت.
	 */
	private function register_episode() {
		register_post_type(
			'episode',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'قسمت', 'manacore' ), __( 'قسمت‌ها', 'manacore' ) ),
					'menu_icon'     => 'dashicons-playlist-video',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_episode', 'episode' ),
						'with_front' => false,
					),
					'rest_base'     => 'episodes',
					'has_archive'   => false,
					'menu_position' => 23,
					'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields' ),
				)
			)
		);
	}

	/**
	 * نوع محتوای عوامل (بازیگر/کارگردان).
	 */
	private function register_person() {
		register_post_type(
			'person',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'عامل', 'manacore' ), __( 'عوامل', 'manacore' ) ),
					'menu_icon'     => 'dashicons-groups',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_person', 'person' ),
						'with_front' => false,
					),
					'rest_base'     => 'people',
					'menu_position' => 24,
					'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				)
			)
		);
	}

	/**
	 * نوع محتوای مجموعه (کالکشن).
	 */
	private function register_collection() {
		register_post_type(
			'collection',
			$this->base_args(
				array(
					'labels'        => $this->labels( __( 'مجموعه', 'manacore' ), __( 'مجموعه‌ها', 'manacore' ) ),
					'menu_icon'     => 'dashicons-images-alt2',
					'rewrite'       => array(
						'slug'       => manacore_get_option( 'slug_collection', 'collection' ),
						'with_front' => false,
					),
					'rest_base'     => 'collections',
					'menu_position' => 25,
					'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				)
			)
		);
	}

	/**
	 * متن راهنمای فیلد عنوان.
	 *
	 * @param string   $text متن.
	 * @param \WP_Post $post پست.
	 * @return string
	 */
	public function title_placeholder( $text, $post ) {
		switch ( $post->post_type ) {
			case 'movie':
				return __( 'نام فیلم را وارد کنید', 'manacore' );
			case 'series':
				return __( 'نام سریال را وارد کنید', 'manacore' );
			case 'anime':
				return __( 'نام انیمه را وارد کنید', 'manacore' );
			case 'episode':
				return __( 'نام قسمت را وارد کنید', 'manacore' );
			case 'person':
				return __( 'نام عامل را وارد کنید', 'manacore' );
		}
		return $text;
	}

	/**
	 * پیوند یکتای قسمت را زیر اثر والد قرار می‌دهد.
	 *
	 * @param string   $link پیوند.
	 * @param \WP_Post $post پست.
	 * @return string
	 */
	public function episode_permalink( $link, $post ) {
		if ( 'episode' !== $post->post_type ) {
			return $link;
		}
		$parent = (int) get_post_meta( $post->ID, 'manacore_parent_title', true );
		if ( ! $parent ) {
			return $link;
		}
		$season  = (int) get_post_meta( $post->ID, 'manacore_season_number', true );
		$episode = (int) get_post_meta( $post->ID, 'manacore_episode_number', true );
		if ( ! $season || ! $episode ) {
			return $link;
		}
		return trailingslashit( get_permalink( $parent ) ) . sprintf( 'season-%d/episode-%d/', $season, $episode );
	}
}
