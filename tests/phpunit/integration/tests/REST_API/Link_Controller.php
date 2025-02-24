<?php

namespace Google\Web_Stories\Tests\Integration\REST_API;

use Google\Web_Stories\Tests\Integration\DependencyInjectedRestTestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class Link_Controller
 *
 * @package Google\Web_Stories\Tests\REST_API
 *
 * @coversDefaultClass \Google\Web_Stories\REST_API\Link_Controller
 */
class Link_Controller extends DependencyInjectedRestTestCase {

	protected static $editor;
	protected static $subscriber;

	const URL_INVALID          = 'https://https://invalid.commmm';
	const URL_404              = 'https://example.com/404';
	const URL_500              = 'https://example.com/500';
	const URL_CHARACTERS       = 'https://example.com/characters';
	const URL_EMPTY_DOCUMENT   = 'https://example.com/empty';
	const URL_VALID_TITLE_ONLY = 'https://example.com';
	const URL_VALID            = 'https://amp.dev';

	/**
	 * Count of the number of requests attempted.
	 *
	 * @var int
	 */
	protected $request_count = 0;

	/**
	 * Test instance.
	 *
	 * @var \Google\Web_Stories\REST_API\Link_Controller
	 */
	private $controller;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$subscriber = $factory->user->create(
			[
				'role' => 'subscriber',
			]
		);
		self::$editor     = $factory->user->create(
			[
				'role'       => 'editor',
				'user_email' => 'editor@example.com',
			]
		);
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
		$this->request_count = 0;

		$this->controller = $this->injector->make( \Google\Web_Stories\REST_API\Link_Controller::class );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ] );

		parent::tear_down();
	}

	/**
	 * Intercept link processing requests and mock responses.
	 *
	 * @param mixed  $preempt Whether to preempt an HTTP request's return value. Default false.
	 * @param mixed  $r       HTTP request arguments.
	 * @param string $url     The request URL.
	 * @return array|WP_Error Response data.
	 */
	public function mock_http_request( $preempt, $r, $url ) {
		++ $this->request_count;

		if ( false !== strpos( $url, self::URL_INVALID ) ) {
			return $preempt;
		}

		if ( false !== strpos( $url, self::URL_404 ) ) {
			return [
				'response' => [
					'code' => 404,
				],
			];
		}

		if ( false !== strpos( $url, self::URL_500 ) ) {
			return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
		}

		if ( false !== strpos( $url, self::URL_EMPTY_DOCUMENT ) ) {
			return [
				'response' => [
					'code' => 200,
				],
				'body'     => '<html></html>',
			];
		}

		if ( false !== strpos( $url, self::URL_CHARACTERS ) ) {
			return [
				'response' => [
					'code' => 200,
				],
				'body'     => file_get_contents( WEB_STORIES_TEST_DATA_DIR . '/characters.example.com.html' ),
			];
		}

		if ( false !== strpos( $url, self::URL_VALID_TITLE_ONLY ) ) {
			return [
				'response' => [
					'code' => 200,
				],
				'body'     => file_get_contents( WEB_STORIES_TEST_DATA_DIR . '/example.com.html' ),
			];
		}

		if ( false !== strpos( $url, self::URL_VALID ) ) {
			return [
				'response' => [
					'code' => 200,
				],
				'body'     => file_get_contents( WEB_STORIES_TEST_DATA_DIR . '/amp.dev.html' ),
			];
		}

		return $preempt;
	}

	/**
	 * @covers ::register
	 */
	public function test_register() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/web-stories/v1/link', $routes );

		$route = $routes['/web-stories/v1/link'];
		$this->assertCount( 1, $route );
		$this->assertArrayHasKey( 'callback', $route[0] );
		$this->assertArrayHasKey( 'permission_callback', $route[0] );
		$this->assertArrayHasKey( 'methods', $route[0] );
		$this->assertArrayHasKey( 'args', $route[0] );
	}

	/**
	 * @covers ::parse_link_permissions_check
	 */
	public function test_no_user() {
		$this->controller->register();

		$request  = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * @covers ::parse_link_permissions_check
	 */
	public function test_without_permission() {
		$this->controller->register();

		wp_set_current_user( self::$subscriber );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_VALID );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $data['code'], 'rest_forbidden' );
	}

	/**
	 * @covers ::parse_link
	 */
	public function test_url_invalid_url() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_INVALID );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 0, $this->request_count );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_url_returning_500() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_500 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_url', $response, 404 );
	}

	public function test_url_returning_404() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_404 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEqualSetsWithIndex(
			[
				'title'       => '',
				'image'       => '',
				'description' => '',
			],
			$data
		);
	}

	public function test_url_empty_string() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', '' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 0, $this->request_count );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_empty_url() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_EMPTY_DOCUMENT );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$expected = [
			'title'       => '',
			'image'       => '',
			'description' => '',
		];

		// Subsequent requests is cached and so it should not cause a request.
		rest_get_server()->dispatch( $request );

		$this->assertEquals( 1, $this->request_count );
		$this->assertNotEmpty( $data );
		$this->assertEqualSetsWithIndex( $expected, $data );
	}

	public function test_characters_url() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_CHARACTERS );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$expected = [
			'title'       => 'Chizuru Kagura estará em The King of Fighters XV; novo trailer',
			'image'       => '',
			'description' => 'Com a revelação de Chizuru, foi revelado a segunda equipe: a “Team Sacred Treasures”, […]',
		];

		// Subsequent requests is cached and so it should not cause a request.
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, $this->request_count );

		$this->assertNotEmpty( $data );
		$this->assertEqualSetsWithIndex( $expected, $data );
	}

	public function test_example_url() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_VALID_TITLE_ONLY );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$expected = [
			'title'       => 'Example Domain',
			'image'       => '',
			'description' => '',
		];

		// Subsequent requests is cached and so it should not cause a request.
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, $this->request_count );

		$this->assertNotEmpty( $data );
		$this->assertEqualSetsWithIndex( $expected, $data );
	}

	public function test_valid_url() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_VALID );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$expected = [
			'title'       => 'AMP - a web component framework to easily create user-first web experiences - amp.dev',
			'image'       => 'https://amp.dev/static/img/sharing/default-600x314.png',
			'description' => 'Whether you are a publisher, e-commerce company, storyteller, advertiser or email sender, AMP makes it easy to create great experiences on the web. Use AMP to build websites, stories, ads and emails.',
		];

		// Subsequent requests is cached and so it should not cause a request.
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, $this->request_count );

		$this->assertNotEmpty( $data );
		$this->assertEqualSetsWithIndex( $expected, $data );
	}

	public function test_removes_trailing_slashes() {
		$this->controller->register();

		wp_set_current_user( self::$editor );
		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_VALID_TITLE_ONLY );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$expected = [
			'title'       => 'Example Domain',
			'image'       => '',
			'description' => '',
		];

		$request = new WP_REST_Request( WP_REST_Server::READABLE, '/web-stories/v1/link' );
		$request->set_param( 'url', self::URL_VALID_TITLE_ONLY . '/' );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, $this->request_count );

		$this->assertNotEmpty( $data );
		$this->assertEqualSetsWithIndex( $expected, $data );
	}
}
