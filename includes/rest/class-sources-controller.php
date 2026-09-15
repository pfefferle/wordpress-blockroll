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
