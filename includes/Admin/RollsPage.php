<?php
/**
 * Admin page — manage physical ticket rolls.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Ticket\RollRepository;

/**
 * Class RollsPage
 *
 * Registers a WooCommerce sub-menu page that allows store admins to manage the
 * physical rolls of paper tickets assigned to each raffle product.
 *
 * Each roll record stores the starting printed number, total ticket count, and
 * an optional human-readable label.  Rolls are consumed in sort_order / id
 * order — the first roll's tickets are issued before the next roll begins.
 */
class RollsPage {

	/** Admin page slug. */
	const PAGE_SLUG = 'raffle-ticket-rolls';

	/** Nonce action for adding a roll. */
	const NONCE_ADD = 'raffle_ticket_roll_add';

	/** Nonce action for deleting a roll. */
	const NONCE_DELETE = 'raffle_ticket_roll_delete';

	/**
	 * Constructor.
	 *
	 * @param RollRepository $roll_repo Roll persistence layer.
	 * @param string         $label     Configurable display label (e.g. "Raffle Tickets").
	 */
	public function __construct(
		private RollRepository $roll_repo,
		private string $label
	) {}

	/**
	 * Register the admin sub-menu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register(): void {
		/* translators: %s: configurable ticket label, e.g. "Raffle Tickets" */
		$page_title = esc_html( sprintf( __( '%s Rolls', 'wp-woocommerce-raffle-ticket' ), $this->label ) );

