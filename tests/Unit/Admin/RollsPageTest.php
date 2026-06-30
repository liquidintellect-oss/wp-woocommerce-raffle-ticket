<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Admin\RollsPage;
use WpWoocommerceRaffleTicket\Ticket\RollRepository;

// ── Test case ─────────────────────────────────────────────────────────────────

class RollsPageTest extends TestCase {

	private RollRepository $roll_repo;
	private RollsPage $page;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->roll_repo = $this->createMock( RollRepository::class );
		$this->page      = new RollsPage( $this->roll_repo, 'Raffle Tickets' );
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		$_GET  = array();
		$_POST = array();
	}

	// ── register() ───────────────────────────────────────────────────────────

	/** @test */
	public function register_adds_submenu_page_under_woocommerce(): void {
		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'add_submenu_page',
			array(
				'times' => 1,
				'args'  => array(
					'woocommerce',
					\Mockery::any(),
					\Mockery::any(),
					'manage_woocommerce',
					RollsPage::PAGE_SLUG,
					\Mockery::any(),
				),
			)
		);

		$this->page->register();

		$this->addToAssertionCount( 1 );
	}

	// ── maybeAddRoll() ────────────────────────────────────────────────────────

	/** @test */
	public function maybe_add_roll_does_nothing_when_action_absent(): void {
		$this->expectNotToPerformAssertions();

		$_POST = array();

		$this->page->maybeAddRoll();
	}

	/** @test */
	public function maybe_add_roll_does_nothing_for_wrong_action(): void {
		$this->expectNotToPerformAssertions();

		$_POST = array(
			'action'            => 'delete_roll',
			'raffle_rolls_page' => RollsPage::PAGE_SLUG,
		);

		$this->page->maybeAddRoll();
	}

	/** @test */
	public function maybe_add_roll_does_nothing_without_page_marker(): void {
		$this->expectNotToPerformAssertions();

		$_POST = array( 'action' => 'add_roll' );

		$this->page->maybeAddRoll();
	}

	/** @test */
	public function maybe_add_roll_dies_on_invalid_nonce(): void {
		$_POST = array(
			'action'            => 'add_roll',
			'raffle_rolls_page' => RollsPage::PAGE_SLUG,
			'_wpnonce'          => 'bad',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->page->maybeAddRoll();
	}

	/** @test */
	public function maybe_add_roll_dies_when_user_lacks_capability(): void {
		$_POST = array(
			'action'            => 'add_roll',
			'raffle_rolls_page' => RollsPage::PAGE_SLUG,
			'_wpnonce'          => 'valid',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->page->maybeAddRoll();
	}

	/** @test */
	public function maybe_add_roll_calls_create_and_redirects(): void {
		$_POST = array(
			'action'            => 'add_roll',
			'raffle_rolls_page' => RollsPage::PAGE_SLUG,
			'_wpnonce'          => 'valid',
			'roll_product_id'   => '10',
			'roll_label'        => 'Roll A',
			'roll_start_number' => '1001',
			'roll_ticket_count' => '500',
			'roll_sort_order'   => '0',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/wp-admin/admin.php?page=' . RollsPage::PAGE_SLUG ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->roll_repo
			->expects( $this->once() )
			->method( 'create' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_safe_redirect' );
		$this->page->maybeAddRoll();
	}

	/** @test */
	public function maybe_add_roll_skips_create_when_product_id_is_zero(): void {
		$_POST = array(
			'action'            => 'add_roll',
			'raffle_rolls_page' => RollsPage::PAGE_SLUG,
			'_wpnonce'          => 'valid',
			'roll_product_id'   => '0',
			'roll_ticket_count' => '500',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/redirect' ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->roll_repo->expects( $this->never() )->method( 'create' );

		$this->expectException( \RuntimeException::class );
		$this->page->maybeAddRoll();
	}

	// ── maybeDeleteRoll() ─────────────────────────────────────────────────────

	/** @test */
	public function maybe_delete_roll_does_nothing_when_page_absent(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array();

		$this->page->maybeDeleteRoll();
	}

	/** @test */
	public function maybe_delete_roll_does_nothing_for_wrong_action(): void {
		$this->expectNotToPerformAssertions();

		$_GET = array(
			'page'   => RollsPage::PAGE_SLUG,
			'action' => 'add_roll',
		);

		$this->page->maybeDeleteRoll();
	}

	/** @test */
	public function maybe_delete_roll_dies_on_invalid_nonce(): void {
		$_GET = array(
			'page'     => RollsPage::PAGE_SLUG,
			'action'   => 'delete_roll',
			'roll_id'  => '3',
			'_wpnonce' => 'bad',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => false ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'wp_die',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_die' );
				},
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->page->maybeDeleteRoll();
	}

	/** @test */
	public function maybe_delete_roll_calls_delete_and_redirects(): void {
		$_GET = array(
			'page'     => RollsPage::PAGE_SLUG,
			'action'   => 'delete_roll',
			'roll_id'  => '5',
			'_wpnonce' => 'valid',
		);

		WP_Mock::userFunction( 'sanitize_text_field', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_unslash', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_verify_nonce', array( 'return' => true ) );
		WP_Mock::userFunction( 'current_user_can', array( 'return' => true ) );
		WP_Mock::userFunction( 'absint', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/redirect' ) );
		WP_Mock::userFunction(
			'wp_safe_redirect',
			array(
				'times'  => 1,
				'return' => static function () {
					throw new \RuntimeException( 'wp_safe_redirect' );
				},
			)
		);

		$this->roll_repo
			->expects( $this->once() )
			->method( 'delete' )
			->with( 5 );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_safe_redirect' );
		$this->page->maybeDeleteRoll();
	}

	// ── renderRollsTable() ────────────────────────────────────────────────────

	/** @test */
	public function render_rolls_table_shows_no_rolls_message_when_empty(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		ob_start();
		$this->page->renderRollsTable( array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No rolls', $output );
	}

	/** @test */
	public function render_rolls_table_shows_roll_data(): void {
		$row = (object) array(
			'id'             => 1,
			'product_name'   => 'Lucky Draw',
			'label'          => 'Roll A',
			'start_number'   => 1001,
			'ticket_count'   => 500,
			'current_offset' => 10,
			'sort_order'     => 0,
		);

		WP_Mock::userFunction( '__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_url', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_js', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'wp_nonce_url', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'http://example.com/delete' ) );
		WP_Mock::userFunction( 'admin_url', array( 'return' => 'http://example.com/wp-admin/admin.php' ) );

		ob_start();
		$this->page->renderRollsTable( array( $row ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Lucky Draw', $output );
		$this->assertStringContainsString( 'Roll A', $output );
		$this->assertStringContainsString( '1001', $output );
		$this->assertStringContainsString( '500', $output );
		// Last number: 1001 + 500 - 1 = 1500.
		$this->assertStringContainsString( '1500', $output );
		// Remaining: 500 - 10 = 490.
		$this->assertStringContainsString( '490', $output );
	}
}
