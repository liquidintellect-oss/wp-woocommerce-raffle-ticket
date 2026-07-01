<?php
/**
 * Admin report page — CSV export, roll management, and plugin settings.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Admin\PluginSettings;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class ReportPage
 *
 * Registers a WooCommerce sub-menu page with three tabs:
 *
 *   - Report   — CSV download and retroactive ticket assignment.
 *   - Rolls    — physical roll inventory (add / delete rolls per product).
 *   - Settings — plugin-wide options (ticket label).
 *
 * Tab-specific `admin_init` handlers (CSV stream, roll mutations, retroactive
 * assignment) fire before WordPress emits any HTML so that HTTP headers and
 * redirects work cleanly.
 */
class ReportPage {

	/** Admin page slug — shared across all tabs. */
	const PAGE_SLUG = 'raffle-ticket-report';

	/** Nonce action for adding a roll. */
	const NONCE_ROLL_ADD = 'raffle_ticket_roll_add';

	/** Nonce action for deleting a roll. */
	const NONCE_ROLL_DELETE = 'raffle_ticket_roll_delete';

	/**
	 * Constructor.
	 *
	 * @param TicketRepository $ticket_repo   Ticket retrieval layer.
	 * @param OrderHandler     $order_handler Order handler for retroactive assignment.
	 * @param string           $label         Configurable display label (e.g. "Raffle Tickets").
	 * @param RollRepository   $roll_repo     Roll repository for roll management and overflow detection.
	 */
	public function __construct(
		private TicketRepository $ticket_repo,
		private OrderHandler $order_handler,
		private string $label,
		private RollRepository $roll_repo
	) {}

	// ── Admin menu ────────────────────────────────────────────────────────────

	/**
	 * Register the admin sub-menu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register(): void {
		/* translators: %s: configurable ticket label, e.g. "Raffle Tickets" */
		$page_title = esc_html( sprintf( __( '%s Report', 'wp-woocommerce-raffle-ticket' ), $this->label ) );

