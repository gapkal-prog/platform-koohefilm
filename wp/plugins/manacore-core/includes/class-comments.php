<?php
/**
 * دیدگاه‌ها — هم‌سطح قابلیت‌های مرجع طراحی (`cinora/detail.html`).
 *
 * سه قابلیت مرجع در وردپرس معادل نداشتند و اینجا کامل می‌شوند:
 *
 *   ۱) «اسپویل در متن» — کاربر بخشی از متن را انتخاب می‌کند، ابزار
 *      `[spoiler]…[/spoiler]` را دور آن می‌گذارد و خروجی با دکمه‌ی
 *      «متن حاوی اسپویل» نمایش داده می‌شود.
 *   ۲) «تمام دیدگاه اسپویل دارد» — به‌صورت فراداده روی دیدگاه ذخیره
 *      می‌شود و متن تا کلیک کاربر تار می‌ماند.
 *   ۳) فرم فشرده‌ی مرجع: سرصفحه‌ی داستان‌گو، شمارنده‌ی نویسه و
 *      سطر ابزارها، به‌همراه حالت خالی «اولین نفری باش…».
 *
 * کلاس‌ها و تگ‌های خروجی همان قرارداد بخش دیدگاه‌ها هستند
 * (`.comments-section`، `.comment-compose`، `.comment-text`، …) تا
 * استایل مرجع بدون بازنویسی روی آن‌ها بنشیند.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Comments
 */
class Comments {

	use Singleton;

	/**
	 * کلید فراداده‌ی «تمام دیدگاه اسپویل دارد».
	 *
	 * @var string
	 */
	const META_SPOILER = 'manacore_comment_spoiler';

	/**
	 * بیشینه‌ی نویسه‌ی دیدگاه، مثل مرجع.
	 *
	 * @var int
	 */
	const MAX_LENGTH = 1500;

	/**
	 * ثبت قلاب‌ها.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'comment_form_defaults', array( $this, 'form_defaults' ) );
		add_filter( 'comment_form_fields', array( $this, 'reorder_fields' ) );
		add_filter( 'comment_form_submit_button', array( $this, 'submit_button' ) );
		add_filter( 'comment_form_submit_field', array( $this, 'submit_field' ) );
		add_action( 'comment_post', array( $this, 'save_spoiler' ), 10, 2 );
		add_filter( 'comment_text', array( $this, 'comment_text' ), 20, 2 );
		add_filter( 'render_block', array( $this, 'spoiler_flag' ), 10, 3 );
		add_action( 'comment_form_after', array( $this, 'compose_note' ) );
	}

	/**
	 * قالب فرم دیدگاه، هم‌شکل `.comment-compose` مرجع.
	 *
	 * نکته‌ی مهم: هسته سطر ابزارها را نمی‌شناسد و دکمه‌ی ارسال را در
	 * پاراگراف جدا می‌سازد. اینجا فقط چند تغییر کوچک می‌دهیم تا دکمه
	 * واقعی هسته (با ورودی‌های مخفی لازم برای پاسخ‌گویی) داخل همان
	 * سطر بنشیند: `comment_field` سطر `.compose-actions` را باز می‌کند،
	 * `submit_button` کلاس دکمه را به دکمه‌ی سایت تغییر می‌دهد و
	 * `submit_field` پاراگراف را به `div` تبدیل و سطر را می‌بندد.
	 *
	 * @param array<string,mixed> $defaults پیش‌فرض‌های هسته.
	 * @return array<string,mixed>
	 */
	public function form_defaults( $defaults ) {
		$text = sprintf(
			'<textarea id="comment" name="comment" rows="2" maxlength="%1$d" minlength="3" required placeholder="%2$s"></textarea>',
			self::MAX_LENGTH,
			esc_attr__( 'از تجربه تماشایت بنویس؛ اما داستان را برای دیگران لو نده...', 'manacore' )
		);

		$defaults['comment_field'] = sprintf(
			'<div class="compose-header"><span class="comment-avatar" aria-hidden="true">؟</span><p>%1$s</p></div>' .
			'<p class="comment-form-comment"><label for="comment">%2$s</label>%3$s</p>' .
			'<div class="compose-actions">' .
				'<button type="button" class="spoiler-tool" data-spoiler-tool>%4$s</button>' .
				/*
			 * `id="comment-spoiler"` عیناً مثل مرجع است تا هر گزینشگر CSS/آزمونی
			 * که روی همان شناسه نوشته شده (و همچنین پیوند صریح برچسب به فیلد)
			 * کار کند؛ `class="spoiler-whole"` رفتاری است و دست‌نخورده می‌ماند.
			 */
			'<label class="spoiler-whole" for="comment-spoiler"><input type="checkbox" id="comment-spoiler" name="manacore_spoiler" value="1" data-whole-spoiler-input /> %5$s</label>' .
				'<span class="comment-length" data-comment-length aria-live="polite">۰ / %6$s</span>',
			/* سطر اینجا بسته نمی‌شود؛ `submit_field` دکمه‌ی ارسال را
			   داخل همین سطر می‌گذارد و سپس می‌بندد. */
			esc_html__( 'دیدگاهت بخشی از داستان ماست.', 'manacore' ),
			esc_html__( 'دیدگاه', 'manacore' ),
			$text,
			esc_html__( '◉ اسپویل در متن', 'manacore' ),
			esc_html__( 'تمام دیدگاه اسپویل دارد', 'manacore' ),
			manacore_fa_digits( (string) self::MAX_LENGTH )
		);
		// phpcs:ignore Squiz.Strings.DoubleQuoteUsage -- رشته‌ی بالا عمداً ناتمام است.

		$defaults['comment_notes_before'] = '';
		$defaults['comment_notes_after']  = '';
		$defaults['title_reply']          = '';
		$defaults['title_reply_before']   = '';
		$defaults['title_reply_after']    = '';
		$defaults['class_submit']         = 'manacore-btn is-primary';
		$defaults['label_submit']         = __( 'ارسال دیدگاه', 'manacore' );
		$defaults['format']               = 'html5';

		return $defaults;
	}

