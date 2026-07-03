<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Ticket\TicketNumber;
use WpWoocommerceRaffleTicket\Ticket\TicketNumberGenerator;
use WpWoocommerceRaffleTicket\Ticket\TicketRoll;

class TicketNumberGeneratorTest extends TestCase {

	private TicketNumberGenerator $generator;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->generator = new TicketNumberGenerator();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function makeRoll( int $start, int $count, string $direction = 'asc' ): TicketRoll {
		return new TicketRoll( 1, 10, 'Roll A', $start, $count, 0, 0, $direction );
	}

	/** @test */
	public function it_returns_a_ticket_number_instance(): void {
		$roll   = $this->makeRoll( 1, 9999 );
		$ticket = $this->generator->generate( 'RAFFLE-', $roll, 1 );

		$this->assertInstanceOf( TicketNumber::class, $ticket );
	}

	/** @test */
	public function it_formats_with_padding_based_on_last_number(): void {
		// start=1, count=9999 → last=9999 (4 digits), offset=1 → physical=1 → "R-0001".
		$roll   = $this->makeRoll( 1, 9999 );
		$ticket = $this->generator->generate( 'R-', $roll, 1 );

		$this->assertSame( 'R-0001', $ticket->getFormatted() );
	}

	/** @test */
	public function it_computes_physical_number_as_start_plus_offset_minus_one(): void {
		// start=1001, count=500, offset=1 → physical=1001; last=1500 (4 digits).
		$roll   = $this->makeRoll( 1001, 500 );
		$ticket = $this->generator->generate( 'RAFFLE-', $roll, 1 );

		$this->assertSame( 'RAFFLE-1001', $ticket->getFormatted() );
	}

	/** @test */
	public function it_advances_physical_number_with_offset(): void {
		// start=1001, count=500, offset=42 → physical=1042.
		$roll   = $this->makeRoll( 1001, 500 );
		$ticket = $this->generator->generate( 'R-', $roll, 42 );

		$this->assertSame( 'R-1042', $ticket->getFormatted() );
	}

	/** @test */
	public function it_produces_last_ticket_at_offset_equal_to_count(): void {
		// start=1001, count=500, offset=500 → physical=1500.
		$roll   = $this->makeRoll( 1001, 500 );
		$ticket = $this->generator->generate( 'R-', $roll, 500 );

		$this->assertSame( 'R-1500', $ticket->getFormatted() );
	}

	/** @test */
	public function it_uses_correct_padding_for_three_digit_last_number(): void {
		// start=1, count=100 → last=100 (3 digits), offset=5 → physical=5 → "T-005".
		$roll   = $this->makeRoll( 1, 100 );
		$ticket = $this->generator->generate( 'T-', $roll, 5 );

		$this->assertSame( 'T-005', $ticket->getFormatted() );
	}

	/** @test */
	public function it_does_not_truncate_physical_number_exceeding_pad_width(): void {
		// start=1, count=9 → last=9 (1 digit), offset=10 → physical=10 (2 digits).
		$roll   = $this->makeRoll( 1, 9 );
		$ticket = $this->generator->generate( 'X-', $roll, 10 );

		$this->assertSame( 'X-10', $ticket->getFormatted() );
	}

	/** @test */
	public function it_handles_empty_prefix(): void {
		// start=1, count=999 → last=999 (3 digits), offset=7 → "007".
		$roll   = $this->makeRoll( 1, 999 );
		$ticket = $this->generator->generate( '', $roll, 7 );

		$this->assertSame( '007', $ticket->getFormatted() );
	}

	/** @test */
	public function it_stores_physical_number_as_sequence(): void {
		// start=2000, count=100, offset=1 → physical=2000.
		$roll   = $this->makeRoll( 2000, 100 );
		$ticket = $this->generator->generate( 'DRAW-', $roll, 1 );

		$this->assertSame( 2000, $ticket->getSequence() );
	}

	/** @test */
	public function it_stores_prefix_on_value_object(): void {
		$roll   = $this->makeRoll( 1, 100 );
		$ticket = $this->generator->generate( 'DRAW-', $roll, 1 );

		$this->assertSame( 'DRAW-', $ticket->getPrefix() );
	}

	// ── Descending rolls ──────────────────────────────────────────────────────

	/** @test */
	public function descending_first_offset_returns_start_number(): void {
		// start=1000, count=500, offset=1 → physical=1000; last=501 (4 digits each).
		$roll   = $this->makeRoll( 1000, 500, 'desc' );
		$ticket = $this->generator->generate( 'R-', $roll, 1 );

		$this->assertSame( 'R-1000', $ticket->getFormatted() );
		$this->assertSame( 1000, $ticket->getSequence() );
	}

	/** @test */
	public function descending_advances_downward_with_offset(): void {
		// start=1000, count=500, offset=10 → physical=991.
		$roll   = $this->makeRoll( 1000, 500, 'desc' );
		$ticket = $this->generator->generate( 'R-', $roll, 10 );

		$this->assertSame( 'R-0991', $ticket->getFormatted() );
	}

	/** @test */
	public function descending_last_ticket_at_offset_equal_to_count(): void {
		// start=1000, count=500, offset=500 → physical=501.
		$roll   = $this->makeRoll( 1000, 500, 'desc' );
		$ticket = $this->generator->generate( 'R-', $roll, 500 );

		$this->assertSame( 'R-0501', $ticket->getFormatted() );
	}

	/** @test */
	public function descending_pads_based_on_start_number_digits(): void {
		// start=100, count=100, offset=1 → physical=100; last=1 → pad=3.
		$roll   = $this->makeRoll( 100, 100, 'desc' );
		$ticket = $this->generator->generate( 'T-', $roll, 100 );

		// last ticket is 1, padded to 3 digits (width of start=100).
		$this->assertSame( 'T-001', $ticket->getFormatted() );
	}
}