		add_submenu_page(
			'woocommerce',
			$page_title,
			esc_html( $this->label ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	// ── admin_init handlers ───────────────────────────────────────────────────

	/**
	 * Intercept download requests during admin_init before any HTML is output.
	 *
	 * Called on the `admin_init` hook.  When the current request targets our
	 * report page with `action=download` and a valid nonce, this method streams
	 * the CSV and exits, preventing WordPress from rendering the admin shell.
	 *
	 * @return void
	 */
	public function maybeStreamCsv(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'] ) ||
			self::PAGE_SLUG !== $_GET['page'] ||
			! isset( $_GET['action'] ) ||
			'download' !== $_GET['action']
		) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'raffle_ticket_report_download' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-woocommerce-raffle-ticket' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this report.', 'wp-woocommerce-raffle-ticket' ) );
		}

		$this->streamCsv();
		exit;
	}

	/**
	 * Intercept retroactive-assignment requests during admin_init.
	 *
	 * When the current request targets our report page with
	 * `action=assign_retroactive` and a valid nonce, this method queries all
	 * processing and completed orders, assigns tickets to any that are missing
	 * them, then redirects back to the Report tab with an assigned count.
	 *
	 * @return void
	 */
	public function maybeAssignRetroactive(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'] ) ||
			self::PAGE_SLUG !== $_GET['page'] ||
			! isset( $_GET['action'] ) ||
			'assign_retroactive' !== $_GET['action']
		) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'raffle_ticket_assign_retroactive' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-woocommerce-raffle-ticket' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-woocommerce-raffle-ticket' ) );
		}

		$orders   = wc_get_orders(
			array(
				'status' => array( 'processing', 'completed' ),
				'limit'  => -1,
			)
		);
		$assigned = 0;

		foreach ( $orders as $order ) {
			$order_id = (int) $order->get_id();
			if ( ! $this->ticket_repo->hasAssignedTicketsForOrder( $order_id ) ) {
				$this->order_handler->handle( $order_id );
				++$assigned;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => self::PAGE_SLUG,
					'assigned' => $assigned,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle an "Add Roll" form submission during admin_init.
	 *
	 * Processes POST requests with action=add_roll targeting our page, creates
	 * the roll record, then redirects back to the Rolls tab.
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

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ROLL_ADD ) ) {
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
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => 'rolls',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle a "Delete Roll" request during admin_init.
	 *
	 * Processes GET requests with action=delete_roll targeting our page, deletes
	 * the roll record, then redirects back to the Rolls tab.
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

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ROLL_DELETE ) ) {
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
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => 'rolls',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ── Page rendering ────────────────────────────────────────────────────────

	/**
	 * Render the full page: heading, global notices, tab navigation, and active tab content.
	 *
	 * By the time WordPress calls this callback, the admin shell HTML has already
	 * been output, so no CSV streaming or redirects happen here.
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'report';
		$assigned         = isset( $_GET['assigned'] ) ? (int) $_GET['assigned'] : null;
		$settings_updated = isset( $_GET['settings-updated'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$overflow_product_ids = $this->ticket_repo->findProductsWithUnassignedTickets();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->label ); ?></h1>

			<?php if ( ! empty( $overflow_product_ids ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Attention: tickets pending roll assignment', 'wp-woocommerce-raffle-ticket' ); ?></strong>
					</p>
					<p>
						<?php
						esc_html_e(
							'The following products have orders that could not be assigned a physical ticket number because no rolls with remaining capacity were available. Add rolls on the Rolls tab and then click "Assign Missing Tickets" to resolve.',
							'wp-woocommerce-raffle-ticket'
						);
						?>
					</p>
					<ul>
					<?php foreach ( $overflow_product_ids as $product_id ) : ?>
						<li>
							<?php
							$post = get_post( $product_id );
							echo esc_html( $post ? $post->post_title : '#' . $product_id );
							?>
						</li>
					<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( null !== $assigned ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of orders processed for retroactive ticket assignment */
							esc_html__( 'Retroactive ticket assignment complete. %d order(s) were processed.', 'wp-woocommerce-raffle-ticket' ),
							absint( $assigned )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $settings_updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wp-woocommerce-raffle-ticket' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'report'   => __( 'Report', 'wp-woocommerce-raffle-ticket' ),
					'rolls'    => __( 'Rolls', 'wp-woocommerce-raffle-ticket' ),
					'settings' => __( 'Settings', 'wp-woocommerce-raffle-ticket' ),
				);
				foreach ( $tabs as $tab_key => $tab_label ) :
					$tab_url   = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $tab_key,
						),
						admin_url( 'admin.php' )
					);
					$is_active = $current_tab === $tab_key;
					?>
					<a
						href="<?php echo esc_url( $tab_url ); ?>"
						class="nav-tab<?php echo $is_active ? ' nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'rolls' === $current_tab ) {
				$this->renderRollsTab();
			} elseif ( 'settings' === $current_tab ) {
				$this->renderSettingsTab();
			} else {
				$this->renderReportTab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the Report tab content: CSV download and retroactive assignment.
	 *
	 * @return void
	 */
	public function renderReportTab(): void {
		$download_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=download' ),
			'raffle_ticket_report_download'
		);

		$assign_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&action=assign_retroactive' ),
			'raffle_ticket_assign_retroactive'
		);
		?>
		<div class="raffle-ticket-tab-content">
			<p>
				<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Download CSV', 'wp-woocommerce-raffle-ticket' ); ?>
				</a>
				&nbsp;
				<a href="<?php echo esc_url( $assign_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Assign Missing Tickets', 'wp-woocommerce-raffle-ticket' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Rolls tab content: existing rolls table and add-roll form.
	 *
	 * @return void
	 */
	public function renderRollsTab(): void {
		$rolls = $this->roll_repo->findAll();
		?>
		<div class="raffle-ticket-tab-content">
			<?php $this->renderRollsTable( $rolls ); ?>

			<h2><?php esc_html_e( 'Add Roll', 'wp-woocommerce-raffle-ticket' ); ?></h2>
			<?php $this->renderAddForm(); ?>
		</div>
		<?php
	}

	/**
	 * Render the Settings tab content: plugin-wide options form.
	 *
	 * @return void
	 */
	public function renderSettingsTab(): void {
		?>
		<div class="raffle-ticket-tab-content">
			<form method="post" action="options.php">
				<?php settings_fields( PluginSettings::OPTIONS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( PluginSettings::OPTION_KEY ); ?>">
								<?php esc_html_e( 'Ticket Label', 'wp-woocommerce-raffle-ticket' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="<?php echo esc_attr( PluginSettings::OPTION_KEY ); ?>"
								name="<?php echo esc_attr( PluginSettings::OPTION_KEY ); ?>"
								value="<?php echo esc_attr( PluginSettings::getLabel() ); ?>"
								class="regular-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Label used throughout the store wherever tickets are displayed (e.g. "Raffle Tickets", "Lottery Tickets", "Event Tickets").', 'wp-woocommerce-raffle-ticket' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Save Settings', 'wp-woocommerce-raffle-ticket' ) ); ?>
			</form>
		</div>
		<?php
	}

	// ── Roll sub-views ────────────────────────────────────────────────────────

	/**
	 * Render the table of existing rolls.
	 *
	 * @param object[] $rolls Raw roll rows with product_name property from RollRepository::findAll().
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
					self::NONCE_ROLL_DELETE
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
			<?php wp_nonce_field( self::NONCE_ROLL_ADD ); ?>
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

	// ── CSV ───────────────────────────────────────────────────────────────────

	/**
	 * Send CSV headers and stream the report to the browser.
	 *
	 * Only called from maybeStreamCsv() during admin_init, before any page
	 * output, so headers can be sent cleanly.
	 *
	 * @return void
	 */
	public function streamCsv(): void {
		$filename = 'raffle-tickets-' . gmdate( 'Y-m-d' ) . '.csv';
		$this->sendCsvHeaders( $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$stream = fopen( 'php://output', 'w' );
		$this->writeCsv( $stream );
	}

	/**
	 * Send HTTP headers for a CSV file download.
	 *
	 * @param string $filename The suggested download filename.
	 *
	 * @return void
	 */
	public function sendCsvHeaders( string $filename ): void {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}

	/**
	 * Write CSV rows to the given stream resource.
	 *
	 * Accepts any writable stream (e.g. php://output in production, a temp
	 * stream in tests) making the output straightforward to unit-test.
	 *
	 * @param resource $stream A writable stream resource.
	 *
	 * @return void
	 */
	public function writeCsv( $stream ): void {
		// Header row.
		fputcsv(
			$stream,
			array(
				__( 'Order ID', 'wp-woocommerce-raffle-ticket' ),
				__( 'Customer Name', 'wp-woocommerce-raffle-ticket' ),
				__( 'Customer Email', 'wp-woocommerce-raffle-ticket' ),
				__( 'Product Name', 'wp-woocommerce-raffle-ticket' ),
				__( 'Ticket Number', 'wp-woocommerce-raffle-ticket' ),
				__( 'Roll', 'wp-woocommerce-raffle-ticket' ),
				__( 'Purchase Date', 'wp-woocommerce-raffle-ticket' ),
			),
			',',
			'"',
			'\\'
		);

		foreach ( $this->ticket_repo->findAll() as $row ) {
			$order          = wc_get_order( (int) $row->order_id );
			$customer_name  = $order ? $order->get_formatted_billing_full_name() : '';
			$customer_email = $order ? $order->get_billing_email() : '';

			// Show a human-readable label for pending (unassigned) tickets.
			$ticket_number = null === $row->roll_id
				? __( 'Pending', 'wp-woocommerce-raffle-ticket' )
				: $row->ticket_number;

			// Build a human-readable roll identifier for assigned tickets.
			$roll_label = $this->formatCsvRollLabel( $row );

			fputcsv(
				$stream,
				array(
					$row->order_id,
					$customer_name,
					$customer_email,
					$row->product_name ?? '',
					$ticket_number,
					$roll_label,
					$row->created_at,
				),
				',',
				'"',
				'\\'
			);
		}
	}

	/**
	 * Build a concise roll identifier string for a CSV data row.
	 *
	 * Returns an empty string for pending tickets (null roll_id).
	 * For assigned tickets returns "{label} ({start}–{last})" when a label is
	 * set, otherwise "Roll #{id} ({start}–{last})".
	 *
	 * @param object $row A ticket row object from TicketRepository::findAll().
	 *
	 * @return string
	 */
	private function formatCsvRollLabel( object $row ): string {
		if ( ! isset( $row->roll_id ) || null === $row->roll_id ) {
			return '';
		}

		$range = ( isset( $row->roll_start, $row->roll_last ) )
			? sprintf( '%s-%s', $row->roll_start, $row->roll_last )
			: '';

		$name = ( isset( $row->roll_label ) && '' !== (string) $row->roll_label )
			? (string) $row->roll_label
			/* translators: %d: roll database ID */
			: sprintf( __( 'Roll #%d', 'wp-woocommerce-raffle-ticket' ), (int) $row->roll_id );

		return '' !== $range
			? sprintf( '%s (%s)', $name, $range )
			: $name;
	}
}
