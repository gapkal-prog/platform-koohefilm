<?php
/**
 * Admin conveniences: subscribers overview and post-list indicator.
 *
 * @package ManaCore\Subs
 */

namespace ManaCore\Subs;

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Admin {

	use Singleton;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ), 31 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// Premium indicator on the ManaCore post lists.
		add_action( 'admin_init', array( $this, 'register_columns' ) );
	}

	/* ---------------------------------------------------------------------
	 * Subscribers page
	 * ------------------------------------------------------------------ */

	/**
	 * Add the subscribers submenu.
	 */
	public function menu() {
		add_submenu_page(
			'manacore',
			__( 'مشترکان', 'manacore' ),
			__( 'مشترکان', 'manacore' ),
			'manage_options',
			'manacore-subscribers',
			array( $this, 'render' )
		);
	}

	/**
	 * Handle the revoke action from the list.
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['manacore_subs_action'], $_GET['user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action  = sanitize_key( wp_unslash( $_GET['manacore_subs_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$user_id = absint( wp_unslash( $_GET['user'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $user_id || ! wp_verify_nonce( $nonce, 'manacore_subs_' . $action . '_' . $user_id ) ) {
			return;
		}

		if ( 'revoke' === $action ) {
			Meta::instance()->revoke( $user_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'manacore-subscribers',
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Users who currently hold a manual grant.
	 *
	 * @return array
	 */
	protected function manual_subscribers() {
		$keys = manacore_subs_meta_keys();

		$query = new \WP_User_Query(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => $keys['level'],
						'compare' => 'EXISTS',
					),
				),
				'number'     => 200,
				'orderby'    => 'registered',
				'order'      => 'DESC',
			)
		);

		return (array) $query->get_results();
	}

	/**
	 * Render the subscribers overview.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$users  = $this->manual_subscribers();
		$access = Access::instance();
		$plans  = Plans::instance();
		?>
		<div class="wrap manacore-settings-wrap">
			<div class="manacore-settings-header">
				<span class="manacore-brand-mark">ManaCore</span>
				<h1><?php esc_html_e( 'مشترکان', 'manacore' ); ?></h1>
			</div>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'تغییرات ذخیره شد.', 'manacore' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'این فهرست کاربرانی را نشان می‌دهد که به‌صورت دستی یا از طریق سفارش، سطح اشتراک گرفته‌اند. اشتراک‌های فعال ووکامرس مستقیماً از خود ووکامرس خوانده می‌شوند.', 'manacore' ); ?>
			</p>

			<table class="widefat striped manacore-provider-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'کاربر', 'manacore' ); ?></th>
						<th><?php esc_html_e( 'سطح مؤثر', 'manacore' ); ?></th>
						<th><?php esc_html_e( 'انقضا', 'manacore' ); ?></th>
						<th><?php esc_html_e( 'منبع', 'manacore' ); ?></th>
						<th><?php esc_html_e( 'یادداشت', 'manacore' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'manacore' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $users ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'هنوز اشتراک دستی ثبت نشده است.', 'manacore' ); ?></td>
						</tr>
					<?php endif; ?>

					<?php
					$keys = manacore_subs_meta_keys();

					foreach ( $users as $user ) :
						$level   = $access->user_level( $user->ID );
						$expires = $access->expiry( $user->ID );
						$source  = $access->source( $user->ID );
						$note    = (string) get_user_meta( $user->ID, $keys['note'], true );

						$revoke = wp_nonce_url(
							add_query_arg(
								array(
									'page'                 => 'manacore-subscribers',
									'manacore_subs_action' => 'revoke',
									'user'                 => $user->ID,
								),
								admin_url( 'admin.php' )
							),
							'manacore_subs_revoke_' . $user->ID
						);
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
									<strong><?php echo esc_html( $user->display_name ); ?></strong>
								</a>
								<br /><small><?php echo esc_html( $user->user_email ); ?></small>
							</td>
							<td>
								<?php if ( $level ) : ?>
									<span class="manacore-pill is-on"><?php echo esc_html( $plans->label( $level ) ); ?></span>
								<?php else : ?>
									<span class="manacore-pill is-off"><?php esc_html_e( 'منقضی', 'manacore' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								echo $expires
									? esc_html( date_i18n( 'Y/m/d', $expires ) )
									: esc_html__( 'نامحدود', 'manacore' );
								?>
							</td>
							<td><?php echo esc_html( $this->source_label( $source ) ); ?></td>
							<td><?php echo esc_html( $note ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $revoke ); ?>">
									<?php esc_html_e( 'لغو', 'manacore' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Human label for a source slug.
	 *
	 * @param string $source Source slug.
	 * @return string
	 */
	protected function source_label( $source ) {
		switch ( $source ) {
			case 'manual':
				return __( 'دستی', 'manacore' );
			case 'woocommerce':
				return __( 'ووکامرس', 'manacore' );
			case 'filter':
				return __( 'افزونه‌ی دیگر', 'manacore' );
			default:
				return '—';
		}
	}

	/* ---------------------------------------------------------------------
	 * Post list column
	 * ------------------------------------------------------------------ */

	/**
	 * Attach the premium column to every ManaCore post type.
	 */
	public function register_columns() {
		if ( ! function_exists( 'manacore_post_types' ) ) {
			return;
		}

		foreach ( array_keys( manacore_post_types() ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'post_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'post_column_value' ), 10, 2 );
		}
	}

	/**
	 * Add the access column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function post_column( $columns ) {
		$columns['manacore_access'] = __( 'دسترسی', 'manacore' );

		return $columns;
	}

	/**
	 * Render the access column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public function post_column_value( $column, $post_id ) {
		if ( 'manacore_access' !== $column ) {
			return;
		}

		$required = Access::instance()->required_level( $post_id );

		if ( '' === $required ) {
			echo '<span class="manacore-pill is-off">' . esc_html__( 'آزاد', 'manacore' ) . '</span>';
			return;
		}

		echo '<span class="manacore-pill is-on">' . esc_html( Plans::instance()->label( $required ) ) . '</span>';
	}
}
