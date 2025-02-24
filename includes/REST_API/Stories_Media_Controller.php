<?php
/**
 * Class Stories_Media_Controller
 *
 * @package   Google\Web_Stories
 * @copyright 2020 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://github.com/google/web-stories-wp
 */

/**
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Web_Stories\REST_API;

use Google\Web_Stories\Infrastructure\Delayed;
use Google\Web_Stories\Infrastructure\Registerable;
use Google\Web_Stories\Infrastructure\Service;
use Google\Web_Stories\Media\Types;
use WP_Post;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Attachments_Controller;

/**
 * Stories_Media_Controller class.
 */
class Stories_Media_Controller extends WP_REST_Attachments_Controller implements Service, Delayed, Registerable {
	/**
	 * Types instance.
	 *
	 * @var Types Types instance.
	 */
	private $types;

	/**
	 * Constructor.
	 *
	 * Override the namespace.
	 *
	 * @param Types $types Types instance.
	 *
	 * @since 1.0.0
	 */
	public function __construct( Types $types ) {
		parent::__construct( 'attachment' );
		$this->namespace = 'web-stories/v1';
		$this->types     = $types;
	}

	/**
	 * Register the service.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	public function register() {
		$this->register_routes();
	}

	/**
	 * Get the action to use for registering the service.
	 *
	 * @since 1.7.0
	 *
	 * @return string Registration action to use.
	 */
	public static function get_registration_action(): string {
		return 'rest_api_init';
	}

	/**
	 * Get the action priority to use for registering the service.
	 *
	 * @since 1.7.0
	 *
	 * @return int Registration action priority to use.
	 */
	public static function get_registration_action_priority(): int {
		return 100;
	}

