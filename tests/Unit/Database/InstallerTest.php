<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Database\Installer;

// ── Fixtures ──────────────────────────────────────────────────────────────────

/**
 * $wpdb spy for Installer tests.
 */
class WpdbInstallerSpy {

	public string $prefix = 'wp_';

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class InstallerTest extends TestCase {

	private WpdbInstallerSpy $wpdb_spy;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->wpdb_spy                     = new WpdbInstallerSpy();
		$GLOBALS['wpdb']                    = $this->wpdb_spy;
		$GLOBALS['wp_raffle_dbdelta_calls'] = array(); // Reset capture array.
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		unset( $GLOBALS['wpdb'] );
	}

	/** @test */
	public function install_calls_db_delta_twice(): void {
		( new Installer() )->install();

		$this->assertCount( 2, $GLOBALS['wp_raffle_dbdelta_calls'] );
	}

	/** @test */
	public function install_creates_raffle_tickets_table(): void {
		( new Installer() )->install();

		$this->assertStringContainsString( 'wp_raffle_tickets', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_creates_raffle_ticket_sequences_table(): void {
		( new Installer() )->install();

		$this->assertStringContainsString( 'wp_raffle_ticket_sequences', $GLOBALS['wp_raffle_dbdelta_calls'][1] );
	}

	/** @test */
	public function install_includes_ticket_number_unique_key(): void {
		( new Installer() )->install();

		$this->assertStringContainsString( 'UNIQUE KEY', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_includes_charset_collate(): void {
		( new Installer() )->install();

		$this->assertStringContainsString( 'utf8mb4', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_tickets_table_has_order_id_index(): void {
		( new Installer() )->install();

		$this->assertStringContainsString( 'KEY order_id', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_sequences_table_has_unique_product_id(): void {
		( new Installer() )->install();

		$this->assertStringContainsString( 'UNIQUE KEY product_id', $GLOBALS['wp_raffle_dbdelta_calls'][1] );
	}
}
