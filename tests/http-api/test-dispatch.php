<?php
/**
 * Test the API dispatcher.
 *
 * @author Iron Bound Designs
 * @since  1.0
 */
use ITELIC\API\Contracts\Authenticatable;

/**
 * Class ITELIC_Test_HTTP_API
 */
class ITELIC_Test_HTTP_API extends ITELIC_UnitTestCase {

	public function test_unknown_response_generates_404() {

		$dispatch = new \ITELIC\API\Dispatch();

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'garbage' );

		$response = $dispatch->process( $mock_wp_query );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );
		$this->assertFalse( $data['success'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 404, $data['error']['code'] );
	}

	public function test_dispatch_calls_endpoint_serve_method_and_returns_given_response() {

		$response = new \ITELIC\API\Response();

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Endpoint' )->getMock();
		$mock_endpoint->expects( $this->once() )->method( 'serve' )->willReturn( $response );

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$this->assertEquals( $response, $dispatch->process( $mock_wp_query ) );
	}

	public function test_error_response_generated_if_api_exception_thrown() {

		$exception = new \ITELIC\API\Exception( 'Error', 5 );

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Endpoint' )->getMock();
		$mock_endpoint->expects( $this->once() )->method( 'serve' )->will( $this->throwException( $exception ) );

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$response = $dispatch->process( $mock_wp_query );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertArrayHasKey( 'message', $data['error'] );
		$this->assertArrayHasKey( 'code', $data['error'] );
		$this->assertEquals( $exception->getMessage(), $data['error']['message'] );
		$this->assertEquals( $exception->getCode(), $data['error']['code'] );
	}

	public function test_existing_license_key_auth_mode_rejects_request_without_key() {

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Authenticatable' )->getMock();
		$mock_endpoint->expects( $this->never() )->method( 'serve' );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_mode' )->willReturn( Authenticatable::MODE_EXISTS );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_error_code' )->willReturn( \ITELIC\API\Endpoint::CODE_INVALID_KEY );

		WP_Mock::wpFunction( 'itelic_get_key', array(
			'times'  => 1,
			'args'   => array( 'abcd-1234' ),
			'return' => false
		) );

		$_SERVER['PHP_AUTH_USER'] = 'abcd-1234';

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$response = $dispatch->process( $mock_wp_query );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( \ITELIC\API\Endpoint::CODE_INVALID_KEY, $data['error']['code'] );
	}

	/**
	 * @dataProvider existing_license_key_auth_mode_data_provider
	 */
	public function test_existing_license_key_auth_mode_returns_valid_response_with_key_of_any_status( $key ) {

		$response = new \ITELIC\API\Response();

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Authenticatable' )->getMock();
		$mock_endpoint->expects( $this->once() )->method( 'serve' )->willReturn( $response );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_mode' )->willReturn( Authenticatable::MODE_EXISTS );

		WP_Mock::wpFunction( 'itelic_get_key', array(
			'times'  => 1,
			'args'   => array( 'abcd-1234' ),
			'return' => $key
		) );

		$_SERVER['PHP_AUTH_USER'] = 'abcd-1234';

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$this->assertEquals( $response, $dispatch->process( $mock_wp_query ) );
	}

	public function existing_license_key_auth_mode_data_provider() {

		$mock_key = $this->getMockBuilder( '\ITELIC\Key' )->disableOriginalConstructor()->getMock();
		$mock_key->method( 'get_status' )->willReturn( \ITELIC\Key::ACTIVE );

		$keys = array(
			'Active license key' => array( $mock_key )
		);

		$mock_key->method( 'get_status' )->willReturn( \ITELIC\Key::EXPIRED );
		$keys['Expired license key'] = array( $mock_key );

		$mock_key->method( 'get_status' )->willReturn( \ITELIC\Key::DISABLED );
		$keys['Disabled license key'] = array( $mock_key );

		return $keys;
	}

	public function test_active_license_key_auth_mode_rejects_request_without_key() {

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Authenticatable' )->getMock();
		$mock_endpoint->expects( $this->never() )->method( 'serve' );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_mode' )->willReturn( Authenticatable::MODE_ACTIVE );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_error_code' )->willReturn( \ITELIC\API\Endpoint::CODE_INVALID_KEY );

		WP_Mock::wpFunction( 'itelic_get_key', array(
			'times'  => 1,
			'args'   => array( 'abcd-1234' ),
			'return' => false
		) );

		$_SERVER['PHP_AUTH_USER'] = 'abcd-1234';

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$response = $dispatch->process( $mock_wp_query );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( \ITELIC\API\Endpoint::CODE_INVALID_KEY, $data['error']['code'] );
	}

	/**
	 * @dataProvider valid_license_key_auth_mode_data_provider
	 */
	public function test_active_license_key_auth_mode_rejects_request_with_non_active_key( $key ) {

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Authenticatable' )->getMock();
		$mock_endpoint->expects( $this->never() )->method( 'serve' );
		$mock_endpoint->expects( $this->exactly( 2 ) )->method( 'get_auth_mode' )->willReturn( Authenticatable::MODE_ACTIVE );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_error_code' )->willReturn( \ITELIC\API\Endpoint::CODE_INVALID_KEY );

		WP_Mock::wpFunction( 'itelic_get_key', array(
			'times'  => 1,
			'args'   => array( 'abcd-1234' ),
			'return' => $key
		) );

		$_SERVER['PHP_AUTH_USER'] = 'abcd-1234';

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$response = $dispatch->process( $mock_wp_query );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( \ITELIC\API\Endpoint::CODE_INVALID_KEY, $data['error']['code'] );
	}

	public function valid_license_key_auth_mode_data_provider() {

		$mock_key = $this->getMockBuilder( '\ITELIC\Key' )->disableOriginalConstructor()->getMock();

		$keys = array();

		$mock_key->method( 'get_status' )->willReturn( \ITELIC\Key::EXPIRED );
		$keys['Expired license key'] = array( $mock_key );

		$mock_key->method( 'get_status' )->willReturn( \ITELIC\Key::DISABLED );
		$keys['Disabled license key'] = array( $mock_key );

		return $keys;
	}

	public function test_active_license_key_autho_mode_returns_valid_response_for_active_key() {

		$response = new \ITELIC\API\Response();

		$mock_endpoint = $this->getMockBuilder( '\ITELIC\API\Contracts\Authenticatable' )->getMock();
		$mock_endpoint->expects( $this->once() )->method( 'serve' )->willReturn( $response );
		$mock_endpoint->expects( $this->once() )->method( 'get_auth_mode' )->willReturn( Authenticatable::MODE_ACTIVE );

		$mock_key = $this->getMockBuilder( '\ITELIC\Key' )->disableOriginalConstructor()->getMock();
		$mock_key->method( 'get_status' )->willReturn( \ITELIC\Key::ACTIVE );

		WP_Mock::wpFunction( 'itelic_get_key', array(
			'times'  => 1,
			'args'   => array( 'abcd-1234' ),
			'return' => $mock_key
		) );

		$_SERVER['PHP_AUTH_USER'] = 'abcd-1234';

		$mock_wp_query = $this->getMockBuilder( '\WP_Query' )->disableOriginalConstructor()->getMock();
		$mock_wp_query->expects( $this->once() )->method( 'get' )->with( 'itelic_api' )->willReturn( 'mock' );

		$dispatch = new \ITELIC\API\Dispatch();
		\ITELIC\API\Dispatch::register_endpoint( $mock_endpoint, 'mock' );

		$this->assertEquals( $response, $dispatch->process( $mock_wp_query ) );
	}

}