<?php
/**
 * Represents release objects.
 *
 * @author Iron Bound Designs
 * @since  1.0
 */

namespace ITELIC;

use IronBound\Cache\Cache;
use IronBound\DB\Model;
use IronBound\DB\Table\Table;
use IronBound\DB\Manager;
use IronBound\DB\Exception as DB_Exception;
use ITELIC_API\Query\Activations;

/**
 * Class Release
 *
 * @package ITELIC
 */
class Release extends Model {

	/**
	 * Major releases. 1.5 -> 1.6
	 */
	const TYPE_MAJOR = 'major';

	/**
	 * Minor, bug fixing releases. 1.5.3 -> 1.5.4
	 */
	const TYPE_MINOR = 'minor';

	/**
	 * Security releases. Follows minor release version number syntax.
	 */
	const TYPE_SECURITY = 'security';

	/**
	 * Pre-releases. Alpha, beta, etc...
	 */
	const TYPE_PRERELEASE = 'pre-release';

	/**
	 * Restricted releases. Only distributed to a subset of customers.
	 */
	const TYPE_RESTRICTED = 'restricted';

	/**
	 * Draft status. Default. Not yet available.
	 */
	const STATUS_DRAFT = 'draft';

	/**
	 * Active releases.
	 */
	const STATUS_ACTIVE = 'active';

	/**
	 * A paused release.
	 */
	const STATUS_PAUSED = 'paused';

	/**
	 * An archived release.
	 */
	const STATUS_ARCHIVED = 'archived';

	/**
	 * @var int
	 */
	private $ID;

	/**
	 * @var \IT_Exchange_Product
	 */
	private $product;

	/**
	 * @var int
	 */
	private $download;

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var string
	 */
	private $changelog;

	/**
	 * @var \DateTime|null
	 */
	private $start_date;

	/**
	 * Constructor.
	 *
	 * @param \stdClass $data
	 */
	public function __construct( \stdClass $data ) {
		$this->init( $data );
	}

	/**
	 * Init an object.
	 *
	 * @since 1.0
	 *
	 * @param \stdClass $data
	 */
	protected function init( \stdClass $data ) {
		$this->ID      = $data->ID;
		$this->product = it_exchange_get_product( $data->product );

		if ( ! $this->product ) {
			throw new \InvalidArgumentException( "Invalid product." );
		}

		$this->download = (int) $data->download;
		$this->version  = $data->version;

		if ( array_key_exists( $data->status, self::get_statuses() ) ) {
			$this->status = $data->status;
		} else {
			throw new \InvalidArgumentException( "Invalid status." );
		}

		if ( array_key_exists( $data->type, self::get_types() ) ) {
			$this->type = $data->type;
		} else {
			throw new \InvalidArgumentException( "Invalid type." );
		}

		$this->changelog = $data->changelog;

		if ( $data->start_date && $data->start_date != '0000-00-00 00:00:00' ) {
			$this->start_date = new \DateTime( $data->start_date );
		}
	}

	/**
	 * Retrieve a release by its ID.
	 *
	 * @since 1.0
	 *
	 * @param int $id
	 *
	 * @return Release|null
	 */
	public static function with_id( $id ) {
		return self::get( $id );
	}

	/**
	 * Create a new release record.
	 *
	 * If status is set to active, the start date will automatically be set to
	 * now.
	 *
	 * @since 1.0
	 *
	 * @param \IT_Exchange_Product $product
	 * @param \WP_Post             $file Attachment of the download
	 * @param string               $version
	 * @param string               $type
	 * @param string               $status
	 * @param string               $changelog
	 *
	 * @return Release|null
	 * @throws DB_Exception
	 */
	public static function create( \IT_Exchange_Product $product, \WP_Post $file, $version, $type, $status = '', $changelog = '' ) {

		if ( empty( $status ) ) {
			$status = self::STATUS_DRAFT;
		}

		if ( ! array_key_exists( $status, self::get_statuses() ) ) {
			throw new \InvalidArgumentException( "Invalid status." );
		}

		if ( ! array_key_exists( $type, self::get_types() ) ) {
			throw new \InvalidArgumentException( "Invalid type." );
		}

		if ( get_post_type( $file ) != 'attachment' ) {
			throw new \InvalidArgumentException( "Invalid update file." );
		}

		if ( ! it_exchange_product_has_feature( $product->ID, 'licensing' ) ) {
			throw new \InvalidArgumentException( "Product given does not have the licensing feature enabled." );
		}

		$data = array(
			'product'   => $product->ID,
			'download'  => $file->ID,
			'version'   => $version,
			'type'      => $type,
			'status'    => $status,
			'changelog' => wp_kses_post( $changelog )
		);

		if ( $status == self::STATUS_ACTIVE ) {
			$data['start_date'] = current_time( 'mysql' );
		}

		$db = Manager::make_simple_query_object( 'itelic-releases' );
		$ID = $db->insert( $data );

		$release = self::get( $ID );

		if ( $release ) {

			self::do_activation( $product, $file, $version );

			Cache::add( $release );
		}

		return $release;
	}

