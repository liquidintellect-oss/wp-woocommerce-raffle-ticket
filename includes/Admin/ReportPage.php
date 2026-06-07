<?php
/**
 * Admin report page — CSV export of raffle tickets.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class ReportPage
 *
 * Registers a WooCommerce sub-menu page that provides a CSV download of all
 * raffle ticket assignments, including customer name, email, product, ticket
 * number, and purchase date.
 *
 * The CSV stream is initiated from `admin_init` — before WordPress outputs any
 * HTML — so that the Content-Type and Content-Disposition headers are sent
 * cleanly.  The page renderer itself only ever outputs the HTML landing page
 * with the download button.
 */
class ReportPage {

	/**
	 * Constructor.
	 *
	 * @param TicketRepository $ticket_repo   Ticket retrieval layer.
	 * @param OrderHandler     $order_handler Order handler for retroactive assignment.
	 */
	public function __construct(
		private TicketRepository $ticket_repo,
		private OrderHandler $order_handler
	) {}

	/**
	 * Register the admin sub-menu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Raffle Ticket Report', 'wp-woocommerce-raffle-ticket' ),
			esc_html__( 'Raffle Tickets', 'wp-woocommerce-raffle-ticket' ),
			'manage_woocommerce',
			'raffle-ticket-report',
			array( $this, 'render' )
		);
	}

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
		// Only act on our own page.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'] ) ||
			'raffle-ticket-report' !== $_GET['page'] ||
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
	 * "processing" and "completed" orders, assigns tickets to any that are
	 * missing them, then redirects back to the report page with a count.
	 *
	 * @return void
	 */
	public function maybeAssignRetroactive(): void {
		// Only act on our own page with the correct action.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			! isset( $_GET['page'] ) ||
			'raffle-ticket-report' !== $_GET['page'] ||
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
			if ( ! $this->ticket_repo->hasTicketsForOrder( $order_id ) ) {
				$this->order_handler->handle( $order_id );
				++$assigned;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'raffle-ticket-report',
					'assigned' => $assigned,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the HTML landing page with the download and retroactive-assign buttons.
	 *
	 * By the time WordPress calls this callback, the admin shell HTML has
	 * already been output, so no CSV streaming or redirects happen here.
	 *
	 * @return void
	 */
	public function render(): void {
		$download_url = wp_nonce_url(
			admin_url( 'admin.php?page=raffle-ticket-report&action=download' ),
			'raffle_ticket_report_download'
		);

		$assign_url = wp_nonce_url(
			admin_url( 'admin.php?page=raffle-ticket-report&action=assign_retroactive' ),
			'raffle_ticket_assign_retroactive'
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$assigned = isset( $_GET['assigned'] ) ? (int) $_GET['assigned'] : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Raffle Ticket Report', 'wp-woocommerce-raffle-ticket' ); ?></h1>
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

			fputcsv(
				$stream,
				array(
					$row->order_id,
					$customer_name,
					$customer_email,
					$row->product_name ?? '',
					$row->ticket_number,
					$row->created_at,
				),
				',',
				'"',
				'\\'
			);
		}
	}
}
