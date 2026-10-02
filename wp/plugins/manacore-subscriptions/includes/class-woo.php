<?php
/**
 * WooCommerce Subscriptions integration.
 *
 * Degrades gracefully: when WooCommerce Subscriptions is absent, every method
 * returns an empty/neutral value and the plugin falls back to manual grants.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Woo.
 */
class Woo {

	use Singleton;

	/**
	 * Per-request memo keyed by user id.
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Statuses that count as "active".
	 *
	 * @return array
	 */
	protected function active_statuses() {
		$statuses = array( 'active' );

		if ( manacore_subs_get_option( 'allow_pending_cancel', 1 ) ) {
			$statuses[] = 'pending-cancel';
		}

		/**
		 * Filter which subscription statuses grant access.
		 *
		 * @param array $statuses Status slugs.
		 */
		return (array) apply_filters( 'manacore_subs_active_statuses', $statuses );
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		// Refresh cached decisions whenever a subscription changes state.
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'woocommerce_subscription_status_active', array( $this, 'on_subscription' ) );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'on_subscription' ) );
		add_action( 'woocommerce_subscription_status_expired', array( $this, 'on_subscription' ) );
		add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'on_subscription' ) );

		// Simple (non-subscription) product support: a completed order can grant
		// a fixed-length pass.
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ) );

		// Show the level on the WooCommerce "My account" dashboard.
		add_action( 'woocommerce_account_dashboard', array( $this, 'account_notice' ) );
	}

	/* ---------------------------------------------------------------------
	 * Level resolution
	 * ------------------------------------------------------------------ */

	/**
	 * Highest level granted by the user's active subscriptions.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public function user_level( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! manacore_subs_woo_active() ) {
			return '';
		}

		if ( isset( $this->cache[ $user_id ]['level'] ) ) {
			return $this->cache[ $user_id ]['level'];
		}

		$plans    = Plans::instance();
		$statuses = $this->active_statuses();
		$best     = '';
		$weight   = 0;

		foreach ( $this->subscriptions( $user_id ) as $subscription ) {
			if ( ! method_exists( $subscription, 'has_status' ) || ! $subscription->has_status( $statuses ) ) {
				continue;
			}

			foreach ( $this->subscription_products( $subscription ) as $product_id ) {
				$level = $plans->level_for_product( $product_id );

				if ( ! $level ) {
					continue;
				}

				$current = $plans->weight( $level );

				if ( $current > $weight ) {
					$weight = $current;
					$best   = $level;
				}
			}
		}

		$this->cache[ $user_id ]['level'] = $best;

		return $best;
	}

	/**
	 * The furthest next-payment/end timestamp across active subscriptions.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function next_payment( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! manacore_subs_woo_active() ) {
			return 0;
		}

		if ( isset( $this->cache[ $user_id ]['next'] ) ) {
			return $this->cache[ $user_id ]['next'];
		}

		$statuses = $this->active_statuses();
		$furthest = 0;

		foreach ( $this->subscriptions( $user_id ) as $subscription ) {
			if ( ! method_exists( $subscription, 'has_status' ) || ! $subscription->has_status( $statuses ) ) {
				continue;
			}

			foreach ( array( 'next_payment', 'end' ) as $type ) {
				if ( ! method_exists( $subscription, 'get_time' ) ) {
					continue;
				}

				$time = (int) $subscription->get_time( $type );

				if ( $time > $furthest ) {
					$furthest = $time;
				}
			}
		}

		$this->cache[ $user_id ]['next'] = $furthest;

		return $furthest;
	}

	/**
	 * All subscription objects for a user.
	 *
	 * @param int $user_id User id.
	 * @return array
	 */
	public function subscriptions( $user_id ) {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return array();
		}

		$subscriptions = wcs_get_users_subscriptions( absint( $user_id ) );

		return is_array( $subscriptions ) ? $subscriptions : array();
	}

	/**
	 * Product (and variation) ids inside a subscription.
	 *
	 * @param object $subscription Subscription object.
	 * @return array
	 */
	protected function subscription_products( $subscription ) {
		$ids = array();

		if ( ! method_exists( $subscription, 'get_items' ) ) {
			return $ids;
		}

		foreach ( $subscription->get_items() as $item ) {
			if ( method_exists( $item, 'get_variation_id' ) ) {
				$variation = absint( $item->get_variation_id() );

				if ( $variation ) {
					$ids[] = $variation;
				}
			}

			if ( method_exists( $item, 'get_product_id' ) ) {
				$product = absint( $item->get_product_id() );

				if ( $product ) {
					$ids[] = $product;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether the user has any active subscription at all.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public function has_active( $user_id ) {
		return '' !== $this->user_level( $user_id );
	}

	/* ---------------------------------------------------------------------
	 * One-off orders (non-subscription passes)
	 * ------------------------------------------------------------------ */

	/**
	 * Grant a timed pass when a mapped simple product is purchased.
	 *
	 * Only runs when the site owner enabled it, so subscription-only stores are
	 * unaffected.
	 *
	 * @param int $order_id Order id.
	 */
	public function on_order_completed( $order_id ) {
		if ( ! manacore_subs_get_option( 'grant_on_order', 0 ) || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! method_exists( $order, 'get_user_id' ) ) {
			return;
		}

		$user_id = absint( $order->get_user_id() );

		if ( ! $user_id ) {
			return;
		}

		// Skip renewals of real subscriptions – those are handled above.
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {
			return;
		}

		$plans  = Plans::instance();
		$best   = '';
		$weight = 0;

		foreach ( $order->get_items() as $item ) {
			$candidates = array();

			if ( method_exists( $item, 'get_variation_id' ) && $item->get_variation_id() ) {
				$candidates[] = absint( $item->get_variation_id() );
			}

			if ( method_exists( $item, 'get_product_id' ) ) {
				$candidates[] = absint( $item->get_product_id() );
			}

			foreach ( $candidates as $product_id ) {
				$map = $plans->product_map();

				// Only explicitly mapped products create a pass.
				if ( ! isset( $map[ $product_id ] ) ) {
					continue;
				}

				$current = $plans->weight( $map[ $product_id ] );

				if ( $current > $weight ) {
					$weight = $current;
					$best   = $map[ $product_id ];
				}
			}
		}

		if ( ! $best ) {
			return;
		}

		$days = absint( manacore_subs_get_option( 'order_days', 30 ) );
		$days = $days ? $days : 30;

		Meta::instance()->grant(
			$user_id,
			$best,
			time() + ( $days * DAY_IN_SECONDS ),
			'woocommerce',
			sprintf(
				/* translators: %d: order id. */
				__( 'اعطای خودکار از سفارش #%d', 'manacore' ),
				absint( $order_id )
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Cache invalidation
	 * ------------------------------------------------------------------ */

	/**
	 * Flush when a subscription status changes.
	 *
	 * @param object $subscription Subscription.
	 * @param string $new_status   New status.
	 * @param string $old_status   Old status.
	 */
	public function on_status_change( $subscription, $new_status = '', $old_status = '' ) {
		unset( $new_status, $old_status );
		$this->on_subscription( $subscription );
	}

	/**
	 * Flush caches for the owner of a subscription.
	 *
	 * @param object $subscription Subscription.
	 */
	public function on_subscription( $subscription ) {
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_user_id' ) ) {
			return;
		}

		$user_id = absint( $subscription->get_user_id() );

		if ( ! $user_id ) {
			return;
		}

		unset( $this->cache[ $user_id ] );
		Access::instance()->flush( $user_id );

		/**
		 * Fires when a user's subscription level may have changed.
		 *
		 * @param int $user_id User id.
		 */
		do_action( 'manacore_subs_level_changed', $user_id );
	}

	/* ---------------------------------------------------------------------
	 * My account notice
	 * ------------------------------------------------------------------ */

	/**
	 * Render a short status line on the WooCommerce account dashboard.
	 */
	public function account_notice() {
		if ( ! manacore_subs_get_option( 'account_notice', 1 ) ) {
			return;
		}

		echo Account::instance()->status_card(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
