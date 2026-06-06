<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Product\ProductSettings;
use WpWoocommerceRaffleTicket\Ticket\SequenceRepository;
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
	private SequenceRepository $seq_repo;
	private TicketNumberGenerator $generator;
	private OrderHandler $handler;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->ticket_repo = $this->createMock( TicketRepository::class );
		$this->seq_repo    = $this->createMock( SequenceRepository::class );
		$this->generator   = $this->createMock( TicketNumberGenerator::class );
		$this->handler     = new OrderHandler( $this->ticket_repo, $this->seq_repo, $this->generator );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function mockProductMeta( int $product_id, bool $enabled, string $prefix = 'R-', int $min = 1, int $max = 100 ): void {
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_ENABLED, true ), 'return' => $enabled ? '1' : '' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_PREFIX, true ), 'return' => $prefix ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_MIN_SEQUENCE, true ), 'return' => (string) $min ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_MAX_SEQUENCE, true ), 'return' => (string) $max ) );
	}

	/** @test */
	public function handle_returns_early_when_tickets_already_exist(): void {
		$this->ticket_repo
			->expects( $this->once() )
			->method( 'hasTicketsForOrder' )
			->with( 99 )
			->willReturn( true );

		$this->ticket_repo->expects( $this->never() )->method( 'save' );

		WP_Mock::userFunction( 'wc_get_order', array( 'times' => 0 ) );

		$this->handler->handle( 99 );
	}

	/** @test */
	public function handle_returns_early_when_order_not_found(): void {
		$this->ticket_repo->method( 'hasTicketsForOrder' )->willReturn( false );

		WP_Mock::userFunction( 'wc_get_order', array( 'args' => array( 1 ), 'return' => false ) );

		$this->ticket_repo->expects( $this->never() )->method( 'save' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_skips_non_raffle_products(): void {
		$item  = new WcOrderItemStub( 10, 1, 'Regular Product' );
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasTicketsForOrder' )->willReturn( false );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, false ); // Not a raffle product.

		$this->seq_repo->expects( $this->never() )->method( 'nextSequence' );
		$this->ticket_repo->expects( $this->never() )->method( 'save' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_assigns_one_ticket_per_unit_of_quantity(): void {
		$item  = new WcOrderItemStub( 10, 3, 'Raffle Product' ); // qty = 3.
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasTicketsForOrder' )->willReturn( false );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-', 1, 100 );

		$ticket = new TicketNumber( 'R-', 1, 'R-001' );
		$this->seq_repo->method( 'nextSequence' )->willReturn( 1 );
		$this->generator->method( 'generate' )->willReturn( $ticket );

		// save() must be called once per quantity unit (3 times).
		$this->ticket_repo->expects( $this->exactly( 3 ) )->method( 'save' );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function handle_adds_order_note_when_sold_out(): void {
		$item  = new WcOrderItemStub( 10, 1, 'Raffle Product' );
		$order = new WcOrderStub( 1, 5, array( 99 => $item ) );

		$this->ticket_repo->method( 'hasTicketsForOrder' )->willReturn( false );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-', 1, 100 );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );

		$this->seq_repo->method( 'nextSequence' )->willThrowException( new RuntimeException( 'sold_out' ) );

		$this->handler->handle( 1 );

		$this->assertCount( 1, $order->getNotes() );
		$this->assertStringContainsString( 'Raffle Product', $order->getNotes()[0] );
	}

	/** @test */
	public function handle_passes_customer_id_to_save(): void {
		$item  = new WcOrderItemStub( 10, 1, 'Raffle' );
		$order = new WcOrderStub( 1, 77, array( 55 => $item ) ); // customer_id = 77.

		$this->ticket_repo->method( 'hasTicketsForOrder' )->willReturn( false );

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		$this->mockProductMeta( 10, true, 'R-', 1, 100 );

		$ticket = new TicketNumber( 'R-', 1, 'R-001' );
		$this->seq_repo->method( 'nextSequence' )->willReturn( 1 );
		$this->generator->method( 'generate' )->willReturn( $ticket );

		$this->ticket_repo
			->expects( $this->once() )
			->method( 'save' )
			->with( $ticket, 1, 55, 77, 10 );

		$this->handler->handle( 1 );
	}

	/** @test */
	public function validate_cart_add_returns_passed_for_non_raffle_product(): void {
		$this->mockProductMeta( 5, false );

		$result = $this->handler->validateCartAdd( true, 5, 1 );

		$this->assertTrue( $result );
	}

	/** @test */
	public function validate_cart_add_allows_when_capacity_available(): void {
		$this->mockProductMeta( 5, true, 'R-', 1, 100 );
		$this->seq_repo->method( 'remaining' )->willReturn( 50 );

		$result = $this->handler->validateCartAdd( true, 5, 3 );

		$this->assertTrue( $result );
	}

	/** @test */
	public function validate_cart_add_blocks_when_insufficient_capacity(): void {
		$this->mockProductMeta( 5, true, 'R-', 1, 10 );
		$this->seq_repo->method( 'remaining' )->willReturn( 2 );

		WP_Mock::userFunction( 'wc_add_notice', array( 'times' => 1 ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$result = $this->handler->validateCartAdd( true, 5, 5 ); // Wants 5, only 2 left.

		$this->assertFalse( $result );
	}

	/** @test */
	public function validate_cart_add_blocks_when_sold_out(): void {
		$this->mockProductMeta( 5, true, 'R-', 1, 10 );
		$this->seq_repo->method( 'remaining' )->willReturn( 0 );

		WP_Mock::userFunction( 'wc_add_notice', array( 'times' => 1 ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$result = $this->handler->validateCartAdd( true, 5, 1 );

		$this->assertFalse( $result );
	}
}
