<?php
/**
 * متاباکس‌های ادمین.
 *
 * @package ManaCore\Core
 */

namespace ManaCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Metaboxes
 */
class Metaboxes {

	use Singleton;

	/**
	 * نام nonce.
	 */
	const NONCE = 'manacore_meta_nonce';

	/**
	 * نام فیلد پنهانِ اثرانگشت وضعیت فرم.
	 */
	const STATE = 'manacore_meta_state';

	/**
	 * ثبت هوک‌ها.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * ثبت متاباکس‌ها.
	 */
	public function register() {
		$types = array_merge( manacore_title_post_types(), array( 'episode' ) );

		foreach ( $types as $type ) {
			add_meta_box(
				'manacore-details',
				__( 'مشخصات اثر — ManaCore', 'manacore' ),
				array( $this, 'render_details' ),
				$type,
				'normal',
				'high'
			);

			add_meta_box(
				'manacore-links',
				__( 'لینک‌های دانلود و پخش — ManaCore', 'manacore' ),
				array( $this, 'render_links' ),
				$type,
				'normal',
				'high'
			);
		}

		foreach ( manacore_title_post_types() as $type ) {
			add_meta_box(
				'manacore-fetch',
				__( 'دریافت خودکار اطلاعات', 'manacore' ),
				array( $this, 'render_fetch' ),
				$type,
				'side',
				'high'
			);
		}

		add_meta_box(
			'manacore-collection-items',
			__( 'آثار این مجموعه — ManaCore', 'manacore' ),
			array( $this, 'render_collection_items' ),
			'collection',
			'normal',
			'high'
		);
	}

	/**
	 * رندر متاباکس اعضای مجموعه.
	 *
	 * ترتیب دستی اعضا در متای manacore_collection_items نگهداری می‌شود و
	 * بلوک manacore/titles-grid با source="collection" از همان ترتیب پیروی می‌کند.
	 * در نبود فهرست دستی، آثاری که متای manacore_collection آن‌ها به این
	 * مجموعه اشاره دارد نمایش داده می‌شوند.
	 *
	 * @param \WP_Post $post پست مجموعه.
	 */
	public function render_collection_items( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );

		$stored = get_post_meta( $post->ID, 'manacore_collection_items', true );

