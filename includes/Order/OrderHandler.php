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

use WpWoocommerceRaffleTicket\Product\ProductSettings;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketNumberGenerator;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class OrderHandler
 *
 * Hooks into WooCommerce's order status change to assign unique raffle ticket
 * numbers when an order transitions to "processing" (payment received).
 *
 * Ticket numbers are drawn from physical rolls configured in the admin.  If no
 * rolls have remaining capacity, a pending placeholder is stored and the admin
 * receives a banner prompting them to add more rolls.  Sales are never blocked
 * by roll exhaustion.
 */
class OrderHandler {

	/**
	 * Constructor.
	 *
	 * @param TicketRepository      $ticket_repo Ticket persistence layer.
	 * @param RollRepository        $roll_repo   Physical roll / sequence manager.
	 * @param TicketNumberGenerator $generator   Ticket number factory.
	 */
	public function __construct(
		private TicketRepository $ticket_repo,
		private RollRepository $roll_repo,
		private TicketNumberGenerator $generator
	) {}

	/**
	 * Assign raffle tickets for all eligible items in an order.
	 *
	 * Called when the order status changes to "processing" or when the admin
	 * triggers retroactive assignment.  Guarded by an idempotency check so
	 * retried hook calls never produce duplicate assigned tickets.
	 *
	 * If a product has no rolls with remaining capacity, a pending placeholder
	 * is saved instead.  Pending tickets can be resolved by adding rolls and
	 * running retroactive assignment.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 */
	public function handle( int $order_id ): void {
		// Idempotency — skip if any fully-assigned tickets already exist for
		// this order.  Pending-only orders are re-processed (pending rows are
		// deleted below and fresh assignment is attempted).
		if ( $this->ticket_repo->hasAssignedTicketsForOrder( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Remove any pending placeholders so we can replace them on this run.
		$this->ticket_repo->deleteUnassignedForOrder( $order_id );

		$customer_id = (int) $order->get_customer_id();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = (int) $item->get_product_id();
			$settings   = ProductSettings::forProduct( $product_id );

			if ( ! $settings->isEnabled() ) {
				continue;
			}

			$quantity = (int) $item->get_quantity();

			for ( $i = 0; $i < $quantity; $i++ ) {
				$slot = $this->roll_repo->nextTicket( $product_id );

				if ( null === $slot ) {
					// No rolls available — save a pending placeholder.
					// Sales continue; admin banner will prompt roll addition.
					$this->ticket_repo->saveUnassigned(
						$settings->getPrefix(),
						$order_id,
						(int) $item_id,
						$customer_id,
						$product_id
					);
				} else {
					$roll   = new \WpWoocommerceRaffleTicket\Ticket\TicketRoll(
						$slot['roll_id'],
						$product_id,
						'',
						$slot['start_number'],
						$slot['ticket_count'],
						$slot['offset'],
						0
					);
					$ticket = $this->generator->generate( $settings->getPrefix(), $roll, $slot['offset'] );
					$this->ticket_repo->save(
						$ticket,
						$order_id,
						(int) $item_id,
						$customer_id,
						$product_id,
						$slot['roll_id']
					);
				}
			}
		}
	}
}