		add_submenu_page(
			'woocommerce',
			$page_title,
			esc_html( $page_title ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Handle an "Add Roll" form submission during admin_init.
	 *
	 * Processes POST requests with action=add_roll targeting our page, then
	 * redirects back to the rolls page.
	 *
	 * @return void
	 */
	public function maybeAddRoll(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_POST['action'] ) ||
			'add_roll' !== $_POST['action'] ||
			! isset( $_POST['raffle_rolls_page'] ) ||
			self::PAGE_SLUG !== $_POST['raffle_rolls_page']
		) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ADD ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-woocommerce-raffle-ticket' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-woocommerce-raffle-ticket' ) );
		}

		$product_id   = isset( $_POST['roll_product_id'] )
			? absint( wp_unslash( $_POST['roll_product_id'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 0;
		$label        = isset( $_POST['roll_label'] )
			? sanitize_text_field( wp_unslash( $_POST['roll_label'] ) )
			: '';
		$start_number = isset( $_POST['roll_start_number'] )
			? absint( wp_unslash( $_POST['roll_start_number'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 1;
		$ticket_count = isset( $_POST['roll_ticket_count'] )
			? absint( wp_unslash( $_POST['roll_ticket_count'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 0;
		$sort_order   = isset( $_POST['roll_sort_order'] )
			? absint( wp_unslash( $_POST['roll_sort_order'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 0;

		if ( $product_id > 0 && $ticket_count > 0 ) {
			$this->roll_repo->create( $product_id, $label, $start_number, $ticket_count, $sort_order );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SLUG ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle a "Delete Roll" request during admin_init.
	 *
	 * Processes GET requests with action=delete_roll targeting our page, then
	 * redirects back to the rolls page.
	 *
	 * @return void
	 */
	public function maybeDeleteRoll(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'] ) ||
			self::PAGE_SLUG !== $_GET['page'] ||
			! isset( $_GET['action'] ) ||
			'delete_roll' !== $_GET['action']
		) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_DELETE ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-woocommerce-raffle-ticket' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-woocommerce-raffle-ticket' ) );
		}

		$roll_id = isset( $_GET['roll_id'] )
			? absint( wp_unslash( $_GET['roll_id'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: 0;

		if ( $roll_id > 0 ) {
			$this->roll_repo->delete( $roll_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SLUG ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the rolls management page.
	 *
	 * Displays all existing rolls grouped by product and an "Add Roll" form.
	 *
	 * @return void
	 */
	public function render(): void {
		$rolls = $this->roll_repo->findAll();
		?>
		<div class="wrap">
			<h1>
			<?php
			/* translators: %s: configurable ticket label, e.g. "Raffle Tickets" */
			echo esc_html( sprintf( __( '%s Rolls', 'wp-woocommerce-raffle-ticket' ), $this->label ) );
			?>
			</h1>

			<?php $this->renderRollsTable( $rolls ); ?>

			<hr />

			<h2><?php esc_html_e( 'Add Roll', 'wp-woocommerce-raffle-ticket' ); ?></h2>
			<?php $this->renderAddForm(); ?>
		</div>
		<?php
	}

	/**
	 * Render the table of existing rolls.
	 *
	 * @param object[] $rolls Raw roll rows with product_name property.
	 *
	 * @return void
	 */
	public function renderRollsTable( array $rolls ): void {
		if ( empty( $rolls ) ) {
			echo '<p>' . esc_html__( 'No rolls have been added yet.', 'wp-woocommerce-raffle-ticket' ) . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Label', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Start #', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Count', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Last #', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Assigned', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Remaining', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Order', 'wp-woocommerce-raffle-ticket' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-woocommerce-raffle-ticket' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rolls as $row ) : ?>
				<?php
				$last_number = (int) $row->start_number + (int) $row->ticket_count - 1;
				$remaining   = max( 0, (int) $row->ticket_count - (int) $row->current_offset );
				$delete_url  = wp_nonce_url(
					add_query_arg(
						array(
							'page'    => self::PAGE_SLUG,
							'action'  => 'delete_roll',
							'roll_id' => (int) $row->id,
						),
						admin_url( 'admin.php' )
					),
					self::NONCE_DELETE
				);
				?>
				<tr>
					<td><?php echo esc_html( $row->product_name ?? '' ); ?></td>
					<td><?php echo esc_html( $row->label ?? '' ); ?></td>
					<td><?php echo esc_html( (string) $row->start_number ); ?></td>
					<td><?php echo esc_html( (string) $row->ticket_count ); ?></td>
					<td><?php echo esc_html( (string) $last_number ); ?></td>
					<td><?php echo esc_html( (string) $row->current_offset ); ?></td>
					<td><?php echo esc_html( (string) $remaining ); ?></td>
					<td><?php echo esc_html( (string) $row->sort_order ); ?></td>
					<td>
						<a
							href="<?php echo esc_url( $delete_url ); ?>"
							class="button button-small"
							onclick="return confirm('<?php echo esc_js( __( 'Delete this roll?', 'wp-woocommerce-raffle-ticket' ) ); ?>');"
						>
							<?php esc_html_e( 'Delete', 'wp-woocommerce-raffle-ticket' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the "Add Roll" form.
	 *
	 * @return void
	 */
	public function renderAddForm(): void {
		$raffle_products = $this->getRaffleProducts();
		?>
		<form method="post" action="">
			<?php wp_nonce_field( self::NONCE_ADD ); ?>
			<input type="hidden" name="action" value="add_roll" />
			<input type="hidden" name="raffle_rolls_page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="roll_product_id">
							<?php esc_html_e( 'Product', 'wp-woocommerce-raffle-ticket' ); ?>
						</label>
					</th>
					<td>
						<select id="roll_product_id" name="roll_product_id" required>
							<option value=""><?php esc_html_e( '— Select a product —', 'wp-woocommerce-raffle-ticket' ); ?></option>
							<?php foreach ( $raffle_products as $product ) : ?>
								<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="roll_label">
							<?php esc_html_e( 'Label', 'wp-woocommerce-raffle-ticket' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="roll_label"
							name="roll_label"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. Roll A', 'wp-woocommerce-raffle-ticket' ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="roll_start_number">
							<?php esc_html_e( 'Start Number', 'wp-woocommerce-raffle-ticket' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="roll_start_number"
							name="roll_start_number"
							value="1"
							min="1"
							required
						/>
						<p class="description">
							<?php esc_html_e( 'First printed number on the physical roll.', 'wp-woocommerce-raffle-ticket' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="roll_ticket_count">
							<?php esc_html_e( 'Ticket Count', 'wp-woocommerce-raffle-ticket' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="roll_ticket_count"
							name="roll_ticket_count"
							value=""
							min="1"
							required
						/>
						<p class="description">
							<?php esc_html_e( 'Total number of tickets on this roll.', 'wp-woocommerce-raffle-ticket' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="roll_sort_order">
							<?php esc_html_e( 'Sort Order', 'wp-woocommerce-raffle-ticket' ); ?>
						</label>
					</th>
					<td>
						<input
							type="number"
							id="roll_sort_order"
							name="roll_sort_order"
							value="0"
							min="0"
						/>
						<p class="description">
							<?php esc_html_e( 'Lower numbers are consumed first. Rolls with the same sort order are consumed in creation order.', 'wp-woocommerce-raffle-ticket' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( esc_html__( 'Add Roll', 'wp-woocommerce-raffle-ticket' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Return all published products that have raffle tickets enabled.
	 *
	 * @return \WC_Product[]
	 */
	private function getRaffleProducts(): array {
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		return array_filter(
			$products,
			static function ( $product ) {
				return '1' === get_post_meta( $product->get_id(), '_raffle_ticket_enabled', true );
			}
		);
	}
}
