<?php
/**
 * Access decisions – answers the core plugin's `manacore_user_can_access` filter.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Access.
 */
class Access {

	use Singleton;

	/**
	 * Per-request memo of resolved user levels.
	 *
	 * @var array
	 */
	protected $level_cache = array();

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_filter( 'manacore_user_can_access', array( $this, 'can_access' ), 10, 3 );

		// Invalidate the cached level whenever the stored data changes.
		add_action( 'updated_user_meta', array( $this, 'maybe_flush' ), 10, 3 );
		add_action( 'added_user_meta', array( $this, 'maybe_flush' ), 10, 3 );
		add_action( 'deleted_user_meta', array( $this, 'maybe_flush' ), 10, 3 );
		add_action( 'manacore_subs_level_changed', array( $this, 'flush' ) );
	}

	/* ---------------------------------------------------------------------
	 * Core filter
	 * ------------------------------------------------------------------ */

	/**
	 * Decide whether a user may see the protected parts of a post.
	 *
	 * @param bool $allowed Incoming decision (true by default from core).
	 * @param int  $post_id Post id.
	 * @param int  $user_id User id.
	 * @return bool
	 */
	public function can_access( $allowed, $post_id, $user_id = 0 ) {
		// Respect an earlier `false` from another integration.
		if ( ! $allowed ) {
			return false;
		}

		$post_id = absint( $post_id );
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		// Master switch.
		if ( ! manacore_subs_get_option( 'enabled', 1 ) ) {
			return true;
		}

		// Editors / admins always pass.
		if ( $user_id && user_can( $user_id, 'manacore_bypass_paywall' ) ) {
			return true;
		}

		$required = $this->required_level( $post_id );

		// Not premium content at all.
		if ( '' === $required ) {
			return true;
		}

		if ( ! $user_id ) {
			return false;
		}

		return $this->has_level( $required, $user_id );
	}

	/**
	 * Which level a post requires. Empty string = free content.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public function required_level( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return '';
		}

		$premium = get_post_meta( $post_id, 'manacore_is_premium', true );
		$level   = sanitize_key( (string) get_post_meta( $post_id, 'manacore_sub_level', true ) );

		// An explicit per-post level implies premium even without the checkbox.
		if ( $level && Plans::instance()->exists( $level ) ) {
			$required = $level;
		} elseif ( $premium ) {
			$required = (string) manacore_subs_get_option( 'default_level', Plans::instance()->lowest() );
		} else {
			$required = '';
		}

		/**
		 * Filter the level a post requires.
		 *
		 * @param string $required Level slug ('' = free).
		 * @param int    $post_id  Post id.
		 */
		return (string) apply_filters( 'manacore_subs_required_level', $required, $post_id );
	}

	/* ---------------------------------------------------------------------
	 * Level resolution
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve the active level of a user.
	 *
	 * Sources, highest wins:
	 *   1. Manual grant stored in user meta (if not expired).
	 *   2. Active WooCommerce Subscriptions.
	 *
	 * @param int $user_id Optional user id.
	 * @return string Level slug or ''.
	 */
	public function user_level( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		if ( isset( $this->level_cache[ $user_id ] ) ) {
			return $this->level_cache[ $user_id ];
		}

		$plans  = Plans::instance();
		$levels = array();

		$manual = $this->manual_level( $user_id );

		if ( $manual ) {
			$levels[] = $manual;
		}

		$woo = Woo::instance()->user_level( $user_id );

		if ( $woo ) {
			$levels[] = $woo;
		}

		/**
		 * Filter the raw list of levels a user holds before the highest is
		 * picked. Third-party membership plugins can append their own.
		 *
		 * @param array $levels  Level slugs.
		 * @param int   $user_id User id.
		 */
		$levels = (array) apply_filters( 'manacore_subs_user_levels', $levels, $user_id );

		$best   = '';
		$weight = 0;

		foreach ( $levels as $level ) {
			$level = sanitize_key( (string) $level );

			if ( ! $plans->exists( $level ) ) {
				continue;
			}

			$current = $plans->weight( $level );

			if ( $current > $weight ) {
				$weight = $current;
				$best   = $level;
			}
		}

		/**
		 * Filter the final resolved level of a user.
		 *
		 * @param string $best    Level slug ('' = none).
		 * @param int    $user_id User id.
		 */
		$best = (string) apply_filters( 'manacore_subs_user_level', $best, $user_id );

		$this->level_cache[ $user_id ] = $best;

		return $best;
	}

	/**
	 * Manually granted level from user meta, honouring the expiry date.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public function manual_level( $user_id ) {
		$keys  = manacore_subs_meta_keys();
		$level = sanitize_key( (string) get_user_meta( $user_id, $keys['level'], true ) );

		if ( ! $level || ! Plans::instance()->exists( $level ) ) {
			return '';
		}

		$expires = (int) get_user_meta( $user_id, $keys['expires'], true );

		// 0 / empty means an unlimited grant.
		if ( $expires && $expires < time() ) {
			return '';
		}

		return $level;
	}

	/**
	 * Whether a user meets (or beats) a required level.
	 *
	 * @param string $required Required level slug.
	 * @param int    $user_id  Optional user id.
	 * @return bool
	 */
	public function has_level( $required, $user_id = 0 ) {
		$plans    = Plans::instance();
		$required = sanitize_key( (string) $required );

		if ( '' === $required ) {
			return true;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, 'manacore_bypass_paywall' ) ) {
			return true;
		}

		$level = $this->user_level( $user_id );

		if ( '' === $level ) {
			return false;
		}

		// When strict mode is off, any active subscription unlocks everything.
		if ( ! manacore_subs_get_option( 'strict_levels', 1 ) ) {
			return true;
		}

		return $plans->weight( $level ) >= $plans->weight( $required );
	}

	/**
	 * Expiry timestamp for a user (0 = unlimited or none).
	 *
	 * @param int $user_id Optional user id.
	 * @return int
	 */
	public function expiry( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return 0;
		}

		$keys    = manacore_subs_meta_keys();
		$manual  = $this->manual_level( $user_id );
		$expires = $manual ? (int) get_user_meta( $user_id, $keys['expires'], true ) : 0;
		$woo     = Woo::instance()->next_payment( $user_id );

		if ( $woo && ( ! $expires || $woo > $expires ) ) {
			return $woo;
		}

		return $expires;
	}

	/**
	 * Where the level came from – 'manual', 'woocommerce' or ''.
	 *
	 * @param int $user_id Optional user id.
	 * @return string
	 */
	public function source( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$level = $this->user_level( $user_id );

		if ( '' === $level ) {
			return '';
		}

		if ( $this->manual_level( $user_id ) === $level ) {
			return 'manual';
		}

		if ( Woo::instance()->user_level( $user_id ) === $level ) {
			return 'woocommerce';
		}

		return 'filter';
	}

	/* ---------------------------------------------------------------------
	 * Cache
	 * ------------------------------------------------------------------ */

	/**
	 * Clear the memoised level for a user (or everyone).
	 *
	 * @param int $user_id Optional user id.
	 */
	public function flush( $user_id = 0 ) {
		if ( $user_id ) {
			unset( $this->level_cache[ absint( $user_id ) ] );
			return;
		}

		$this->level_cache = array();
	}

	/**
	 * Flush when one of our user-meta keys changes.
	 *
	 * @param int    $meta_id  Meta row id.
	 * @param int    $user_id  User id.
	 * @param string $meta_key Meta key.
	 */
	public function maybe_flush( $meta_id, $user_id, $meta_key ) {
		if ( in_array( $meta_key, manacore_subs_meta_keys(), true ) ) {
			$this->flush( $user_id );
		}
	}
}
