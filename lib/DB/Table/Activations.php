<?php
/**
 * DB for storing activations.
 *
 * Each time a site is activated, a new record should be inserted.
 *
 * @author    Iron Bound Designs
 * @since     1.0
 * @license   AGPL
 * @copyright Iron Bound Designs, 2015.
 */

namespace ITELIC\DB\Table;

use IronBound\DB\Table\Table;

/**
 * Class Activations
 * @package ITELIC\DB\Table
 */
class Activations implements Table {

	/**
	 * Columns in the table.
	 *
	 * key => sprintf field type
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'id'           => '%d',
			'lkey'         => '%s',
			'location'     => '%s',
			'status'       => '%s',
			'activation'   => '%s',
			'deactivation' => '%s',
			'release_id'   => '%d',
		);
	}

	/**
	 * Default column values.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_column_defaults() {
		return array(
			'id'           => 0,
			'lkey'         => '',
			'location'     => '',
			'status'       => 'active',
			'activation'   => '',
			'deactivation' => '',
			'release_id'   => '',
		);
	}

	/**
	 * Retrieve the name of the database table.
	 *
	 * @since 1.0
	 *
	 * @param \wpdb $wpdb
	 *
	 * @return string
	 */
	public function get_table_name( \wpdb $wpdb ) {
		return $wpdb->prefix . 'itelic_activations';
	}

	/**
	 * Get the slug of this table.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'itelic-activations';
	}

	/**
	 * Retrieve the name of the primary key.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_primary_key() {
		return 'id';
	}

	/**
	 * Get creation SQL.
	 *
	 * @since 1.0
	 *
	 * @param \wpdb $wpdb
	 *
	 * @return string
	 */
	public function get_creation_sql( \wpdb $wpdb ) {

		$tn = $this->get_table_name( $wpdb );

		return "CREATE TABLE {$tn} (
		id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
		lkey VARCHAR(128) NOT NULL,
		location VARCHAR(191) NOT NULL,
		activation DATETIME NOT NULL,
		deactivation DATETIME DEFAULT NULL,
		status VARCHAR(20) NOT NULL,
		release_id BIGINT(20),
		PRIMARY KEY  (id),
		KEY lkey (lkey),
		UNIQUE KEY key__location (lkey,location)
		) {$wpdb->get_charset_collate()};";
	}

	/**
	 * Get version of this table.
	 *
	 * @since 1.0
	 *
	 * @return int
	 */
	public function get_version() {
		return 1;
	}
}