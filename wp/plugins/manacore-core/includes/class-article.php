<?php
/**
 * برگه‌ی تک‌نوشته — هم‌ارز `cinora/article.html`.
 *
 * مرجع یک «مقاله»ی کامل دارد: سرصفحه‌ی مقاله (نشان دسته، تیتر، توضیح،
 * سطر نویسنده)، پوشش ۱۶:۹، پاراگراف لید، نقل‌قول، **فصل‌های شماره‌دار**،
 * برچسب‌ها، ستون کنارِ «در این داستان می‌خوانی» با نوار پیشرفت مطالعه،
 * و بخش «داستان هنوز ادامه دارد...».
 *
 * در وردپرس، متن نوشته مالِ ویرایشگر است و نباید به HTML ایستا تبدیل
 * شود؛ پس این کلاس دو کار کوچک و ویرایش‌پذیر انجام می‌دهد:
 *
 *   ۱) **افزودن ساختار به متنِ ویرایشگر** — نخستین پاراگراف متن کلاس
 *      `.article-lead` می‌گیرد و هر تیتر `h2` به یک «فصل» تبدیل می‌شود:
 *      کلاس `.article-chapter`، شناسه‌ی `chapter-N` و نشان شماره‌ی فارسی
 *      (`۰۱`، `۰۲`، …). کافی است نویسنده در ویرایشگر `h2` بزند.
 *   ۲) **فهرست فصل‌ها** (`Article::chapters()`) — همان منبعی که بلوک
 *      «فهرست مقاله» از آن، فهرست ستون کنار و نوار پیشرفت را می‌سازد.
 *      (کمکِ `Blocks::render_minutes` هم از `Article::read_minutes()`.)
 *
 * هیچ رشته‌ای hard-code نیست: همه‌ی متن‌ها از فراداده و متن واقعی
 * نوشته می‌آید و همه چیز از ویرایشگر وردپرس قابل تغییر است.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Article
 */
class Article {

	use Singleton;

	/**
	 * نشانه‌ی «داخل متن مقاله هستیم» در طول رندر بلوک‌ها.
	 *
	 * @var bool
	 */
	protected static $in_body = false;

	/**
	 * شماره‌ی فصل در حال رندر (ترتیب سند).
	 *
	 * @var int
	 */
	protected static $chapter_cursor = 0;

	/**
	 * آیا پاراگراف لید رندر شده است؟
	 *
	 * @var bool
	 */
	protected static $lead_done = false;

	/**
	 * حافظه‌ی بدنه‌ی ساخته‌شده در همین درخواست، به‌ازای شناسه‌ی نوشته.
	 *
	 * بدنه یک بار برای هر نوشته ساخته می‌شود؛ اگر همان متن در همان درخواست
	 * دوباره رندر شود (مثلاً در فهرست یا الگوی تکراری) کار دوباره انجام
	 * نمی‌شود و صافی‌های فرزند دو بار اجرا نمی‌شوند.
	 *
	 * @var array<int,string>
	 */
	protected static $body_cache = array();

	/**
	 * ثبت قلاب‌ها.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'pre_render_block', array( $this, 'open_body' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'close_body' ), 5, 2 );
		add_filter( 'render_block_core/paragraph', array( $this, 'paragraph' ), 10, 2 );
		add_filter( 'render_block_core/heading', array( $this, 'heading' ), 10, 2 );
		add_filter( 'render_block_core/post-content', array( $this, 'body' ), 10, 3 );
	}

	/**
	 * آیا برگه‌ی کنونی تک‌نوشته‌ی وبلاگ است؟
	 *
	 * @return bool
	 */
	public static function is_article_view() {
		return is_singular( 'post' );
	}

	/**
	 * شمار دقیقه‌ی مطالعه بر پایه‌ی ۲۰۰ واژه در دقیقه (قرارداد مرجع).
	 *
	 * @param int $post_id شناسه‌ی نوشته.
	 * @return int
	 */
	public static function read_minutes( $post_id ) {
		$content = wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );
		$words   = preg_split( '/\s+/u', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );

