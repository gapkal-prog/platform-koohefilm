<?php
/**
 * Singleton trait.
 *
 * @package ManaCore\Sources
 */

namespace ManaCore\Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable singleton implementation.
 */
trait Singleton {

	/**
	 * Instances keyed by class name.
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * Get the shared instance.
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

	/**
	 * Protected constructor.
	 */
	protected function __construct() {}
}
