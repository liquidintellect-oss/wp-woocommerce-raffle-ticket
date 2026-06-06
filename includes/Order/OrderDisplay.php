<?php
/**
 * Order display — raffle tickets in order detail views.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class OrderDisplay
 *
 * Renders raffle ticket numbers on both the customer-facing order details page
 * and the WordPress admin order view.
 */
class OrderDisplay {

	/**
	 * Constructor.
	 *
	 * @param TicketRepository $ticket_repo Ticket retrieval layer.
	 */
	public function __construct( private TicketRepository $ticket_repo ) {}

	/**
	 * Render the raffle ticket list on the customer order details page.
	 *
	 * Hooked to `woocommerce_order_details_after_order_table`.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 *
	 * @return void
	 */
	public function renderCustomer( $order ): void {
		$tickets = $this->ticket_repo->findByOrder( (int) $order->get_id() );

		if ( empty( $tickets ) ) {
			return;
		}

		echo '<h2 class="raffle-tickets-heading">' . esc_html__( 'Your Raffle Tickets', 'wp-woocommerce-raffle-ticket' ) . '</h2>';
		echo '<ul class="raffle-tickets">';
		foreach ( $tickets as $ticket ) {
			echo '<li>' . esc_html( $ticket->ticket_number ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render the raffle ticket list in the admin order view.
	 *
	 * Hooked to `woocommerce_admin_order_data_after_order_details`.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 *
	 * @return void
	 */
	public function renderAdmin( $order ): void {
		$tickets = $this->ticket_repo->findByOrder( (int) $order->get_id() );

		if ( empty( $tickets ) ) {
			return;
		}

		echo '<div class="order_data_column raffle-tickets-admin">';
		echo '<h4>' . esc_html__( 'Raffle Tickets', 'wp-woocommerce-raffle-ticket' ) . '</h4>';
		echo '<ul>';
		foreach ( $tickets as $ticket ) {
			echo '<li>' . esc_html( $ticket->ticket_number ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}
