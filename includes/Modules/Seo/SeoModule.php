<?php
// includes/Modules/Seo/SeoModule.php
declare( strict_types=1 );

namespace WP_AI_Mind\Modules\Seo;

use WP_AI_Mind\Providers\ProviderFactory;
use WP_AI_Mind\Providers\CompletionRequest;
use WP_AI_Mind\Providers\ProviderException;
use WP_AI_Mind\Settings\ProviderSettings;

class SeoModule {

	public static function register(): void {
		\add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		\add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function enqueue_assets( string $hook ): void {
		// Only load on the SEO admin page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page detection, never output.
		if ( sanitize_key( \wp_unslash( $_GET['page'] ?? '' ) ) !== 'wp-ai-mind-seo' ) {
			return;
		}

		$asset_file = WP_AI_MIND_DIR . 'assets/seo/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [],
				'version'      => WP_AI_MIND_VERSION,
			];

		\wp_enqueue_script(
			'wp-ai-mind-seo',
			WP_AI_MIND_URL . 'assets/seo/index.js',
			array_merge( $asset['dependencies'], [ 'wp-element', 'wp-api-fetch', 'wp-i18n' ] ),
			$asset['version'],
			true
		);

		\wp_localize_script(
			'wp-ai-mind-seo',
			'wpAiMindData',
			[
				'nonce'   => \wp_create_nonce( 'wp_rest' ),
				'restUrl' => \esc_url_raw( \rest_url( 'wp-ai-mind/v1' ) ),
				'isPro'   => \wp_ai_mind_is_pro(),
			]
		);

		\wp_enqueue_style(
			'wp-ai-mind-seo',
			WP_AI_MIND_URL . 'assets/seo/index.css',
			[],
			$asset['version']
		);
	}

	public static function register_routes(): void {
		\register_rest_route(
			'wp-ai-mind/v1',
			'/seo/generate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'handle_generate' ],
				'permission_callback' => fn() => \current_user_can( 'edit_posts' ) && \wp_ai_mind_is_pro(),
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		\register_rest_route(
			'wp-ai-mind/v1',
			'/seo/apply',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'handle_apply' ],
				'permission_callback' => fn() => \current_user_can( 'edit_posts' ) && \wp_ai_mind_is_pro(),
				'args'                => [
					'post_id'        => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'meta_title'     => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'og_description' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'excerpt'        => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'alt_text'       => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public static function handle_generate( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = $request->get_param( 'post_id' );
		$post    = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'error' => __( 'Post not found.', 'wp-ai-mind' ) ], 404 );
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response( [ 'error' => __( 'Forbidden.', 'wp-ai-mind' ) ], 403 );
		}

		$title   = $post->post_title;
		$excerpt = $post->post_excerpt;
		$content = \wp_strip_all_tags( $post->post_content );
		$content = mb_substr( $content, 0, 2000 );

		$alt_text_current = '';
		$thumb_id         = \get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$alt_text_current = \get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
		}

		$prompt = "You are an SEO specialist. Analyse this blog post and return a JSON object with exactly these four keys:\n"
			. "- \"meta_title\": SEO title, maximum 60 characters, compelling and keyword-rich\n"
			. "- \"og_description\": Open Graph description, maximum 160 characters, engaging summary\n"
			. "- \"excerpt\": 1-3 sentence post summary for internal WordPress excerpt field\n"
			. "- \"alt_text\": descriptive alt text for the featured image based on the post topic\n\n"
			. "Post title: {$title}\n"
			. "Current excerpt: {$excerpt}\n"
			. "Post content (first 2000 chars): {$content}\n\n"
			. 'Return only valid JSON. No markdown fences, no commentary.';

		$req = new CompletionRequest(
			messages:   [
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
			system:     'You are an expert SEO specialist for WordPress blogs.',
			max_tokens: 512,
			metadata:   [
				'feature' => 'seo',
				'post_id' => $post_id,
			],
		);

		try {
			$factory  = new ProviderFactory( new ProviderSettings() );
			$provider = $factory->make_default();
			$response = $provider->complete( $req );
		} catch ( ProviderException $e ) {
			return new \WP_REST_Response( [ 'error' => $e->getMessage() ], 502 );
		} catch ( \Exception $e ) {
			return new \WP_REST_Response( [ 'error' => $e->getMessage() ], 500 );
		}

		$raw  = trim( $response->content );
		$raw  = preg_replace( '/^```(?:json)?\s*/i', '', $raw );
		$raw  = preg_replace( '/\s*```$/i', '', $raw );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new \WP_REST_Response( [ 'error' => __( 'AI returned invalid JSON.', 'wp-ai-mind' ) ], 502 );
		}

		return new \WP_REST_Response(
			[
				'post_id'        => $post_id,
				'meta_title'     => \sanitize_text_field( $data['meta_title'] ?? '' ),
				'og_description' => \sanitize_text_field( $data['og_description'] ?? '' ),
				'excerpt'        => \sanitize_textarea_field( $data['excerpt'] ?? '' ),
				'alt_text'       => \sanitize_text_field( $data['alt_text'] ?? '' ),
				'tokens_used'    => $response->total_tokens,
			],
			200
		);
	}

	public static function handle_apply( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = $request->get_param( 'post_id' );
		$post    = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'error' => __( 'Post not found.', 'wp-ai-mind' ) ], 404 );
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_REST_Response( [ 'error' => __( 'Forbidden.', 'wp-ai-mind' ) ], 403 );
		}

		$updated = [];

		$excerpt = $request->get_param( 'excerpt' );
		if ( null !== $excerpt && '' !== $excerpt ) {
			\wp_update_post(
				[
					'ID'           => $post_id,
					'post_excerpt' => $excerpt,
				]
			);
			$updated[] = 'excerpt';
		}

		$meta_title = $request->get_param( 'meta_title' );
		if ( null !== $meta_title && '' !== $meta_title ) {
			\update_post_meta( $post_id, '_yoast_wpseo_title', $meta_title );
			\update_post_meta( $post_id, 'rank_math_title', $meta_title );
			$updated[] = 'meta_title';
		}

		$og_description = $request->get_param( 'og_description' );
		if ( null !== $og_description && '' !== $og_description ) {
			\update_post_meta( $post_id, '_yoast_wpseo_metadesc', $og_description );
			\update_post_meta( $post_id, 'rank_math_description', $og_description );
			$updated[] = 'og_description';
		}

		$alt_text = $request->get_param( 'alt_text' );
		if ( null !== $alt_text && '' !== $alt_text ) {
			$thumb_id = \get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				\update_post_meta( $thumb_id, '_wp_attachment_image_alt', $alt_text );
				$updated[] = 'alt_text';
			}
		}

		return new \WP_REST_Response(
			[
				'post_id' => $post_id,
				'updated' => $updated,
			],
			200
		);
	}
}
