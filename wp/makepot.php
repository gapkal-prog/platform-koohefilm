<?php
/**
 * ساخت فایل‌های ترجمه (.pot) برای افزونه‌ها و قالب.
 *
 * بدون نیاز به wp-cli یا xgettext؛ فایل‌های PHP و JS را با توکنایزر و
 * الگوهای منظم می‌خواند و رشته‌های ترجمه‌پذیر را استخراج می‌کند.
 *
 * استفاده: php wp/makepot.php
 */

$wp_dir = __DIR__;

/**
 * بسته‌هایی که برای هرکدام یک فایل .pot ساخته می‌شود.
 */
$packages = array(
	array(
		'name'   => 'ManaCore',
		'domain' => 'manacore',
		'out'    => $wp_dir . '/plugins/manacore-core/languages/manacore.pot',
		// دامنه‌ی manacore بین سه افزونه مشترک است، پس همه‌ی آن‌ها اسکن می‌شوند.
		'roots'  => array(
			$wp_dir . '/plugins/manacore-core',
			$wp_dir . '/plugins/manacore-sources',
			$wp_dir . '/plugins/manacore-subscriptions',
		),
	),
	array(
		'name'   => 'Koohe Film',
		'domain' => 'koohe-film',
		'out'    => $wp_dir . '/themes/koohe-film/languages/koohe-film.pot',
		'roots'  => array( $wp_dir . '/themes/koohe-film' ),
	),
);

/**
 * توابع ترجمه و شماره‌ی آرگومان‌های معنادار آن‌ها.
 *
 * single  = ایندکس رشته‌ی اصلی
 * plural  = ایندکس شکل جمع (یا null)
 * context = ایندکس زمینه (یا null)
 * domain  = ایندکس دامنه‌ی متنی
 */
$functions = array(
	'__'             => array( 'single' => 0, 'plural' => null, 'context' => null, 'domain' => 1 ),
	'_e'             => array( 'single' => 0, 'plural' => null, 'context' => null, 'domain' => 1 ),
	'esc_html__'     => array( 'single' => 0, 'plural' => null, 'context' => null, 'domain' => 1 ),
	'esc_html_e'     => array( 'single' => 0, 'plural' => null, 'context' => null, 'domain' => 1 ),
	'esc_attr__'     => array( 'single' => 0, 'plural' => null, 'context' => null, 'domain' => 1 ),
	'esc_attr_e'     => array( 'single' => 0, 'plural' => null, 'context' => null, 'domain' => 1 ),
	'_x'             => array( 'single' => 0, 'plural' => null, 'context' => 1, 'domain' => 2 ),
	'esc_html_x'     => array( 'single' => 0, 'plural' => null, 'context' => 1, 'domain' => 2 ),
	'esc_attr_x'     => array( 'single' => 0, 'plural' => null, 'context' => 1, 'domain' => 2 ),
	'_n'             => array( 'single' => 0, 'plural' => 1, 'context' => null, 'domain' => 3 ),
	'_nx'            => array( 'single' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ),
	'_n_noop'        => array( 'single' => 0, 'plural' => 1, 'context' => null, 'domain' => 2 ),
);

/**
 * فهرست بازگشتی فایل‌ها با پسوندهای داده‌شده.
 *
 * @param string $dir  پوشه.
 * @param array  $exts پسوندها.
 * @return array
 */
function mc_scan_files( $dir, array $exts ) {
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( preg_match( '#/(node_modules|\.git|dist|vendor)/#', $path ) ) {
			continue;
		}
		if ( in_array( strtolower( $file->getExtension() ), $exts, true ) ) {
			$out[] = $path;
		}
	}
	sort( $out );
	return $out;
}

/**
 * استخراج رشته‌ها از یک فایل PHP با توکنایزر (دقیق‌تر از regex).
 *
 * @param string $path      مسیر فایل.
 * @param string $domain    دامنه‌ی هدف.
 * @param array  $functions تعریف توابع.
 * @param array  $strings   مرجع مجموعه‌ی نتایج.
 * @param string $rel_base  مسیر پایه برای نمایش نسبی.
 */
