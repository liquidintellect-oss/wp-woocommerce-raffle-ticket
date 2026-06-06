<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Ticket\TicketNumber;

class TicketNumberTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	/** @test */
	public function it_stores_and_returns_all_parts(): void {
		$ticket = new TicketNumber( 'RAFFLE-', 42, 'RAFFLE-0042' );

		$this->assertSame( 'RAFFLE-', $ticket->getPrefix() );
		$this->assertSame( 42, $ticket->getSequence() );
		$this->assertSame( 'RAFFLE-0042', $ticket->getFormatted() );
	}

	/** @test */
	public function to_string_returns_formatted(): void {
		$ticket = new TicketNumber( 'T-', 1, 'T-001' );

		$this->assertSame( 'T-001', (string) $ticket );
	}

	/** @test */
	public function it_supports_empty_prefix(): void {
		$ticket = new TicketNumber( '', 7, '007' );

		$this->assertSame( '', $ticket->getPrefix() );
		$this->assertSame( '007', $ticket->getFormatted() );
	}

	/** @test */
	public function it_is_immutable_across_accesses(): void {
		$ticket = new TicketNumber( 'X-', 99, 'X-099' );

		// Calling getters multiple times returns the same value.
		$this->assertSame( $ticket->getFormatted(), $ticket->getFormatted() );
		$this->assertSame( $ticket->getSequence(), $ticket->getSequence() );
	}
}
