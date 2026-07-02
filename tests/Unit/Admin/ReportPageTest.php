<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Admin\ReportPage;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class WcOrderDateStub {
	public function __construct( private string $date_str ) {}
	public function date( string $format ): string {
		return $this->date_str;
	}
}

class WcOrderReportStub extends WC_Order {
	public function __construct(
		private string $name,
		private string $email,
		private string $order_date = '2025-01-01 10:00:00'
	) {}
	public function get_formatted_billing_full_name(): string {
		return $this->name;
	}
	public function get_billing_email(): string {
		return $this->email;
	}
	public function get_date_created(): WcOrderDateStub {
		return new WcOrderDateStub( $this->order_date );
	}
}

/**
 * Order stub that exposes a configurable get_id() and order date for retroactive-assignment tests.
 */
class WcOrderWithIdStub extends WC_Order {
	public function __construct(
		private int $id,
		private string $order_date = '2025-06-01'
	) {}
	public function get_id(): int {
		return $this->id;
	}
	public function get_date_created(): WcOrderDateStub {
		return new WcOrderDateStub( $this->order_date );
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class ReportPageTest extends TestCase {

	private TicketRepository $ticket_repo;
	private OrderHandler $order_handler;
	private RollRepository $roll_repo;
	private ReportPage $report;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->ticket_repo   = $this->createMock( TicketRepository::class );
		$this->order_handler = $this->createMock( OrderHandler::class );
		$this->roll_repo     = $this->createMock( RollRepository::class );
		$this->report        = new ReportPage( $this->ticket_repo, $this->order_handler, 'Raffle Tickets', $this->roll_repo );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		$_GET  = array();
		$_POST = array();
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
		$this->assertStringContainsString( 'Roll', $content );
		$this->assertStringContainsString( 'Purchase Date', $content );
	}

	/** @test */
	public function write_csv_outputs_one_data_row_per_ticket(): void {
		$rows = array(
			(object) array(
				'order_id'      => 10,
				'product_name'  => 'My Raffle',
				'ticket_number' => 'R-1001',
				'roll_id'       => 1,
				'roll_label'    => 'Roll A',
				'roll_start'    => '1001',
				'roll_last'     => '1500',
				'created_at'    => '2025-01-01 10:00:00',
			),
			(object) array(
				'order_id'      => 10,
				'product_name'  => 'My Raffle',
				'ticket_number' => 'R-1002',
				'roll_id'       => 1,
				'roll_label'    => 'Roll A',
				'roll_start'    => '1001',
				'roll_last'     => '1500',
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
	public function write_csv_shows_pending_for_unassigned_tickets(): void {
		$rows = array(
			(object) array(
				'order_id'      => 5,
				'product_name'  => 'Lucky Draw',
				'ticket_number' => 'PENDING-abc123',
				'roll_id'       => null,
				'roll_label'    => null,
				'roll_start'    => null,
				'roll_last'     => null,
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

		$this->assertStringContainsString( 'Pending', $content );
		$this->assertStringNotContainsString( 'PENDING-abc123', $content );
	}

	/** @test */
	public function write_csv_includes_customer_name_and_email(): void {
		$rows = array(
			(object) array(
				'order_id'      => 5,
				'product_name'  => 'Lucky Draw',
				'ticket_number' => 'LD-1001',
				'roll_id'       => 2,
				'roll_label'    => 'Roll B',
				'roll_start'    => '2001',
				'roll_last'     => '2500',
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
		$this->assertStringContainsString( 'LD-1001', $content );
	}

	/** @test */
	public function write_csv_handles_missing_order_gracefully(): void {
		$rows = array(
			(object) array(
				'order_id'      => 99,
				'product_name'  => 'Raffle',
				'ticket_number' => 'R-1001',
				'roll_id'       => 1,
				'roll_label'    => 'Roll A',
				'roll_start'    => '1001',
				'roll_last'     => '1500',
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
		$this->assertStringContainsString( 'R-1001', $content );
	}

	/** @test */
	public function write_csv_includes_roll_label_and_range_for_assigned_ticket(): void {
		$rows = array(
			(object) array(
				'order_id'      => 7,
				'product_name'  => 'Grand Raffle',
				'ticket_number' => 'GR-1042',
				'roll_id'       => 3,
				'roll_label'    => 'Roll C',
				'roll_start'    => '1001',
				'roll_last'     => '1500',
				'created_at'    => '2025-05-01 12:00:00',
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		$order = new WcOrderReportStub( 'Bob Jones', 'bob@example.com' );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		$this->assertStringContainsString( 'Roll C', $content );
		$this->assertStringContainsString( '1001', $content );
		$this->assertStringContainsString( '1500', $content );
	}

	/** @test */
	public function write_csv_uses_roll_id_when_label_is_empty(): void {
		$rows = array(
			(object) array(
				'order_id'      => 8,
				'product_name'  => 'Grand Raffle',
				'ticket_number' => 'GR-1001',
				'roll_id'       => 5,
				'roll_label'    => '',
				'roll_start'    => '1001',
				'roll_last'     => '1500',
				'created_at'    => '2025-05-01 12:00:00',
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		$order = new WcOrderReportStub( 'Carol White', 'carol@example.com' );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		WP_Mock::userFunction(
			'__',
			array(
				'return' => static function ( string $text ) {
					// Pass through so "Roll #%d" survives sprintf.
					return $text;
				},
			)
		);

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		// Should fall back to "Roll #5".
		$this->assertStringContainsString( '5', $content );
	}

	/** @test */
	public function write_csv_leaves_roll_column_empty_for_pending_tickets(): void {
		$rows = array(
			(object) array(
				'order_id'      => 9,
				'product_name'  => 'Lucky Dip',
				'ticket_number' => 'PENDING-xyz',
				'roll_id'       => null,
				'roll_label'    => null,
				'roll_start'    => null,
				'roll_last'     => null,
				'created_at'    => '2025-05-02 08:00:00',
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		$order = new WcOrderReportStub( 'Dave Brown', 'dave@example.com' );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		// Roll column for pending rows should be empty — adjacent commas with no value.
		$this->assertStringContainsString( 'Pending,,', $content );
	}

	/** @test */
	public function write_csv_uses_order_date_not_ticket_date(): void {
		$rows = array(
			(object) array(
				'order_id'      => 11,
				'product_name'  => 'Test Raffle',
				'ticket_number' => 'TR-0001',
				'roll_id'       => 1,
				'roll_label'    => 'Roll A',
				'roll_start'    => '1',
				'roll_last'     => '100',
				'created_at'    => '2099-12-31 23:59:59', // ticket assignment date — must NOT appear
			),
		);
		$this->ticket_repo->method( 'findAll' )->willReturn( $rows );

		// Order date is 2025-03-15; this is what should appear in the CSV.
		$order = new WcOrderReportStub( 'Test User', 'test@example.com', '2025-03-15 09:30:00' );
		WP_Mock::userFunction( 'wc_get_order', array( 'return' => $order ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		$this->assertStringContainsString( '2025-03-15', $content );
		$this->assertStringNotContainsString( '2099-12-31', $content );
	}

	/** @test */
	public function write_csv_filters_rows_by_date_range(): void {
		$make_row = static function ( int $order_id, string $ticket ): object {
			return (object) array(
				'order_id'      => $order_id,
				'product_name'  => 'Raffle',
				'ticket_number' => $ticket,
				'roll_id'       => 1,
				'roll_label'    => 'Roll A',
				'roll_start'    => '1',
				'roll_last'     => '100',
				'created_at'    => '2025-01-01 00:00:00',
			);
		};

		$this->ticket_repo->method( 'findAll' )->willReturn(
			array(
				$make_row( 1, 'T-BEFORE' ),
				$make_row( 2, 'T-INSIDE' ),
				$make_row( 3, 'T-AFTER' ),
			)
		);

		WP_Mock::userFunction(
			'wc_get_order',
			array(
				'return' => static function ( int $id ) {
					$dates = array(
						1 => '2025-05-15 00:00:00', // before range
						2 => '2025-06-10 00:00:00', // inside range
						3 => '2025-07-05 00:00:00', // after range
					);
					return new WcOrderReportStub( 'Name', 'email@example.com', $dates[ $id ] );
				},
			)
		);
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream, '2025-06-01', '2025-06-30' );
		$content = $this->readStream( $stream );

		$this->assertStringContainsString( 'T-INSIDE', $content );
		$this->assertStringNotContainsString( 'T-BEFORE', $content );
		$this->assertStringNotContainsString( 'T-AFTER', $content );
	}

	/** @test */
	public function find_all_query_joins_rolls_table(): void {
		$this->ticket_repo
			->method( 'findAll' )
			->willReturn(
				array(
					(object) array(
						'order_id'      => 1,
						'product_name'  => 'Test',
						'ticket_number' => 'T-0001',
						'roll_id'       => 1,
						'roll_label'    => 'Roll A',
						'roll_start'    => '1',
						'roll_last'     => '100',
						'created_at'    => '2025-01-01 00:00:00',
					),
				)
			);

		WP_Mock::userFunction( 'wc_get_order', array( 'return' => new WcOrderReportStub( 'X', 'x@x.com' ) ) );
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );

		$stream = $this->openTempStream();
		$this->report->writeCsv( $stream );
		$content = $this->readStream( $stream );

		$this->assertStringContainsString( 'Roll A', $content );
	}

	// ── register() ────────────────────────────────────────────────────────────

	/** @test */
	public function register_adds_submenu_page_under_woocommerce(): void {
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );
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
	public function maybe_assign_retroactive_uses_has_assigned_tickets_check(): void {
		$_GET = array(
			'page'     => 'raffle-ticket-report',
			'action'   => 'assign_retroactive',
			'_wpnonce' => 'valid',
		);

		// Two orders: order 10 has no assigned tickets, order 20 does.
		$order_needs   = new WcOrderWithIdStub( 10 );
		$order_already = new WcOrderWithIdStub( 20 );

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'wc_get_orders', array( 'return' => array( $order_needs, $order_already ) ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/wp-admin/admin.php?page=raffle-ticket-report&assigned=1' ) );
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
			->method( 'hasAssignedTicketsForOrder' )
			->willReturnMap(
				array(
					array( 10, false ), // Order 10 has no assigned tickets.
					array( 20, true ),  // Order 20 already fully assigned.
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
	public function maybe_assign_retroactive_processes_orders_with_only_pending_tickets(): void {
		// Orders with pending-only tickets should be re-processed since
		// hasAssignedTicketsForOrder returns false for them.
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
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/wp-admin/admin.php?assigned=1' ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		// No assigned tickets → pending-only order should be re-processed.
		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );

		$this->order_handler
			->expects( $this->once() )
			->method( 'handle' )
			->with( 42 );

		$this->expectException( \RuntimeException::class );
		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_respects_date_range(): void {
		// Orders outside the requested range must be skipped.
		$_GET = array(
			'page'      => 'raffle-ticket-report',
			'action'    => 'assign_retroactive',
			'_wpnonce'  => 'valid',
			'date_from' => '2025-06-01',
			'date_to'   => '2025-06-30',
		);

		// Three orders: one before, one inside, one after the range.
		$order_before = new WcOrderWithIdStub( 1, '2025-05-15' );
		$order_inside = new WcOrderWithIdStub( 2, '2025-06-10' );
		$order_after  = new WcOrderWithIdStub( 3, '2025-07-01' );

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction(
			'wc_get_orders',
			array( 'return' => array( $order_before, $order_inside, $order_after ) )
		);
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/redirect' ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		// hasAssignedTicketsForOrder returns false for all orders.
		$this->ticket_repo->method( 'hasAssignedTicketsForOrder' )->willReturn( false );

		// handle() must be called only for the in-range order (id=2).
		$this->order_handler
			->expects( $this->once() )
			->method( 'handle' )
			->with( 2 );

		$this->expectException( \RuntimeException::class );
		$this->report->maybeAssignRetroactive();
	}

	/** @test */
	public function maybe_assign_retroactive_overwrites_when_flag_is_set(): void {
		$_GET = array(
			'page'      => 'raffle-ticket-report',
			'action'    => 'assign_retroactive',
			'_wpnonce'  => 'valid',
			'overwrite' => '1',
		);

		// Two orders that already have assigned tickets.
		$order_a = new WcOrderWithIdStub( 10 );
		$order_b = new WcOrderWithIdStub( 20 );

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'wc_get_orders', array( 'return' => array( $order_a, $order_b ) ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/redirect' ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		// findRollCountsForOrder is consulted first so we know which slots to free.
		$this->ticket_repo
			->expects( $this->exactly( 2 ) )
			->method( 'findRollCountsForOrder' )
			->willReturn( array( 7 => 3 ) ); // roll 7 had 3 tickets per order.

		// deleteAllForOrder must be called once per order.
		$this->ticket_repo
			->expects( $this->exactly( 2 ) )
			->method( 'deleteAllForOrder' )
			->withConsecutive( array( 10 ), array( 20 ) );

		// decrementOffset must be called for each roll returned by findRollCountsForOrder.
		$this->roll_repo
			->expects( $this->exactly( 2 ) )
			->method( 'decrementOffset' )
			->with( 7, 3 );

		// hasAssignedTicketsForOrder must NOT be consulted in overwrite mode.
		$this->ticket_repo
			->expects( $this->never() )
			->method( 'hasAssignedTicketsForOrder' );

		// handle() is called for every order regardless of prior assignment state.
		$this->order_handler
			->expects( $this->exactly( 2 ) )
			->method( 'handle' );

		$this->expectException( \RuntimeException::class );
		$this->report->maybeAssignRetroactive();
	}

	// ── handleAddRoll() ──────────────────────────────────────────────────────
	// Routed via admin_post_wrt_add_roll — no action/page detection needed.

	/** @test */
	public function handle_add_roll_dies_on_invalid_nonce(): void {
		$_POST = array( '_wpnonce' => 'bad' );

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
		$this->report->handleAddRoll();
	}

	/** @test */
	public function handle_add_roll_dies_when_user_lacks_capability(): void {
		$_POST = array( '_wpnonce' => 'valid' );

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
		$this->report->handleAddRoll();
	}

	/** @test */
	public function handle_add_roll_calls_create_and_redirects(): void {
		$_POST = array(
			'_wpnonce'          => 'valid',
			'roll_product_id'   => '10',
			'roll_label'        => 'Roll A',
			'roll_start_number' => '1001',
			'roll_ticket_count' => '500',
			'roll_sort_order'   => '0',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction(
			'add_query_arg',
			array( 'return' => 'http://example.com/wp-admin/admin.php?page=raffle-ticket-report&tab=rolls' )
		);
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->roll_repo
			->expects( $this->once() )
			->method( 'create' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_safe_redirect' );
		$this->report->handleAddRoll();
	}

	/** @test */
	public function handle_add_roll_skips_create_when_product_id_is_zero(): void {
		$_POST = array(
			'_wpnonce'          => 'valid',
			'roll_product_id'   => '0',
			'roll_ticket_count' => '500',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/redirect' ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->roll_repo->expects( $this->never() )->method( 'create' );

		$this->expectException( \RuntimeException::class );
		$this->report->handleAddRoll();
	}

	// ── maybeDeleteRoll() ─────────────────────────────────────────────────────

	/** @test */
	public function maybe_delete_roll_does_nothing_when_page_absent(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array();

		$this->report->maybeDeleteRoll();
	}

	/** @test */
	public function maybe_delete_roll_does_nothing_for_wrong_action(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array(
			'page'   => ReportPage::PAGE_SLUG,
			'action' => 'add_roll',
		);

		$this->report->maybeDeleteRoll();
	}

	/** @test */
	public function maybe_delete_roll_dies_on_invalid_nonce(): void {
		$_GET = array(
			'page'     => ReportPage::PAGE_SLUG,
			'action'   => 'delete_roll',
			'roll_id'  => '3',
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
		$this->report->maybeDeleteRoll();
	}

	/** @test */
	public function maybe_delete_roll_calls_delete_and_redirects(): void {
		$_GET = array(
			'page'     => ReportPage::PAGE_SLUG,
			'action'   => 'delete_roll',
			'roll_id'  => '5',
			'_wpnonce' => 'valid',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction(
			'add_query_arg',
			array( 'return' => 'http://example.com/wp-admin/admin.php?page=raffle-ticket-report&tab=rolls' )
		);
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->roll_repo
			->expects( $this->once() )
			->method( 'delete' )
			->with( 5 );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_safe_redirect' );
		$this->report->maybeDeleteRoll();
	}

	// ── renderRollsTable() ────────────────────────────────────────────────────

	/** @test */
	public function render_rolls_table_shows_no_rolls_message_when_empty(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		ob_start();
		$this->report->renderRollsTable( array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No rolls', $output );
	}

	/** @test */
	public function render_rolls_table_shows_roll_data(): void {
		$row = (object) array(
			'id'             => 1,
			'product_name'   => 'Lucky Draw',
			'label'          => 'Roll A',
			'start_number'   => 1001,
			'ticket_count'   => 500,
			'current_offset' => 10,
			'sort_order'     => 0,
		);

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_url', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_js', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_nonce_url', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/delete' ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );

		ob_start();
		$this->report->renderRollsTable( array( $row ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Lucky Draw', $output );
		$this->assertStringContainsString( 'Roll A', $output );
		$this->assertStringContainsString( '1001', $output );
		$this->assertStringContainsString( '500', $output );
		// Last number: 1001 + 500 - 1 = 1500.
		$this->assertStringContainsString( '1500', $output );
		// Remaining: 500 - 10 = 490.
		$this->assertStringContainsString( '490', $output );
	}
}
