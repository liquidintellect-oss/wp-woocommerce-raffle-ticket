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
	public function install_calls_db_delta_three_times(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertCount( 3, $GLOBALS['wp_raffle_dbdelta_calls'] );
	}

	/** @test */
	public function install_creates_raffle_tickets_table(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'wp_raffle_tickets', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_creates_raffle_ticket_sequences_table(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'wp_raffle_ticket_sequences', $GLOBALS['wp_raffle_dbdelta_calls'][1] );
	}

	/** @test */
	public function install_creates_raffle_ticket_rolls_table(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'wp_raffle_ticket_rolls', $GLOBALS['wp_raffle_dbdelta_calls'][2] );
	}

	/** @test */
	public function install_tickets_table_includes_roll_id_column(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'roll_id', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_includes_ticket_number_unique_key(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'UNIQUE KEY', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_includes_charset_collate(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'utf8mb4', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_tickets_table_has_order_id_index(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'KEY order_id', $GLOBALS['wp_raffle_dbdelta_calls'][0] );
	}

	/** @test */
	public function install_sequences_table_has_unique_product_id(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'UNIQUE KEY product_id', $GLOBALS['wp_raffle_dbdelta_calls'][1] );
	}

	/** @test */
	public function install_rolls_table_has_product_id_sort_index(): void {
		WP_Mock::userFunction( 'update_option' );

		( new Installer() )->install();

		$this->assertStringContainsString( 'product_id_sort', $GLOBALS['wp_raffle_dbdelta_calls'][2] );
	}

	/** @test */
	public function install_saves_db_version_option(): void {
		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
				'args'  => array( Installer::DB_VERSION_OPTION, Installer::DB_VERSION ),
			)
		);

		( new Installer() )->install();

		// Assertion satisfied by WP_Mock at tearDown.
		$this->assertTrue( true );
	}
}
