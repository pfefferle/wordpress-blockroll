<?php
/**
 * Import REST controller.
 *
 * @package Blockroll
 */

namespace Blockroll\Rest;

use Blockroll\Import;

/**
 * REST endpoint that turns an OPML document into blogroll links.
 */
class Import_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'import';

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
						'opml' => array(
							'description' => \__( 'An OPML document.', 'blockroll' ),
							'type'        => 'string',
						),
						'url'  => array(
							'description'       => \__( 'A URL to an OPML document.', 'blockroll' ),
							'type'              => 'string',
							'format'            => 'uri',
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
	 * Parse the OPML into links.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Parsed links.
	 */
	public function create_item( $request ) {
		$opml = $request->get_param( 'opml' );

		if ( ! $opml && $request->get_param( 'url' ) ) {
			$response = \wp_safe_remote_get(
				$request->get_param( 'url' ),
				array(
					'timeout'             => 10,
					'limit_response_size' => MB_IN_BYTES,
				)
			);
			if ( \is_wp_error( $response ) || 200 !== \wp_remote_retrieve_response_code( $response ) ) {
				return new \WP_Error(
					'blockroll_fetch_failed',
					\__( 'The OPML file could not be fetched.', 'blockroll' ),
					array( 'status' => 502 )
				);
			}
			$opml = \wp_remote_retrieve_body( $response );
		}

		if ( ! $opml ) {
			return new \WP_Error(
				'blockroll_missing_opml',
				\__( 'Provide an OPML document or a URL to one.', 'blockroll' ),
				array( 'status' => 400 )
			);
		}

		$links = Import::parse( $opml );
		if ( \is_wp_error( $links ) ) {
			return $links;
		}

		return \rest_ensure_response( array( 'links' => $links ) );
	}

	/**
	 * Schema of the import result.
	 *
	 * @return array Item schema.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'blockroll-import',
			'type'       => 'object',
			'properties' => array(
				'links' => array(
					'description' => \__( 'The imported links.', 'blockroll' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'object' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
