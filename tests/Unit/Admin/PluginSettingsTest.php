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

	// ── addSection() ──────────────────────────────────────────────────────────

	/** @test */
	public function add_section_appends_raffle_tickets_entry(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$result = $this->settings->addSection( array() );

		$this->assertArrayHasKey( PluginSettings::SECTION_ID, $result );
		$this->assertSame( 'Raffle Tickets', $result[ PluginSettings::SECTION_ID ] );
	}

	/** @test */
	public function add_section_preserves_existing_sections(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$existing = array( 'inventory' => 'Inventory', 'downloadable' => 'Downloadable products' );
		$result   = $this->settings->addSection( $existing );

		$this->assertArrayHasKey( 'inventory', $result );
		$this->assertArrayHasKey( 'downloadable', $result );
		$this->assertArrayHasKey( PluginSettings::SECTION_ID, $result );
	}

	// ── addSettings() ─────────────────────────────────────────────────────────

	/** @test */
	public function add_settings_returns_unchanged_settings_for_different_section(): void {
		$existing = array( array( 'id' => 'some_other_setting', 'type' => 'text' ) );

		$result = $this->settings->addSettings( $existing, 'inventory' );

		$this->assertSame( $existing, $result );
	}

	/** @test */
	public function add_settings_returns_plugin_fields_for_raffle_tickets_section(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$result = $this->settings->addSettings( array(), PluginSettings::SECTION_ID );

		// Should contain a title, the label field, and a sectionend.
		$this->assertCount( 3, $result );
	}

	/** @test */
	public function add_settings_includes_label_field_with_correct_option_key(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$result = $this->settings->addSettings( array(), PluginSettings::SECTION_ID );

		$field_ids = array_column( $result, 'id' );
		$this->assertContains( PluginSettings::OPTION_KEY, $field_ids );
	}

	/** @test */
	public function add_settings_label_field_has_correct_default(): void {
		WP_Mock::userFunction( 'esc_html__', array( 'return_arg' => 0 ) );

		$result = $this->settings->addSettings( array(), PluginSettings::SECTION_ID );

		$label_field = array_values(
			array_filter( $result, fn( $f ) => isset( $f['id'] ) && PluginSettings::OPTION_KEY === $f['id'] )
		)[0] ?? null;

		$this->assertNotNull( $label_field );
		$this->assertSame( PluginSettings::DEFAULT_LABEL, $label_field['default'] );
	}

	// ── register() ────────────────────────────────────────────────────────────

	/** @test */
	public function register_adds_woocommerce_section_and_settings_filters(): void {
		WP_Mock::expectFilterAdded( 'woocommerce_get_sections_products', array( $this->settings, 'addSection' ) );
		WP_Mock::expectFilterAdded( 'woocommerce_get_settings_products', array( $this->settings, 'addSettings' ), 10, 2 );

		$this->settings->register();

		$this->addToAssertionCount( 1 );
	}
}
