<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Product\ProductSettings;
use WpWoocommerceRaffleTicket\Ticket\TicketNumber;
use WpWoocommerceRaffleTicket\Ticket\TicketNumberGenerator;

class TicketNumberGeneratorTest extends TestCase {

	private TicketNumberGenerator $generator;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->generator = new TicketNumberGenerator();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function makeSettings(
		string $prefix,
		int $min,
		int $max,
		bool $enabled = true
	): ProductSettings {
		return new ProductSettings( $enabled, $prefix, $min, $max );
	}

	/** @test */
	public function it_returns_a_ticket_number_instance(): void {
		$settings = $this->makeSettings( 'RAFFLE-', 1, 9999 );
		$ticket   = $this->generator->generate( $settings, 1 );

		$this->assertInstanceOf( TicketNumber::class, $ticket );
	}

	/** @test */
	public function it_pads_sequence_to_width_of_max(): void {
		// max=9999 → 4-digit padding → sequence 42 → "0042"
		$settings = $this->makeSettings( 'R-', 1, 9999 );
		$ticket   = $this->generator->generate( $settings, 42 );

		$this->assertSame( 'R-0042', $ticket->getFormatted() );
	}

	/** @test */
	public function it_uses_correct_padding_for_three_digit_max(): void {
		// max=100 → 3-digit padding → sequence 5 → "005"
		$settings = $this->makeSettings( 'T-', 1, 100 );
		$ticket   = $this->generator->generate( $settings, 5 );

		$this->assertSame( 'T-005', $ticket->getFormatted() );
	}

	/** @test */
	public function it_does_not_truncate_sequence_exceeding_padding_width(): void {
		// max=9 → 1-digit padding; sequence 42 still renders as "42" (no truncation).
		$settings = $this->makeSettings( 'X-', 1, 9 );
		$ticket   = $this->generator->generate( $settings, 42 );

		$this->assertSame( 'X-42', $ticket->getFormatted() );
	}

	/** @test */
	public function it_handles_empty_prefix(): void {
		$settings = $this->makeSettings( '', 1, 999 );
		$ticket   = $this->generator->generate( $settings, 7 );

		$this->assertSame( '007', $ticket->getFormatted() );
	}

	/** @test */
	public function it_stores_correct_prefix_and_sequence_on_value_object(): void {
		$settings = $this->makeSettings( 'DRAW-', 1, 9999 );
		$ticket   = $this->generator->generate( $settings, 1 );

		$this->assertSame( 'DRAW-', $ticket->getPrefix() );
		$this->assertSame( 1, $ticket->getSequence() );
	}

	/** @test */
	public function it_generates_min_sequence_correctly(): void {
		// min=100, max=999 → 3-digit padding; first ticket = "100"
		$settings = $this->makeSettings( 'F-', 100, 999 );
		$ticket   = $this->generator->generate( $settings, 100 );

		$this->assertSame( 'F-100', $ticket->getFormatted() );
	}
}