function mc_extract_php( $path, $domain, array $functions, array &$strings, $rel_base ) {
	$code   = file_get_contents( $path );
	$tokens = token_get_all( $code );
	$rel    = ltrim( str_replace( $rel_base, '', $path ), '/' );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];
		if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
			continue;
		}
		$fn = $token[1];
		if ( ! isset( $functions[ $fn ] ) ) {
			continue;
		}
		// نباید متد یا ویژگی باشد ( ->__ یا ::__ ).
		$prev = $i > 0 ? $tokens[ $i - 1 ] : null;
		if ( is_array( $prev ) && in_array( $prev[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}
		// آرگومان‌ها باید بلافاصله با پرانتز شروع شوند.
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// جمع‌آوری آرگومان‌ها در سطح اول پرانتز.
		$args     = array();
		$current  = null;
		$depth    = 0;
		$line     = $token[2];
		$literal  = true;

		for ( $k = $j; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( ! is_array( $t ) ) {
				if ( '(' === $t ) {
					$depth++;
					if ( 1 === $depth ) {
						continue;
					}
				} elseif ( ')' === $t ) {
					$depth--;
					if ( 0 === $depth ) {
						$args[] = $literal ? $current : null;
						break;
					}
				} elseif ( ',' === $t && 1 === $depth ) {
					$args[]  = $literal ? $current : null;
					$current = null;
					$literal = true;
					continue;
				}
			}
			if ( $depth < 1 ) {
				continue;
			}
			if ( is_array( $t ) ) {
				if ( T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
					// حذف کوتیشن و بازگردانی escape.
					$raw   = $t[1];
					$quote = $raw[0];
					$body  = substr( $raw, 1, -1 );
					if ( "'" === $quote ) {
						$body = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
					} else {
						$body = stripcslashes( $body );
					}
					// الحاق رشته‌ها ( 'a' . 'b' ) پشتیبانی می‌شود.
					$current = ( null === $current ) ? $body : $current . $body;
				} elseif ( T_WHITESPACE === $t[0] || T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) {
					continue;
				} else {
					// متغیر/تابع → آرگومان ثابت نیست.
					$literal = false;
				}
			} elseif ( '.' !== $t ) {
				$literal = false;
			}
		}

		$map = $functions[ $fn ];

		// دامنه باید مطابق باشد.
		$found_domain = isset( $args[ $map['domain'] ] ) ? $args[ $map['domain'] ] : null;
		if ( $found_domain !== $domain ) {
			continue;
		}
		$msgid = isset( $args[ $map['single'] ] ) ? $args[ $map['single'] ] : null;
		if ( null === $msgid || '' === $msgid ) {
			continue;
		}

		$msgid_plural = ( null !== $map['plural'] && isset( $args[ $map['plural'] ] ) ) ? $args[ $map['plural'] ] : null;
		$msgctxt      = ( null !== $map['context'] && isset( $args[ $map['context'] ] ) ) ? $args[ $map['context'] ] : null;

		$key = ( null === $msgctxt ? '' : $msgctxt . "\x04" ) . $msgid;

		if ( ! isset( $strings[ $key ] ) ) {
			$strings[ $key ] = array(
				'msgid'        => $msgid,
				'msgid_plural' => $msgid_plural,
				'msgctxt'      => $msgctxt,
				'refs'         => array(),
			);
		}
		if ( $msgid_plural && ! $strings[ $key ]['msgid_plural'] ) {
			$strings[ $key ]['msgid_plural'] = $msgid_plural;
		}
		$ref = $rel . ':' . $line;
		if ( ! in_array( $ref, $strings[ $key ]['refs'], true ) ) {
			$strings[ $key ]['refs'][] = $ref;
		}
	}
}

/**
 * استخراج رشته‌ها از یک فایل JavaScript.
 *
 * فقط الگوی wp.i18n سه‌گانه‌ی رایج پروژه: __( '…', 'domain' ).
 *
 * @param string $path     مسیر فایل.
 * @param string $domain   دامنه.
 * @param array  $strings  مرجع نتایج.
 * @param string $rel_base مسیر پایه.
 */
