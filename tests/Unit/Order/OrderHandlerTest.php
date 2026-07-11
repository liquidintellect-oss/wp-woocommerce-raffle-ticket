<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Product\ProductSettings;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketNumber;
use WpWoocommerceRaffleTicket\Ticket\TicketNumberGenerator;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class WcOrderItemStub {
	public function __construct(
		private int $product_id,
		private int $quantity,
		private string $name
	) {}
	public function get_product_id(): int {
		return $this->product_id; }
	public function get_quantity(): int {
		return $this->quantity; }
	public function get_name(): string {
		return $this->name; }
}

class WcOrderStub extends WC_Order {
	private array $notes = array();

	public function __construct(
		private int $id,
		private int $customer_id,
		private array $items,
		private string $billing_name = 'Jane Doe',
		private string $billing_email = 'jane@example.com'
	) {}

	public function get_id(): int {
		return $this->id; }
	public function get_customer_id(): int {
		return $this->customer_id; }
	public function get_items(): array {
		return $this->items; }
	public function add_order_note( string $note ): void {
		$this->notes[] = $note; }
	public function getNotes(): array {
		return $this->notes; }
	public function get_formatted_billing_full_name(): string {
		return $this->billing_name; }
	public function get_billing_email(): string {
		return $this->billing_email; }
}

// ── Test case ─────────────────────────────────────────────────────────────────

class OrderHandlerTest extends TestCase {

	private TicketRepository $ticket_repo;
	private RollRepository $roll_repo;
	private TicketNumberGenerator $generator;
	private OrderHandler $handler;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->ticket_repo = $this->createMock( TicketRepository::class );
		$this->roll_repo   = $this->createMock( RollRepository::class );
		$this->generator   = $this->createMock( TicketNumberGenerator::class );
		$this->handler     = new OrderHandler( $this->ticket_repo, $this->roll_repo, $this->generator );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function mockProductMeta( int $product_id, bool $enabled, string $prefix = 'R-' ): void {
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_ENABLED, true ), 'return' => $enabled ? '1' : '' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_PREFIX, true ), 'return' => $prefix ) );
	}

	/** @test */
	public function handle_returns_early_when_assigned_tickets_already_exist(): void {
		$this->ticket_repo
			->expects( $this->once() )
			->method( 'hasAssignedTicketsForOrder' )
			->with( 99 )
			->willReturn( true );

		$this->ticket_repo->expects( $this->never() )->method( 'save' );
		$this->ticket_repo->expects( $this->never() )->method( 'saveUnassigned' );

		WP_Mock::userFunction( 'wc_get_order', array( 'times' => 0 ) );

		$this->handler->handle( 99 );
	}

	/** @test */
	public function handle_returns_early_when_order_not_found(): void {
		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->method( 'deleteUnassignedForOrder' ); // no-op.

		WP_Mock::userFunction( 'wc_get_order', array( 'args' => array( 1 ), 'return' => false ) );

		$this->ticket_repo->expects( $this->never() )->method( 'save' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_deletes_pending_before_reassigning(): void {
		$item  = new WcOrderItemStub( 10, 1, 'Raffle' );
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->expects( $this->once() )->method( 'deleteUnassignedForOrder' )->with( 1 );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, false );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_skips_non_raffle_products(): void {
		$item  = new WcOrderItemStub( 10, 1, 'Regular Product' );
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->method( 'deleteUnassignedForOrder' );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, false ); // Not a raffle product.

		$this->roll_repo->expects( $this->never() )->method( 'nextTicket' );
		$this->ticket_repo->expects( $this->never() )->method( 'save' );
		$this->ticket_repo->expects( $this->never() )->method( 'saveUnassigned' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_returns_early_when_order_is_a_refund(): void {
		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );

		// wc_get_order returns a refund object.
		$refund = $this->createMock( \WC_Order_Refund::class );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $refund ) );

		$this->ticket_repo->expects( $this->never() )->method( 'save' );
		$this->ticket_repo->expects( $this->never() )->method( 'saveUnassigned' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_assigns_one_ticket_per_unit_of_quantity(): void {
		$item  = new WcOrderItemStub( 10, 3, 'Raffle Product' ); // qty = 3.
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->method( 'deleteUnassignedForOrder' );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-' );

		$slot = array( 'roll_id' => 1, 'start_number' => 1, 'ticket_count' => 100, 'offset' => 1, 'direction' => 'asc' );
		$this->roll_repo->method( 'nextTicket' )->willReturn( $slot );

		$ticket = new TicketNumber( 'R-', 1, 'R-001' );
		$this->generator->method( 'generate' )->willReturn( $ticket );

		// save() must be called once per quantity unit (3 times).
		$this->ticket_repo->expects( $this->exactly( 3 ) )->method( 'save' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_saves_unassigned_when_no_rolls_available(): void {
		$item  = new WcOrderItemStub( 10, 2, 'Raffle Product' );
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->method( 'deleteUnassignedForOrder' );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-' );

		// No rolls available.
		$this->roll_repo->method( 'nextTicket' )->willReturn( null );

		// 2 units, each becomes an unassigned placeholder.
		$this->ticket_repo->expects( $this->exactly( 2 ) )->method( 'saveUnassigned' );
		$this->ticket_repo->expects( $this->never() )->method( 'save' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_passes_roll_id_to_save(): void {
		$item  = new WcOrderItemStub( 10, 1, 'Raffle' );
		$order = new WcOrderStub( 1, 77, array( 55 => $item ) ); // customer_id = 77.

		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->method( 'deleteUnassignedForOrder' );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-' );

		$slot = array( 'roll_id' => 7, 'start_number' => 1001, 'ticket_count' => 500, 'offset' => 1, 'direction' => 'asc' );
		$this->roll_repo->method( 'nextTicket' )->willReturn( $slot );

		$ticket = new TicketNumber( 'R-', 1001, 'R-1001' );
		$this->generator->method( 'generate' )->willReturn( $ticket );

		$this->ticket_repo
			->expects( $this->once() )
			->method( 'save' )
			->with( $ticket, 1, 55, 77, 10, 7 );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_does_not_block_sale_when_rolls_exhausted(): void {
		// Verify no exception is thrown when rolls run out.
		$item  = new WcOrderItemStub( 10, 1, 'Raffle' );
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );
		$this->ticket_repo->method( 'deleteUnassignedForOrder' );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-' );

		$this->roll_repo->method( 'nextTicket' )->willReturn( null );
		$this->ticket_repo->method( 'saveUnassigned' ); // Should be called without exception.

		// If no exception is thrown, the test passes.
		$this->handler->handle( 1 );
		$this->assertTrue( true );
	}
}
