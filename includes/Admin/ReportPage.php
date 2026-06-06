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

use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class ReportPage
 *
 * Registers a WooCommerce sub-menu page that provides a CSV download of all
 * raffle ticket assignments, including customer name, email, product, ticket
 * number, and purchase date.
 */
class ReportPage {

	/**
	 * Constructor.
	 *
	 * @param TicketRepository $ticket_repo Ticket retrieval layer.
	 */
	public function __construct( private TicketRepository $ticket_repo ) {}

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
	 * Render the report page or stream the CSV download.
	 *
	 * When the `action=download` query arg is present and the nonce is valid,
	 * this method streams the CSV file and exits.  Otherwise it renders the
	 * standard HTML admin page with the download button.
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'download' === $_GET['action'] ) {
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$nonce = isset( $_GET['_wpnonce'] )
				? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
				: '';
			if ( ! wp_verify_nonce( $nonce, 'raffle_ticket_report_download' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wp-woocommerce-raffle-ticket' ) );
			}
			$this->streamCsv();
			exit;
		}

		$download_url = wp_nonce_url(
			admin_url( 'admin.php?page=raffle-ticket-report&action=download' ),
			'raffle_ticket_report_download'
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Raffle Ticket Report', 'wp-woocommerce-raffle-ticket' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Download CSV', 'wp-woocommerce-raffle-ticket' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Send CSV headers and stream the report to the browser.
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
	 * @param string $filename The suggested filename for the download.
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
