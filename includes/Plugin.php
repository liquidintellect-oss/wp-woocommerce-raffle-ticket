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

use WpWoocommerceRaffleTicket\Admin\PluginSettings;
use WpWoocommerceRaffleTicket\Admin\ReportPage;
use WpWoocommerceRaffleTicket\Order\OrderDisplay;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Product\ProductMetaBox;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;
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
		$ticket_repo     = new TicketRepository();
		$roll_repo       = new RollRepository();
		$generator       = new TicketNumberGenerator();
		$label           = PluginSettings::getLabel();
		$order_handler   = new OrderHandler( $ticket_repo, $roll_repo, $generator );
		$order_display   = new OrderDisplay( $ticket_repo, $label );
		$meta_box        = new ProductMetaBox( $label );
		$report_page     = new ReportPage( $ticket_repo, $order_handler, $label, $roll_repo );
		$plugin_settings = new PluginSettings();

		// Assign tickets when payment is received.
		// 'processing' covers most gateways (PayPal, etc.).
		// 'completed'  covers credit-card gateways (Stripe, Square, etc.) that skip processing.
		add_action( 'woocommerce_order_status_processing', array( $order_handler, 'handle' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $order_handler, 'handle' ), 10, 1 );

		// Product edit page — raffle settings meta box.
		add_action( 'add_meta_boxes', array( $meta_box, 'register' ) );
		add_action( 'woocommerce_process_product_meta', array( $meta_box, 'save' ) );

		// Order details — customer-facing and admin views.
		add_action( 'woocommerce_order_details_after_order_table', array( $order_display, 'renderCustomer' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $order_display, 'renderAdmin' ) );

		// Register the ticket-label option with the WordPress Settings API.
		$plugin_settings->register();

		// Admin page: Report tab (CSV export + retroactive assignment),
		// Rolls tab (roll add / delete), and Settings tab (ticket label).
		add_action( 'admin_menu', array( $report_page, 'register' ) );
		add_action( 'admin_init', array( $report_page, 'maybeStreamCsv' ) );
		add_action( 'admin_init', array( $report_page, 'maybeAssignRetroactive' ) );
		add_action( 'admin_init', array( $report_page, 'maybeAddRoll' ) );
		add_action( 'admin_init', array( $report_page, 'maybeDeleteRoll' ) );
	}
}
