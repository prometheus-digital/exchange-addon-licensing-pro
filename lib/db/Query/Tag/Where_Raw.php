<?php
/**
 * Allows for Raw where statements.
 *
 * @author Iron Bound Designs
 * @since  1.0
 */

namespace ITELIC\DB\Query\Tag;

/**
 * Class Where_Raw
 * @package ITELIC\DB\Query\Tag
 */
class Where_Raw extends Where {

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 *
	 * @param string $sql
	 */
	public function __construct( $sql ) {
		parent::__construct( null, null, $sql );
	}

	/**
	 * Get the raw sql as the comparsion data.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	protected function get_comparison() {
		return $this->value;
	}
}