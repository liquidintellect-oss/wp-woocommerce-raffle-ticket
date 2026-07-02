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
	public array $deletes = array();
	public array $queries = array();

	private array $get_results_return = array();
	private mixed $get_var_return     = '0';
	private array $get_col_return     = array();

	public function setGetResultsReturn( array $rows ): void {
		$this->get_results_return = $rows;
	}

	public function setGetVarReturn( mixed $value ): void {
		$this->get_var_return = $value;
	}

	public function setGetColReturn( array $value ): void {
		$this->get_col_return = $value;
	}

	public function insert( string $table, array $data, array $format ): int {
		$this->inserts[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);
		return 1;
	}

	public function delete( string $table, array $where, array $where_format ): int {
		$this->deletes[] = compact( 'table', 'where', 'where_format' );
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

	public function get_col( string $sql ): array {
		$this->queries[] = $sql;
		return $this->get_col_return;
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

	// ── save() ────────────────────────────────────────────────────────────────

	/** @test */
	public function save_calls_wpdb_insert_on_correct_table(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-01-01 10:00:00' ) );

		$ticket = new TicketNumber( 'R-', 1001, 'R-1001' );
		$this->repo->save( $ticket, 100, 10, 42, 5, 3 );

		$this->assertCount( 1, $this->wpdb_spy->inserts );
		$this->assertSame( 'wp_raffle_tickets', $this->wpdb_spy->inserts[0]['table'] );
	}

	/** @test */
	public function save_inserts_correct_data_including_roll_id(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-06-01 12:00:00' ) );

		$ticket = new TicketNumber( 'DRAW-', 2007, 'DRAW-2007' );
		$this->repo->save( $ticket, 200, 20, 55, 9, 7 );

		$data = $this->wpdb_spy->inserts[0]['data'];
		$this->assertSame( 9, $data['product_id'] );
		$this->assertSame( 200, $data['order_id'] );
		$this->assertSame( 20, $data['order_item_id'] );
		$this->assertSame( 55, $data['customer_id'] );
		$this->assertSame( 'DRAW-2007', $data['ticket_number'] );
		$this->assertSame( 'DRAW-', $data['ticket_prefix'] );
		$this->assertSame( 2007, $data['ticket_sequence'] );
		$this->assertSame( 7, $data['roll_id'] );
		$this->assertSame( '2025-06-01 12:00:00', $data['created_at'] );
	}

	// ── saveUnassigned() ──────────────────────────────────────────────────────

	/** @test */
	public function save_unassigned_inserts_into_raffle_tickets_table(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-01-01 10:00:00' ) );

		$this->repo->saveUnassigned( 'R-', 100, 10, 42, 5 );

		$this->assertCount( 1, $this->wpdb_spy->inserts );
		$this->assertSame( 'wp_raffle_tickets', $this->wpdb_spy->inserts[0]['table'] );
	}

	/** @test */
	public function save_unassigned_stores_null_roll_id_and_zero_sequence(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-01-01 10:00:00' ) );

		$this->repo->saveUnassigned( 'R-', 100, 10, 42, 5 );

		$data = $this->wpdb_spy->inserts[0]['data'];
		$this->assertNull( $data['roll_id'] );
		$this->assertSame( 0, $data['ticket_sequence'] );
		$this->assertSame( 'R-', $data['ticket_prefix'] );
	}

	/** @test */
	public function save_unassigned_ticket_number_starts_with_pending_prefix(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-01-01 10:00:00' ) );

		$this->repo->saveUnassigned( 'R-', 100, 10, 42, 5 );

		$ticket_number = $this->wpdb_spy->inserts[0]['data']['ticket_number'];
		$this->assertStringStartsWith( TicketRepository::PENDING_PREFIX, $ticket_number );
	}

	// ── deleteUnassignedForOrder() ────────────────────────────────────────────

	/** @test */
	public function delete_unassigned_for_order_deletes_from_correct_table(): void {
		$this->repo->deleteUnassignedForOrder( 100 );

		$this->assertCount( 1, $this->wpdb_spy->deletes );
		$this->assertSame( 'wp_raffle_tickets', $this->wpdb_spy->deletes[0]['table'] );
	}

	/** @test */
	public function delete_unassigned_for_order_filters_by_order_id_and_null_roll(): void {
		$this->repo->deleteUnassignedForOrder( 42 );

		$where = $this->wpdb_spy->deletes[0]['where'];
		$this->assertSame( 42, $where['order_id'] );
		$this->assertNull( $where['roll_id'] );
	}

	// ── deleteAllForOrder() ───────────────────────────────────────────────────

	/** @test */
	public function delete_all_for_order_deletes_from_correct_table(): void {
		$this->repo->deleteAllForOrder( 55 );

		$this->assertCount( 1, $this->wpdb_spy->deletes );
		$this->assertSame( 'wp_raffle_tickets', $this->wpdb_spy->deletes[0]['table'] );
	}

	/** @test */
	public function delete_all_for_order_filters_by_order_id_only(): void {
		$this->repo->deleteAllForOrder( 77 );

		$where = $this->wpdb_spy->deletes[0]['where'];
		// Only order_id in the WHERE — no roll_id filter so both assigned and pending are removed.
		$this->assertSame( 77, $where['order_id'] );
		$this->assertArrayNotHasKey( 'roll_id', $where );
	}

	// ── findByOrder() ─────────────────────────────────────────────────────────

	/** @test */
	public function find_by_order_returns_empty_array_when_no_results(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$result = $this->repo->findByOrder( 999 );

		$this->assertSame( array(), $result );
	}

	/** @test */
	public function find_by_order_returns_rows_from_wpdb(): void {
		$row = (object) array( 'ticket_number' => 'R-1001', 'order_id' => 100, 'roll_id' => 3 );
		$this->wpdb_spy->setGetResultsReturn( array( $row ) );

		$result = $this->repo->findByOrder( 100 );

		$this->assertCount( 1, $result );
		$this->assertSame( 'R-1001', $result[0]->ticket_number );
	}

	/** @test */
	public function find_by_order_query_filters_to_assigned_tickets_only(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$this->repo->findByOrder( 42 );

		$query = $this->wpdb_spy->queries[0];
		$this->assertStringContainsString( 'roll_id IS NOT NULL', $query );
		$this->assertStringContainsString( '42', $query );
	}

	/** @test */
	public function find_by_order_query_joins_rolls_table(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$this->repo->findByOrder( 10 );

		$query = $this->wpdb_spy->queries[0];
		$this->assertStringContainsString( 'raffle_ticket_rolls', $query );
		$this->assertStringContainsString( 'roll_label', $query );
		$this->assertStringContainsString( 'roll_start', $query );
		$this->assertStringContainsString( 'roll_last', $query );
	}

	// ── findAll() ─────────────────────────────────────────────────────────────

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
	public function find_all_query_joins_rolls_table_and_selects_roll_columns(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$this->repo->findAll();

		$query = $this->wpdb_spy->queries[0];
		$this->assertStringContainsString( 'raffle_ticket_rolls', $query );
		$this->assertStringContainsString( 'roll_label', $query );
		$this->assertStringContainsString( 'roll_start', $query );
		$this->assertStringContainsString( 'roll_last', $query );
	}

	// ── hasAssignedTicketsForOrder() ──────────────────────────────────────────

	/** @test */
	public function has_assigned_tickets_for_order_returns_true_when_count_positive(): void {
		$this->wpdb_spy->setGetVarReturn( '2' );

		$result = $this->repo->hasAssignedTicketsForOrder( 100 );

		$this->assertTrue( $result );
	}

	/** @test */
	public function has_assigned_tickets_for_order_returns_false_when_count_zero(): void {
		$this->wpdb_spy->setGetVarReturn( '0' );

		$result = $this->repo->hasAssignedTicketsForOrder( 100 );

		$this->assertFalse( $result );
	}

	/** @test */
	public function has_assigned_tickets_for_order_query_filters_by_roll_id_not_null(): void {
		$this->wpdb_spy->setGetVarReturn( '0' );

		$this->repo->hasAssignedTicketsForOrder( 55 );

		$this->assertStringContainsString( 'roll_id IS NOT NULL', $this->wpdb_spy->queries[0] );
	}

	// ── findProductsWithUnassignedTickets() ───────────────────────────────────

	/** @test */
	public function find_products_with_unassigned_tickets_returns_int_array(): void {
		$this->wpdb_spy->setGetColReturn( array( '5', '12' ) );

		$result = $this->repo->findProductsWithUnassignedTickets();

		$this->assertSame( array( 5, 12 ), $result );
	}

	/** @test */
	public function find_products_with_unassigned_tickets_returns_empty_when_none(): void {
		$this->wpdb_spy->setGetColReturn( array() );

		$result = $this->repo->findProductsWithUnassignedTickets();

		$this->assertSame( array(), $result );
	}

	/** @test */
	public function find_products_with_unassigned_tickets_query_filters_null_roll_id(): void {
		$this->wpdb_spy->setGetColReturn( array() );

		$this->repo->findProductsWithUnassignedTickets();

		$this->assertStringContainsString( 'roll_id IS NULL', $this->wpdb_spy->queries[0] );
	}
}
