<?php
/**
 * Discovery REST controller.
 *
 * @package Blockroll
 */

namespace Blockroll\Rest;

use Blockroll\Discovery;
use Blockroll\Icon;

/**
 * REST endpoint that fetches a URL and extracts link details.
 *
 * The editor cannot fetch foreign sites itself (CORS), so it asks
 * this endpoint to do it.
 */
class Discovery_Controller extends \WP_REST_Controller {
	use Helpers;

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
						'url' => \array_merge(
							$this->url_arg( \__( 'The URL to inspect.', 'blockroll' ) ),
							array( 'required' => true )
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Fetch the URL and extract link details.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Extracted link data.
	 */
	public function create_item( $request ) {
		$url  = $request->get_param( 'url' );
		$body = $this->fetch_url( $url, \__( 'The site could not be reached.', 'blockroll' ) );
		if ( \is_wp_error( $body ) ) {
			return $body;
		}

		$found = Discovery::from_html( $body, $url );

		// The icon is fetched here, once, and travels with the post from
		// then on. The visitor's browser never asks the foreign server.
		$found['photo'] = Icon::embed( $found['photo'] );

		return \rest_ensure_response(
			\array_merge( $found, array( 'url' => \esc_url_raw( $url ) ) )
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
					'description' => \__( 'An image for the site, embedded in the page.', 'blockroll' ),
					'type'        => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
