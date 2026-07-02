<?php
/**
 * Plugin Name:       WooCommerce Raffle Ticket
 * Plugin URI:        https://liquidintellect.com
 * Description:       Assigns unique raffle ticket numbers to WooCommerce product purchases.
 * Version:           @projectVersion@
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Liquid Intellect, Inc. - OSS
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-woocommerce-raffle-ticket
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package WpWoocommerceRaffleTicket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Autoloader — maps WpWoocommerceRaffleTicket\ to includes/.
spl_autoload_register(
	function ( string $class_name ): void {
		$prefix   = 'WpWoocommerceRaffleTicket\\';
		$base_dir = __DIR__ . '/includes/';
		$len      = strlen( $prefix );

		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Activation hook — create / upgrade custom DB tables.
register_activation_hook(
	__FILE__,
	function (): void {
		( new WpWoocommerceRaffleTicket\Database\Installer() )->install();
	}
);

// Bootstrap after all plugins are loaded so WooCommerce is available.
// Also run a schema upgrade check so that updates deployed to an already-active
// plugin (which skip the activation hook) still migrate the database.
add_action(
	'plugins_loaded',
	function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$installer = new WpWoocommerceRaffleTicket\Database\Installer();

		if ( get_option( WpWoocommerceRaffleTicket\Database\Installer::DB_VERSION_OPTION ) !== WpWoocommerceRaffleTicket\Database\Installer::DB_VERSION ) {
			$installer->install();
		}

		( new WpWoocommerceRaffleTicket\Plugin() )->register();
	}
);
