<?php
/**
 * License Key Class
 *
 * @author Iron Bound Designs
 * @since  1.0
 */

namespace ITELIC;

use ITELIC\DB\Manager;
use ITELIC_API\Query\Activations;

/**
 * Class ITELIC_Key
 *
 * Class used to represent a license key.
 *
 * @since 1.0
 */
class Key implements API\Serializable, \Serializable {

	/**
	 * Represents when this license is active.
	 */
	const ACTIVE = 'active';

	/**
	 * Represents when this license key has expired.
	 */
	const EXPIRED = 'expired';

	/**
	 * Represents when this license key was disabled by an admin.
	 */
	const DISABLED = 'disabled';

	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var \IT_Exchange_Transaction
	 */
	private $transaction;

	/**
	 * @var \IT_Exchange_Product
	 */
	private $product;

	/**
	 * @var \IT_Exchange_Customer
	 */
	private $customer;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var \DateTime|null
	 */
	private $expires = null;

	/**
	 * @var int
	 */
	private $max;

	/**
	 * Constructor.
	 *
	 * @param object $data Data from the DB
	 *
	 * @throws \InvalidArgumentException If an invalid transaction, product or
	 *                                  customer.
	 */
	public function __construct( $data ) {
		$this->init( $data );
	}

	/**
	 * Initialize this object.
	 *
	 * @param object $data
	 */
	protected function init( $data ) {
		$this->key         = $data->lkey;
		$this->transaction = it_exchange_get_transaction( $data->transaction_id );
		$this->product     = it_exchange_get_product( $data->product );
		$this->customer    = it_exchange_get_customer( $data->customer );
		$this->status      = $data->status;
		$this->max         = $data->max;

		if ( ! empty( $data->expires ) ) {
			$this->expires = new \DateTime( $data->expires );
		}

		foreach (
			array(
				'transaction',
				'product',
				'customer'
			) as $maybe_error
		) {
			if ( ! $this->$maybe_error || is_wp_error( $this->$maybe_error ) ) {
				throw new \InvalidArgumentException( "Invalid $maybe_error" );
			}
		}
	}

	/**
	 * Retrieve a license key object by using the license key.
	 *
	 * @param string $key
	 *
	 * @return Key
	 */
	public static function with_key( $key ) {

		$db   = Manager::make_query_object( 'keys' );
		$data = $db->get( $key );

		if ( $data ) {
			return new Key( $data );
		} else {
			return null;
		}
	}

	/**
	 * Create a license key record.
	 *
	 * @since 1.0
	 *
	 * @param string                   $key
	 * @param \IT_Exchange_Transaction $transaction
	 * @param \IT_Exchange_Product     $product
	 * @param \IT_Exchange_Customer    $customer
	 * @param int                      $max
	 * @param \DateTime                $expires
	 * @param string                   $status
	 *
	 * @return Key
	 */
	public static function create( $key, \IT_Exchange_Transaction $transaction, \IT_Exchange_Product $product, \IT_Exchange_Customer $customer, $max, \DateTime $expires = null, $status = '' ) {

		if ( empty( $status ) ) {
			$status = self::ACTIVE;
		}

		$data = array(
			'lkey'           => $key,
			'transaction_id' => $transaction->ID,
			'product'        => $product->ID,
			'customer'       => $customer->id,
			'status'         => $status,
			'max'            => $max,
			'expires'        => isset( $expires ) ? $expires->format( "Y-m-d H:i:s" ) : null
		);

		$db = Manager::make_query_object( 'keys' );
		$db->insert( $data );

		return self::with_key( $key );
	}

	/**
	 * Check if this license is valid.
	 *
	 * The license is valid as long as:
	 *      The number of activations, is less than the max.
	 *      The transaction is cleared for delivery.
	 *      The subscription is not expired.
	 *
	 * @return bool
	 */
	public function is_valid() {

		if ( $this->get_active_count() >= $this->get_max() ) {
			return false;
		}

		if ( ! it_exchange_transaction_is_cleared_for_delivery( $this->get_transaction() ) ) {
			return false;
		}

		if ( $this->get_transaction()->get_transaction_meta( 'subscriber_status' ) != 'active' ) {
			return false;
		}

		return true;
	}

	/**
	 * Log an activation of this license.
	 *
	 * @param Activation $activation
	 */
	public function log_activation( Activation $activation ) {
		// nothing to do
	}

	/**
	 * Extend the expiration date of this license,
	 * by its length. For example, if a license has an
	 * expiration date of one year after purchase,
	 * extending it will extend the expiration date by one year.
	 *
	 * @since 1.0
	 *
	 * @return \DateTime
	 */
	public function extend() {
		if ( $this->get_expires() === null ) {
			return null;
		}

		$type  = it_exchange_get_product_feature( $this->get_product()->ID, 'recurring-payments', array( 'setting' => 'interval' ) );
		$count = it_exchange_get_product_feature( $this->get_product()->ID, 'recurring-payments', array( 'setting' => 'interval-count' ) );

		$interval = convert_rp_to_date_interval( $type, $count );
		$expires  = $this->get_expires();

		$expires->add( $interval );
		$this->set_expires( $expires );

		return $this->get_expires();
	}

