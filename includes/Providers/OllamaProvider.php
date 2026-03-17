<?php
// includes/Providers/OllamaProvider.php
declare( strict_types=1 );
namespace WP_AI_Mind\Providers;

class OllamaProvider extends AbstractProvider {

	private const DEFAULT_MODEL = 'llama3.2';

	public function __construct(
		private readonly string $base_url      = 'http://localhost:11434',
		private readonly string $default_model = self::DEFAULT_MODEL,
	) {}

	public function get_slug(): string   { return 'ollama'; }
	public function get_models(): array  { return [ $this->default_model => $this->default_model ]; }
	public function is_available(): bool { return '' !== $this->base_url; }

	/**
	 * Ollama does not support function/tool calling in the plugin's tool-use protocol.
	 *
	 * @return bool
	 */
	public function supports_tools(): bool {
		return false;
	}

	protected function do_complete( CompletionRequest $request ): CompletionResponse {
		$messages = $request->messages;
		if ( '' !== $request->system ) {
			array_unshift( $messages, [ 'role' => 'system', 'content' => $request->system ] );
		}
		$body = [
			'model'    => $request->model ?: $this->default_model,
			'messages' => $messages,
			'stream'   => false,
			'options'  => [ 'temperature' => $request->temperature ],
		];
		$raw = $this->post( '/api/chat', $body );

		$content    = $raw['message']['content'] ?? '';
		$in_tokens  = (int) ( $raw['prompt_eval_count'] ?? 0 );
		$out_tokens = (int) ( $raw['eval_count']        ?? 0 );

		return new CompletionResponse(
			$content,
			$raw['model'] ?? $this->default_model,
			$in_tokens,
			$out_tokens,
			0.0, // local inference — no cost
			$raw
		);
	}

	protected function do_stream( CompletionRequest $request, callable $on_chunk ): CompletionResponse {
		$response = $this->do_complete( $request );
		foreach ( explode( ' ', $response->content ) as $i => $word ) {
			$on_chunk( ( $i > 0 ? ' ' : '' ) . $word );
		}
		return $response;
	}

	public function generate_image( string $prompt, array $options = [] ): int {
		throw new ProviderException(
			'Ollama does not support image generation. Use Gemini or OpenAI.',
			'ollama',
			0
		);
	}

	private function post( string $path, array $body ): array {
		$response = wp_remote_post( rtrim( $this->base_url, '/' ) . $path, [
			'timeout' => 120, // Ollama can be slow on first run
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( $response->get_error_message(), 'ollama' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code < 200 || $code >= 300 ) {
			throw new ProviderException( $data['error'] ?? "HTTP {$code}", 'ollama', $code, $data );
		}
		return $data;
	}
}
