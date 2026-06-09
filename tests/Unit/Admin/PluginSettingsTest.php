<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Admin\PluginSettings;

class PluginSettingsTest extends TestCase {

	private PluginSettings $settings;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->settings = new PluginSettings();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ── getLabel() ────────────────────────────────────────────────────────────

	/** @test */
	public function get_label_returns_default_when_option_is_not_set(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => PluginSettings::DEFAULT_LABEL,
			)
		);

		$this->assertSame( 'Raffle Tickets', PluginSettings::getLabel() );
	}

	/** @test */
	public function get_label_returns_custom_value_when_option_is_set(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => 'Lottery Tickets',
			)
		);

		$this->assertSame( 'Lottery Tickets', PluginSettings::getLabel() );
	}

	/** @test */
	public function get_label_falls_back_to_default_when_option_is_empty_string(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => '',
			)
		);

		$this->assertSame( 'Raffle Tickets', PluginSettings::getLabel() );
	}

	/** @test */
	public function get_label_falls_back_to_default_when_option_is_whitespace(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => '   ',
			)
		);

		$this->assertSame( 'Raffle Tickets', PluginSettings::getLabel() );
	}

	// ── register() ────────────────────────────────────────────────────────────

	/** @test */
	public function register_hooks_admin_menu_and_admin_init_actions(): void {
		WP_Mock::expectActionAdded( 'admin_menu', array( $this->settings, 'addMenuPage' ) );
		WP_Mock::expectActionAdded( 'admin_init', array( $this->settings, 'registerSettings' ) );

		$this->settings->register();

		$this->addToAssertionCount( 1 );
	}

	// ── addMenuPage() ─────────────────────────────────────────────────────────

	/** @test */
	public function add_menu_page_registers_submenu_under_woocommerce(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'add_submenu_page',
			array(
				'times' => 1,
				'args'  => array(
					'woocommerce',
					\Mockery::any(),
					\Mockery::any(),
					'manage_woocommerce',
					PluginSettings::PAGE_SLUG,
					\Mockery::any(),
				),
			)
		);

		$this->settings->addMenuPage();

		$this->addToAssertionCount( 1 );
	}

	// ── registerSettings() ────────────────────────────────────────────────────

	/** @test */
	public function register_settings_calls_register_setting_with_correct_option_key(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction(
			'register_setting',
			array(
				'times' => 1,
				'args'  => array( PluginSettings::OPTIONS_GROUP, PluginSettings::OPTION_KEY, \Mockery::any() ),
			)
		);
		WP_Mock::userFunction( 'add_settings_section', array( 'times' => 1 ) );
		WP_Mock::userFunction( 'add_settings_field', array( 'times' => 1 ) );

		$this->settings->registerSettings();

		$this->addToAssertionCount( 1 );
	}

	// ── renderLabelField() ────────────────────────────────────────────────────

	/** @test */
	public function render_label_field_outputs_text_input_with_current_value(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => 'Lottery Tickets',
			)
		);
		WP_Mock::userFunction( 'esc_attr', array( 'return_arg' => 0 ) );
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		ob_start();
		$this->settings->renderLabelField();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="text"', $output );
		$this->assertStringContainsString( PluginSettings::OPTION_KEY, $output );
		$this->assertStringContainsString( 'Lottery Tickets', $output );
	}
}
