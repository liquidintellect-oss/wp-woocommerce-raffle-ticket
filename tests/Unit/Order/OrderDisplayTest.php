<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Order\OrderDisplay;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class WcOrderDisplayStub extends WC_Order {
	public function __construct( private int $id ) {}
	public function get_id(): int {
		return $this->id; }
}

// ── Test case ─────────────────────────────────────────────────────────────────

class OrderDisplayTest extends TestCase {

	private TicketRepository $ticket_repo;
	private OrderDisplay $display;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->ticket_repo = $this->createMock( TicketRepository::class );
		$this->display     = new OrderDisplay( $this->ticket_repo, 'Raffle Tickets' );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	/** @test */
	public function render_customer_produces_no_output_when_no_tickets(): void {
		$order = new WcOrderDisplayStub( 1 );
		$this->ticket_repo->method( 'findByOrder' )->willReturn( array() );

		ob_start();
		$this->display->renderCustomer( $order );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/** @test */
	public function render_customer_outputs_heading_and_list_when_tickets_exist(): void {
		$order   = new WcOrderDisplayStub( 1 );
		$tickets = array(
			(object) array( 'ticket_number' => 'R-001' ),
			(object) array( 'ticket_number' => 'R-002' ),
		);
		$this->ticket_repo->method( 'findByOrder' )->willReturn( $tickets );

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );

		ob_start();
		$this->display->renderCustomer( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'raffle-tickets', $output );
		$this->assertStringContainsString( 'R-001', $output );
		$this->assertStringContainsString( 'R-002', $output );
	}

	/** @test */
	public function render_customer_uses_correct_order_id(): void {
		$order = new WcOrderDisplayStub( 42 );
		$this->ticket_repo
			->expects( $this->once() )
			->method( 'findByOrder' )
			->with( 42 )
			->willReturn( array() );

		$this->display->renderCustomer( $order );
	}

	/** @test */
	public function render_admin_produces_no_output_when_no_tickets(): void {
		$order = new WcOrderDisplayStub( 1 );
		$this->ticket_repo->method( 'findByOrder' )->willReturn( array() );

		ob_start();
		$this->display->renderAdmin( $order );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/** @test */
	public function render_admin_outputs_ticket_numbers(): void {
		$order   = new WcOrderDisplayStub( 1 );
		$tickets = array(
			(object) array( 'ticket_number' => 'RAFFLE-0001' ),
		);
		$this->ticket_repo->method( 'findByOrder' )->willReturn( $tickets );

		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );

		ob_start();
		$this->display->renderAdmin( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'RAFFLE-0001', $output );
		$this->assertStringContainsString( 'raffle-tickets-admin', $output );
	}
}