function mc_extract_js( $path, $domain, array &$strings, $rel_base ) {
	$code  = file_get_contents( $path );
	$rel   = ltrim( str_replace( $rel_base, '', $path ), '/' );
	$lines = explode( "\n", $code );

	// __( 'متن', 'دامنه' )  و  _x( 'متن', 'زمینه', 'دامنه' )
	$patterns = array(
		'/\b__\(\s*([\'"])(.*?)(?<!\\\\)\1\s*,\s*([\'"])' . preg_quote( $domain, '/' ) . '\3\s*\)/u' => array( 'msgid' => 2, 'ctx' => null ),
		'/\b_x\(\s*([\'"])(.*?)(?<!\\\\)\1\s*,\s*([\'"])(.*?)(?<!\\\\)\3\s*,\s*([\'"])' . preg_quote( $domain, '/' ) . '\5\s*\)/u' => array( 'msgid' => 2, 'ctx' => 4 ),
	);

	foreach ( $lines as $index => $line ) {
		foreach ( $patterns as $pattern => $map ) {
			if ( ! preg_match_all( $pattern, $line, $matches, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $matches as $m ) {
				$msgid = stripcslashes( $m[ $map['msgid'] ] );
				if ( '' === $msgid ) {
					continue;
				}
				$msgctxt = ( null !== $map['ctx'] && isset( $m[ $map['ctx'] ] ) ) ? stripcslashes( $m[ $map['ctx'] ] ) : null;
				$key     = ( null === $msgctxt ? '' : $msgctxt . "\x04" ) . $msgid;

				if ( ! isset( $strings[ $key ] ) ) {
					$strings[ $key ] = array(
						'msgid'        => $msgid,
						'msgid_plural' => null,
						'msgctxt'      => $msgctxt,
						'refs'         => array(),
					);
				}
				$ref = $rel . ':' . ( $index + 1 );
				if ( ! in_array( $ref, $strings[ $key ]['refs'], true ) ) {
					$strings[ $key ]['refs'][] = $ref;
				}
			}
		}
	}
}

/**
 * فرار دادن رشته برای قالب PO.
 *
 * @param string $value رشته.
 * @return string
 */
function mc_po_escape( $value ) {
	$value = str_replace( array( '\\', '"', "\t", "\r" ), array( '\\\\', '\\"', '\\t', '' ), $value );
	return str_replace( "\n", '\\n', $value );
}

/* ---------------- اجرا ---------------- */

echo "ساخت فایل‌های ترجمه (.pot)\n";
echo "----------------------------------------------------------\n";

$exit = 0;

foreach ( $packages as $package ) {
	$strings = array();

	foreach ( $package['roots'] as $root ) {
		$base = dirname( $root );
		foreach ( mc_scan_files( $root, array( 'php' ) ) as $file ) {
			mc_extract_php( $file, $package['domain'], $functions, $strings, $base );
		}
		foreach ( mc_scan_files( $root, array( 'js' ) ) as $file ) {
			mc_extract_js( $file, $package['domain'], $strings, $base );
		}
	}

	// مرتب‌سازی بر اساس نخستین ارجاع تا خروجی پایدار بماند.
	uasort(
		$strings,
		function ( $a, $b ) {
			$ref = strcmp( (string) reset( $a['refs'] ), (string) reset( $b['refs'] ) );
			return 0 !== $ref ? $ref : strcmp( $a['msgid'], $b['msgid'] );
		}
	);

	$now  = gmdate( 'Y-m-d H:iO' );
	$body = <<<POT
# Copyright (C) ManaCore
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: {$package['name']} 1.0.0\\n"
"Report-Msgid-Bugs-To: https://manacore.dev\\n"
"POT-Creation-Date: {$now}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: fa_IR\\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\\n"
"X-Generator: manacore-makepot\\n"
"X-Domain: {$package['domain']}\\n"

POT;

	foreach ( $strings as $entry ) {
		$body .= "\n";
		// ارجاع‌ها را در چند خط می‌شکنیم تا خطوط طولانی نشود.
		$chunks = array_chunk( $entry['refs'], 4 );
		foreach ( $chunks as $chunk ) {
			$body .= '#: ' . implode( ' ', $chunk ) . "\n";
		}
		if ( null !== $entry['msgctxt'] ) {
			$body .= 'msgctxt "' . mc_po_escape( $entry['msgctxt'] ) . "\"\n";
		}
		$body .= 'msgid "' . mc_po_escape( $entry['msgid'] ) . "\"\n";
		if ( null !== $entry['msgid_plural'] ) {
			$body .= 'msgid_plural "' . mc_po_escape( $entry['msgid_plural'] ) . "\"\n";
			$body .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n";
		} else {
			$body .= "msgstr \"\"\n";
		}
	}

	$dir = dirname( $package['out'] );
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) ) {
		printf( "  ! ساخت پوشه ناموفق: %s\n", $dir );
		$exit = 1;
		continue;
	}

	if ( false === file_put_contents( $package['out'], $body ) ) {
		printf( "  ! نوشتن ناموفق: %s\n", $package['out'] );
		$exit = 1;
		continue;
	}

	printf(
		"  \xE2\x9C\x93 %-34s %4d رشته  (%s)\n",
		basename( $package['out'] ),
		count( $strings ),
		$package['domain']
	);
}

echo "----------------------------------------------------------\n";
exit( $exit );
