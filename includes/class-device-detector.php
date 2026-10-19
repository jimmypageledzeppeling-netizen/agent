<?php
/**
 * Detects device category from the User-Agent header
 *
 * Only fixed labels are returned, the raw User-Agent is never stored.
 *
 * @package Agent007
 */

namespace Agent007;

/**
 * Device detector class
 *
 * @since 1.2.0
 */
class DeviceDetector {

	/**
	 * Max length of the stored label (matches access_log.device)
	 */
	const MAX_LENGTH = 255;

	/**
	 * Build a short device label, e.g. "Mobile · Android · Chrome"
	 *
	 * @param string $user_agent Raw User-Agent header.
	 *
	 * @return string
	 *
	 * @since 1.2.0
	 */
	public function detect( $user_agent ) {
		$ua = is_string( $user_agent ) ? trim( $user_agent ) : '';

		if ( '' === $ua ) {
			return 'Unknown';
		}

		if ( preg_match( '/bot|crawl|spider|curl|wget|python|httpie|postman|go-http-client/i', $ua ) ) {
			return 'Bot/API';
		}

		$type = 'Desktop';
		if ( preg_match( '/iPad|Tablet/i', $ua ) ) {
			$type = 'Tablet';
		} elseif ( preg_match( '/Mobi|iPhone|iPod|Windows Phone/i', $ua ) ) {
			$type = 'Mobile';
		}

		$os = 'OS?';
		if ( preg_match( '/Windows NT/i', $ua ) ) {
			$os = 'Windows';
		} elseif ( preg_match( '/Android/i', $ua ) ) {
			$os = 'Android';
		} elseif ( preg_match( '/iPhone|iPad|iPod/i', $ua ) ) {
			$os = 'iOS';
		} elseif ( preg_match( '/Mac OS X|Macintosh/i', $ua ) ) {
			$os = 'macOS';
		} elseif ( preg_match( '/Linux/i', $ua ) ) {
			$os = 'Linux';
		}

		// Order matters: Edge and Opera also contain "Chrome", Chrome contains "Safari".
		$browser = 'Browser?';
		if ( preg_match( '/Edg/i', $ua ) ) {
			$browser = 'Edge';
		} elseif ( preg_match( '/OPR|Opera/i', $ua ) ) {
			$browser = 'Opera';
		} elseif ( preg_match( '/YaBrowser/i', $ua ) ) {
			$browser = 'Yandex';
		} elseif ( preg_match( '/Chrome|CriOS/i', $ua ) ) {
			$browser = 'Chrome';
		} elseif ( preg_match( '/Firefox|FxiOS/i', $ua ) ) {
			$browser = 'Firefox';
		} elseif ( preg_match( '/Safari/i', $ua ) ) {
			$browser = 'Safari';
		}

		return substr( implode( ' · ', array( $type, $os, $browser ) ), 0, self::MAX_LENGTH );
	}
}