	/**
	 * ترتیب میدان‌ها: نخست نام/ایمیل، آخر متن دیدگاه.
	 *
	 * هسته همیشه میدان `comment` را اول می‌آورد؛ مرجع فیلدهای هویتی را
	 * بالای کادر متن دارد.
	 *
	 * @param array<string,string> $fields میدان‌های فرم.
	 * @return array<string,string>
	 */
	public function reorder_fields( $fields ) {
		if ( empty( $fields['comment'] ) ) {
			return $fields;
		}
		$comment = $fields['comment'];
		unset( $fields['comment'] );

		return $fields + array( 'comment' => $comment );
	}

	/**
	 * ذخیره‌ی گزینه‌ی «تمام دیدگاه اسپویل دارد».
	 *
	 * @param int        $comment_id شناسه‌ی دیدگاه.
	 * @param int|string $approved   وضعیت تأیید.
	 * @return void
	 */
	public function save_spoiler( $comment_id, $approved ) {
		unset( $approved );
		$flag = isset( $_POST['manacore_spoiler'] ) ? (int) $_POST['manacore_spoiler'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- هسته پیش از این قلاب نانس فرم دیدگاه را بررسی کرده است.
		if ( $flag ) {
			update_comment_meta( $comment_id, self::META_SPOILER, 1 );
		}
	}

	/**
	 * دکمه‌ی ارسال: کلاس دکمه‌ی سایت روی دکمه‌ی هسته/بلوک.
	 *
	 * @param string $button دکمه‌ی ساخته‌شده.
	 * @return string
	 */
	public function submit_button( $button ) {
		if ( false === strpos( $button, 'wp-block-button__link' ) ) {
			return $button; // مسیر پیش‌فرض `class_submit` خودش درست است.
		}

		return preg_replace( '#class="[^"]*"#', 'class="manacore-btn is-primary"', $button, 1 );
	}

	/**
	 * جای دکمه‌ی ارسال: داخل سطر ابزارها به‌جای پاراگراف جدا.
	 *
	 * @param string $field میدان ارسال آماده.
	 * @return string
	 */
	public function submit_field( $field ) {
		$div = preg_replace( '#<p class="form-submit[^"]*"\s*>(.*)</p>#s', '<div class="form-submit">$1</div>', $field, 1 );
		if ( null !== $div && $div !== $field ) {
			$field = $div;
		} else {
			$field = '<div class="form-submit">' . preg_replace( '#</p>\s*$#', '', $field ) . '</div>';
		}

		return $field . '</div>';
	}

	/**
	 * راهنمای زیر فرم، مثل `.compose-note` مرجع.
	 *
	 * @return void
	 */
	public function compose_note() {
		echo '<p class="compose-note">' . esc_html__( 'برای پنهان کردن فقط بخشی از دیدگاه، متن را انتخاب کن و «اسپویل در متن» را بزن.', 'manacore' ) . '</p>';
	}

	/**
	 * متن دیدگاه: پوشش `.comment-text` و تبدیل اسپویل‌ها.
	 *
	 * @param string            $text    متن آماده‌ی نمایش.
	 * @param \WP_Comment|array $comment دیدگاه.
	 * @return string
	 */
	public function comment_text( $text, $comment = null ) {
		$id = is_object( $comment ) && isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0;
		$whole = $id && get_comment_meta( $id, self::META_SPOILER, true );

		if ( $whole ) {
			$body = sprintf(
				'<button type="button" class="whole-spoiler" data-whole-spoiler aria-expanded="false">%1$s<b>%2$s</b></button>' .
				'<span class="whole-spoiler__text" hidden>%3$s</span>',
				esc_html__( '◉ این دیدگاه بخشی از داستان را لو می‌دهد.', 'manacore' ),
				esc_html__( 'نمایش دیدگاه', 'manacore' ),
				$this->inline_spoilers( $text )
			);
		} else {
			$body = $this->inline_spoilers( $text );
		}

		return '<span class="comment-text"' . ( $whole ? ' data-whole-spoiler-text' : '' ) . '>' . $body . '</span>';
	}

	/**
	 * تبدیل `[spoiler]…[/spoiler]` به دکمه‌ی آشکارساز.
	 *
	 * متن پنهان در همان عنصر می‌ماند (نه حذف) تا کاربر واقعاً بتواند
	 * آن را بخواند؛ مرجع فقط دکمه را نشان می‌دهد و متن را دور می‌ریزد.
	 *
	 * @param string $text متن دیدگاه.
	 * @return string
	 */
	protected function inline_spoilers( $text ) {
		if ( false === strpos( $text, '[spoiler]' ) ) {
			return $text;
		}

		return preg_replace_callback(
			'#\[spoiler\](.*?)\[/spoiler\]#is',
			function ( $m ) {
				return sprintf(
					'<span class="inline-spoiler"><button type="button" class="inline-spoiler__btn" data-inline-spoiler aria-expanded="false">%1$s</button><span class="inline-spoiler__text" hidden>%2$s</span></span>',
					esc_html__( '◉ متن حاوی اسپویل — برای نمایش کلیک کن', 'manacore' ),
					wp_kses_post( $m[1] )
				);
			},
			$text
		);
	}

	/**
	 * نشان «⚠ اسپویل» در سطر نام و تاریخ دیدگاه.
	 *
	 * @param string        $content محتوای بلوک.
	 * @param array         $block   بلوک.
	 * @param \WP_Block|null $instance نمونه‌ی بلوک.
	 * @return string
	 */
	public function spoiler_flag( $content, $block, $instance = null ) {
		if ( empty( $block['blockName'] ) || 'core/comment-date' !== $block['blockName'] ) {
			return $content;
		}

		/*
		 * شناسه‌ی دیدگاه از «نمونه‌ی بلوک» خوانده می‌شود، نه از `$block`.
		 *
		 * در قالب‌های بخش سایت، `$block['context']` هیچ‌وقت پر نمی‌شود؛
		 * نتیجه این بود که نشان «⚠ اسپویل» هرگز روی دیدگاه‌های
		 * اسپویل‌دار چاپ نمی‌شد (سنجیده‌شده روی برگه‌ی قسمت). زمینه‌ی
		 * واقعی از `WP_Block` می‌آید و پارامتر سوم همین است.
		 */
		$id = 0;

		if ( $instance instanceof \WP_Block && isset( $instance->context['commentId'] ) ) {
			$id = (int) $instance->context['commentId'];
		} elseif ( isset( $block['context']['commentId'] ) ) {
			$id = (int) $block['context']['commentId'];
		}
		if ( ! $id || ! get_comment_meta( $id, self::META_SPOILER, true ) ) {
			return $content;
		}

		return $content . '<small class="spoiler-flag">' . esc_html__( '⚠ اسپویل', 'manacore' ) . '</small>';
	}
}
