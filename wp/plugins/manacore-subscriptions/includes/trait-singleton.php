<?php
/**
 * Singleton trait.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Shared singleton behaviour.
 */
trait Singleton {

	/**
	 * Instances keyed by class name.
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * Get (or create) the instance for the called class.
	 *
	 * @return static
	 */
	public static function instance() {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
		}

		return self::$instances[ $class ];
	}
}
