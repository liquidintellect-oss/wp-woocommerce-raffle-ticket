<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;
use WpWoocommerceRaffleTicket\Ticket\TicketRoll;

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * $wpdb spy for RollRepository tests.
 *
 * Supports configuring the results returned by get_row(), get_var(),
 * get_results(), and query().  Records all insert() and delete() calls.
 */
class WpdbRollSpy {

	public string $prefix = 'wp_';
	public string $posts  = 'wp_posts';
	public array $queries = array();
	public array $inserts = array();
	public array $deletes = array();
	public int $insert_id = 0;

	private mixed $get_row_return     = null;
	private mixed $get_var_return     = null;
	private array $get_results_return = array();
	private int $query_return         = 1; // rows affected.

	public function setGetRowReturn( mixed $value ): void {
		$this->get_row_return = $value;
	}

	public function setGetVarReturn( mixed $value ): void {
		$this->get_var_return = $value;
	}

	public function setGetResultsReturn( array $value ): void {
		$this->get_results_return = $value;
	}

	public function setQueryReturn( int $value ): void {
		$this->query_return = $value;
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

	public function get_row( string $sql ): mixed {
		$this->queries[] = $sql;
		return $this->get_row_return;
	}

	public function get_var( string $sql ): mixed {
		$this->queries[] = $sql;
		return $this->get_var_return;
	}

	public function get_results( string $sql ): array {
		$this->queries[] = $sql;
		return $this->get_results_return;
	}

	public function query( string $sql ): int {
		$this->queries[] = $sql;
		return $this->query_return;
	}

	public function insert( string $table, array $data, array $format ): int {
		$this->inserts[] = compact( 'table', 'data', 'format' );
		return 1;
	}

	public function delete( string $table, array $where, array $where_format ): int {
		$this->deletes[] = compact( 'table', 'where', 'where_format' );
		return 1;
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class RollRepositoryTest extends TestCase {

	private WpdbRollSpy $wpdb_spy;
	private RollRepository $repo;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->wpdb_spy  = new WpdbRollSpy();
		$GLOBALS['wpdb'] = $this->wpdb_spy;
		$this->repo      = new RollRepository();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		unset( $GLOBALS['wpdb'] );
	}

	// ── nextTicket() ──────────────────────────────────────────────────────────

	/** @test */
	public function next_ticket_returns_null_when_no_rolls_available(): void {
		$this->wpdb_spy->setGetRowReturn( null );

		$result = $this->repo->nextTicket( 10 );

		$this->assertNull( $result );
	}

	/** @test */
	public function next_ticket_returns_slot_array_on_success(): void {
		$roll = (object) array(
			'id'             => 3,
			'start_number'   => 1001,
			'ticket_count'   => 500,
			'current_offset' => 0,
		);
		$this->wpdb_spy->setGetRowReturn( $roll );
		$this->wpdb_spy->setQueryReturn( 1 );   // UPDATE affected one row.
		$this->wpdb_spy->setGetVarReturn( '7' ); // Simulated new offset value.

		$result = $this->repo->nextTicket( 10 );

		$this->assertIsArray( $result );
		$this->assertSame( 3, $result['roll_id'] );
		$this->assertSame( 1001, $result['start_number'] );
		$this->assertSame( 500, $result['ticket_count'] );
		$this->assertSame( 7, $result['offset'] );
	}

	/** @test */
	public function next_ticket_issues_select_with_product_id(): void {
		$roll = (object) array(
			'id'             => 1,
			'start_number'   => 1,
			'ticket_count'   => 100,
			'current_offset' => 0,
		);
		$this->wpdb_spy->setGetRowReturn( $roll );
		$this->wpdb_spy->setGetVarReturn( '1' );

		$this->repo->nextTicket( 42 );

		$this->assertStringContainsString( '42', $this->wpdb_spy->queries[0] );
	}

	/** @test */
	public function next_ticket_issues_update_with_last_insert_id(): void {
		$roll = (object) array(
			'id'             => 5,
			'start_number'   => 100,
			'ticket_count'   => 50,
			'current_offset' => 10,
		);
		$this->wpdb_spy->setGetRowReturn( $roll );
		$this->wpdb_spy->setGetVarReturn( '11' );

		$this->repo->nextTicket( 10 );

		$update_query = $this->wpdb_spy->queries[1];
		$this->assertStringContainsString( 'UPDATE', $update_query );
		$this->assertStringContainsString( 'LAST_INSERT_ID', $update_query );
	}

	/** @test */
	public function next_ticket_returns_null_after_max_retries_on_race(): void {
		// query() always returns 0 rows affected (race condition — roll always exhausted).
		$roll = (object) array(
			'id'             => 1,
			'start_number'   => 1,
			'ticket_count'   => 100,
			'current_offset' => 0,
		);
		$this->wpdb_spy->setGetRowReturn( $roll );
		$this->wpdb_spy->setQueryReturn( 0 ); // Always fails — simulates race.

		$result = $this->repo->nextTicket( 10 );

		$this->assertNull( $result );
	}

	// ── remainingForProduct() ─────────────────────────────────────────────────

	/** @test */
	public function remaining_for_product_returns_integer_from_query(): void {
		$this->wpdb_spy->setGetVarReturn( '75' );

		$result = $this->repo->remainingForProduct( 10 );

		$this->assertSame( 75, $result );
	}

	/** @test */
	public function remaining_for_product_returns_zero_when_no_rolls(): void {
		$this->wpdb_spy->setGetVarReturn( null );

		$result = $this->repo->remainingForProduct( 10 );

		$this->assertSame( 0, $result );
	}

	// ── findByProduct() ───────────────────────────────────────────────────────

	/** @test */
	public function find_by_product_returns_empty_array_when_no_rows(): void {
		$this->wpdb_spy->setGetResultsReturn( array() );

		$result = $this->repo->findByProduct( 10 );

		$this->assertSame( array(), $result );
	}

	/** @test */
	public function find_by_product_returns_ticket_roll_objects(): void {
		$row = (object) array(
			'id'             => 1,
			'product_id'     => 10,
			'label'          => 'Roll A',
			'start_number'   => 1001,
			'ticket_count'   => 500,
			'current_offset' => 0,
			'sort_order'     => 0,
		);
		$this->wpdb_spy->setGetResultsReturn( array( $row ) );

		$result = $this->repo->findByProduct( 10 );

		$this->assertCount( 1, $result );
		$this->assertInstanceOf( TicketRoll::class, $result[0] );
		$this->assertSame( 1001, $result[0]->getStartNumber() );
	}

	// ── create() ─────────────────────────────────────────────────────────────

	/** @test */
	public function create_inserts_into_rolls_table(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-01-01 10:00:00' ) );
		$this->wpdb_spy->insert_id = 7;

		$id = $this->repo->create( 10, 'Roll A', 1001, 500, 0 );

		$this->assertCount( 1, $this->wpdb_spy->inserts );
		$this->assertSame( 'wp_raffle_ticket_rolls', $this->wpdb_spy->inserts[0]['table'] );
	}

	/** @test */
	public function create_stores_correct_data(): void {
		WP_Mock::userFunction( 'current_time', array( 'return' => '2025-06-01 12:00:00' ) );

		$this->repo->create( 10, 'Roll B', 2001, 250, 1 );

		$data = $this->wpdb_spy->inserts[0]['data'];
		$this->assertSame( 10, $data['product_id'] );
		$this->assertSame( 'Roll B', $data['label'] );
		$this->assertSame( 2001, $data['start_number'] );
		$this->assertSame( 250, $data['ticket_count'] );
		$this->assertSame( 0, $data['current_offset'] );
		$this->assertSame( 1, $data['sort_order'] );
	}

	// ── delete() ─────────────────────────────────────────────────────────────

	/** @test */
	public function delete_removes_roll_by_id(): void {
		$this->repo->delete( 5 );

		$this->assertCount( 1, $this->wpdb_spy->deletes );
		$this->assertSame( 'wp_raffle_ticket_rolls', $this->wpdb_spy->deletes[0]['table'] );
		$this->assertSame( 5, $this->wpdb_spy->deletes[0]['where']['id'] );
	}
}