	/**
	 * Retrieves a collection of media.
	 *
	 * Read _web_stories_envelope param to envelope response.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$response = parent::get_items( $request );

		if ( $request['_web_stories_envelope'] && ! is_wp_error( $response ) ) {
			$embed    = isset( $request['_embed'] ) ? rest_parse_embed_param( $request['_embed'] ) : false;
			$response = rest_get_server()->envelope_response( $response, $embed );
		}
		return $response;
	}

	/**
	 * Creates a single attachment.
	 *
	 * Override the existing method so we can set parent id.
	 *
	 * @since 1.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, WP_Error object on failure.
	 */
	public function create_item( $request ) {
		// WP_REST_Attachments_Controller doesn't allow setting an attachment as the parent post.
		// Hence we are working around this here.
		$parent_post = ! empty( $request['post'] ) ? (int) $request['post'] : null;
		$original_id = ! empty( $request['original_id'] ) ? (int) $request['original_id'] : null;
		unset( $request['post'] );

		$response = parent::create_item( $request );
		if ( is_wp_error( $response ) || ( ! $parent_post && ! $original_id ) ) {
			return $response;
		}

		$data       = $response->get_data();
		$attachment = $this->process_post( $data['id'], $parent_post, $original_id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}

		$new_response = $this->prepare_item_for_response( $attachment, $request );
		$data         = $new_response->get_data();
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Process post to update attribute.
	 *
	 * @since 1.11.0
	 *
	 * @param int      $post_id Post id.
	 * @param int|null $parent_post New post parent. Default null.
	 * @param int|null $original_id Original id to copy data from. Default null.
	 *
	 * @return WP_Post|WP_Error
	 */
	protected function process_post( $post_id, $parent_post, $original_id ) {
		$args = [ 'ID' => $post_id ];

		if ( $parent_post ) {
			$args['post_parent'] = $parent_post;
		}

		if ( $original_id ) {
			$attachment_post = $this->get_post( (int) $original_id );
			if ( is_wp_error( $attachment_post ) ) {
				return $attachment_post;
			}
			$args['post_content'] = $attachment_post->post_content;
			$args['post_excerpt'] = $attachment_post->post_excerpt;
			$args['post_title']   = $attachment_post->post_title;

			// Copy the image alt text from the edited image.
			$image_alt = (string) get_post_meta( $original_id, '_wp_attachment_image_alt', true );

			if ( ! empty( $image_alt ) ) {
				// update_post_meta() expects slashed.
				update_post_meta( $post_id, '_wp_attachment_image_alt', wp_slash( $image_alt ) );
			}
		}

		$attachment_id = wp_update_post( $args, true );
		if ( is_wp_error( $attachment_id ) ) {
			if ( 'db_update_error' === $attachment_id->get_error_code() ) {
				$attachment_id->add_data( [ 'status' => 500 ] );
			} else {
				$attachment_id->add_data( [ 'status' => 400 ] );
			}

			return $attachment_id;
		}

		return $this->get_post( $attachment_id );
	}

	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * @since 1.0.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params(): array {
		$query_params = parent::get_collection_params();

		$query_params['_web_stories_envelope'] = [
			'description' => __( 'Envelope request for preloading.', 'web-stories' ),
			'type'        => 'boolean',
			'default'     => false,
		];

		return $query_params;
	}

	/**
	 * Filter request by allowed mime types.
	 *
	 * @since 1.2.0
	 *
	 * @param array           $prepared_args Optional. Array of prepared arguments. Default empty array.
	 * @param WP_REST_Request $request       Optional. Request to prepare items for.
	 * @return array Array of query arguments.
	 */
	protected function prepare_items_query( $prepared_args = [], $request = null ) {
		$query_args = parent::prepare_items_query( $prepared_args, $request );

		if ( empty( $request['mime_type'] ) && empty( $request['media_type'] ) ) {
			$media_types      = $this->get_media_types();
			$media_type_mimes = array_values( $media_types );
			$media_type_mimes = array_filter( $media_type_mimes );
			$media_type_mimes = array_merge( ...$media_type_mimes );

			$query_args['post_mime_type'] = $media_type_mimes;
		}

		/**
		 * Filters WP_Query arguments when querying posts via the REST API.
		 *
		 * @since 1.10.0
		 *
		 * @link https://developer.wordpress.org/reference/classes/wp_query/
		 *
		 * @param array           $args    Array of arguments for WP_Query.
		 * @param WP_REST_Request $request The REST API request.
		 */
		return apply_filters( 'web_stories_rest_attachment_query', $query_args, $request );
	}


	/**
	 * Prepares a single attachment output for response.
	 *
	 * @since 1.7.2
	 *
	 * @param WP_Post         $post    Attachment object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $post, $request ) {
		$response = parent::prepare_item_for_response( $post, $request );

		/**
		 * Filters an attachment returned from the REST API.
		 *
		 * Allows modification of the attachment right before it is returned.
		 *
		 * Note the filter is run after rest_prepare_attachment is run. This filter is designed to only target web stories rest api requests.
		 *
		 * @since 1.7.2
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_Post          $post     The original attachment post.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'web_stories_rest_prepare_attachment', $response, $post, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @since 1.12.0
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $post ): array {
		$links = parent::prepare_links( $post );
		$links = $this->add_taxonomy_links( $links, $post );

		return $links;
	}

	/**
	 * Adds a REST API links for the taxonomies.
	 *
	 * @since 1.12.0
	 *
	 * @param array   $links Links for the given post.
	 * @param WP_Post $post Post object.
	 *
	 * @return array Modified list of links.
	 */
	private function add_taxonomy_links( array $links, WP_Post $post ): array {
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );

		if ( empty( $taxonomies ) ) {
			return $links;
		}
		$links['https://api.w.org/term'] = [];

		foreach ( $taxonomies as $taxonomy_obj ) {
			// Skip taxonomies that are not public.
			if ( empty( $taxonomy_obj->show_in_rest ) ) {
				continue;
			}

			$controller = $taxonomy_obj->get_rest_controller();

			if ( ! $controller ) {
				continue;
			}

			$namespace = method_exists( $controller, 'get_namespace' ) ? $controller->get_namespace() : 'wp/v2';
			$tax       = $taxonomy_obj->name;
			$tax_base  = ! empty( $taxonomy_obj->rest_base ) ? $taxonomy_obj->rest_base : $tax;

			$terms_url = add_query_arg(
				'post',
				$post->ID,
				rest_url( sprintf( '%s/%s', $namespace, $tax_base ) )
			);

			$links['https://api.w.org/term'][] = [
				'href'       => $terms_url,
				'taxonomy'   => $tax,
				'embeddable' => true,
			];
		}
		return $links;
	}

	/**
	 * Retrieves the attachment's schema, conforming to JSON Schema.
	 *
	 * Removes some unneeded fields to improve performance by
	 * avoiding some expensive database queries.
	 *
	 * @since 1.10.0
	 *
	 * @return array Item schema as an array.
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = parent::get_item_schema();

		unset(
			$schema['properties']['permalink_template'],
			$schema['properties']['generated_slug'],
			$schema['properties']['description']
		);

		$schema['properties']['original_id'] = [
			'description' => __( 'Unique identifier for original attachment id.', 'web-stories' ),
			'type'        => 'integer',
			'context'     => [ 'view', 'edit', 'embed' ],
		];

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the supported media types.
	 *
	 * Media types are considered the MIME type category.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array of supported media types.
	 */
	protected function get_media_types(): array {
		return $this->types->get_allowed_mime_types();
	}
}
