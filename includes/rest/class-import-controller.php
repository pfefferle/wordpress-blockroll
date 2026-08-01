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
						'url'  => $this->url_arg( \__( 'A URL to an OPML document.', 'blockroll' ) ),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
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
			$opml = $this->fetch_url(
				$request->get_param( 'url' ),
				\__( 'The OPML file could not be fetched.', 'blockroll' )
			);
			if ( \is_wp_error( $opml ) ) {
				return $opml;
			}
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
