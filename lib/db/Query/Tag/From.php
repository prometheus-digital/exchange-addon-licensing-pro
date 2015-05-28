<?php
/**
 * From tag.
 *
 * @author Iron Bound Designs
 * @since  1.0
 */

namespace ITELIC\DB\Query\Tag;

/**
 * Class From
 * @package ITELIC\DB\Query\Tag
 */
class From extends Generic {

	/**
	 * Constructor.
	 *
	 * @param string      $table Table name.
	 * @param string|null $as    Give the table a name for use in other parts of the query.
	 */
	public function __construct( $table, $as = null ) {

		if ( $as !== null ) {
			$table .= " $as";
		}

		parent::__construct( "FROM", $table );
	}

	/**
	 * Query on another table.
	 *
	 * @since 1.0
	 *
	 * @param string      $table
	 * @param string|null $as
	 */
	public function also( $table, $as = null ) {

		if ( $as !== null ) {
			$table .= " $as";
		}

		$this->value .= ", $table";
	}
}

