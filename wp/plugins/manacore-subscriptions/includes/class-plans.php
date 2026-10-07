<?php
/**
 * Subscription levels and their mapping to WooCommerce products.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Plans.
 */
class Plans {

	use Singleton;

	/**
	 * Cached level definitions.
	 *
	 * @var array|null
	 */
	protected $levels = null;

	/**
	 * Cached product → level map.
	 *
	 * @var array|null
	 */
	protected $map = null;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_filter( 'manacore_subscribe_url', array( $this, 'filter_subscribe_url' ) );
	}

	/* ---------------------------------------------------------------------
	 * Levels
	 * ------------------------------------------------------------------ */

	/**
	 * Default level definitions. Ordered lowest → highest.
	 *
	 * @return array
	 */
	protected function defaults() {
		return array(
			'basic' => array(
				'label'  => __( 'پایه', 'manacore' ),
				'weight' => 10,
			),
			'plus'  => array(
				'label'    => __( 'ویژه', 'manacore' ),
				'weight'   => 20,
				'featured' => true,
			),
			'vip'   => array(
				'label'  => __( 'VIP', 'manacore' ),
				'weight' => 30,
			),
		);
	}

	/**
	 * All levels, allowing site owners to add their own.
	 *
	 * @return array
	 */
	public function levels() {
		if ( null !== $this->levels ) {
			return $this->levels;
		}

		$levels = $this->defaults();

		/**
		 * Filter the available subscription levels.
		 *
		 * @param array $levels Level slug => array( label, weight ).
		 */
		$levels = (array) apply_filters( 'manacore_subs_levels', $levels );

		// Normalise and sort by weight ascending.
		$clean = array();

		foreach ( $levels as $slug => $level ) {
			$slug = sanitize_key( $slug );

			if ( '' === $slug ) {
				continue;
			}

			$clean[ $slug ] = array(
				'label'    => isset( $level['label'] ) ? (string) $level['label'] : $slug,
				/* عنوان کارت: اگر نباشد، همان برچسب سطح. */
				'title'    => ! empty( $level['title'] ) ? (string) $level['title'] : ( isset( $level['label'] ) ? (string) $level['label'] : $slug ),
				'weight'   => isset( $level['weight'] ) ? (int) $level['weight'] : 10,
				'featured' => ! empty( $level['featured'] ),
				/*
				 * داده‌های نمایشی کارت طرح.
				 *
				 * تا پیش از اینجا فقط `label/weight/featured` از فیلتر رد
				 * می‌شد و بقیه‌ی کلیدها دور ریخته می‌شد؛ یعنی حتی با فیلتر
				 * هم نمی‌شد کارتِ کاملِ مرجع (تگ‌لاین، قیمت، تخفیف، فهرست
				 * ویژگی‌ها، نشان) ساخت. اکنون همه‌ی این‌ها اختیاری‌اند و اگر
				 * نباشند کارت ساده می‌ماند.
				 */
				'tag'        => isset( $level['tag'] ) ? (string) $level['tag'] : '',
				'amount'     => isset( $level['amount'] ) ? (float) $level['amount'] : 0.0,
				'old_amount' => isset( $level['old_amount'] ) ? (float) $level['old_amount'] : 0.0,
				'months'     => isset( $level['months'] ) ? max( 1, (int) $level['months'] ) : 1,
				'currency'   => isset( $level['currency'] ) ? (string) $level['currency'] : __( 'تومان', 'manacore' ),
				'ribbon'     => isset( $level['ribbon'] ) ? (string) $level['ribbon'] : '',
				'icon'       => isset( $level['icon'] ) ? sanitize_key( $level['icon'] ) : '',
				'features'   => isset( $level['features'] ) ? array_values( array_filter( array_map( 'strval', (array) $level['features'] ), 'strlen' ) ) : array(),
			);
		}

		uasort(
			$clean,
			static function ( $a, $b ) {
				return $a['weight'] <=> $b['weight'];
			}
		);

		$this->levels = $clean;

		return $this->levels;
	}

	/**
	 * Human label for a level slug.
	 *
	 * @param string $level Level slug.
	 * @return string
	 */
	public function label( $level ) {
		$levels = $this->levels();
		$level  = sanitize_key( $level );

		return isset( $levels[ $level ] ) ? $levels[ $level ]['label'] : '';
	}

	/**
	 * Numeric weight for a level slug (0 when unknown/empty).
	 *
	 * @param string $level Level slug.
	 * @return int
	 */
	public function weight( $level ) {
		$levels = $this->levels();
		$level  = sanitize_key( (string) $level );

		return isset( $levels[ $level ] ) ? (int) $levels[ $level ]['weight'] : 0;
	}

	/**
	 * Does a level slug exist?
	 *
	 * @param string $level Level slug.
	 * @return bool
	 */
	public function exists( $level ) {
		return array_key_exists( sanitize_key( (string) $level ), $this->levels() );
	}

	/**
	 * The lowest configured level – used as the default requirement for
	 * premium content that does not specify one.
	 *
	 * @return string
	 */
	public function lowest() {
		$levels = $this->levels();
		$slugs  = array_keys( $levels );

		return $slugs ? (string) $slugs[0] : '';
	}

	/**
	 * The highest configured level.
	 *
	 * @return string
	 */
	public function highest() {
		$slugs = array_keys( $this->levels() );

		return $slugs ? (string) end( $slugs ) : '';
	}

	/* ---------------------------------------------------------------------
	 * Pricing
	 * ------------------------------------------------------------------ */

	/**
	 * Price figures for a plan card.
	 *
	 * Source of truth order — mirrors the reference card, which shows an
	 * original price, the current price, the resulting discount and the
	 * monthly equivalent:
	 *
	 *   1. a mapped WooCommerce product (regular/sale price), because a real
	 *      shop must never disagree with the checkout;
	 *   2. the `amount` / `old_amount` given in the level definition (the
	 *      filter `manacore_subs_levels`), for sites without a shop.
	 *
	 * @param string $slug  Level slug.
	 * @param array  $level Normalised level definition.
	 * @return array{amount:float,old_amount:float,months:int,currency:string,discount:int,monthly:float,source:string}
	 */
	public function price_data( $slug, $level ) {
		$amount     = isset( $level['amount'] ) ? (float) $level['amount'] : 0.0;
		$old_amount = isset( $level['old_amount'] ) ? (float) $level['old_amount'] : 0.0;
		$months     = isset( $level['months'] ) ? (int) $level['months'] : 1;
		$currency   = isset( $level['currency'] ) ? (string) $level['currency'] : '';
		$source     = $amount > 0 ? 'level' : '';

		$product_id = $this->product_for_level( $slug );

		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product && '' !== (string) $product->get_price() ) {
				$amount     = (float) $product->get_price();
				$old_amount = (float) $product->get_regular_price();
				$source     = 'woo';
			}
		}

		$discount = ( $amount > 0 && $old_amount > $amount )
			? (int) round( ( 1 - ( $amount / $old_amount ) ) * 100 )
			: 0;

		/**
		 * Filter the resolved price figures of a plan card.
		 *
		 * @param array  $price Price figures.
		 * @param string $slug  Level slug.
		 * @param array  $level Level definition.
		 */
		return (array) apply_filters(
			'manacore_subs_price_data',
			array(
				'amount'     => $amount,
				'old_amount' => $old_amount,
				'months'     => max( 1, $months ),
				'currency'   => $currency,
				'discount'   => $discount,
				'monthly'    => $amount > 0 ? $amount / max( 1, $months ) : 0.0,
				'source'     => $source,
			),
			$slug,
			$level
		);
	}

	/**
	 * First product id mapped to a level, or 0.
	 *
	 * @param string $slug Level slug.
	 * @return int
	 */
	public function product_for_level( $slug ) {
		$ids = array_keys( $this->product_map(), $slug, true );

		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Icon markup for a plan card.
	 *
	 * A fixed map of inline SVGs (the reference uses ▣ / ♛ / ◌ glyphs); a
	 * slug keeps the markup safe and themable, and an unknown slug falls
	 * back to the generic mark.
	 *
	 * @param string $slug Icon slug.
	 * @param int    $size Pixel size.
	 * @return string
	 */
	public function icon_markup( $slug, $size = 22 ) {
		$paths = array(
			'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
			'crown'  => '<path d="m3 8 4 4 5-8 5 8 4-4v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>',
			'circle' => '<circle cx="12" cy="12" r="7"/>',
			'star'   => '<path d="m12 3-1.5 5.5L5 10l5.5 1.5L12 17l1.5-5.5L19 10l-5.5-1.5L12 3z"/>',
			'spark'  => '<path d="M12 3v18M3 12h18"/>',
		);

		$slug = isset( $paths[ $slug ] ) ? $slug : 'circle';

		/** This map is fixed, so the markup below is trusted. */
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$paths[ $slug ]
		);
	}

	/**
	 * Formatted amount with thousands separators and Persian digits.
	 *
	 * @param float $value Value.
	 * @return string
	 */
	public function format_amount( $value ) {
		$formatted = number_format_i18n( (float) $value );

		/*
		 * جداکننده‌ی هزارگان فارسی.
		 *
		 * `number_format_i18n` در زبان فا_IR ویرگول لاتین می‌گذارد، اما مرجع
		 * از `Intl.NumberFormat('fa-IR')` استفاده می‌کند که U+066C (٬) را
		 * می‌نویسد: «۱۱۹٬۰۰۰» در برابر «۱۱۹,۰۰۰» (سنجیده‌شده). ممیز هم به
		 * ممیز فارسی (٫) تبدیل می‌شود تا قیمت‌های اعشاری درست بنشینند.
		 */
		$formatted = strtr( $formatted, array( ',' => '٬', '.' => '٫' ) );

		return function_exists( 'manacore_fa_digits' ) ? manacore_fa_digits( $formatted ) : $formatted;
	}

	/* ---------------------------------------------------------------------
	 * Product mapping
	 * ------------------------------------------------------------------ */

	/**
	 * Product/variation id → level slug map.
	 *
	 * Stored as a textarea in settings, one rule per line:
	 *   123 = vip
	 *   456=plus
	 *
	 * @return array
	 */
	public function product_map() {
		if ( null !== $this->map ) {
			return $this->map;
		}

		$raw   = (string) manacore_subs_get_option( 'product_map', '' );
		$map   = array();
		$lines = preg_split( '/\r\n|\r|\n/', $raw );

		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$parts = explode( '=', $line, 2 );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$product_id = absint( trim( $parts[0] ) );
			$level      = sanitize_key( trim( $parts[1] ) );

			if ( ! $product_id || ! $this->exists( $level ) ) {
				continue;
			}

			$map[ $product_id ] = $level;
		}

		/**
		 * Filter the product → level map.
		 *
		 * @param array $map Product id => level slug.
		 */
		$this->map = (array) apply_filters( 'manacore_subs_product_map', $map );

		return $this->map;
	}

	/**
	 * Resolve the level granted by a product id.
	 *
	 * Falls back to the default level when the product is not mapped but the
	 * site owner enabled "any subscription grants access".
	 *
	 * @param int $product_id Product or variation id.
	 * @return string
	 */
	public function level_for_product( $product_id ) {
		$map        = $this->product_map();
		$product_id = absint( $product_id );

		if ( isset( $map[ $product_id ] ) ) {
			return $map[ $product_id ];
		}

		// Variations inherit their parent's mapping.
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product && $product->get_parent_id() ) {
				$parent = absint( $product->get_parent_id() );

				if ( isset( $map[ $parent ] ) ) {
					return $map[ $parent ];
				}
			}
		}

		if ( manacore_subs_get_option( 'any_product', 1 ) ) {
			return (string) manacore_subs_get_option( 'default_level', $this->lowest() );
		}

		return '';
	}

	/**
	 * Product ids that grant a given level (or better).
	 *
	 * @param string $level Level slug.
	 * @return array
	 */
	public function products_for_level( $level ) {
		$target = $this->weight( $level );
		$ids    = array();

		foreach ( $this->product_map() as $product_id => $slug ) {
			if ( $this->weight( $slug ) >= $target ) {
				$ids[] = (int) $product_id;
			}
		}

		return $ids;
	}

	/* ---------------------------------------------------------------------
	 * Subscribe URL
	 * ------------------------------------------------------------------ */

	/**
	 * The page users are sent to in order to subscribe.
	 *
	 * Priority: explicit setting → WooCommerce product/shop page → /subscribe/.
	 *
	 * @return string
	 */
	public function subscribe_url() {
		$page_id = absint( manacore_subs_get_option( 'page_id', 0 ) );

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return (string) get_permalink( $page_id );
		}

		$custom = trim( (string) manacore_subs_get_option( 'page_url', '' ) );

		if ( $custom ) {
			return esc_url_raw( $custom );
		}

		$map = $this->product_map();

		if ( $map && function_exists( 'get_permalink' ) ) {
			$first = (int) array_key_first( $map );
			$link  = get_permalink( $first );

			if ( $link ) {
				return (string) $link;
			}
		}

		if ( manacore_subs_wc_active() && function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );

			if ( $shop ) {
				return (string) $shop;
			}
		}

		return home_url( '/subscribe/' );
	}

	/**
	 * Provide the core plugin's `manacore_subscribe_url` filter value.
	 *
	 * @param string $url Default URL from core.
	 * @return string
	 */
	public function filter_subscribe_url( $url ) {
		$resolved = $this->subscribe_url();

		return $resolved ? $resolved : $url;
	}
}
