<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Ticket\TicketRoll;

class TicketRollTest extends TestCase {

	private function makeRoll(
		int $start_number,
		int $ticket_count,
		int $current_offset = 0,
		int $id = 1,
		int $product_id = 10,
		string $label = 'Roll A',
		int $sort_order = 0
	): TicketRoll {
		return new TicketRoll( $id, $product_id, $label, $start_number, $ticket_count, $current_offset, $sort_order );
	}

	/** @test */
	public function getters_return_constructor_values(): void {
		$roll = new TicketRoll( 5, 10, 'Test Roll', 1001, 500, 42, 3 );

		$this->assertSame( 5, $roll->getId() );
		$this->assertSame( 10, $roll->getProductId() );
		$this->assertSame( 'Test Roll', $roll->getLabel() );
		$this->assertSame( 1001, $roll->getStartNumber() );
		$this->assertSame( 500, $roll->getTicketCount() );
		$this->assertSame( 42, $roll->getCurrentOffset() );
		$this->assertSame( 3, $roll->getSortOrder() );
	}

	/** @test */
	public function get_last_number_is_start_plus_count_minus_one(): void {
		// start=1001, count=500 → last=1500.
		$roll = $this->makeRoll( 1001, 500 );

		$this->assertSame( 1500, $roll->getLastNumber() );
	}

	/** @test */
	public function get_last_number_single_ticket_roll(): void {
		// start=42, count=1 → last=42.
		$roll = $this->makeRoll( 42, 1 );

		$this->assertSame( 42, $roll->getLastNumber() );
	}

	/** @test */
	public function get_remaining_capacity_is_count_minus_offset(): void {
		$roll = $this->makeRoll( 1, 100, 60 );

		$this->assertSame( 40, $roll->getRemainingCapacity() );
	}

	/** @test */
	public function get_remaining_capacity_is_zero_when_exhausted(): void {
		$roll = $this->makeRoll( 1, 100, 100 );

		$this->assertSame( 0, $roll->getRemainingCapacity() );
	}

	/** @test */
	public function get_remaining_capacity_is_zero_when_over_exhausted(): void {
		// Should not happen in practice but guard against it.
		$roll = $this->makeRoll( 1, 100, 101 );

		$this->assertSame( 0, $roll->getRemainingCapacity() );
	}

	/** @test */
	public function get_remaining_capacity_is_full_count_when_untouched(): void {
		$roll = $this->makeRoll( 1, 250, 0 );

		$this->assertSame( 250, $roll->getRemainingCapacity() );
	}

	/** @test */
	public function is_exhausted_returns_false_when_capacity_remains(): void {
		$roll = $this->makeRoll( 1, 100, 99 );

		$this->assertFalse( $roll->isExhausted() );
	}

	/** @test */
	public function is_exhausted_returns_true_when_fully_consumed(): void {
		$roll = $this->makeRoll( 1, 100, 100 );

		$this->assertTrue( $roll->isExhausted() );
	}

	/** @test */
	public function is_exhausted_returns_true_when_over_consumed(): void {
		$roll = $this->makeRoll( 1, 100, 101 );

		$this->assertTrue( $roll->isExhausted() );
	}
}