	/**
	 * Renew this license.
	 *
	 * @since 1.0
	 *
	 * @param \IT_Exchange_Transaction $transaction
	 *
	 * @return Renewal
	 */
	public function renew( \IT_Exchange_Transaction $transaction = null ) {

		if ( $this->get_expires() === null ) {
			throw new \InvalidArgumentException( __( "You can't renew a license key that doesn't expire.", Plugin::SLUG ) );
		}

		if ( $transaction === null ) {
			$date = null;
		} else {
			$date = new \DateTime( $transaction->post_date );
		}

		$record = Renewal::create( $this, $transaction, $this->get_expires(), $date );

		$this->extend();

		return $record;
	}

	/**
	 * Get all activations of this license key.
	 *
	 * @since 1.0
	 *
	 * @param string $status
	 *
	 * @return Activation[]
	 */
	public function get_activations( $status = '' ) {

		$args = array(
			'key' => $this->get_key()
		);

		if ( $status ) {
			$args['status'] = $status;
		}

		$query = new Activations( $args );

		return $query->get_results();
	}

	/**
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * @return \IT_Exchange_Transaction
	 */
	public function get_transaction() {
		return $this->transaction;
	}

	/**
	 * @return \IT_Exchange_Product
	 */
	public function get_product() {
		return $this->product;
	}

	/**
	 * @return \IT_Exchange_Customer
	 */
	public function get_customer() {
		return $this->customer;
	}

	/**
	 * Retrieve the status.
	 *
	 * @param bool $label If true, retrieve the label form.
	 *
	 * @return string
	 */
	public function get_status( $label = false ) {

		if ( ! $label ) {
			return $this->status;
		}

		$stauses = self::get_statuses();

		return isset( $stauses[ $this->status ] ) ? $stauses[ $this->status ] : __( "Unknown", Plugin::SLUG );
	}

	/**
	 * Set the status of this key.
	 *
	 * @since 1.0
	 *
	 * @param string $status
	 */
	public function set_status( $status ) {
		if ( ! array_key_exists( $status, self::get_statuses() ) ) {
			throw new \InvalidArgumentException( __( "Invalid value for key status.", Plugin::SLUG ) );
		}

		$this->status = $status;
		$this->update_value( 'status', $this->get_status() );
	}

	/**
	 * Get the list of statuses.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			self::ACTIVE   => __( "Active", Plugin::SLUG ),
			self::DISABLED => __( "Disabled", Plugin::SLUG ),
			self::EXPIRED  => __( "Expired", Plugin::SLUG )
		);
	}

	/**
	 * @return int
	 */
	public function get_active_count() {

		$db = Manager::make_query_object( 'activations' );

		return $db->count( array(
			'lkey'   => $this->get_key(),
			'status' => Activation::ACTIVE
		) );
	}

	/**
	 * @return \DateTime|null
	 */
	public function get_expires() {
		return $this->expires;
	}

	/**
	 * Set the expiry date.
	 *
	 * @since 1.0
	 *
	 * @param \DateTime $expires . Set null for forever.
	 */
	public function set_expires( \DateTime $expires = null ) {

		$this->expires = $expires;

		if ( $expires ) {
			$val = $expires->format( "Y-m-d H:i:s" );
		} else {
			$val = null;
		}

		$this->update_value( 'expires', $val );
	}

	/**
	 * @return int
	 */
	public function get_max() {
		return $this->max;
	}

	/**
	 * Set the maximum number of activations.
	 *
	 * @since 1.0
	 *
	 * @param int $max
	 */
	public function set_max( $max ) {

		$this->max = absint( $max );
		$this->update_value( 'max', $this->get_max() );
	}

	/**
	 * Is this an online product, IE are activations tied to URLs.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function is_online_product() {
		return (bool) it_exchange_get_product_feature( $this->get_product()->ID,
			'licensing', array( 'field' => 'online-software' ) );
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->get_key();
	}

	/**
	 * Get data suitable for the API.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_api_data() {

		$activations = $this->get_activations();

		$data = array(
			'transaction' => $this->get_transaction()->ID,
			'product'     => $this->get_product()->ID,
			'customer'    => $this->get_customer()->wp_user->ID,
			'status'      => $this->get_status(),
			'max'         => $this->get_max(),
			'activations' => array(
				'count'        => count( $activations ),
				'count_active' => $this->get_active_count(),
				'list'         => $activations
			)
		);

		return $data;
	}

	/**
	 * Delete the license key.
	 */
	public function delete() {
		$keys = Manager::make_query_object( 'keys' );
		$keys->delete( $this->get_key() );

		$activations = Manager::make_query_object( 'activations' );
		$activations->delete_many( array( 'lkey' => $this->get_key() ) );

		$renewals = Manager::make_query_object( 'renewals' );
		$renewals->delete_many( array( 'lkey' => $this->get_key() ) );
	}

	/**
	 * Update a particular value.
	 *
	 * @since 1.0
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @throws \RuntimeException|DB\Exception
	 */
	protected function update_value( $key, $value ) {

		$data = array(
			$key => $value
		);

		$db = Manager::make_query_object( 'keys' );
		$db->update( $this->get_key(), $data );
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return serialize( array(
			'key' => $this->get_key()
		) );
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 *
	 * @param string $serialized <p>
	 *                           The string representation of the object.
	 *                           </p>
	 *
	 * @return void
	 */
	public function unserialize( $serialized ) {

		$db   = Manager::make_query_object( 'keys' );
		$data = $db->get( $serialized['key'] );

		$this->init( $data );
	}

}