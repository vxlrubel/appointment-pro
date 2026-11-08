<?php
/**
 * Generic database repository helpers.
 *
 * @package Praxleo_Appointment
 */

namespace Praxleo\Appointment\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around $wpdb for the plugin tables.
 */
class Repository {

	/**
	 * Return a prefixed table name.
	 *
	 * @param string $name Table suffix.
	 * @return string
	 */
	public static function table( string $name ): string {
		return Schema::table( $name );
	}

	/**
	 * Current time in UTC in MySQL format.
	 *
	 * @return string
	 */
	public static function now(): string {
		return current_time( 'mysql', true );
	}

	/**
	 * Fetch all rows from a table.
	 *
	 * @param string               $table Table suffix.
	 * @param array<string, mixed> $where Column => value pairs.
	 * @param string               $order Raw ORDER BY clause.
	 * @return array<int, array<string, mixed>>
	 */
	public static function all( string $table, array $where = array(), string $order = 'id ASC' ): array {
		global $wpdb;

		$sql    = 'SELECT * FROM ' . self::table( $table );
		$params = array();

		if ( $where ) {
			$parts = array();

			foreach ( $where as $column => $value ) {
				if ( is_array( $value ) ) {
					$in      = implode( ',', array_map( 'intval', $value ) );
					$parts[] = "`$column` IN ($in)";
				} elseif ( null === $value ) {
					$parts[] = "`$column` IS NULL";
				} else {
					$parts[]  = "`$column` = %s";
					$params[] = $value;
				}
			}

			$sql .= ' WHERE ' . implode( ' AND ', $parts );
		}

		$sql .= ' ORDER BY ' . $order;

		if ( $params ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find a single row by ID.
	 *
	 * @param string $table Table suffix.
	 * @param int    $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public static function find( string $table, int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( $table ) . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a row.
	 *
	 * @param string               $table Table suffix.
	 * @param array<string, mixed> $data Column => value pairs.
	 * @return int Inserted ID.
	 */
	public static function insert( string $table, array $data ): int {
		global $wpdb;

		$columns      = array();
		$placeholders = array();
		$values       = array();

		foreach ( $data as $column => $value ) {
			$columns[] = '`' . $column . '`';

			if ( null === $value ) {
				$placeholders[] = 'NULL';
			} else {
				$placeholders[] = '%s';
				$values[]       = $value;
			}
		}

		$sql = 'INSERT INTO ' . self::table( $table ) . ' (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $placeholders ) . ')';

		if ( $values ) {
			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		} else {
			$wpdb->query( $sql );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update rows matching a where clause.
	 *
	 * @param string               $table Table suffix.
	 * @param array<string, mixed> $data Column => value pairs.
	 * @param array<string, mixed> $where Column => value pairs.
	 * @return int|false Number of affected rows or false on error.
	 */
	public static function update( string $table, array $data, array $where ) {
		global $wpdb;

		$sets   = array();
		$values = array();

		foreach ( $data as $column => $value ) {
			if ( null === $value ) {
				$sets[] = '`' . $column . '` = NULL';
			} else {
				$sets[]   = '`' . $column . '` = %s';
				$values[] = $value;
			}
		}

		$where_sql = array();

		foreach ( $where as $column => $value ) {
			$where_sql[] = '`' . $column . '` = %s';
			$values[]    = $value;
		}

		$sql = 'UPDATE ' . self::table( $table ) . ' SET ' . implode( ', ', $sets ) . ' WHERE ' . implode( ' AND ', $where_sql );

		if ( $values ) {
			return $wpdb->query( $wpdb->prepare( $sql, $values ) );
		}

		return $wpdb->query( $sql );
	}

	/**
	 * Delete rows matching a where clause.
	 *
	 * @param string               $table Table suffix.
	 * @param array<string, mixed> $where Column => value pairs.
	 * @return int|false Number of deleted rows or false on error.
	 */
	public static function delete( string $table, array $where ) {
		global $wpdb;

		return $wpdb->delete( self::table( $table ), $where );
	}

	/**
	 * Run a custom query and return rows.
	 *
	 * @param string $sql Prepared SQL string.
	 * @param array<int, mixed> $params Query parameters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function query( string $sql, array $params = array() ): array {
		global $wpdb;

		if ( $params ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		}

		return is_array( $rows ) ? $rows : array();
	}
}
