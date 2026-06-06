<?php
/**
 * Order handler — assigns raffle tickets on payment.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;
use WpWoocommerceRaffleTicket\Product\ProductSettings;
use WpWoocommerceRaffleTicket\Ticket\SequenceRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketNumberGenerator;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class OrderHandler
 *
 * Hooks into WooCommerce's order status change to assign unique raffle ticket
 * numbers when an order transitions to "processing" (payment received).
 *
 * Also filters add-to-cart validation to block purchases once a raffle is sold
 * out, providing a first line of defence before the checkout flow.
 */
class OrderHandler {

	/**
	 * Constructor.
	 *
	 * @param TicketRepository      $ticket_repo Ticket persistence layer.
	 * @param SequenceRepository    $seq_repo    Concurrent-safe sequence counter.
	 * @param TicketNumberGenerator $generator   Ticket number factory.
	 */
	public function __construct(
		private TicketRepository $ticket_repo,
		private SequenceRepository $seq_repo,
		private TicketNumberGenerator $generator
	) {}

	/**
	 * Assign raffle tickets for all eligible items in an order.
	 *
	 * Called when the order status changes to "processing".  Guarded by an
	 * idempotency check so retried hook calls never produce duplicate tickets.
	 * Each unit of a raffle product (qty = N → N tickets) receives its own
	 * unique sequence number.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 */
	public function handle( int $order_id ): void {
		// Idempotency — skip if tickets were already assigned for this order.
		if ( $this->ticket_repo->hasTicketsForOrder( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$customer_id = (int) $order->get_customer_id();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = (int) $item->get_product_id();
			$settings   = ProductSettings::forProduct( $product_id );

			if ( ! $settings->isEnabled() ) {
				continue;
			}

			$quantity = (int) $item->get_quantity();

			for ( $i = 0; $i < $quantity; $i++ ) {
				try {
					$sequence = $this->seq_repo->nextSequence(
						$product_id,
						$settings->getMinSequence(),
						$settings->getMaxSequence()
					);
					$ticket   = $this->generator->generate( $settings, $sequence );
					$this->ticket_repo->save(
						$ticket,
						$order_id,
						(int) $item_id,
						$customer_id,
						$product_id
					);
				} catch ( RuntimeException $e ) {
					if ( 'sold_out' === $e->getMessage() ) {
						$order->add_order_note(
							sprintf(
								/* translators: %s: product name */
								esc_html__( 'Raffle ticket sold out for product: %s', 'wp-woocommerce-raffle-ticket' ),
								esc_html( $item->get_name() )
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Validate add-to-cart requests for raffle products.
	 *
	 * Blocks the add when the requested quantity exceeds remaining capacity.
	 * This is the primary sold-out guard; the RuntimeException in handle() is
	 * the fallback for race conditions.
	 *
	 * @param bool $passed     Whether validation has passed so far.
	 * @param int  $product_id The product being added to cart.
	 * @param int  $quantity   The quantity being requested.
	 *
	 * @return bool
	 */
	public function validateCartAdd( bool $passed, int $product_id, int $quantity ): bool {
		$settings = ProductSettings::forProduct( $product_id );

		if ( ! $settings->isEnabled() ) {
			return $passed;
		}

		$remaining = $this->seq_repo->remaining(
			$product_id,
			$settings->getMinSequence(),
			$settings->getMaxSequence()
		);

		if ( $quantity > $remaining ) {
			wc_add_notice(
				esc_html__( 'Sorry, this raffle is sold out.', 'wp-woocommerce-raffle-ticket' ),
				'error'
			);
			return false;
		}

		return $passed;
	}
}
