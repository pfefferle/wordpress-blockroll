<?php
/**
 * Sources REST controller.
 *
 * @package Blockroll
 */

namespace Blockroll\Rest;

use Blockroll\Sources;

/**
 * REST endpoint that lists available blogroll sources.
 */
class Sources_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'sources';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<source>[\w-]+)/links',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_links' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'source' => array(
							'description'       => \__( 'The source slug.', 'blockroll' ),
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => function ( $source ) {
								return \sanitize_key( $source ) === $source;
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Get source options.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response Source options.
	 */
	public function get_items( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature of WP_REST_Controller.
		$sources = array();
		foreach ( Sources::all() as $value => $label ) {
			$sources[] = array(
				'value' => $value,
				'label' => (string) $label,
			);
		}

		return \rest_ensure_response( $sources );
	}

	/**
	 * Get normalized preview links for a source.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error Source links, or an error for an unknown source.
	 */
	public function get_links( $request ) {
		$source  = \sanitize_key( $request['source'] );
		$sources = Sources::all();
		if ( Sources::MANUAL === $source || ! isset( $sources[ $source ] ) ) {
			return new \WP_Error(
				'blockroll_unknown_source',
				\__( 'Unknown source.', 'blockroll' ),
				array( 'status' => 404 )
			);
		}

		return \rest_ensure_response(
			Sources::links(
				array(
					'source' => $source,
				)
			)
		);
	}

	/**
	 * Schema of a source.
	 *
	 * @return array Item schema.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'blockroll-source',
			'type'       => 'object',
			'properties' => array(
				'value' => array(
					'description' => \__( 'The source slug.', 'blockroll' ),
					'type'        => 'string',
				),
				'label' => array(
					'description' => \__( 'The source label.', 'blockroll' ),
					'type'        => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
