<?php
/**
 * Front-end account UI: status card, paywall notice, shortcodes and blocks.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Account.
 */
class Account {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

		add_shortcode( 'manacore_subscription', array( $this, 'shortcode_status' ) );
		add_shortcode( 'manacore_plans', array( $this, 'shortcode_plans' ) );
		add_shortcode( 'manacore_members_only', array( $this, 'shortcode_members_only' ) );

		// Optionally hide premium posts' content body as well as the links.
		add_filter( 'the_content', array( $this, 'maybe_gate_content' ), 20 );

		// Expose the level as a body class so the theme can style around it.
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueue the small front-end stylesheet.
	 */
	public function assets() {
		wp_enqueue_style(
			'manacore-subs',
			MANACORE_SUBS_URL . 'assets/front.css',
			array(),
			MANACORE_SUBS_VERSION
		);
	}

	/* ---------------------------------------------------------------------
	 * Status card
	 * ------------------------------------------------------------------ */

	/**
	 * Render the current user's subscription status card.
	 *
	 * @param array $args Optional args (compact).
	 * @return string
	 */
	public function status_card( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'compact' => false,
			)
		);

		if ( ! is_user_logged_in() ) {
			return $this->notice_markup(
				__( 'برای مشاهده وضعیت اشتراک وارد حساب کاربری شوید.', 'manacore' ),
				wp_login_url( home_url( add_query_arg( array() ) ) ),
				__( 'ورود به حساب', 'manacore' )
			);
		}

		$access  = Access::instance();
		$plans   = Plans::instance();
		$level   = $access->user_level();
		$expires = $access->expiry();
		$source  = $access->source();

		ob_start();
		?>
		<div class="manacore-sub-card<?php echo $args['compact'] ? ' is-compact' : ''; ?>">
			<div class="manacore-sub-card-head">
				<span class="manacore-sub-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22">
						<path fill="currentColor" d="M12 2l2.9 6.1 6.6.9-4.8 4.7 1.2 6.6L12 17.2 6.1 20.3l1.2-6.6L2.5 9l6.6-.9L12 2z"/>
					</svg>
				</span>
				<div>
					<h3 class="manacore-sub-title"><?php esc_html_e( 'وضعیت اشتراک', 'manacore' ); ?></h3>
					<?php if ( $level ) : ?>
						<p class="manacore-sub-level is-active">
							<?php echo esc_html( $plans->label( $level ) ); ?>
						</p>
					<?php else : ?>
						<p class="manacore-sub-level is-inactive">
							<?php esc_html_e( 'بدون اشتراک فعال', 'manacore' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $level ) : ?>
				<ul class="manacore-sub-facts">
					<?php if ( $expires ) : ?>
						<li>
							<b><?php esc_html_e( 'اعتبار تا', 'manacore' ); ?></b>
							<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), $expires ) ); ?></span>
						</li>
						<li>
							<b><?php esc_html_e( 'روزهای باقی‌مانده', 'manacore' ); ?></b>
							<span><?php echo esc_html( number_format_i18n( $this->days_left( $expires ) ) ); ?></span>
						</li>
					<?php else : ?>
						<li>
							<b><?php esc_html_e( 'اعتبار', 'manacore' ); ?></b>
							<span><?php esc_html_e( 'نامحدود', 'manacore' ); ?></span>
						</li>
					<?php endif; ?>

					<?php if ( 'woocommerce' === $source ) : ?>
						<li>
							<b><?php esc_html_e( 'منبع', 'manacore' ); ?></b>
							<span><?php esc_html_e( 'اشتراک ووکامرس', 'manacore' ); ?></span>
						</li>
					<?php endif; ?>
				</ul>

				<?php if ( manacore_subs_wc_active() && function_exists( 'wc_get_account_endpoint_url' ) ) : ?>
					<a class="manacore-btn" href="<?php echo esc_url( wc_get_account_endpoint_url( 'subscriptions' ) ); ?>">
						<?php esc_html_e( 'مدیریت اشتراک', 'manacore' ); ?>
					</a>
				<?php endif; ?>
			<?php else : ?>
				<p class="manacore-sub-hint">
					<?php esc_html_e( 'با تهیه اشتراک به همه‌ی لینک‌های ویژه و کیفیت‌های بالا دسترسی پیدا می‌کنید.', 'manacore' ); ?>
				</p>
				<a class="manacore-btn is-primary" href="<?php echo esc_url( manacore_subs_url() ); ?>">
					<?php esc_html_e( 'تهیه اشتراک', 'manacore' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Whole days left until a timestamp.
	 *
	 * @param int $timestamp Expiry timestamp.
	 * @return int
	 */
	protected function days_left( $timestamp ) {
		$diff = (int) $timestamp - time();

		return $diff > 0 ? (int) ceil( $diff / DAY_IN_SECONDS ) : 0;
	}

	/**
	 * Generic locked notice markup (mirrors the core plugin's styling).
	 *
	 * @param string $message Message.
	 * @param string $url     Button URL.
	 * @param string $label   Button label.
	 * @return string
	 */
	public function notice_markup( $message, $url, $label ) {
		if ( class_exists( '\ManaCore\Core\Templates' ) && method_exists( '\ManaCore\Core\Templates', 'locked_notice' ) ) {
			return (string) \ManaCore\Core\Templates::locked_notice( $message, $url, $label );
		}

		return sprintf(
			'<div class="manacore-locked"><p>%1$s</p><a class="manacore-btn is-primary" href="%2$s">%3$s</a></div>',
			esc_html( $message ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/* ---------------------------------------------------------------------
	 * Plans table
	 * ------------------------------------------------------------------ */

	/**
	 * Render the configured levels as a comparison list.
	 *
	 * @return string
	 */
	public function plans_markup() {
		$plans   = Plans::instance();
		$levels  = $plans->levels();
		$current = Access::instance()->user_level();

		if ( ! $levels ) {
			return '';
		}

		ob_start();
		?>
		<div class="manacore-plans">
			<?php foreach ( $levels as $slug => $level ) : ?>
				<?php
				$is_current = ( $slug === $current );
				$products   = $plans->product_map();
				$product_id = array_search( $slug, $products, true );
				$url        = $product_id ? get_permalink( (int) $product_id ) : manacore_subs_url();					?>
					<div class="manacore-plan<?php echo $is_current ? ' is-current' : ( ! empty( $level['featured'] ) ? ' is-featured' : '' ); ?>">
					<h4 class="manacore-plan-title"><?php echo esc_html( $level['label'] ); ?></h4>

					<?php if ( $product_id && function_exists( 'wc_get_product' ) ) : ?>
						<?php $product = wc_get_product( (int) $product_id ); ?>
						<?php if ( $product ) : ?>
							<div class="manacore-plan-price">
								<?php echo wp_kses_post( $product->get_price_html() ); ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( $is_current ) : ?>
						<span class="manacore-plan-badge"><?php esc_html_e( 'اشتراک فعلی شما', 'manacore' ); ?></span>
					<?php else : ?>
						<a class="manacore-btn is-primary" href="<?php echo esc_url( $url ? $url : manacore_subs_url() ); ?>">
							<?php esc_html_e( 'انتخاب', 'manacore' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Content gating
	 * ------------------------------------------------------------------ */

	/**
	 * Optionally replace the post body of premium content with a paywall.
	 *
	 * Disabled by default – most sites only gate the download links.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function maybe_gate_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( ! manacore_subs_get_option( 'gate_content', 0 ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( ! $post_id || ! function_exists( 'manacore_post_types' ) ) {
			return $content;
		}

		if ( ! array_key_exists( (string) get_post_type( $post_id ), manacore_post_types() ) ) {
			return $content;
		}

		if ( manacore_user_can_access( $post_id ) ) {
			return $content;
		}

		$teaser_words = absint( manacore_subs_get_option( 'teaser_words', 60 ) );
		$teaser       = $teaser_words ? wpautop( wp_trim_words( wp_strip_all_tags( $content ), $teaser_words ) ) : '';

		return $teaser . $this->notice_markup(
			__( 'ادامه‌ی این مطلب مخصوص کاربران دارای اشتراک فعال است.', 'manacore' ),
			manacore_subs_url(),
			__( 'تهیه اشتراک', 'manacore' )
		);
	}

	/**
	 * Add the subscription level to the body classes.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$level = Access::instance()->user_level();

		$classes[] = $level ? 'manacore-subscriber' : 'manacore-guest';

		if ( $level ) {
			$classes[] = 'manacore-level-' . sanitize_html_class( $level );
		}

		return $classes;
	}

	/* ---------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------ */

	/**
	 * [manacore_subscription] – status card.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode_status( $atts ) {
		$atts = shortcode_atts( array( 'compact' => '0' ), $atts, 'manacore_subscription' );

		return $this->status_card( array( 'compact' => (bool) intval( $atts['compact'] ) ) );
	}

	/**
	 * [manacore_plans] – plans list.
	 *
	 * @return string
	 */
	public function shortcode_plans() {
		return $this->plans_markup();
	}

	/**
	 * [manacore_members_only level="vip"]…[/manacore_members_only]
	 *
	 * @param array  $atts    Attributes.
	 * @param string $content Enclosed content.
	 * @return string
	 */
	public function shortcode_members_only( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'level'   => '',
				'message' => '',
			),
			$atts,
			'manacore_members_only'
		);

		$level = $atts['level'] ? sanitize_key( $atts['level'] ) : Plans::instance()->lowest();

		if ( manacore_subs_user_has_level( $level ) ) {
			return do_shortcode( (string) $content );
		}

		$message = $atts['message']
			? sanitize_text_field( $atts['message'] )
			: __( 'این بخش مخصوص کاربران دارای اشتراک فعال است.', 'manacore' );

		return $this->notice_markup( $message, manacore_subs_url(), __( 'تهیه اشتراک', 'manacore' ) );
	}

	/* ---------------------------------------------------------------------
	 * Blocks
	 * ------------------------------------------------------------------ */

	/**
	 * Register the dynamic blocks used inside FSE templates.
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'manacore/subscription-status',
			array(
				'api_version'     => 3,
				'title'           => __( 'وضعیت اشتراک', 'manacore' ),
				'category'        => 'manacore',
				'icon'            => 'star-filled',
				'attributes'      => array(
					'compact' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'render_callback' => array( $this, 'render_status_block' ),
			)
		);

		register_block_type(
			'manacore/subscription-plans',
			array(
				'api_version'     => 3,
				'title'           => __( 'پلن‌های اشتراک', 'manacore' ),
				'category'        => 'manacore',
				'icon'            => 'money-alt',
				'render_callback' => array( $this, 'render_plans_block' ),
			)
		);
	}

	/**
	 * Render callback for the status block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_status_block( $attributes ) {
		$compact = ! empty( $attributes['compact'] );

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'manacore-block manacore-sub-block' ) ),
			$this->status_card( array( 'compact' => $compact ) )
		);
	}

	/**
	 * Render callback for the plans block.
	 *
	 * @return string
	 */
	public function render_plans_block() {
		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'manacore-block manacore-plans-block' ) ),
			$this->plans_markup()
		);
	}
}
