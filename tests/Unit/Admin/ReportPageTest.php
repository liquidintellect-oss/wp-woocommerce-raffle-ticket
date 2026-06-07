<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Admin\ReportPage;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class WcOrderReportStub extends WC_Order {
	public function __construct(
		private string $name,
		private string $email
	) {}
	public function get_formatted_billing_full_name(): string {
		return $this->name;
	}
	public function get_billing_email(): string {
		return $this->email;
	}
}

/**
 * Order stub that exposes a configurable get_id() for retroactive-assignment tests.
 */
class WcOrderWithIdStub extends WC_Order {
	public function __construct( private int $id ) {}
	public function get_id(): int {
		return $this->id;
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class ReportPageTest extends TestCase {

	private TicketRepository $ticket_repo;
	private OrderHandler $order_handler;
	private ReportPage $report;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->ticket_repo   = $this->createMock( TicketRepository::class );
		$this->order_handler = $this->createMock( OrderHandler::class );
		$this->report        = new ReportPage( $this->ticket_repo, $this->order_handler );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		$_GET = array();
	}

	private function openTempStream() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		return fopen( 'php://temp', 'w+' );
	}

	private function readStream( $stream ): string {
		rewind( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = stream_get_contents( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $stream );
		return $content;
	}

	// ── writeCsv() ────────────────────────────────────────────────────────────

	/** @test */
	public function write_csv_outputs_header_row(): void {
		$this->ticket_repo->method( 'findAll' )->willReturn( array() );

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		$this->assertStringContainsString( 'Order ID', $content );
		$this->assertStringContainsString( 'Customer Name', $content );
		$this->assertStringContainsString( 'Customer Email', $content );
		$this->assertStringContainsString( 'Product Name', $content );
		$this->assertStringContainsString( 'Ticket Number', $content );
		$this->assertStringContainsString( 'Purchase Date', $content );
	}

	/** @test */
	public function write_csv_outputs_one_data_row_per_ticket(): void {
		$rows = array(
			(object) array(
				'order_id'      => 10,
				'product_name'  => 'My Raffle',
				'ticket_number' => 'R-001',
				'created_at'    => '2025-01-01 10:00:00',
			),
			(object) array(
				'order_id'      => 10,
				'product_name'  => 'My Raffle',
				'ticket_number' => 'R-002',
				'created_at'    => '2025-01-01 10:00:01',
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		$order = new WcOrderReportStub( 'Jane Doe', 'jane@example.com' );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		$lines = array_filter( explode( "\n", trim( $content ) ) );
		// 1 header + 2 data rows.
		$this->assertCount( 3, $lines );
	}

	/** @test */
	public function write_csv_includes_customer_name_and_email(): void {
		$rows = array(
			(object) array(
				'order_id'      => 5,
				'product_name'  => 'Lucky Draw',
				'ticket_number' => 'LD-001',
				'created_at'    => '2025-03-10 09:00:00',
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		$order = new WcOrderReportStub( 'Alice Smith', 'alice@test.com' );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		$this->assertStringContainsString( 'Alice Smith', $content );
		$this->assertStringContainsString( 'alice@test.com', $content );
		$this->assertStringContainsString( 'LD-001', $content );
	}

	/** @test */
	public function write_csv_handles_missing_order_gracefully(): void {
		$rows = array(
			(object) array(
				'order_id'      => 99,
				'product_name'  => 'Raffle',
				'ticket_number' => 'R-001',
				'created_at'    => '2025-01-01 10:00:00',
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		// wc_get_order returns false — order not found.
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => false ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		// Should not throw.
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		// Data row still present with empty customer fields.
		$this->assertStringContainsString( 'R-001', $content );
	}

	// ── register() ────────────────────────────────────────────────────────────

	/** @test */
	public function register_adds_submenu_page_under_woocommerce(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'add_submenu_page',
			array(
				'times' => 1,
				'args'  => array(
					'woocommerce',
					\Mockery::any(),
					\Mockery::any(),
					'manage_woocommerce',
					'raffle-ticket-report',
					\Mockery::any(),
				),
			)
		);

		$this->report->register();

		$this->addToAssertionCount( 1 );
	}

	// ── maybeStreamCsv() ──────────────────────────────────────────────────────

	/** @test */
	public function maybe_stream_csv_does_nothing_when_page_param_absent(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array();

		// If we get here without calling streamCsv, the test passes.
		$this->report->maybeStreamCsv();
	}

	/** @test */
	public function maybe_stream_csv_does_nothing_when_action_param_absent(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array( 'page' => 'raffle-ticket-report' );

		$this->report->maybeStreamCsv();
	}

	/** @test */
	public function maybe_stream_csv_does_nothing_for_different_page(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array(
			'page'   => 'some-other-page',
			'action' => 'download',
		);

		$this->report->maybeStreamCsv();
	}

	/** @test */
	public function maybe_stream_csv_dies_on_invalid_nonce(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'download',
			'_wpnonce' => 'bad',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		// Make wp_die throw so execution stops, mirroring real WordPress behaviour.
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->report->maybeStreamCsv();
	}

	/** @test */
	public function maybe_stream_csv_dies_when_user_lacks_capability(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'download',
			'_wpnonce' => 'valid',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		// Make wp_die throw so execution stops, mirroring real WordPress behaviour.
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->report->maybeStreamCsv();
	}

	// ── maybeAssignRetroactive() ──────────────────────────────────────────────

	/** @test */
	public function maybe_assign_retroactive_does_nothing_when_action_is_absent(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array( 'page' => 'raffle-ticket-report' );

		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_does_nothing_for_different_page(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array(
			'page'   => 'some-other-page',
			'action' => 'assign_retroactive',
		);

		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_does_nothing_for_download_action(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array(
			'page'   => 'raffle-ticket-report',
			'action' => 'download',
		);

		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_dies_on_invalid_nonce(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'assign_retroactive',
			'_wpnonce' => 'bad',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_dies_when_user_lacks_capability(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'assign_retroactive',
			'_wpnonce' => 'valid',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_calls_handle_for_orders_without_tickets(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'assign_retroactive',
			'_wpnonce' => 'valid',
		);

		// Two orders: order 10 has no tickets (needs assignment), order 20 already assigned.
		$order_needs   = new WcOrderWithIdStub( 10 );
		$order_already = new WcOrderWithIdStub( 20 );

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction(
			'wc_get_orders',
			array(
				'return' => array( $order_needs, $order_already ),
			)
		);
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/wp-admin/admin.php?page=raffle-ticket-report&assigned=1' ) );
		// Throw so that the subsequent exit; is never reached — prevents PHPUnit from terminating.
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->ticket_repo
			->method( 'hasTicketsForOrder' )
			->willReturnMap(
				array(
					array( 10, false ), // Order 10 has no tickets — needs assignment.
					array( 20, true ),  // Order 20 already has tickets — skip.
				)
			);

		// handle() should be called only for order 10.
		$this->order_handler
			->expects( $this->once() )
			->method( 'handle' )
			->with( 10 );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_safe_redirect' );
		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_skips_orders_that_already_have_tickets(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'assign_retroactive',
			'_wpnonce' => 'valid',
		);

		$order = new WcOrderWithIdStub( 42 );

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'wc_get_orders', array( 'return' => array( $order ) ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/wp-admin/admin.php?page=raffle-ticket-report&assigned=0' ) );
		// Throw so that the subsequent exit; is never reached.
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		// Order already has tickets — should skip.
		$this->ticket_repo->method( 'hasTicketsForOrder' )->willReturn( true );

		// handle() should never be called.
		$this->order_handler->expects( $this->never() )->method( 'handle' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_safe_redirect' );
		$this->report->maybeAssignRetroactive();
	}
}
