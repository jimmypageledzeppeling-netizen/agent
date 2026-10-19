<?php
/**
 * Input validation rules shared by REST, join trap and admin
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Validator class
 *
 * @since 1.2.0
 */
class Validator {

	/**
	 * Check phone format: digits only, at least 10
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public static function is_phone( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^\d{10,}$/', $value );
	}

	/**
	 * Check key format: exactly 10 Latin letters, case-sensitive
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 *
	 * @since 1.2.0
	 */
	public static function is_key( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z]{10}$/', $value );
	}
}
