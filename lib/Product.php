<?php
/**
 * Contains method useful for interacting with a licensing product.
 *
 * @author    Iron Bound Designs
 * @since     1.0
 * @license   AGPL
 * @copyright Iron Bound Designs, 2015.
 */

namespace ITELIC;

/**
 * Class Product
 *
 * @package ITELIC
 */
class Product extends \IT_Exchange_Product {

	/**
	 * Constructor.
	 *
	 * @param \IT_Exchange_Product $product
	 */
	public function __construct( \IT_Exchange_Product $product ) {

		if ( ! $product->product_type == 'digital-downloads-product-type' ) {
			throw new \InvalidArgumentException( "Product must have the digital downloads product type." );
		}

		parent::__construct( $product->ID );
	}

	/**
	 * Get a product instance.
	 *
	 * @since 1.0
	 *
	 * @param int $ID
	 *
	 * @return Product|null
	 */
	public static function get( $ID ) {
		$product = it_exchange_get_product( $ID );

		if ( ! $product ) {
			return null;
		}

		try {
			return new self( $product );
		}
		catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Check if this product has a feature.
	 *
	 * @since 1.0
	 *
	 * @param string $feature
	 * @param array  $options
	 *
	 * @return bool
	 */
	public function has_feature( $feature, $options = array() ) {
		return it_exchange_product_has_feature( $this->ID, $feature, $options );
	}

	/**
	 * Get a product feature.
	 *
	 * @since 1.0
	 *
	 * @param string $feature
	 * @param array  $options
	 *
	 * @return mixed
	 */
	public function get_feature( $feature, $options = array() ) {
		return it_exchange_get_product_feature( $this->ID, $feature, $options );
	}

	/**
	 * Update a product feature.
	 *
	 * @since 1.0
	 *
	 * @param string $feature
	 * @param mixed  $data
	 * @param array  $options
	 *
	 * @return bool
	 */
	public function update_feature( $feature, $data, $options = array() ) {
		return it_exchange_update_product_feature( $this->ID, $feature, $data, $options );
	}

	/**
	 * Get the changelog for this product.
	 *
	 * @since 1.0
	 *
	 * @param int $num_releases
	 *
	 * @return string
	 */
	public function get_changelog( $num_releases = 10 ) {

		$log = wp_cache_get( $this->ID, 'itelic-changelog' );

		if ( ! $log ) {

			$releases = itelic_get_releases( array(
				'product'        => $this->ID,
				'status'         => array(
					Release::STATUS_ACTIVE,
					Release::STATUS_ARCHIVED,
					Release::STATUS_PAUSED
				),
				'order'          => array( 'start_date' => 'DESC' ),
				'items_per_page' => $num_releases
			) );

			$log = '';

			foreach ( $releases as $release ) {
				$log .= "<strong>v{$release->get_version()} – {$release->get_start_date()->format( get_option( 'date_format' ) )}</strong>";
				$log .= $release->get_changelog();
			}

			wp_cache_set( $this->ID, $log, 'itelic-changelog' );
		}

		return $log;
	}

	/**
	 * Get the latest release available for an activation record.
	 *
	 * By default, returns the latest version saved. But is used for getting
	 * pre-release or restricted versions.
	 *
	 * @since 1.0
	 *
	 * @param Activation $activation
	 *
	 * @return Release
	 */
	public function get_latest_release_for_activation( Activation $activation ) {

		$track = $activation->get_meta( 'track', true );

		if ( ! $track || $track != 'pre-release' ) {
			$version = it_exchange_get_product_feature( $this->ID, 'licensing', array( 'field' => 'version' ) );
			$release = itelic_get_release_by_version( $this->ID, $version );
		} else {
			$releases = itelic_get_releases( array(
				'product'        => $activation->get_key()->get_product()->ID,
				'order'          => array(
					'start_date' => 'DESC'
				),
				'items_per_page' => 1
			) );

			$release = reset( $releases );
		}

		/**
		 * Filter the latest release for an activation record.
		 *
		 * @since 1.0
		 *
		 * @param Release    $release
		 * @param Activation $activation
		 */

		return apply_filters( 'itelic_get_latest_release_for_activation', $release, $activation );
	}
}