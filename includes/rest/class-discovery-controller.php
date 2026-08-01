<?php
/**
 * Discovery REST controller.
 *
 * @package Blockroll
 */

namespace Blockroll\Rest;

use Blockroll\Discovery;

/**
 * REST endpoint that fetches a URL and extracts link details.
 *
 * The editor cannot fetch foreign sites itself (CORS), so it asks
 * this endpoint to do it.
 */
class Discovery_Controller extends \WP_REST_Controller {
	/**
	 * Namespace of the route.
	 *
	 * @var string
	 */
	protected $namespace = 'blockroll/v1';

	/**
	 * Base of the route.
	 *
	 * @var string
	 */
	protected $rest_base = 'discover';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'url' => array(
							'description'       => \__( 'The URL to inspect.', 'blockroll' ),
							'type'              => 'string',
							'format'            => 'uri',
							'required'          => true,
							'validate_callback' => function ( $url ) {
								return (bool) \wp_http_validate_url( $url );
							},
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Whether the current user may use the endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error True when allowed.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! \current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				\__( 'Sorry, you are not allowed to do that.', 'blockroll' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Fetch the URL and extract link details.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Extracted link data.
	 */
	public function create_item( $request ) {
		$url      = $request->get_param( 'url' );
		$response = \wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'limit_response_size' => MB_IN_BYTES,
			)
		);
		if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'blockroll_fetch_failed',
				\__( 'The site could not be reached.', 'blockroll' ),
				array( 'status' => 502 )
			);
		}

		return \rest_ensure_response(
			\array_merge(
				Discovery::from_html( \wp_remote_retrieve_body( $response ), $url ),
				array( 'url' => \esc_url_raw( $url ) )
			)
		);
	}

	/**
	 * Schema of the discovered link.
	 *
	 * @return array Item schema.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'blockroll-link',
			'type'       => 'object',
			'properties' => array(
				'url'         => array(
					'description' => \__( 'The site address.', 'blockroll' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'name'        => array(
					'description' => \__( 'The site name.', 'blockroll' ),
					'type'        => 'string',
				),
				'description' => array(
					'description' => \__( 'A short description of the site.', 'blockroll' ),
					'type'        => 'string',
				),
				'feedUrl'     => array(
					'description' => \__( 'The feed address.', 'blockroll' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'photo'       => array(
					'description' => \__( 'An image for the site.', 'blockroll' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