		if ( is_array( $stored ) ) {
			$selected = array_values( array_filter( array_map( 'absint', $stored ) ) );
		} elseif ( is_string( $stored ) && '' !== $stored ) {
			$selected = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $stored ) ) ) );
		} else {
			$selected = array();
		}

		$titles = get_posts(
			array(
				'post_type'      => manacore_title_post_types(),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			)
		);

		/* آثاری که از سمت خودشان به این مجموعه متصل شده‌اند. */
		$linked = get_posts(
			array(
				'post_type'      => manacore_title_post_types(),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => 'manacore_collection',
						'value' => $post->ID,
					),
				),
			)
		);
		?>
		<div class="manacore-collection-items">
			<p class="description">
				<?php esc_html_e( 'آثار عضو این مجموعه را انتخاب کنید. ترتیب انتخاب، ترتیب نمایش در بلوک «آثار مجموعه» خواهد بود.', 'manacore' ); ?>
			</p>

			<label class="screen-reader-text" for="manacore_collection_items">
				<?php esc_html_e( 'آثار مجموعه', 'manacore' ); ?>
			</label>
			<select
				id="manacore_collection_items"
				name="manacore_collection_items[]"
				class="widefat"
				multiple="multiple"
				size="12"
			>
				<?php foreach ( $titles as $item ) : ?>
					<option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( in_array( (int) $item->ID, $selected, true ) ); ?>>
						<?php
						printf(
							/* translators: 1: عنوان اثر، 2: نوع محتوا */
							'%1$s (%2$s)',
							esc_html( $item->post_title ),
							esc_html( (string) get_post_type( $item ) )
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php if ( ! empty( $linked ) ) : ?>
				<p class="description manacore-desc">
					<?php
					printf(
						/* translators: %d: تعداد آثار */
						esc_html__( '%d اثر از طریق فیلد «مجموعه» در صفحه‌ی خودشان به این مجموعه متصل شده‌اند. در صورت خالی بودن فهرست بالا، همان‌ها نمایش داده می‌شوند.', 'manacore' ),
						count( $linked )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * رندر متاباکس مشخصات.
	 *
	 * @param \WP_Post $post پست.
	 */
	public function render_details( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$groups = Meta::instance()->groups_for( $post->post_type );

		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'فیلدی برای این نوع محتوا تعریف نشده است.', 'manacore' ) . '</p>';
			return;
		}

		/*
		 * A fingerprint of the values this form was rendered with.
		 *
		 * Without it a save cannot tell "the editor cleared this field" apart
		 * from "the field was written server-side after the form was rendered"
		 * — which is exactly what happens when data is imported over REST from
		 * the auto-fetch panel. Comparing against this marker on save keeps
		 * freshly imported values instead of overwriting them with the stale
		 * inputs still sitting in the browser.
		 */
		$this->render_state( $post->ID, $groups );
		?>
		<div class="manacore-metabox" data-manacore-tabs>
			<nav class="manacore-tabs" role="tablist">
				<?php $first = true; ?>
				<?php foreach ( $groups as $key => $group ) : ?>
					<button type="button"
						class="manacore-tab<?php echo $first ? ' is-active' : ''; ?>"
						data-tab="<?php echo esc_attr( $key ); ?>"
						role="tab"
						aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $group['icon'] ); ?>"></span>
						<?php echo esc_html( $group['label'] ); ?>
					</button>
					<?php $first = false; ?>
				<?php endforeach; ?>
			</nav>

			<?php $first = true; ?>
			<?php foreach ( $groups as $key => $group ) : ?>
				<div class="manacore-tab-panel<?php echo $first ? ' is-active' : ''; ?>"
					data-panel="<?php echo esc_attr( $key ); ?>" role="tabpanel">
					<?php
					if ( 'credits' === $key ) {
						$this->render_people_finder();
					}
					?>
					<div class="manacore-grid">
						<?php
						foreach ( $group['fields'] as $field_key => $field ) {
							$this->render_field( $field_key, $field, $post->ID );
						}
						?>
					</div>
				</div>
				<?php $first = false; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * درج اثرانگشت مقادیر رندرشده در فرم.
	 *
	 * برای هر فیلد یک هش از مقدار لحظه‌ی رندر ذخیره می‌شود. هنگام ذخیره اگر
	 * مقدار فعلی متا با این هش تفاوت داشته باشد یعنی داده پس از رندر فرم
	 * (مثلاً توسط «درج اطلاعات») نوشته شده است، و ورودی خالیِ مرورگر نباید آن
	 * را پاک کند.
	 *
	 * @param int   $post_id شناسه‌ی پست.
	 * @param array $groups  گروه‌های فیلد.
	 */
	protected function render_state( $post_id, $groups ) {
		$state = array();

		foreach ( $groups as $group ) {
			foreach ( array_keys( $group['fields'] ) as $key ) {
				$state[ $key ] = $this->value_fingerprint( get_post_meta( $post_id, $key, true ) );
			}
		}

		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::STATE ),
			esc_attr( wp_json_encode( $state ) )
		);
	}

	/**
	 * هش کوتاه یک مقدار متا برای مقایسه‌ی وضعیت.
	 *
	 * @param mixed $value مقدار.
	 * @return string
	 */
	protected function value_fingerprint( $value ) {
		if ( '' === $value || null === $value || array() === $value ) {
			return '';
		}

		return substr( md5( is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ) ), 0, 12 );
	}

	/**
	 * خواندن اثرانگشت‌های ارسالی فرم.
	 *
	 * @return array|null نقشه‌ی کلید => هش، یا null اگر فرم اثرانگشت نداشت.
	 */
	protected function posted_state() {
		if ( ! isset( $_POST[ self::STATE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return null;
		}

		$raw = sanitize_textarea_field( wp_unslash( $_POST[ self::STATE ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$map = json_decode( $raw, true );

		return is_array( $map ) ? $map : null;
	}

	/**
	 * آیا مقدار فعلی متا پس از رندر فرم تغییر کرده است؟
	 *
	 * @param int         $post_id شناسه‌ی پست.
	 * @param string      $key     کلید متا.
	 * @param array|null  $state   اثرانگشت‌های فرم.
	 * @return bool
	 */
	protected function changed_since_render( $post_id, $key, $state ) {
		// فرم‌های قدیمی یا بدون اثرانگشت: رفتار پیشین حفظ می‌شود.
		if ( null === $state || ! array_key_exists( $key, $state ) ) {
			return false;
		}

		$current = $this->value_fingerprint( get_post_meta( $post_id, $key, true ) );

		return (string) $state[ $key ] !== $current;
	}

	/**
	 * رندر یک فیلد.
	 *
	 * @param string $key     کلید متا.
	 * @param array  $field   تعریف فیلد.
	 * @param int    $post_id شناسه‌ی پست.
	 */
	protected function render_field( $key, $field, $post_id ) {
		$value = get_post_meta( $post_id, $key, true );
		$attrs = isset( $field['attrs'] ) ? $field['attrs'] : array();
		$wide  = in_array( $field['type'], array( 'textarea', 'repeater', 'gallery', 'people' ), true );

		$attr_string = '';
		foreach ( $attrs as $attr_key => $attr_value ) {
			if ( true === $attr_value ) {
				$attr_string .= ' ' . esc_attr( $attr_key );
			} else {
				$attr_string .= sprintf( ' %s="%s"', esc_attr( $attr_key ), esc_attr( $attr_value ) );
			}
		}
		?>
		<div class="manacore-field<?php echo $wide ? ' is-wide' : ''; ?>">
			<label class="manacore-label" for="<?php echo esc_attr( $key ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
			</label>

			<?php
			switch ( $field['type'] ) :
				case 'textarea':
					?>
					<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
						rows="4" class="widefat"<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
					<?php
					break;

				case 'select':
					?>
					<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat">
						<?php foreach ( $field['options'] as $opt_key => $opt_label ) : ?>
							<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $value, $opt_key ); ?>>
								<?php echo esc_html( $opt_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'checkbox':
					?>
					<label class="manacore-switch">
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="0" />
						<input type="checkbox" id="<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $value, '1' ); ?> />
						<span class="manacore-switch-track"><span class="manacore-switch-thumb"></span></span>
					</label>
					<?php
					break;

				case 'post_select':
					$source = isset( $field['source'] ) ? $field['source'] : array( 'post' );
					$posts  = get_posts(
						array(
							'post_type'      => $source,
							'posts_per_page' => 300,
							'orderby'        => 'title',
							'order'          => 'ASC',
							'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
						)
					);
					?>
					<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat">
						<option value=""><?php esc_html_e( '— انتخاب کنید —', 'manacore' ); ?></option>
						<?php foreach ( $posts as $item ) : ?>
							<option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( (int) $value, $item->ID ); ?>>
								<?php echo esc_html( $item->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'image_url':
					?>
					<div class="manacore-media-field">
						<input type="url" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
							value="<?php echo esc_attr( (string) $value ); ?>" class="widefat manacore-media-input" />
						<button type="button" class="button manacore-media-pick">
							<?php esc_html_e( 'انتخاب', 'manacore' ); ?>
						</button>
					</div>
					<?php if ( $value ) : ?>
						<img class="manacore-media-preview" src="<?php echo esc_url( (string) $value ); ?>" alt="" />
					<?php else : ?>
						<img class="manacore-media-preview" src="" alt="" hidden />
					<?php endif; ?>
					<?php
					break;

				case 'gallery':
					?>
					<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
						rows="4" class="widefat"
						placeholder="<?php esc_attr_e( 'هر آدرس تصویر در یک خط', 'manacore' ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
					<?php
					break;

				case 'repeater':
					$this->render_repeater( $key, $field, $value );
					break;

				case 'people':
					$this->render_people( $key, $field, $value );
					break;

				default:
					?>
					<input type="<?php echo esc_attr( $field['type'] ); ?>"
						id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
						value="<?php echo esc_attr( (string) $value ); ?>"
						class="widefat"<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
					<?php
			endswitch;
			?>

			<?php if ( ! empty( $field['description'] ) ) : ?>
				<p class="manacore-desc"><?php echo esc_html( $field['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * رندر فیلد تکرارشونده.
	 *
	 * @param string $key   کلید.
	 * @param array  $field تعریف.
	 * @param mixed  $value مقدار.
	 */
	protected function render_repeater( $key, $field, $value ) {
		$rows = is_string( $value ) ? json_decode( $value, true ) : $value;
		$rows = is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array();
		?>
		<div class="manacore-repeater" data-repeater="<?php echo esc_attr( $key ); ?>">
			<div class="manacore-repeater-rows">
				<?php foreach ( $rows as $index => $row ) : ?>
					<div class="manacore-repeater-row">
						<span class="manacore-drag dashicons dashicons-menu"></span>
						<div class="manacore-repeater-fields">
							<?php
							foreach ( $field['subfields'] as $sub_key => $sub ) {
								$this->render_repeater_sub( $key, $index, $sub_key, $sub, $row );
							}
							?>
						</div>
						<button type="button" class="button-link manacore-repeater-remove" aria-label="<?php esc_attr_e( 'حذف', 'manacore' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				<?php endforeach; ?>
			</div>

			<template class="manacore-repeater-template">
				<div class="manacore-repeater-row">
					<span class="manacore-drag dashicons dashicons-menu"></span>
					<div class="manacore-repeater-fields">
						<?php
						foreach ( $field['subfields'] as $sub_key => $sub ) {
							$this->render_repeater_sub( $key, '__INDEX__', $sub_key, $sub, array() );
						}
						?>
					</div>
					<button type="button" class="button-link manacore-repeater-remove" aria-label="<?php esc_attr_e( 'حذف', 'manacore' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</template>

			<button type="button" class="button manacore-repeater-add">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'افزودن ردیف', 'manacore' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * رندر یک زیرفیلد ریپیتر (متن، متن بلند، نشانی، پیکر عامل یا مخفی).
	 *
	 * @param string     $key       کلید ریپیتر.
	 * @param string|int $index     اندیس ردیف یا `__INDEX__` برای الگو.
	 * @param string     $sub_key   کلید زیرفیلد.
	 * @param array      $sub       تعریف زیرفیلد.
	 * @param array      $row       داده‌ی ردیف (خالی برای الگو).
	 */
	protected function render_repeater_sub( $key, $index, $sub_key, array $sub, array $row ) {
		$name  = $key . '[' . $index . '][' . $sub_key . ']';
		$value = (string) ( $row[ $sub_key ] ?? '' );
		$type  = (string) ( $sub['type'] ?? 'text' );

		if ( ! empty( $sub['hidden'] ) ) {
			?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php
			return;
		}

		if ( ! empty( $sub['picker'] ) ) {
			$person_id = Crew::valid_person_id( $row['person_id'] ?? 0 );
			?>
			<div class="manacore-sub manacore-sub-picker" data-person-picker>
				<span class="manacore-sub-label"><?php echo esc_html( $sub['label'] ); ?></span>
				<input type="text" class="manacore-person-search" name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>" autocomplete="off"
					role="combobox" aria-autocomplete="list" aria-expanded="false"
					aria-label="<?php esc_attr_e( 'نام بازیگر؛ برای پیوند به صفحه‌ی عامل جست‌وجو کنید', 'manacore' ); ?>"
					placeholder="<?php esc_attr_e( 'جست‌وجوی عامل یا نام آزاد…', 'manacore' ); ?>"
					data-person-search data-role="cast" />
				<div class="manacore-people-results" data-people-results role="listbox" hidden></div>
				<span class="manacore-person-badge" data-person-badge<?php echo $person_id ? '' : ' hidden'; ?>>
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'به صفحه‌ی عامل پیوند خورده', 'manacore' ); ?>
					<button type="button" class="button-link" data-person-unlink><?php esc_html_e( 'جدا کردن', 'manacore' ); ?></button>
				</span>
			</div>
			<?php
			return;
		}
		?>
		<label class="manacore-sub">
			<span><?php echo esc_html( $sub['label'] ); ?></span>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea rows="2" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input type="<?php echo esc_attr( 'image_url' === $type ? 'url' : $type ); ?>"
					name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * رندر فیلد عوامل به‌صورت برچسب (chip) با جست‌وجوی عوامل ثبت‌شده و نام آزاد.
	 *
	 * مقدار واقعی در ورودی مخفی به‌صورت JSON نگه‌داری می‌شود؛ رابط برچسب‌ها در
	 * admin-people.js ساخته می‌شود.
	 *
	 * @param string $key   کلید متا.
	 * @param array  $field تعریف فیلد.
	 * @param mixed  $value مقدار ذخیره‌شده (JSON یا متن قدیمی).
	 */
	protected function render_people( $key, $field, $value ) {
		$items = Crew::parse( $value );
		$role  = (string) ( $field['role'] ?? '' );
		$label = (string) $field['label'];
		?>
		<div class="manacore-people" data-people="<?php echo esc_attr( $role ); ?>">
			<div class="manacore-people-chips" data-people-chips></div>
			<input type="search" class="widefat manacore-people-input" autocomplete="off"
				role="combobox" aria-autocomplete="list" aria-expanded="false"
				aria-label="<?php echo esc_attr( sprintf( /* translators: %s: نام نقش */ __( 'افزودن %s', 'manacore' ), $label ) ); ?>"
				placeholder="<?php esc_attr_e( 'نام عامل را جست‌وجو کنید یا نام آزاد را تایپ و Enter بزنید…', 'manacore' ); ?>"
				data-people-input />
			<div class="manacore-people-results" data-people-results role="listbox" hidden></div>
			<input type="hidden" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( Crew::encode( $items ) ); ?>" data-people-value />
		</div>
		<?php
	}

	/**
	 * پنل جست‌وجوی عوامل ثبت‌شده در تب «عوامل»؛ انتخاب هر عامل به نقش انتخابی اضافه می‌شود.
	 */
	protected function render_people_finder() {
		$roles = array(
			'director' => __( 'کارگردان', 'manacore' ),
			'writer'   => __( 'نویسنده', 'manacore' ),
			'producer' => __( 'تهیه‌کننده', 'manacore' ),
			'composer' => __( 'آهنگساز', 'manacore' ),
			'cast'     => __( 'بازیگر', 'manacore' ),
		);
		?>
		<div class="manacore-people-finder" data-people-finder>
			<div class="manacore-people-finder-head">
				<strong><?php esc_html_e( 'جست‌وجوی عوامل ثبت‌شده', 'manacore' ); ?></strong>
				<span class="manacore-desc"><?php esc_html_e( 'عامل را پیدا کنید و به نقش موردنظر اضافه کنید. عوامل از بخش «عوامل» خوانده می‌شوند.', 'manacore' ); ?></span>
			</div>
			<div class="manacore-people-finder-row">
				<input type="search" class="widefat" autocomplete="off" data-finder-input
					aria-label="<?php esc_attr_e( 'جست‌وجوی عامل', 'manacore' ); ?>"
					placeholder="<?php esc_attr_e( 'نام فارسی، لاتین یا اصلی عامل…', 'manacore' ); ?>" />
				<select data-finder-role aria-label="<?php esc_attr_e( 'نقش', 'manacore' ); ?>">
					<?php foreach ( $roles as $role_key => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="manacore-people-results" data-finder-results role="listbox" hidden></div>
		</div>
		<?php
	}

	/**
	 * رندر متاباکس لینک‌ها.
	 *
	 * @param \WP_Post $post پست.
	 */
	public function render_links( $post ) {
		$groups    = Links::get( $post->ID );
		$is_serial = in_array( $post->post_type, manacore_serial_post_types(), true );
		?>
		<div class="manacore-links-app"
			data-manacore-links
			data-is-serial="<?php echo $is_serial ? '1' : '0'; ?>"
			data-qualities="<?php echo esc_attr( wp_json_encode( manacore_qualities() ) ); ?>"
			data-languages="<?php echo esc_attr( wp_json_encode( manacore_languages() ) ); ?>"
			data-types="<?php echo esc_attr( wp_json_encode( manacore_link_types() ) ); ?>"
			data-value="<?php echo esc_attr( wp_json_encode( $groups, JSON_UNESCAPED_UNICODE ) ); ?>">

			<div class="manacore-links-toolbar">
				<button type="button" class="button button-primary" data-action="add-group">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'افزودن گروه لینک', 'manacore' ); ?>
				</button>
				<?php if ( $is_serial ) : ?>
					<button type="button" class="button" data-action="add-season-group">
						<span class="dashicons dashicons-editor-ol"></span>
						<?php esc_html_e( 'افزودن فصل کامل', 'manacore' ); ?>
					</button>
				<?php endif; ?>
				<button type="button" class="button" data-action="bulk-import">
					<span class="dashicons dashicons-editor-paste-text"></span>
					<?php esc_html_e( 'ورود گروهی لینک', 'manacore' ); ?>
				</button>
				<button type="button" class="button" data-action="collapse-all">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					<?php esc_html_e( 'بستن همه', 'manacore' ); ?>
				</button>
				<span class="manacore-links-count" data-links-count></span>
			</div>

			<div class="manacore-links-groups" data-groups></div>

			<p class="manacore-desc">
				<?php esc_html_e( 'گروه‌ها را می‌توانید با کشیدن جابه‌جا کنید. هر گروه می‌تواند شامل چند لینک با کیفیت و قسمت متفاوت باشد.', 'manacore' ); ?>
			</p>

			<input type="hidden" name="manacore_links_json" data-links-input
				value="<?php echo esc_attr( wp_json_encode( $groups, JSON_UNESCAPED_UNICODE ) ); ?>" />
		</div>
		<?php
	}

	/**
	 * رندر متاباکس دریافت خودکار (اگر افزونه‌ی منابع فعال باشد).
	 *
	 * @param \WP_Post $post پست.
	 */
	public function render_fetch( $post ) {
		if ( ! has_action( 'manacore_fetch_metabox' ) ) {
			echo '<p class="manacore-desc">' . esc_html__( 'برای دریافت خودکار اطلاعات، افزونه‌ی «ManaCore Sources» را فعال کنید.', 'manacore' ) . '</p>';
			return;
		}
		do_action( 'manacore_fetch_metabox', $post );
	}

	/**
	 * ذخیره‌ی مقادیر.
	 *
	 * @param int      $post_id شناسه‌ی پست.
	 * @param \WP_Post $post    پست.
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// ذخیره‌ی اعضای مجموعه (نوع محتوای collection).
		if ( 'collection' === $post->post_type ) {
			$this->save_collection_items( $post_id );
			do_action( 'manacore_after_save_meta', $post_id, $post );
			return;
		}

		$types = array_merge( manacore_title_post_types(), array( 'episode' ) );
		if ( ! in_array( $post->post_type, $types, true ) ) {
			return;
		}

		// ذخیره‌ی فیلدهای اسکیما.
		$state  = $this->posted_state();
		$groups = Meta::instance()->groups_for( $post->post_type );

		foreach ( $groups as $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				if ( 'repeater' === $field['type'] ) {
					$this->save_repeater( $post_id, $key, $field, $state );
					continue;
				}

				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}

				$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$val = $this->sanitize_field( $raw, $field );

				if ( '' === $val || null === $val ) {
					/*
					 * An empty input only clears the value when it really was
					 * empty while the form was on screen. If the meta changed
					 * in the meantime — the auto-fetch importer writing over
					 * REST — the stale input is ignored so imported data is
					 * not silently destroyed on the next save.
					 */
					if ( $this->changed_since_render( $post_id, $key, $state ) ) {
						continue;
					}

					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $val );
				}
			}
		}

		// ذخیره‌ی لینک‌ها.
		if ( isset( $_POST['manacore_links_json'] ) ) {
			$json = wp_unslash( $_POST['manacore_links_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			Links::save( $post_id, $json );
		}

		do_action( 'manacore_after_save_meta', $post_id, $post );
	}

	/**
	 * ذخیره‌ی فهرست مرتب اعضای مجموعه.
	 *
	 * @param int $post_id شناسه‌ی مجموعه.
	 */
	protected function save_collection_items( $post_id ) {
		if ( ! isset( $_POST['manacore_collection_items'] ) || ! is_array( $_POST['manacore_collection_items'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_post_meta( $post_id, 'manacore_collection_items' );
			return;
		}

		$raw   = wp_unslash( $_POST['manacore_collection_items'] ); // phpcs:ignore WordPress.Security
		$clean = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, 'manacore_collection_items' );
			return;
		}

		update_post_meta( $post_id, 'manacore_collection_items', $clean );
	}

	/**
	 * ذخیره‌ی فیلد تکرارشونده.
	 *
	 * @param int        $post_id شناسه‌ی پست.
	 * @param string     $key     کلید.
	 * @param array      $field   تعریف.
	 * @param array|null $state   اثرانگشت وضعیت فرم.
	 */
	protected function save_repeater( $post_id, $key, $field, $state = null ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			/*
			 * Repeaters submit nothing when they hold no rows, so an absent key
			 * is ambiguous. Only clear the stored rows when the form really was
			 * rendered empty; rows written after the render (auto-fetch import
			 * of cast or seasons) must survive the save.
			 */
			if ( $this->changed_since_render( $post_id, $key, $state ) ) {
				return;
			}

			delete_post_meta( $post_id, $key );
			return;
		}

		$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security
		$clean = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item  = array();
			$empty = true;
			foreach ( $field['subfields'] as $sub_key => $sub ) {
				$value = isset( $row[ $sub_key ] ) ? $row[ $sub_key ] : '';
				if ( 'image_url' === $sub['type'] || 'url' === $sub['type'] ) {
					$value = esc_url_raw( trim( (string) $value ) );
				} elseif ( 'person_id' === $sub_key ) {
					$value = Crew::valid_person_id( $value ) ?: '';
				} elseif ( 'number' === $sub['type'] ) {
					$value = '' === trim( (string) $value ) ? '' : (float) $value;
				} elseif ( 'textarea' === $sub['type'] ) {
					$value = sanitize_textarea_field( (string) $value );
				} else {
					$value = sanitize_text_field( (string) $value );
				}
				if ( '' !== $value && null !== $value ) {
					$empty = false;
				}
				$item[ $sub_key ] = $value;
			}
			if ( ! $empty ) {
				$clean[] = $item;
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, wp_slash( wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) ) );
	}

	/**
	 * پاک‌سازی مقدار بر اساس نوع فیلد.
	 *
	 * @param mixed $raw   مقدار خام.
	 * @param array $field تعریف فیلد.
	 * @return mixed
	 */
	protected function sanitize_field( $raw, $field ) {
		switch ( $field['type'] ) {
			case 'number':
				return '' === trim( (string) $raw ) ? '' : (float) $raw;
			case 'checkbox':
				return '1' === (string) $raw ? '1' : '';
			case 'url':
			case 'image_url':
				return esc_url_raw( trim( (string) $raw ) );
			case 'textarea':
			case 'gallery':
				return sanitize_textarea_field( (string) $raw );
			case 'date':
				$date = trim( (string) $raw );
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
			case 'select':
				$val = sanitize_text_field( (string) $raw );
				return isset( $field['options'][ $val ] ) ? $val : '';
			case 'post_select':
				return absint( $raw ) ?: '';
			case 'people':
				$items = Crew::sanitize( (string) $raw );
				return $items ? Crew::encode( $items ) : '';
			default:
				return sanitize_text_field( (string) $raw );
		}
	}
}
