<?php

use PHPUnit\Framework\TestCase;
use WP_Mock\Matcher\AnyInstance;
use WpWoocommerceRaffleTicket\Admin\PluginSettings;
use WpWoocommerceRaffleTicket\Admin\ReportPage;
use WpWoocommerceRaffleTicket\Order\OrderDisplay;
use WpWoocommerceRaffleTicket\Order\OrderHandler;
use WpWoocommerceRaffleTicket\Plugin;
use WpWoocommerceRaffleTicket\Product\ProductMetaBox;

class PluginTest extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	/** @test */
	public function register_hooks_woocommerce_order_status_processing(): void {
		WP_Mock::expectActionAdded(
			'woocommerce_order_status_processing',
			array( new AnyInstance( OrderHandler::class ), 'handle' ),
			10,
			1
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_cart_add_to_cart_validation_filter(): void {
		WP_Mock::expectFilterAdded(
			'woocommerce_add_to_cart_validation',
			array( new AnyInstance( OrderHandler::class ), 'validateCartAdd' ),
			10,
			3
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_add_meta_boxes(): void {
		WP_Mock::expectActionAdded(
			'add_meta_boxes',
			array( new AnyInstance( ProductMetaBox::class ), 'register' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_woocommerce_process_product_meta(): void {
		WP_Mock::expectActionAdded(
			'woocommerce_process_product_meta',
			array( new AnyInstance( ProductMetaBox::class ), 'save' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_customer_order_details(): void {
		WP_Mock::expectActionAdded(
			'woocommerce_order_details_after_order_table',
			array( new AnyInstance( OrderDisplay::class ), 'renderCustomer' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_admin_order_details(): void {
		WP_Mock::expectActionAdded(
			'woocommerce_admin_order_data_after_order_details',
			array( new AnyInstance( OrderDisplay::class ), 'renderAdmin' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_admin_menu(): void {
		WP_Mock::expectActionAdded(
			'admin_menu',
			array( new AnyInstance( ReportPage::class ), 'register' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_woocommerce_order_status_completed(): void {
		WP_Mock::expectActionAdded(
			'woocommerce_order_status_completed',
			array( new AnyInstance( OrderHandler::class ), 'handle' ),
			10,
			1
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_admin_init_for_csv_download(): void {
		WP_Mock::expectActionAdded(
			'admin_init',
			array( new AnyInstance( ReportPage::class ), 'maybeStreamCsv' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_admin_init_for_retroactive_assignment(): void {
		WP_Mock::expectActionAdded(
			'admin_init',
			array( new AnyInstance( ReportPage::class ), 'maybeAssignRetroactive' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function register_hooks_plugin_settings_admin_menu(): void {
		WP_Mock::expectActionAdded(
			'admin_menu',
			array( new AnyInstance( PluginSettings::class ), 'addMenuPage' )
		);

		( new Plugin() )->register();

		$this->addToAssertionCount( 1 );
	}
}
