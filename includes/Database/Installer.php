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
 *
 * Version history:
 *   1.0.0 — raffle_tickets + raffle_ticket_sequences tables.
 *   1.1.0 — raffle_ticket_rolls table; roll_id column on raffle_tickets;
 *            raffle_ticket_sequences table retained for schema compatibility.
 */
class Installer {

	/** Current schema version. */
	const DB_VERSION = '1.1.0';

	/** WordPress option key that stores the installed schema version. */
	const DB_VERSION_OPTION = 'wrt_db_version';

	/**
	 * Install / upgrade database tables.
	 *
	 * Loads WordPress's dbDelta() helper and applies CREATE TABLE statements for
	 * all plugin tables.  dbDelta() handles adding new columns to existing tables
	 * automatically.  Explicit migrations (via maybeRunMigrations) are run for
	 * changes that dbDelta cannot perform on its own.
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

		// ── raffle_tickets ────────────────────────────────────────────────────
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
			roll_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY ticket_number (ticket_number),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY roll_id (roll_id)
		) {$charset_collate};";

		dbDelta( $sql_tickets );

		// ── raffle_ticket_sequences ───────────────────────────────────────────
		// Retained for schema compatibility; no longer written to in 1.1.0+.
		$sequences_table = $wpdb->prefix . 'raffle_ticket_sequences';
		$sql_sequences   = "CREATE TABLE {$sequences_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			current_sequence INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY product_id (product_id)
		) {$charset_collate};";

		dbDelta( $sql_sequences );

		// ── raffle_ticket_rolls ───────────────────────────────────────────────
		$rolls_table = $wpdb->prefix . 'raffle_ticket_rolls';
		$sql_rolls   = "CREATE TABLE {$rolls_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(200) NOT NULL DEFAULT '',
			start_number INT UNSIGNED NOT NULL,
			ticket_count INT UNSIGNED NOT NULL,
			current_offset INT UNSIGNED NOT NULL DEFAULT 0,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY product_id_sort (product_id, sort_order, id)
		) {$charset_collate};";

		dbDelta( $sql_rolls );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}
}
