<?php
/**
 * Keys WP CLI command.
 *
 * @author    Iron Bound Designs
 * @since     1.0
 * @license   AGPL
 * @copyright Iron Bound Designs, 2015.
 */

/**
 * Class ITELIC_Key_Command
 */
class ITELIC_Key_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'key';
	protected $obj_id_key = 'lkey';

	/**
	 * @var ITELIC_Fetcher
	 */
	protected $fetcher;

	/**
	 * @var \Faker\Generator
	 */
	protected $faker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->fetcher = new ITELIC_Fetcher( '\ITELIC\Key' );
		$this->faker   = \Faker\Factory::create();
	}

	/**
	 * Get a license key's content by key.
	 *
	 * ## Options
	 *
	 * <key>
	 * : License key. A partial match can be performed by appending '...' to the first few characters of the key.
	 *
	 * [--fields=<fields>]
	 * : Return designated object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table
	 *
	 * [--raw]
	 * : Return raw values. ie a product's ID not title, and a customer ID not display name
	 *
	 * ## Examples
	 *
	 * wp itelic key get abcd-1234 --fields=key,customer
	 *
	 * wp itelic key get abcd...
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function get( $args, $assoc_args ) {

		list( $key ) = $args;

		if ( substr( $key, - 3 ) == '...' ) {
			$key = substr( $key, 0, strlen( $key ) - 3 );

			if ( strlen( $key ) < 3 ) {
				WP_CLI::error( 'At least the first three characters of the key must be provided to perform a partial match.' );
			}

			$keys = itelic_get_keys( array(
				'key_like' => $key
			) );

			if ( empty( $keys ) ) {
				WP_CLI::error( 'No partial match found.' );
			}

			if ( count( $keys ) > 1 ) {
				WP_CLI::line( 'Multiple keys found.' );

				$this->list_( array(), array(
					'key_like' => $key
				) );

				return;
			}

			$object = reset( $keys );
		} else {
			$object = $this->fetcher->get_check( $key );
		}

		$fields = $this->get_fields_for_object( $object, \WP_CLI\Utils\get_flag_value( $assoc_args, 'raw', false ) );

		if ( empty( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = array_keys( $fields );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $fields );
	}

	/**
	 * Get a list of keys
	 *
	 * ## Options
	 *
	 * [--<field>=<value>]
	 * : Include additional query args in keys query.
	 *
	 * [--fields=<fields>]
	 * : Return designated object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table
	 *
	 * [--raw]
	 * : Return raw values. ie, IDs not human readable names.
	 *
	 * @param $args
	 * @param $assoc_args
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$query_args = wp_parse_args( $assoc_args, array(
			'items_per_page' => 20,
			'page'           => 1
		) );

		$query_args['order'] = array(
			'transaction' => 'DESC'
		);

		$results = itelic_get_keys( $query_args );

		$items = array();

		foreach ( $results as $item ) {
			$items[] = $this->get_fields_for_object( $item, \WP_CLI\Utils\get_flag_value( $assoc_args, 'raw', false ) );
		}

		if ( empty( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = array(
				'key',
				'status',
				'product',
				'customer'
			);
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );
	}

	/**
	 * Extend a license key's expiration date.
	 *
	 * ## Options
	 *
	 * <key>
	 * : License key
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function extend( $args, $assoc_args ) {

		list( $key ) = $args;

		if ( substr( $key, - 3 ) == '...' ) {
			$key = substr( $key, 0, strlen( $key ) - 3 );

			if ( strlen( $key ) < 3 ) {
				WP_CLI::error( 'At least the first three characters of the key must be provided to perform a partial match.' );
			}

			$keys = itelic_get_keys( array(
				'key_like' => $key
			) );

			if ( empty( $keys ) ) {
				WP_CLI::error( 'No partial match found.' );
			}

			if ( count( $keys ) > 1 ) {
				WP_CLI::line( 'Multiple keys found.' );

				$this->list_( array(), array(
					'key_like' => $key
				) );

				return;
			}

			$object = reset( $keys );
		} else {
			$object = $this->fetcher->get_check( $key );
		}

		$result = $object->extend();

		if ( ! $result ) {
			WP_CLI::error( "This key does not have an expiry date." );
		}

		WP_CLI::success( sprintf( "New expiration date %s", $result->format( DateTime::ISO8601 ) ) );
	}

	/**
	 * Renew a license key.
	 *
	 * ## Options
	 *
	 * <key>
	 * : License key
	 *
	 * [<transaction>]
	 * : Optionally tie this renewal to a transaction
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function renew( $args, $assoc_args ) {

		list( $key, $transaction ) = array_pad( $args, 2, 0 );

		if ( substr( $key, - 3 ) == '...' ) {
			$key = substr( $key, 0, strlen( $key ) - 3 );

			if ( strlen( $key ) < 3 ) {
				WP_CLI::error( 'At least the first three characters of the key must be provided to perform a partial match.' );
			}

			$keys = itelic_get_keys( array(
				'key_like' => $key
			) );

			if ( empty( $keys ) ) {
				WP_CLI::error( 'No partial match found.' );
			}

			if ( count( $keys ) > 1 ) {
				WP_CLI::line( 'Multiple keys found.' );

				$this->list_( array(), array(
					'key_like' => $key
				) );

				return;
			}

			$object = reset( $keys );
		} else {
			$object = $this->fetcher->get_check( $key );
		}

		if ( ! empty( $transaction ) ) {
			$transaction = it_exchange_get_transaction( $transaction );

			if ( ! $transaction ) {
				WP_CLI::error( sprintf( "Invalid transaction with ID %d", $transaction ) );
			}

		} else {
			$transaction = null;
		}

		try {
			$result = $object->renew( $transaction );

			if ( $result ) {
				WP_CLI::success(
					sprintf( "Key has been renewed. New expiration date is %s",
						$object->get_expires()->format( DateTime::ISO8601 ) )
				);

				return;
			}
		}
		catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::error( "An unknown error has occurred" );
	}

	/**
	 * Expire a license key.
	 *
	 * ## Options
	 *
	 * <key>
	 * : License key
	 *
	 * [<when>]
	 * : Specify when the license key expired. Accepts strtotime compatible
	 * value. GMT.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function expire( $args, $assoc_args ) {

		list( $key, $when ) = array_pad( $args, 2, 'now' );

		if ( substr( $key, - 3 ) == '...' ) {
			$key = substr( $key, 0, strlen( $key ) - 3 );

			if ( strlen( $key ) < 3 ) {
				WP_CLI::error( 'At least the first three characters of the key must be provided to perform a partial match.' );
			}

			$keys = itelic_get_keys( array(
				'key_like' => $key
			) );

			if ( empty( $keys ) ) {
				WP_CLI::error( 'No partial match found.' );
			}

			if ( count( $keys ) > 1 ) {
				WP_CLI::line( 'Multiple keys found.' );

				$this->list_( array(), array(
					'key_like' => $key
				) );

				return;
			}

			$object = reset( $keys );
		} else {
			$object = $this->fetcher->get_check( $key );
		}

		try {
			$when = \ITELIC\make_date_time( $when );
		}
		catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$object->expire( $when );

		WP_CLI::success( "Key expired." );
	}

	/**
	 * Disable a license key.
	 *
	 * ## Options
	 *
	 * <key>
	 * : License key
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function disable( $args, $assoc_args ) {

		list( $key ) = $args;

		if ( substr( $key, - 3 ) == '...' ) {
			$key = substr( $key, 0, strlen( $key ) - 3 );

			if ( strlen( $key ) < 3 ) {
				WP_CLI::error( 'At least the first three characters of the key must be provided to perform a partial match.' );
			}

			$keys = itelic_get_keys( array(
				'key_like' => $key
			) );

			if ( empty( $keys ) ) {
				WP_CLI::error( 'No partial match found.' );
			}

			if ( count( $keys ) > 1 ) {
				WP_CLI::line( 'Multiple keys found.' );

				$this->list_( array(), array(
					'key_like' => $key
				) );

				return;
			}

			$object = reset( $keys );
		} else {
			$object = $this->fetcher->get_check( $key );
		}

		$object->set_status( \ITELIC\Key::DISABLED );

		WP_CLI::success( "Key disabled." );
	}

	/**
	 * Create a license key.
	 *
	 * Requires manual purchases add-on.
	 *
	 * ## Options
	 *
	 * <product>
	 * : Product ID.
	 *
	 * <customer>
	 * : Customer ID
	 *
	 * [<key>]
	 * : Optionally, specify the license key to be used.
	 *
	 * [--limit=<limit>]
	 * : Activation limit. Defaults to lowest value available.
	 * Set to '-' for unlimited.
	 *
	 * [--amount-paid=<amount-paid>]
	 * : The amount the customer paid for this key. Defaults to the product
	 * base price.
	 *
	 * [--expires=<expires>]
	 * : License key expiry date.
	 * Default: forever. Accepts strtotime compatible value. GMT.
	 *
	 * [--status=<status>]
	 * : Key status. Accepts: active, expired, disabled. Default: active
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function create( $args, $assoc_args ) {

		list( $ID, $customer, $key ) = array_pad( $args, 3, '' );

		$product = itelic_get_product( $ID );

		if ( ! $product || ! $product->has_feature( 'licensing' ) ) {
			WP_CLI::error( "Invalid product." );
		}

		$customer = it_exchange_get_customer( $customer );

		if ( ! $customer ) {
			WP_CLI::error( "Invalid customer." );
		}

		$create_args = array(
			'product'  => $product->ID,
			'customer' => $customer->id,
			'key'      => $key,
			'status'   => \WP_CLI\Utils\get_flag_value( $assoc_args, 'status', \ITELIC\Key::ACTIVE )
		);

		if ( isset( $assoc_args['limit'] ) ) {
			$create_args['limit'] = $assoc_args['limit'];
		}

		if ( isset( $assoc_args['expires'] ) ) {
			$create_args['expires'] = $assoc_args['expires'];
		}

		if ( isset( $assoc_args['amount-paid'] ) ) {
			$create_args['paid'] = $assoc_args['amount-paid'];
		} else {
			$create_args['paid'] = $product->get_feature( 'base-price' );
		}

		parent::_create( $args, $assoc_args, function () use ( $create_args ) {

			try {
				$key = itelic_create_key( $create_args );

				if ( ! $key ) {
					WP_CLI::error( "Unknown error occurred." );
				}

				return $key->get_pk();
			}
			catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}

			WP_CLI::error( "Unknown error occurred." );
		} );
	}

	/**
	 * Generate license keys.
	 *
	 * ## Options
	 *
	 * [--count=<count>]
	 * : Number of keys to generate. Default 500. Max 750.
	 *
	 * [--product=<product>]
	 * : Only generate keys for a certain product.
	 *
	 * [--activations]
	 * : Generate activations for license keys.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function generate( $args, $assoc_args ) {

		if ( ( $product = \WP_CLI\Utils\get_flag_value( $assoc_args, 'product' ) ) ) {
			$product = itelic_get_product( $product );

			if ( ! $product ) {
				WP_CLI::error( "Invalid product." );
			}

			$products = array( $product->ID );
		} else {
			$products = wp_list_pluck( itelic_get_products_with_licensing_enabled(), 'ID' );
		}

		$count = \WP_CLI\Utils\get_flag_value( $assoc_args, 'count', 500 );

		$notify = \WP_CLI\Utils\make_progress_bar( "Generating keys", $count );

		for ( $i = 0; $i < $count; $i ++ ) {

			$product  = $this->get_product( $products );
			$customer = $this->get_random_customer();

			$min_date = max( strtotime( $product->post_date ), strtotime( $customer->wp_user->user_registered ) );

			$date = $this->faker->dateTimeBetween( "@$min_date" );

			$key_args = array(
				'product'  => $product->ID,
				'customer' => $customer->id,
				'date'     => $date->format( 'Y-m-d H:i:s' ),
				'status'   => $this->get_status(),
				'paid'     => $product->get_feature( 'base-price' )
			);

			$key = itelic_create_key( $key_args );

			if ( is_wp_error( $key ) ) {
				WP_CLI::error( $key );
			}

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activations' ) ) {
				$this->create_activations_for_key( $key );
			}

			if ( $key->get_status() == \ITELIC\Key::EXPIRED ) {
				$key->expire( $key->get_expires() );
			}

			$notify->tick();
		}

		$notify->finish();
	}

	/**
	 * Create activation records for a license key.
	 *
	 * @param \ITELIC\Key $key
	 */
	protected function create_activations_for_key( ITELIC\Key $key ) {

		if ( in_array( $key->get_status(), array(
			\ITELIC\Key::EXPIRED,
			\ITELIC\Key::DISABLED
		) ) ) {
			return;
		}

		$limit = $key->get_max();

		if ( empty( $limit ) ) {
			$limit = 20;
		}

		$limit = min( $limit, $limit / 2 + 2 );

		$created = $key->get_transaction()->post_date_gmt;
		$end     = \ITELIC\make_date_time( $created );
		$end->add( new DateInterval( 'P5D' ) );

		$creation_date = $this->faker->dateTimeBetween( $created, $end );
		$release       = $this->get_release_for_date( $key, $creation_date );

		if ( ! $release ) {
			WP_CLI::error( "Release not created." );
		}

		\ITELIC\Activation::create( $key, $this->faker->domainName, $creation_date, $release );

		$count = rand( 0, $limit - 1 );

		if ( ! $count ) {
			return;
		}

		$now = new DateTime();

		for ( $i = 0; $i < $count; $i ++ ) {

			$expires = $key->get_expires();

			if ( $expires > $now ) {
				$max = $now;
			} else {
				$max = $expires;
			}

			$creation_date = $this->faker->dateTimeBetween( $created, $max );

			$release = $this->get_release_for_date( $key, $creation_date );

			try {
				$a = \ITELIC\Activation::create( $key, $this->faker->domainName, $creation_date, $release );
			}
			catch ( LogicException $e ) {
				continue;
			}
			catch ( IronBound\DB\Exception $e ) {
				continue;
			}

			if ( ! rand( 0, 3 ) ) {

				$deactivate_date = $this->faker->dateTimeBetween( $creation_date, $max );
				$a->deactivate( $deactivate_date );
			}
		}
	}

	/**
	 * Get the latest release available at a certain date.
	 *
	 * @param \ITELIC\Key $key
	 * @param DateTime    $date GMT.
	 * @param string      $track
	 *
	 * @return \ITELIC\Release
	 */
	protected function get_release_for_date( ITELIC\Key $key, DateTime $date, $track = 'stable' ) {

		$types = array(
			\ITELIC\Release::TYPE_MAJOR,
			\ITELIC\Release::TYPE_MINOR,
			\ITELIC\Release::TYPE_SECURITY
		);

		if ( $track == 'pre-release' ) {
			$types[] = \ITELIC\Release::TYPE_PRERELEASE;
		}

		$releases = itelic_get_releases( array(
			'product'             => $key->get_product()->ID,
			'order'               => array(
				'start_date' => 'DESC'
			),
			'start_date'          => array(
				'before' => $date->format( 'Y-m-d H:i:s' )
			),
			'items_per_page'      => 1,
			'sql_calc_found_rows' => false,
			'type'                => $types
		) );

		return reset( $releases );
	}

	/**
	 * Get the license key's status.
	 *
	 * @return string
	 */
	protected function get_status() {

		$rand = rand( 0, 20 );

		if ( $rand < 1 ) {
			return \ITELIC\Key::DISABLED;
		}

		return \ITELIC\Key::ACTIVE;
	}

	/**
	 * Get a product.
	 *
	 * @param $products
	 *
	 * @return \ITELIC\Product
	 */
	protected function get_product( $products ) {
		return itelic_get_product( $products[ array_rand( $products ) ] );
	}

	/**
	 * Get a random customer.
	 *
	 * @return IT_Exchange_Customer
	 */
	protected function get_random_customer() {

		/**
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		$ID = (int) $wpdb->get_var( "SELECT ID FROM $wpdb->users ORDER BY RAND() LIMIT 1" );

		return it_exchange_get_customer( $ID );
	}

	/**
	 * Delete a license key.
	 *
	 * ## Options
	 *
	 * <key>
	 * : Key
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function delete( $args, $assoc_args ) {

		list( $object ) = $args;

		$object = $this->fetcher->get_check( $object );

		try {
			$object->delete();
		}
		catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( "Key deleted." );
	}

	/**
	 * Get data to display for a single object.
	 *
	 * @param \ITELIC\Key $object
	 * @param bool        $raw
	 *
	 * @return array
	 */
	protected function get_fields_for_object( \ITELIC\Key $object, $raw = false ) {
		return array(
			'key'         => $object->get_key(),
			'status'      => $object->get_status( ! $raw ),
			'product'     => $raw ? $object->get_product()->ID : $object->get_product()->post_title,
			'transaction' => $raw ? $object->get_transaction()->ID : it_exchange_get_transaction_order_number( $object->get_transaction() ),
			'customer'    => $raw ? $object->get_customer()->id : $object->get_customer()->wp_user->display_name,
			'expires'     => $object->get_expires() ? $object->get_expires()->format( DateTime::ISO8601 ) : '-',
			'max'         => $object->get_max() ? $object->get_max() : 'Unlimited',
			'activations' => $object->get_active_count()
		);
	}
}

WP_CLI::add_command( 'itelic key', 'ITELIC_Key_Command' );