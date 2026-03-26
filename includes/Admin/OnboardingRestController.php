<?php
declare( strict_types=1 );
namespace WP_AI_Mind\Admin;

use WP_AI_Mind\Settings\ProviderSettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class OnboardingRestController {

	public static function register_routes(): void {
		register_rest_route(
			'wp-ai-mind/v1',
			'/onboarding',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'save' ],
				'permission_callback' => [ self::class, 'check_permission' ],
				'args'                => [
					'seen'           => [
						'type'     => 'boolean',
						'required' => false,
					],
					'provider'       => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => [ 'openai', 'claude', 'gemini' ],
					],
					'api_key'        => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'image_provider' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => [ 'openai', 'gemini' ],
					],
				],
			]
		);
	}

	public static function save( WP_REST_Request $request ): WP_REST_Response {
		$seen = $request->get_param( 'seen' );

		if ( true === $seen ) {
			update_option( 'wp_ai_mind_onboarding_seen', true );
		} elseif ( false === $seen ) {
			delete_option( 'wp_ai_mind_onboarding_seen' );
		}

		$provider = $request->get_param( 'provider' );
		if ( $provider ) {
			update_option( 'wp_ai_mind_default_provider', $provider );

			// API key is only stored when a provider is also specified — they are set together.
			$api_key = $request->get_param( 'api_key' );
			if ( $api_key ) {
				$provider_settings = static::make_provider_settings();
				$provider_settings->set_api_key( $provider, $api_key );
			}
		}

		$image_provider = $request->get_param( 'image_provider' );
		if ( $image_provider ) {
			update_option( 'wp_ai_mind_image_provider', $image_provider );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Checks that the current user has the `manage_options` capability.
	 *
	 * @return bool|\WP_Error
	 */
	public static function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage plugin settings.', 'wp-ai-mind' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * Factory method for ProviderSettings — overridable in tests.
	 *
	 * @return ProviderSettings
	 */
	protected static function make_provider_settings(): ProviderSettings {
		return new ProviderSettings();
	}
}
