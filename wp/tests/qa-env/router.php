<?php
/**
 * روتر سبک برای سرور توکار PHP (`php -S`).
 *
 * فایل واقعی → همان فایل سرو می‌شود؛ در غیر این صورت درخواست به
 * front controller وردپرس می‌رود تا پیوندهای یکتا کار کنند.
 *
 * اجرا:  php -S 0.0.0.0:8099 -t <wp-root> <wp-root>/router.php
 */

$root = getenv( 'WP_ROOT' ) ? getenv( 'WP_ROOT' ) : '/home/user/.cache/wp';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $root . $path;

if ( '/' !== substr( $path, -1 ) && is_file( $file ) ) {
	return false; // فایل واقعی (تصویر، CSS، …)
}

if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	require rtrim( $file, '/' ) . '/index.php';
	return true;
}

require $root . '/index.php';
