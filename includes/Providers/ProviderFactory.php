<?php
// includes/Providers/ProviderFactory.php
declare( strict_types=1 );
namespace WP_AI_Mind\Providers;

use WP_AI_Mind\Settings\ProviderSettings;

class ProviderFactory {

	public function __construct( private readonly ProviderSettings $settings ) {}

	public function make( string $slug ): ProviderInterface {
		return match ( $slug ) {
			'claude' => new ClaudeProvider( $this->settings->get_api_key( 'claude' ) ),
			'openai' => new OpenAIProvider( $this->settings->get_api_key( 'openai' ) ),
			'gemini' => new GeminiProvider( $this->settings->get_api_key( 'gemini' ) ),
			'ollama' => new OllamaProvider(
				(string) get_option( 'wp_ai_mind_ollama_url', 'http://localhost:11434' ),
				(string) get_option( 'wp_ai_mind_ollama_model', 'llama3.2' )
			),
			default  => throw new \InvalidArgumentException( "Unknown provider: {$slug}" ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		};
	}

	/** Returns the active provider for text completions (from settings). */
	public function make_default(): ProviderInterface {
		$slug = get_option( 'wp_ai_mind_default_provider', 'claude' );
		return $this->make( ! empty( $slug ) ? $slug : 'claude' );
	}

	/** Returns the active provider for image generation. */
	public function make_image_provider(): ProviderInterface {
		$slug = get_option( 'wp_ai_mind_image_provider', 'gemini' );
		return $this->make( ! empty( $slug ) ? $slug : 'gemini' );
	}

	/** Returns all configured (available) providers. */
	public function get_available(): array {
		return array_values(
			array_filter(
				array_map( fn( $s ) => $this->make( $s ), [ 'claude', 'openai', 'gemini', 'ollama' ] ),
				fn( $p ) => $p->is_available()
			)
		);
	}

	/** Returns all providers unconditionally (regardless of API key). */
	public function get_all(): array {
		return array_map( fn( $s ) => $this->make( $s ), [ 'claude', 'openai', 'gemini', 'ollama' ] );
	}
}