	/**
	 * Perform the activation.
	 *
	 * Updates the version in product meta, and store the previous version.
	 * Updates the file in the download meta, and store the previous file.
	 *
	 * @since 1.0
	 *
	 * @param \IT_Exchange_Product $product
	 * @param \WP_Post             $file
	 * @param string               $version
	 */
	protected static function do_activation( \IT_Exchange_Product $product, \WP_Post $file, $version ) {

		$download_id   = it_exchange_get_product_feature( $product->ID, 'licensing', array( 'field' => 'update-file' ) );
		$download_data = get_post_meta( $download_id, '_it-exchange-download-info', true );

		// save the previous download URL in case we pause
		update_post_meta( $product->ID, '_itelic_prev_download', $download_data['source'] );

		// update the download url
		$download_data['source'] = wp_get_attachment_url( $file->ID );

		// save the new download
		update_post_meta( $download_id, '_it-exchange-download-info', $download_data );

		// save the previous version of the software
		update_post_meta( $product->ID, '_itelic_prev_version',
			it_exchange_get_product_feature( $product->ID, 'licensing', array( 'field' => 'version' ) ) );

		it_exchange_update_product_feature( $product->ID, 'licensing', array(
			'version' => $version
		) );
	}

	/**
	 * Activate this release.
	 *
	 * @since 1.0
	 */
	public function activate() {

		if ( $this->status != self::STATUS_ACTIVE ) {
			$this->status = self::STATUS_ACTIVE;
			$this->update( 'status', self::STATUS_ACTIVE );
		}

		if ( ! $this->get_start_date() ) {
			$this->set_start_date( new \DateTime() );
		}

		self::do_activation( $this->get_product(), $this->get_download(), $this->get_version() );

		/**
		 * Fires when a release is activated.
		 *
		 * @since 1.0
		 *
		 * @param Release $this
		 */
		do_action( 'itelic_activate_release', $this );
	}

	/**
	 * Pause this release.
	 *
	 * @since 1.0
	 */
	public function pause() {

		if ( $this->status != self::STATUS_PAUSED ) {
			$this->status = self::STATUS_PAUSED;
			$this->update( 'status', self::STATUS_PAUSED );
		}

		$prev_download = get_post_meta( $this->product->ID, '_itelic_prev_download', true );
		$prev_version  = get_post_meta( $this->product->ID, '_itelic_prev_version', true );

		$download_id             = it_exchange_get_product_feature( $this->product->ID, 'licensing', array( 'field' => 'update-file' ) );
		$download_data           = get_post_meta( $download_id, '_it-exchange-download-info', true );
		$download_data['source'] = $prev_download;
		update_post_meta( $download_id, '_it-exchange-download-info', $download_data );

		it_exchange_update_product_feature( $this->get_product()->ID, 'licensing', array(
			'version' => $prev_version
		) );

		/**
		 * Fires when a release is paused.
		 *
		 * @since 1.0
		 *
		 * @param Release $this
		 */
		do_action( 'itelic_pause_release', $this );
	}

	/**
	 * Get the unique pk for this record.
	 *
	 * @since 1.0
	 *
	 * @return mixed (generally int, but not necessarily).
	 */
	public function get_pk() {
		return $this->ID;
	}

	/**
	 * Retrieve the ID of this release.
	 *
	 * @since 1.0
	 *
	 * @return int
	 */
	public function get_ID() {
		return $this->get_pk();
	}

	/**
	 * Get the product this release corresponds to.
	 *
	 * @since 1.0
	 *
	 * @return \IT_Exchange_Product
	 */
	public function get_product() {
		return $this->product;
	}

	/**
	 * Get the attachment file post.
	 *
	 * @since 1.0
	 *
	 * @return \WP_Post
	 */
	public function get_download() {
		return get_post( $this->download );
	}