		return max( 1, (int) ceil( count( (array) $words ) / 200 ) );
	}

	/**
	 * فهرست فصل‌ها: هر تیتر `h2` متن نوشته، به ترتیب سند.
	 *
	 * «فصل» فقط یک تیتر `h2` در متن ویرایشگر است — نه یک بلوک تازه و نه
	 * داده‌ی موازی. اگر نویسنده تیتری اضافه/حذف کند، فهرست و شماره‌ها
	 * خودشان هم‌راستا می‌شوند.
	 *
	 * @param int $post_id شناسه‌ی نوشته.
	 * @return array<int,array{id:string,text:string,index:int}>
	 */
	public static function chapters( $post_id ) {
		$blocks = parse_blocks( (string) get_post_field( 'post_content', $post_id ) );
		$out    = array();

		/*
		 * «فصل» فقط تیتر `h2` در سطح بالای متن است — دقیقاً همان شرطی
		 * که `render_body()` برای ساختن `<section class="article-chapter">`
		 * به کار می‌برد. اگر این دو یکی نباشند، فهرست به لنگرهایی
		 * اشاره می‌کند که در متن وجود ندارند.
		 */
		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) ) {
				continue;
			}

			if ( 'core/heading' !== $block['blockName'] ) {
				continue;
			}

			$level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;

			if ( 2 !== $level ) {
				continue;
			}

			/*
			 * متن از `innerHTML` خودِ بلوک خوانده می‌شود، نه با
			 * `render_block()`. دلیلش یک رخداد واقعی است: صدازدن
			 * `render_block()` همین‌جا، صافی `render_block_core/heading`
			 * را دوباره اجرا می‌کرد و حلقه‌ی بی‌پایان می‌ساخت (درخواست
			 * تا بی‌نهایت باز می‌ماند و سرور dev قفل می‌شد).
			 */
			$text = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );

			if ( '' === $text ) {
				continue;
			}

			$anchor = isset( $block['attrs']['anchor'] ) ? sanitize_title( (string) $block['attrs']['anchor'] ) : '';
			$index  = count( $out );

			$out[] = array(
				'id'    => '' !== $anchor ? $anchor : 'chapter-' . $index,
				'text'  => $text,
				'index' => $index,
			);
		}

		return $out;
	}

	/**
	 * ورود به متن نوشته (بلوک `core/post-content`).
	 *
	 * `pre_render_block` پیش از رندر فرزندان اجرا می‌شود و
	 * `render_block` پس از آن‌ها؛ پس این دو، مرزهای مطمئن «داخل متن»
	 * هستند و بلوک‌های سربرگ/پابرگ هیچ‌وقت لمس نمی‌شوند.
	 *
	 * @param string|null $pre   خروجی میان‌بر (اگر چیزی برگردد، رندر نمی‌شود).
	 * @param array       $block بلوک.
	 * @return string|null
	 */
	public function open_body( $pre, $block ) {
		if ( self::is_article_view() && isset( $block['blockName'] ) && 'core/post-content' === $block['blockName'] ) {
			self::$in_body        = true;
			self::$chapter_cursor = 0;
			self::$lead_done      = false;
		}

		return $pre;
	}

	/**
	 * خروج از متن نوشته.
	 *
	 * @param string $html  خروجی بلوک.
	 * @param array  $block بلوک.
	 * @return string
	 */
	public function close_body( $html, $block ) {
		if ( isset( $block['blockName'] ) && 'core/post-content' === $block['blockName'] ) {
			self::$in_body = false;
		}

		return $html;
	}

	/**
	 * نخستین پاراگراف متن، کلاس `.article-lead` می‌گیرد (مثل مرجع).
	 *
	 * @param string $html  خروجی پاراگراف.
	 * @param array  $block بلوک.
	 * @return string
	 */
	public function paragraph( $html, $block ) {
		if ( ! self::$in_body || self::$lead_done ) {
			return $html;
		}

		// پاراگراف‌های خالی (فاصله‌گذار) لید نمی‌شوند.
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return $html;
		}

		self::$lead_done = true;

		return self::with_class( $html, 'article-lead' );
	}

	/**
	 * هر تیتر `h2` متن، به «فصل» تبدیل می‌شود.
	 *
	 * @param string $html  خروجی تیتر.
	 * @param array  $block بلوک.
	 * @return string
	 */
	public function heading( $html, $block ) {
		if ( ! self::$in_body ) {
			return $html;
		}

		$level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;

		if ( 2 !== $level ) {
			return $html;
		}

		$index = self::$chapter_cursor;
		self::$chapter_cursor++;

		$anchor = isset( $block['attrs']['anchor'] ) ? sanitize_title( (string) $block['attrs']['anchor'] ) : '';
		$id     = '' !== $anchor ? $anchor : 'chapter-' . $index;

		/*
		 * فصل‌های واقعی در `render_body()` داخل
		 * `<section class="article-chapter" id="chapter-N">` می‌نشینند و
		 * شماره‌شان یک `<span>` خواهرِ تیتر است (ساختار مرجع). این صافی
		 * مسیر پشتیبان است: تیتر خالی، یا رندر متن از مسیری جز
		 * `core/post-content`.
		 */
		$html = self::with_class( $html, 'article-chapter' );
		$html = self::with_id( $html, $id );

		return (string) $html;
	}

	/**
	 * بدنه‌ی نوشته: فصل‌ها به ساختار مرجع تبدیل می‌شوند.
	 *
	 * پوسته‌ی خودِ بلوک `core/post-content` دست‌نخورده می‌ماند و فقط
	 * «داخلِ» آن با نشانه‌گذاری مرجع عوض می‌شود؛ به این ترتیب کلاس‌ها و
	 * پوشش‌های وردپرس (پهنا، پدینگ، `is-layout-*`) همان‌طور که هست
	 * می‌ماند و فقط محتوا ساختار می‌گیرد.
	 *
	 * @param string        $html     خروجی بلوک.
	 * @param array         $block    بلوک تجزیه‌شده.
	 * @param \WP_Block|null $instance نمونه‌ی بلوک (برای خواندن «زمینه»).
	 * @return string
	 */
	public function body( $html, $block, $instance = null ) {
		/*
		 * صافی `render_block_{name}` همیشه پس از `render_block` اجرا
		 * می‌شود، یعنی وقتی این‌جا می‌رسیم `close_body` پرچم `$in_body`
		 * را همین حالا صفر کرده است. پس شرط «داخل متن» را این‌جا از
		 * نو می‌سنجیم و به پرچم تکیه نمی‌کنیم.
		 */
		if ( ! self::is_article_view() ) {
			return $html;
		}

		/*
		 * شناسه‌ی نوشته را از «زمینه‌ی» خودِ بلوک می‌خوانیم، نه فقط از حلقه‌ی
		 * سراسری: اگر همین بلوک برای نوشته‌ی دیگری رندر شود (الگوی همگام یا
		 * حلقه‌ی کوئری درون متن)، `get_the_ID()` نوشته‌ی بیرونی است و
		 * بازنویسی بدنه، متن آن نوشته را با متن این نوشته جای‌گزین می‌کرد.
		 */
		$context_id = ( $instance instanceof \WP_Block && isset( $instance->context['postId'] ) )
			? (int) $instance->context['postId']
			: 0;

		$post_id = $context_id > 0 ? $context_id : (int) get_the_ID();

		if ( $post_id <= 0 || ( $context_id > 0 && (int) get_the_ID() !== $context_id ) ) {
			return $html;
		}

		/*
		 * صافی `render_block_{name}` بعد از `render_block` اجرا می‌شود، پس
		 * `close_body` همین حالا پرچم «داخل متن» را خاموش کرده است. برای
		 * رندر دوباره‌ی فرزندان (`render_block()` در `render_body`) پرچم
		 * باید روشن باشد، وگرنه صافی‌های `paragraph()`/`heading()` کار
		 * نمی‌کنند و کلاس `.article-lead` از دست می‌رود.
		 */
		$was_in_body     = self::$in_body;
		$was_lead_done   = self::$lead_done;

		self::$in_body   = true;
		self::$lead_done = false;

		if ( isset( self::$body_cache[ $post_id ] ) ) {
			$body = self::$body_cache[ $post_id ];
		} else {
			$body = self::render_body( $post_id );

			self::$body_cache[ $post_id ] = $body;
		}

		self::$in_body   = $was_in_body;
		self::$lead_done = $was_lead_done;

		if ( '' === trim( $body ) ) {
			return $html;
		}

		$open  = strpos( $html, '>' );
		$close = strrpos( $html, '</div>' );

		if ( false === $open || false === $close || $close <= $open ) {
			return $html;
		}

		return substr( $html, 0, $open + 1 ) . $body . substr( $html, $close );
	}

	/**
	 * ساختن بدنه‌ی متن با ساختار مرجع:
	 *
	 *     <p class="article-lead">…</p>
	 *     <div id="article-chapters">
	 *       <section class="article-chapter" id="chapter-0">
	 *         <span>۰۱</span><h2>…</h2><p>…</p>
	 *       </section>
	 *     </div>
	 *
	 * همه‌چیز از خودِ متن ویرایشگر می‌آید: تیتر `h2` سطح بالا = فصل،
	 * پاراگراف‌های پیش از نخستین فصل = لید و بلوک‌های میانی سرِ جای
	 * خودشان می‌مانند. بلوک‌های فرزند با `render_block()` رندر می‌شوند تا
	 * صافی‌های وردپرس و افزونه‌های دیگر (تصویر، نقل‌قول، …) هم اجرا شوند.
	 *
	 * @param int $post_id شناسه‌ی نوشته.
	 * @return string
	 */
	public static function render_body( $post_id ) {
		$blocks   = parse_blocks( (string) get_post_field( 'post_content', $post_id ) );
		$before   = '';
		$chapters = array();
		$current  = -1;

		foreach ( (array) $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['blockName'] ) ) {
				continue;
			}

			// بلوک بی‌نامِ خالی (فاصله‌ی میان بلوک‌ها در متن خام).
			if ( '' === (string) $block['blockName'] && '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
				continue;
			}

			if ( 'core/heading' === $block['blockName'] ) {
				$level = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
				$text  = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );

				if ( 2 === $level && '' !== $text ) {
					$index  = count( $chapters );
					$anchor = isset( $block['attrs']['anchor'] ) ? sanitize_title( (string) $block['attrs']['anchor'] ) : '';

					$chapters[] = array(
						'id'    => '' !== $anchor ? $anchor : 'chapter-' . $index,
						'index' => $index,
						'html'  => (string) ( $block['innerHTML'] ?? '' ),
					);

					$current = $index;

					// پاراگراف‌های داخل فصل دیگر «لید» نیستند.
					self::$lead_done = true;

					continue;
				}
			}

			$html = render_block( $block );

			if ( $current >= 0 ) {
				$chapters[ $current ]['html'] .= $html;
			} else {
				$before .= $html;
			}
		}

		if ( ! $chapters ) {
			return $before;
		}

		$out = '<div id="article-chapters">';

		foreach ( $chapters as $chapter ) {
			$out .= sprintf(
				'<section class="article-chapter" id="%1$s"><span aria-hidden="true">%2$s</span>%3$s</section>',
				esc_attr( $chapter['id'] ),
				esc_html( manacore_fa_digits( sprintf( '%02d', $chapter['index'] + 1 ) ) ),
				$chapter['html']
			);
		}

		return $before . $out . '</div>';
	}

	/**
	 * افزودن کلاس به نخستین تگ خروجی.
	 *
	 * @param string $html  خروجی.
	 * @param string $class کلاس تازه.
	 * @return string
	 */
	protected static function with_class( $html, $class ) {
		if ( preg_match( '/class="([^"]*)"/i', $html, $m ) ) {
			if ( false !== strpos( $m[1], $class ) ) {
				return $html;
			}

			return (string) preg_replace( '/class="([^"]*)"/i', 'class="$1 ' . $class . '"', $html, 1 );
		}

		return (string) preg_replace( '/^(\s*<[a-z0-9]+)/i', '$1 class="' . $class . '"', $html, 1 );
	}

	/**
	 * افزودن شناسه (اگر از قبل نیست).
	 *
	 * @param string $html خروجی.
	 * @param string $id   شناسه.
	 * @return string
	 */
	protected static function with_id( $html, $id ) {
		if ( preg_match( '/\sid="/i', $html ) ) {
			return $html;
		}

		return (string) preg_replace( '/^(\s*<[a-z0-9]+)/i', '$1 id="' . esc_attr( $id ) . '"', $html, 1 );
	}
}
