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

	private function mockMeta( int $product_id, string $enabled, string $prefix, string $min, string $max ): void {
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
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( $product_id, ProductSettings::META_MIN_SEQUENCE, true ),
				'return' => $min,
			)
		);
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'args'   => array( $product_id, ProductSettings::META_MAX_SEQUENCE, true ),
				'return' => $max,
			)
		);
	}

	/** @test */
	public function for_product_reads_all_four_meta_keys(): void {
		$this->mockMeta( 42, '1', 'RAFFLE-', '1', '9999' );

		$settings = ProductSettings::forProduct( 42 );

		$this->assertTrue( $settings->isEnabled() );
		$this->assertSame( 'RAFFLE-', $settings->getPrefix() );
		$this->assertSame( 1, $settings->getMinSequence() );
		$this->assertSame( 9999, $settings->getMaxSequence() );
	}

	/** @test */
	public function is_enabled_returns_false_when_meta_is_not_one(): void {
		$this->mockMeta( 1, '', 'X-', '1', '100' );

		$settings = ProductSettings::forProduct( 1 );

		$this->assertFalse( $settings->isEnabled() );
	}

	/** @test */
	public function is_enabled_returns_true_only_for_string_one(): void {
		$this->mockMeta( 1, '1', 'X-', '1', '100' );

		$settings = ProductSettings::forProduct( 1 );

		$this->assertTrue( $settings->isEnabled() );
	}

	/** @test */
	public function min_sequence_defaults_to_one_when_meta_missing(): void {
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_ENABLED, true ), 'return' => '1' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_PREFIX, true ), 'return' => 'T-' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_MIN_SEQUENCE, true ), 'return' => '' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_MAX_SEQUENCE, true ), 'return' => '100' ) );

		$settings = ProductSettings::forProduct( 5 );

		$this->assertSame( 1, $settings->getMinSequence() );
	}

	/** @test */
	public function max_sequence_defaults_to_9999_when_meta_missing(): void {
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_ENABLED, true ), 'return' => '1' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_PREFIX, true ), 'return' => 'T-' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_MIN_SEQUENCE, true ), 'return' => '1' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( 5, ProductSettings::META_MAX_SEQUENCE, true ), 'return' => '' ) );

		$settings = ProductSettings::forProduct( 5 );

		$this->assertSame( 9999, $settings->getMaxSequence() );
	}

	/** @test */
	public function constructor_stores_values_directly(): void {
		$settings = new ProductSettings( true, 'PREFIX-', 10, 200 );

		$this->assertTrue( $settings->isEnabled() );
		$this->assertSame( 'PREFIX-', $settings->getPrefix() );
		$this->assertSame( 10, $settings->getMinSequence() );
		$this->assertSame( 200, $settings->getMaxSequence() );
	}
}
