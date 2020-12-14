<?php
/**
 * WP_REST_URL_Details_Controller tests.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since x.x.0
 */

/**
 * Tests for WP_REST_URL_Details_Controller.
 *
 * @since x.x.0
 *
 * @covers WP_REST_URL_Details_Controller
 * @group url-details
 * @group restapi
 */
class WP_REST_URL_Details_Controller_Test extends WP_Test_REST_Controller_Testcase {

	/**
	 * Admin user ID.
	 *
	 * @since x.x.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since x.x.0
	 *
	 * @var int $subscriber_id
	 */
	protected static $subscriber_id;


	protected static $route = '/__experimental/url-details';


	protected static $url_placeholder = 'https://dummysite.com';

	protected static $request_args = array();

	/**
	 * Create fake data before our tests run.
	 *
	 * @since x.x.0
	 *
	 * @param WP_UnitTest_Factory $factory Helper that lets us create fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * Setup.
	 */
	public function setUp() {
		parent::setUp();
		add_filter( 'apply_filters', '__return_false' );
		add_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ), 10, 3 );
	}

	/**
	 * Tear down.
	 */
	public function tearDown() {
		remove_filter( 'apply_filters', '__return_false' );
		remove_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ), 10 );
		static::$request_args = array();
		parent::tearDown();
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( static::$route, $routes );
	}

	public function test_context_param() {
	}

	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::$route );
		$request->set_query_params(
			array(
				'url' => static::$url_placeholder,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Note the <title> comes from the fixture HTML returned by
		// the filter `pre_http_request`.
		$this->assertEquals(
			array(
				'title' => 'Example Website &mdash; - with encoded content.',
			),
			$data
		);
	}

	public function test_get_items_fails_for_user_with_insufficient_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', static::$route );
		$request->set_query_params(
			array(
				'url' => static::$url_placeholder,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 403, $response->get_status() );

		$this->assertEquals(
			'rest_user_cannot_view',
			$data['code']
		);

		$this->assertContains(
			strtolower( 'you are not allowed to process remote urls' ),
			strtolower( $data['message'] )
		);
	}

	/**
	 * @dataProvider provide_invalid_url_data
	 */
	public function test_get_items_fails_for_invalid_url( $expected, $invalid_url ) {

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::$route );
		$request->set_query_params(
			array(
				'url' => $invalid_url,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );

		$this->assertEquals(
			'rest_invalid_param',
			$data['code']
		);

		$this->assertContains(
			strtolower( 'Invalid parameter(s): url' ),
			strtolower( $data['message'] )
		);
	}

	public function test_get_items_fails_for_url_which_returns_a_non_200_status_code() {
		// Force HTTP request to remote site to fail.
		remove_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ), 10 );
		add_filter( 'pre_http_request', array( $this, 'mock_failed_request_to_remote_url' ), 10, 3 );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::$route );
		$request->set_query_params(
			array(
				'url' => static::$url_placeholder, // note: `pre_http_request` causes request to 404.
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );

		$this->assertEquals(
			'no_response',
			$data['code']
		);

		$this->assertContains(
			strtolower( 'Not found' ),
			strtolower( $data['message'] )
		);
	}

	public function test_get_items_fails_for_url_which_returns_empty_body_for_success() {
		// Force HTTP request to remote site to return an empty body in response.
		remove_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ), 10 );
		add_filter( 'pre_http_request', array( $this, 'mock_request_to_remote_url_with_empty_body_response' ), 10, 3 );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::$route );
		$request->set_query_params(
			array(
				'url' => static::$url_placeholder, // note: `pre_http_request` causes request to 404.
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 404, $response->get_status() );

		$this->assertEquals(
			'no_content',
			$data['code']
		);

		$this->assertContains(
			strtolower( 'Unable to retrieve body from response at this URL' ),
			strtolower( $data['message'] )
		);
	}

	public function test_can_filter_http_request_args_via_filter() {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'rest_url_details_http_request_args',
			function( $args, $url ) {
				return array_merge(
					$args,
					array(
						'timeout' => 27, // modify default timeout.
						'body'    => $url, // add new and allow to assert on $url arg passed.
					)
				);
			},
			10,
			2
		);

		$request = new WP_REST_Request( 'GET', static::$route );
		$request->set_query_params(
			array(
				'url' => static::$url_placeholder,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArraySubset(
			array(
				'timeout'             => 27,
				'limit_response_size' => 153600,
				'body'                => static::$url_placeholder,
			),
			static::$request_args
		);

		remove_all_filters( 'rest_url_details_http_request_args' );
	}



	public function test_get_item() {
	}

	public function test_create_item() {

	}

	public function test_update_item() {

	}

	public function test_delete_item() {

	}

	public function test_prepare_item() {

	}

	public function test_get_item_schema() {

	}

	public function provide_invalid_url_data() {
		return array(
			'empty_url'          => array(
				null,
				'',
			), // empty!
			'not_a_string'       => array(
				null,
				1234456,
			),
			'string_but_invalid' => array(
				null,
				'invalid.proto://wordpress.org',
			),
		);
	}

	/**
	 * Mocks the HTTP response for the the `wp_safe_remote_get()` which
	 * would otherwise make a call to a real website.
	 *
	 * @return array faux/mocked response.
	 */
	public function mock_success_request_to_remote_url( $response, $args ) {
		return $this->mock_request_to_remote_url( 'success', $args );
	}

	public function mock_failed_request_to_remote_url( $response, $args ) {
		return $this->mock_request_to_remote_url( 'failure', $args );
	}

	public function mock_request_to_remote_url_with_empty_body_response( $response, $args ) {
		return $this->mock_request_to_remote_url( 'empty_body', $args );
	}

	private function mock_request_to_remote_url( $result_type = 'success', $args ) {

		static::$request_args = $args;

		$types = array(
			'success',
			'failure',
			'empty_body',
		);

		// Default to success.
		if ( ! in_array( $result_type, $types, true ) ) {
			$result_type = $types[0];
		}

		// Both should return 200 for the HTTP response.
		$should_200 = 'success' === $result_type || 'empty_body' === $result_type;

		return array(
			'headers'     => array(),
			'cookies'     => array(),
			'filename'    => null,
			'response'    => array( 'code' => ( $should_200 ? 200 : 404 ) ),
			'status_code' => $should_200 ? 200 : 404,
			'success'     => $should_200 ? 1 : 0,
			'body'        => 'success' === $result_type ? file_get_contents( __DIR__ . '/fixtures/example-website.html' ) : '',
		);
	}
}