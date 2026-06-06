<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Ticket\TicketNumber;
use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * $wpdb spy for TicketRepository tests.
 */
class WpdbTicketSpy {

	public string $prefix = 'wp_';
	public string $posts  = 'wp_posts';
	public array $inserts = array();
	public array $queries = array();

	private array $get_results_return = array();
	private mixed $get_var_return     = '0';

	public function setGetResultsReturn( array $rows ): void {
		$this->get_results_return = $rows;
	}

	public function setGetVarReturn( mixed $value ): void {
		$this->get_var_return = $value;
	}

	public function insert( string $table, array $data, array $format ): int {
		$this->inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);
		return 1;
	}

	public function prepare( string $query, mixed ...$args ): string {
		$i = 0;
		return preg_replace_callback(
			'/%[ds]/',
			function () use ( $args, &$i ) {
				return $args[ $i++ ] ?? '';
			},
			$query
		);
	}

	public function get_results( string $sql ): array {
		$this->queries[] = $sql;
		return $this->get_results_return;
	}

	public function get_var( string $sql ): mixed {
		$this->queries[] = $sql;
		return $this->get_var_return;
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class TicketRepositoryTest extends TestCase {

	private WpdbTicketSpy $wpdb_spy;
	private TicketRepository $repo;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->wpdb_spy  = new WpdbTicketSpy();
		$GLOBALS['wpdb'] = $this->wpdb_spy;
		$this->repo      = new TicketRepository();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		unset( $GLOBALS['wpdb'] );
	}

	/** @test */
	public function save_calls_wpdb_insert_on_correct_table(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-01-01 10:00:00' ) );

		$ticket = new TicketNumber( 'R-', 1, 'R-001' );
		$this->repo->save( $ticket, 100, 10, 42, 5 );

		$this->assertCount( 1, $this->wpdb_spy->inserts );
		$this->assertSame( 'wp_raffle_tickets', $this->wpdb_spy->inserts[0]['table'] );
	}

	/** @test */
	public function save_inserts_correct_data(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-06-01 12:00:00' ) );

		$ticket = new TicketNumber( 'DRAW-', 7, 'DRAW-007' );
		$this->repo->save( $ticket, 200, 20, 55, 9 );

		$data = $this->wpdb_spy->inserts[0]['data'];
		$this->assertSame( 9, $data['product_id'] );
		$this->assertSame( 200, $data['order_id'] );
		$this->assertSame( 20, $data['order_item_id'] );
		$this->assertSame( 55, $data['customer_id'] );
		$this->assertSame( 'DRAW-007', $data['ticket_number'] );
		$this->assertSame( 'DRAW-', $data['ticket_prefix'] );
		$this->assertSame( 7, $data['ticket_sequence'] );
		$this->assertSame( '2025-06-01 12:00:00', $data['created_at'] );
	}

	/** @test */
	public function find_by_order_returns_empty_array_when_no_results(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$result = $this->repo->findByOrder( 999 );

		$this->assertSame( array(), $result );
	}

	/** @test */
	public function find_by_order_returns_rows_from_wpdb(): void {
		$row = (object) array( 'ticket_number' => 'R-001', 'order_id' => 100 );
		$this->wpdb_spy->setGetResultsReturn( array( $row ) );

		$result = $this->repo->findByOrder( 100 );

		$this->assertCount( 1, $result );
		$this->assertSame( 'R-001', $result[0]->ticket_number );
	}

	/** @test */
	public function find_by_order_query_contains_order_id(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$this->repo->findByOrder( 42 );

		$this->assertStringContainsString( '42', $this->wpdb_spy->queries[0] );
	}

	/** @test */
	public function find_all_returns_empty_array_when_no_results(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$result = $this->repo->findAll();

		$this->assertSame( array(), $result );
	}

	/** @test */
	public function find_all_query_joins_posts_table(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$this->repo->findAll();

		$this->assertStringContainsString( 'wp_posts', $this->wpdb_spy->queries[0] );
	}

	/** @test */
	public function has_tickets_for_order_returns_true_when_count_positive(): void {
		$this->wpdb_spy->setGetVarReturn( '3' );

		$result = $this->repo->hasTicketsForOrder( 100 );

		$this->assertTrue( $result );
	}

	/** @test */
	public function has_tickets_for_order_returns_false_when_count_zero(): void {
		$this->wpdb_spy->setGetVarReturn( '0' );

		$result = $this->repo->hasTicketsForOrder( 100 );

		$this->assertFalse( $result );
	}
}
