<?php
/**
 * Global helpers for the Subscriptions plugin.
 *
 * @package ManaCore\Subs
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a subscription option (stored inside the shared manacore_settings array
 * under the `sub_` prefix).
 *
 * @param string $key     Option key without prefix.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function manacore_subs_get_option( $key, $default = '' ) {
	return manacore_get_option( 'sub_' . $key, $default );
}

/**
 * All configured subscription levels, ordered from lowest to highest.
 *
 * @return array level slug => array( label, weight )
 */
function manacore_subs_levels() {
	return ManaCore\Subs\Plans::instance()->levels();
}

/**
 * Numeric weight of a level (higher = more access).
 *
 * @param string $level Level slug.
 * @return int
 */
function manacore_subs_level_weight( $level ) {
	return ManaCore\Subs\Plans::instance()->weight( $level );
}

/**
 * Current user's (or given user's) active subscription level slug.
 * Returns an empty string when the user has no active subscription.
 *
 * @param int $user_id Optional user id.
 * @return string
 */
function manacore_subs_user_level( $user_id = 0 ) {
	return ManaCore\Subs\Access::instance()->user_level( $user_id );
}

/**
 * Whether the user holds an active subscription of any level.
 *
 * @param int $user_id Optional user id.
 * @return bool
 */
function manacore_subs_is_subscriber( $user_id = 0 ) {
	return '' !== manacore_subs_user_level( $user_id );
}

/**
 * Whether the user meets (or exceeds) a required level.
 *
 * @param string $required Required level slug.
 * @param int    $user_id  Optional user id.
 * @return bool
 */
function manacore_subs_user_has_level( $required, $user_id = 0 ) {
	return ManaCore\Subs\Access::instance()->has_level( $required, $user_id );
}

/**
 * Expiry timestamp of the user's subscription (0 when unlimited/none).
 *
 * @param int $user_id Optional user id.
 * @return int
 */
function manacore_subs_expiry( $user_id = 0 ) {
	return ManaCore\Subs\Access::instance()->expiry( $user_id );
}

/**
 * Subscription purchase / pricing page URL.
 *
 * @return string
 */
function manacore_subs_url() {
	return ManaCore\Subs\Plans::instance()->subscribe_url();
}

/**
 * Is WooCommerce Subscriptions available?
 *
 * @return bool
 */
function manacore_subs_woo_active() {
	return class_exists( 'WC_Subscriptions' ) && function_exists( 'wcs_user_has_subscription' );
}

/**
 * Is WooCommerce itself available?
 *
 * @return bool
 */
function manacore_subs_wc_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * User-meta keys used by the manual grant system.
 *
 * @return array
 */
function manacore_subs_meta_keys() {
	return array(
		'level'   => 'manacore_sub_level',
		'expires' => 'manacore_sub_expires',
		'source'  => 'manacore_sub_source',
		'note'    => 'manacore_sub_note',
	);
}