	/**
	 * Change the download this release corresponds to.
	 *
	 * @since 1.0
	 *
	 * @param int $download
	 */
	public function set_download( $download ) {

		if ( get_post_type( $download ) != 'attachment' ) {
			throw new \InvalidArgumentException( "Invalid post type for download." );
		}

		$this->download = $download;

		$this->update( 'download', $download );
	}

	/**
	 * Get the version of this release.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Change the version this release corresponds to.
	 *
	 * @since 1.0
	 *
	 * @param string $version
	 */
	public function set_version( $version ) {
		$this->version = $version;

		$this->update( 'version', $version );
	}

	/**
	 * Get the status of this Release.
	 *
	 * @since 1.0
	 *
	 * @param bool $label
	 *
	 * @return string
	 */
	public function get_status( $label = false ) {

		if ( $label ) {
			$labels = self::get_statuses();

			return $labels[ $this->status ];
		}

		return $this->status;
	}

	/**
	 * Set the status of this release.
	 *
	 * @since 1.0
	 *
	 * @param string $status
	 */
	public function set_status( $status ) {

		if ( ! array_key_exists( $status, self::get_statuses() ) ) {
			throw new \InvalidArgumentException( "Invalid status." );
		}

		if ( $this->status == self::STATUS_DRAFT || $this->status == self::STATUS_PAUSED && $status == self::STATUS_ACTIVE ) {
			$this->activate();
		}

		if ( $this->status == self::STATUS_ACTIVE && $status == self::STATUS_PAUSED ) {
			$this->pause();
		}

		$this->status = $status;
		$this->update( 'status', $status );
	}

	/**
	 * Get the type of this release.
	 *
	 * @since 1.0
	 *
	 * @param bool $label
	 *
	 * @return string
	 */
	public function get_type( $label = false ) {

		if ( $label ) {
			$labels = self::get_types( true );

			return $labels[ $this->type ];
		}

		return $this->type;
	}

	/**
	 * Set the type of this release.
	 *
	 * @since 1.0
	 *
	 * @param string $type
	 */
	public function set_type( $type ) {

		if ( ! array_key_exists( $type, self::get_types() ) ) {
			throw new \InvalidArgumentException( "Invalid type." );
		}

		$this->type = $type;
		$this->update( 'type', $type );
	}

	/**
	 * Get the changelog.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_changelog() {
		return $this->changelog;
	}

	/**
	 * Set the changelog for this release.
	 *
	 * @since 1.0
	 *
	 * @param string $changelog
	 * @param string $mode If replace, replaces changelog. If append, appends
	 *                     to changelog. Default replace.
	 */
	public function set_changelog( $changelog, $mode = 'replace' ) {

		if ( $mode == 'append' ) {
			$this->changelog .= $changelog;
		} else {
			$this->changelog = $changelog;
		}

		$this->update( 'changelog', $this->changelog );
	}

	/**
	 * Get the date when this release started.
	 *
	 * @since 1.0
	 *
	 * @return \DateTime|null
	 */
	public function get_start_date() {
		if ( $this->start_date ) {
			return new \DateTimeImmutable( $this->start_date->format( \DateTime::ISO8601 ) );
		}

		return $this->start_date;
	}

	/**
	 * Set the start date.
	 *
	 * @since 1.0
	 *
	 * @param \DateTime|null $start_date
	 */
	public function set_start_date( \DateTime $start_date = null ) {
		$this->start_date = $start_date;

		if ( $start_date ) {
			$val = $start_date->format( "Y-m-d H:i:s" );
		} else {
			$val = null;
		}

		$this->update( 'start_date', $val );
	}

	/**
	 * Add metadata to this release.
	 *
	 * @since 1.0
	 *
	 * @param string $key    Metadata key
	 * @param mixed  $value  Metadata value. Must be serializable if
	 *                       non-scalar.
	 * @param bool   $unique Optional, default is false. Whether the meta key
	 *                       should be unique for this release.
	 *
	 * @return false|int The meta ID on success, false on failure.
	 */
	public function add_meta( $key, $value, $unique = false ) {
		return add_metadata( 'itelic_release', $this->get_ID(), $key, $value, $unique );
	}

