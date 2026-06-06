<?php
/**
 * Main plugin class.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Admin\ReportPage;
use WpWoocommerceRaffleTicket\Order\OrderDisplay;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Product\ProductMetaBox;
use WpWoocommerceRaffleTicket\Ticket\SequenceRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketNumberGenerator;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class Plugin
 *
 * Wires all components together and registers WordPress / WooCommerce hooks.
 */
class Plugin {

	/**
	 * Register all plugin hooks and filters.
	 *
	 * @return void
	 */
	public function register(): void {
		$ticket_repo   = new TicketRepository();
		$seq_repo      = new SequenceRepository();
		$generator     = new TicketNumberGenerator();
		$order_handler = new OrderHandler( $ticket_repo, $seq_repo, $generator );
		$order_display = new OrderDisplay( $ticket_repo );
		$meta_box      = new ProductMetaBox();
		$report_page   = new ReportPage( $ticket_repo );

		// Assign tickets when payment is received (order → processing).
		add_action( 'woocommerce_order_status_processing', array( $order_handler, 'handle' ), 10, 1 );

		// Block add-to-cart when the raffle is sold out.
		add_filter( 'woocommerce_add_to_cart_validation', array( $order_handler, 'validateCartAdd' ), 10, 3 );

		// Product edit page — raffle settings meta box.
		add_action( 'add_meta_boxes', array( $meta_box, 'register' ) );
		add_action( 'woocommerce_process_product_meta', array( $meta_box, 'save' ) );

		// Order details — customer-facing and admin views.
		add_action( 'woocommerce_order_details_after_order_table', array( $order_display, 'renderCustomer' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $order_display, 'renderAdmin' ) );

		// Admin report / CSV export.
		add_action( 'admin_menu', array( $report_page, 'register' ) );

		// Stream CSV during admin_init — before WordPress outputs any HTML —
		// so that Content-Type / Content-Disposition headers are sent cleanly.
		add_action( 'admin_init', array( $report_page, 'maybeStreamCsv' ) );
	}
}
