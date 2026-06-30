<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Product\ProductSettings;

class ProductSettingsTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function mockMeta( int $product_id, string $enabled, string $prefix ): void {
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( $product_id, ProductSettings::META_ENABLED, true ),
				'return' => $enabled,
			)
		);
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( $product_id, ProductSettings::META_PREFIX, true ),
				'return' => $prefix,
			)
		);
	}

	/** @test */
	public function for_product_reads_enabled_and_prefix_meta(): void {
		$this->mockMeta( 42, '1', 'RAFFLE-' );

		$settings = ProductSettings::forProduct( 42 );

		$this->assertTrue( $settings->isEnabled() );
		$this->assertSame( 'RAFFLE-', $settings->getPrefix() );
	}

	/** @test */
	public function is_enabled_returns_false_when_meta_is_not_one(): void {
		$this->mockMeta( 1, '', 'X-' );

		$settings = ProductSettings::forProduct( 1 );

		$this->assertFalse( $settings->isEnabled() );
	}

	/** @test */
	public function is_enabled_returns_true_only_for_string_one(): void {
		$this->mockMeta( 1, '1', 'X-' );

		$settings = ProductSettings::forProduct( 1 );

		$this->assertTrue( $settings->isEnabled() );
	}

	/** @test */
	public function constructor_stores_values_directly(): void {
		$settings = new ProductSettings( true, 'PREFIX-' );

		$this->assertTrue( $settings->isEnabled() );
		$this->assertSame( 'PREFIX-', $settings->getPrefix() );
	}

	/** @test */
	public function deprecated_meta_key_constants_are_defined(): void {
		// Ensure legacy constants exist for any migration code that references them.
		$this->assertSame( '_raffle_ticket_min_sequence', ProductSettings::META_MIN_SEQUENCE );
		$this->assertSame( '_raffle_ticket_max_sequence', ProductSettings::META_MAX_SEQUENCE );
	}
}
