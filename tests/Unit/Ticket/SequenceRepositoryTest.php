<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Ticket\SequenceRepository;

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * Minimal $wpdb spy for SequenceRepository tests.
 *
 * Records every query() call and allows configuring what get_var() returns.
 */
class WpdbSequenceSpy {

	public string $prefix         = 'wp_';
	public array $queries         = array();
	private mixed $get_var_return = '0';

	public function setGetVarReturn( mixed $value ): void {
		$this->get_var_return = $value;
	}

	public function prepare( string $query, mixed ...$args ): string {
		// Replace %d/%s with the provided args (simplified — sufficient for tests).
		$i = 0;
		return preg_replace_callback(
			'/%[ds]/',
			function () use ( $args, &$i ) {
				return $args[ $i++ ] ?? '';
			},
			$query
		);
	}

	public function query( string $sql ): int {
		$this->queries[] = $sql;
		return 1;
	}

	public function get_var( string $sql ): mixed {
		$this->queries[] = $sql;
		return $this->get_var_return;
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class SequenceRepositoryTest extends TestCase {

	private WpdbSequenceSpy $wpdb_spy;
	private SequenceRepository $repo;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->wpdb_spy  = new WpdbSequenceSpy();
		$GLOBALS['wpdb'] = $this->wpdb_spy;
		$this->repo      = new SequenceRepository();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		unset( $GLOBALS['wpdb'] );
	}

	/** @test */
	public function next_sequence_returns_value_from_last_insert_id(): void {
		$this->wpdb_spy->setGetVarReturn( '5' );

		$result = $this->repo->nextSequence( 10, 1, 100 );

		$this->assertSame( 5, $result );
	}

	/** @test */
	public function next_sequence_issues_insert_on_duplicate_query(): void {
		$this->wpdb_spy->setGetVarReturn( '1' );

		$this->repo->nextSequence( 10, 1, 100 );

		$insert_query = $this->wpdb_spy->queries[0];
		$this->assertStringContainsString( 'INSERT INTO', $insert_query );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $insert_query );
	}

	/** @test */
	public function next_sequence_issues_update_with_last_insert_id(): void {
		$this->wpdb_spy->setGetVarReturn( '1' );

		$this->repo->nextSequence( 10, 1, 100 );

		$update_query = $this->wpdb_spy->queries[1];
		$this->assertStringContainsString( 'UPDATE', $update_query );
		$this->assertStringContainsString( 'LAST_INSERT_ID', $update_query );
	}

	/** @test */
	public function next_sequence_selects_last_insert_id(): void {
		$this->wpdb_spy->setGetVarReturn( '3' );

		$this->repo->nextSequence( 10, 1, 100 );

		$select_query = $this->wpdb_spy->queries[2];
		$this->assertStringContainsString( 'SELECT LAST_INSERT_ID()', $select_query );
	}

	/** @test */
	public function next_sequence_throws_when_sequence_exceeds_max(): void {
		$this->wpdb_spy->setGetVarReturn( '101' ); // Exceeds max=100.

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'sold_out' );

		$this->repo->nextSequence( 10, 1, 100 );
	}

	/** @test */
	public function next_sequence_does_not_throw_when_sequence_equals_max(): void {
		$this->wpdb_spy->setGetVarReturn( '100' );

		$result = $this->repo->nextSequence( 10, 1, 100 );

		$this->assertSame( 100, $result );
	}

	/** @test */
	public function remaining_returns_full_range_when_no_row_exists(): void {
		$this->wpdb_spy->setGetVarReturn( null );

		$result = $this->repo->remaining( 10, 1, 100 );

		// Full range: 100 - 1 + 1 = 100.
		$this->assertSame( 100, $result );
	}

	/** @test */
	public function remaining_returns_correct_count_after_some_issued(): void {
		$this->wpdb_spy->setGetVarReturn( '10' ); // current_sequence = 10.

		$result = $this->repo->remaining( 10, 1, 100 );

		// max - current = 100 - 10 = 90.
		$this->assertSame( 90, $result );
	}

	/** @test */
	public function remaining_returns_zero_when_sold_out(): void {
		$this->wpdb_spy->setGetVarReturn( '100' ); // current_sequence = max.

		$result = $this->repo->remaining( 10, 1, 100 );

		$this->assertSame( 0, $result );
	}

	/** @test */
	public function remaining_returns_zero_when_over_sold(): void {
		$this->wpdb_spy->setGetVarReturn( '101' ); // Over the limit.

		$result = $this->repo->remaining( 10, 1, 100 );

		$this->assertSame( 0, $result );
	}

	/** @test */
	public function next_sequence_seeds_insert_with_min_minus_one(): void {
		$this->wpdb_spy->setGetVarReturn( '5' );

		$this->repo->nextSequence( 10, 5, 100 ); // min=5 → seed with 4.

		$insert_query = $this->wpdb_spy->queries[0];
		$this->assertStringContainsString( '4', $insert_query );
	}
}
