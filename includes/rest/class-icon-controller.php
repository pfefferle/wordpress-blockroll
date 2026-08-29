<?php
/**
 * Icon REST controller.
 *
 * @package Blockroll
 */

namespace Blockroll\Rest;

use Blockroll\Icon;

/**
 * REST endpoint that turns an image URL into an embedded icon.
 *
 * The editor uses it when somebody enters an image address by hand.
 * The `discover` route parses HTML and cannot do anything with an
 * image, and the browser cannot fetch a foreign image either.
 */
class Icon_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'icon';

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
							$this->url_arg( \__( 'The image to embed.', 'blockroll' ) ),
							array( 'required' => true )
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Fetch the image and return it as a data URI.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error The embedded image.
	 */
	public function create_item( $request ) {
		$photo = Icon::embed( $request->get_param( 'url' ) );

		if ( '' === $photo ) {
			return new \WP_Error(
				'blockroll_no_image',
				\__( 'That address did not give us an image we can use.', 'blockroll' ),
				array( 'status' => 502 )
			);
		}

		return \rest_ensure_response( array( 'photo' => $photo ) );
	}

	/**
	 * Schema of the embedded icon.
	 *
	 * @return array Item schema.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'blockroll-icon',
			'type'       => 'object',
			'properties' => array(
				'photo' => array(
					'description' => \__( 'The image, embedded in the page.', 'blockroll' ),
					'type'        => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
