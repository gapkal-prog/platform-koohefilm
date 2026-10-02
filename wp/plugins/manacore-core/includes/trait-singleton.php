<?php
/**
 * تِرِیت تک‌نمونه‌ای.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Singleton
 */
trait Singleton {

	/**
	 * نمونه‌ها.
	 *
	 * @var array<string,object>
	 */
	protected static $instances = array();

	/**
	 * دریافت نمونه.
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
	 * سازنده‌ی محافظت‌شده.
	 */
	protected function __construct() {}
}
