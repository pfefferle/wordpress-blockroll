<?php
/**
 * Shared REST controller helpers.
 *
 * @package Blockroll
 */

namespace Blockroll\Rest;

/**
 * Helpers used by the plugin's REST controllers.
 */
trait Helpers {
	/**
	 * Whether the current user may use the endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error True when allowed.
	 */
	public function create_item_permissions_check( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature of WP_REST_Controller.
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
	 * Schema of a URL argument.
	 *
	 * @param string $description Argument description.
	 * @return array Argument definition.
	 */
	protected function url_arg( $description ) {
		return array(
			'description'       => $description,
			'type'              => 'string',
			'format'            => 'uri',
			'validate_callback' => function ( $url ) {
				return (bool) \wp_http_validate_url( $url );
			},
		);
	}

	/**
	 * Fetch a URL.
	 *
	 * @param string $url           URL to fetch.
	 * @param string $error_message Message for failed requests.
	 * @return string|\WP_Error Response body.
	 */
	protected function fetch_url( $url, $error_message ) {
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
				$error_message,
				array( 'status' => 502 )
			);
		}
		return \wp_remote_retrieve_body( $response );
	}
}
