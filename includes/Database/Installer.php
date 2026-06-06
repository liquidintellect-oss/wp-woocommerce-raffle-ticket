<?php
/**
 * Database installer.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Installer
 *
 * Creates or upgrades custom database tables used by the raffle ticket plugin.
 */
class Installer {

	/**
	 * Install / upgrade database tables.
	 *
	 * Loads WordPress's dbDelta() helper and applies the CREATE TABLE
	 * statements for the raffle_tickets and raffle_ticket_sequences tables.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$tickets_table = $wpdb->prefix . 'raffle_tickets';
		$sql_tickets   = "CREATE TABLE {$tickets_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ticket_number VARCHAR(100) NOT NULL,
			ticket_prefix VARCHAR(50) NOT NULL,
			ticket_sequence INT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY ticket_number (ticket_number),
			KEY order_id (order_id),
			KEY product_id (product_id)
		) {$charset_collate};";

		$sequences_table = $wpdb->prefix . 'raffle_ticket_sequences';
		$sql_sequences   = "CREATE TABLE {$sequences_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			current_sequence INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY product_id (product_id)
		) {$charset_collate};";

		dbDelta( $sql_tickets );
		dbDelta( $sql_sequences );
	}
}