	/**
	 * Retrieve metadata for this release..
	 *
	 * @since 1.5.0
	 *
	 * @param string $key     Optional. The meta key to retrieve. By default,
	 *                        returns data for all keys. Default empty.
	 * @param bool   $single  Optional. Whether to return a single value.
	 *                        Default false.
	 *
	 * @return mixed Will be an array if $single is false. Will be value of
	 *               meta data field if $single is true.
	 */
	public function get_meta( $key = '', $single = false ) {
		return get_metadata( 'itelic_release', $this->get_ID(), $key, $single );
	}

	/**
	 * Update metadata for this release.
	 *
	 * @since 1.0
	 *
	 * @param string $key        Metadata key.
	 * @param mixed  $value      Metadata value. Must be serializable if
	 *                           non-scalar.
	 * @param string $prev_value Optional. Previous value to check before
	 *                           removing. Default empty.
	 *
	 * @return bool|int Meta ID if the key didn't exist, true on successful
	 *                  update, false on failure.
	 */
	public function update_meta( $key, $value, $prev_value = '' ) {
		return update_metadata( 'itelic_release', $this->get_ID(), $key, $value, $prev_value );
	}

	/**
	 * Remove metadata from this release.
	 *
	 * @param string $key   Metadata key.
	 * @param mixed  $value Optional. Metadata value. Must be serializable if
	 *                      non-scalar. Default empty.
	 *
	 * @return bool
	 */
	public function delete_meta( $key, $value = '' ) {
		return delete_metadata( 'itelic_release', $this->get_ID(), $key, $value );
	}

	/**
	 * Get a count of all of the sites that have been updated.
	 *
	 * @since 1.0
	 */
	public function get_total_updated() {

		$found = null;

		$count = wp_cache_get( $this->get_ID(), 'itelic-release-upgrade-count', false, $found );

		if ( ! $found ) {

			$simple_query = Manager::make_simple_query_object( 'itelic-updates' );
			$count        = $simple_query->count( array(
				'release_id' => $this->get_ID()
			) );

			wp_cache_set( $this->get_ID(), $count, 'itelic-release-upgrade-count', HOUR_IN_SECONDS );
		}

		return $count;
	}

	/**
	 * Get the total activations for this product.
	 *
	 * todo determine correct location for this method
	 *
	 * @since 1.0
	 *
	 * @return int
	 */
	public function get_total_active_activations() {

		$query = new Activations( array(
			'status'       => Activation::ACTIVE,
			'product'      => $this->get_product()->ID,
			'return_value' => 'count'
		) );

		return $query->get_total_items();
	}

	/**
	 * The __toString method allows a class to decide how it will react when it
	 * is converted to a string.
	 *
	 * @return string
	 * @link http://php.net/manual/en/language.oop5.magic.php#language.oop5.magic.tostring
	 */
	public function __toString() {
		return sprintf( '%1$s – v%2$s', $this->get_product()->post_title, $this->get_version() );
	}

	/**
	 * Get a list of the various statuses.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			self::STATUS_DRAFT    => __( "Draft", Plugin::SLUG ),
			self::STATUS_ACTIVE   => __( "Active", Plugin::SLUG ),
			self::STATUS_PAUSED   => __( "Paused", Plugin::SLUG ),
			self::STATUS_ARCHIVED => __( "Archived", Plugin::SLUG )
		);
	}

	/**
	 * Get a list of the various types of releases.
	 *
	 * @since 1.0
	 *
	 * @param bool $short
	 *
	 * @return array
	 */
	public static function get_types( $short = false ) {

		if ( $short ) {
			return array(
				self::TYPE_MAJOR      => __( "Major", Plugin::SLUG ),
				self::TYPE_MINOR      => __( "Minor", Plugin::SLUG ),
				self::TYPE_SECURITY   => __( "Security", Plugin::SLUG ),
				self::TYPE_PRERELEASE => __( "Pre-release", Plugin::SLUG ),
				self::TYPE_RESTRICTED => __( "Restricted", Plugin::SLUG )
			);
		}

		return array(
			self::TYPE_MAJOR      => __( "Major Release", Plugin::SLUG ),
			self::TYPE_MINOR      => __( "Minor Release", Plugin::SLUG ),
			self::TYPE_SECURITY   => __( "Security Release", Plugin::SLUG ),
			self::TYPE_PRERELEASE => __( "Pre-release", Plugin::SLUG ),
			self::TYPE_RESTRICTED => __( "Restricted Release", Plugin::SLUG )
		);
	}

	/**
	 * Get the table object for this model.
	 *
	 * @since 1.0
	 *
	 * @returns Table
	 */
	protected static function get_table() {
		return Manager::get( 'itelic-releases' );
	}
}