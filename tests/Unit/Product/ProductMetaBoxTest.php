<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Product\ProductMetaBox;
use WpWoocommerceRaffleTicket\Product\ProductSettings;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class WpPostStub extends WP_Post {
	public function __construct( int $id ) {
		$this->ID = $id;
	}
}

// ── Test case ─────────────────────────────────────────────────────────────────

class ProductMetaBoxTest extends TestCase {

	private ProductMetaBox $meta_box;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->meta_box = new ProductMetaBox();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		$_POST = array();
	}

	private function mockProductMeta( int $product_id ): void {
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_ENABLED, true ), 'return' => '1' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_PREFIX, true ), 'return' => 'RAFFLE-' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_MIN_SEQUENCE, true ), 'return' => '1' ) );
		WP_Mock::userFunction( 'get_post_meta', array( 'args' => array( $product_id, ProductSettings::META_MAX_SEQUENCE, true ), 'return' => '9999' ) );
	}

	/** @test */
	public function register_calls_add_meta_box_for_product_post_type(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'add_meta_box',
			array(
				'times' => 1,
				'args'  => array(
					'raffle_ticket_settings',
					\Mockery::any(),
					\Mockery::any(),
					'product',
					'normal',
					'default',
				),
			)
		);

		$this->meta_box->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function save_returns_early_when_nonce_field_is_absent(): void {
		$this->expectNotToPerformAssertions();

		$_POST = array();

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return' => '' ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );
		// update_post_meta must NOT be called — WP_Mock will throw if it is.

		$this->meta_box->save( 42 );
	}

	/** @test */
	public function save_returns_early_when_nonce_is_invalid(): void {
		$this->expectNotToPerformAssertions();

		$_POST[ ProductMetaBox::NONCE_FIELD ] = 'bad-nonce';

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );

		$this->meta_box->save( 42 );
	}

	/** @test */
	public function save_returns_early_without_capability(): void {
		$this->expectNotToPerformAssertions();

		$_POST[ ProductMetaBox::NONCE_FIELD ] = 'valid';

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => false ) );

		$this->meta_box->save( 42 );
	}

	/** @test */
	public function save_calls_update_post_meta_four_times_on_success(): void {
		$_POST[ ProductMetaBox::NONCE_FIELD ] = 'valid';
		$_POST['raffle_ticket_enabled']       = '1';
		$_POST['raffle_ticket_prefix']        = 'RAFFLE-';
		$_POST['raffle_ticket_min_sequence']  = '1';
		$_POST['raffle_ticket_max_sequence']  = '9999';

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'update_post_meta', array( 'times' => 4, 'return' => true ) );

		$this->meta_box->save( 42 );

		// Assertion satisfied by WP_Mock verifying times => 4 at tearDown.
		$this->assertTrue( true );
	}

	/** @test */
	public function save_writes_empty_string_for_disabled_product(): void {
		$_POST[ ProductMetaBox::NONCE_FIELD ] = 'valid';
		$_POST['raffle_ticket_prefix']        = 'X-';
		$_POST['raffle_ticket_min_sequence']  = '1';
		$_POST['raffle_ticket_max_sequence']  = '100';
		// 'raffle_ticket_enabled' intentionally absent.

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );

		$updated = array();
		WP_Mock::userFunction(
			'update_post_meta',
			array(
				'times'  => 4,
				'return' => function ( $post_id, $key, $value ) use ( &$updated ) {
					$updated[ $key ] = $value;
					return true;
				},
			)
		);

		$this->meta_box->save( 42 );

		$this->assertSame( '', $updated[ ProductSettings::META_ENABLED ] );
	}
}
