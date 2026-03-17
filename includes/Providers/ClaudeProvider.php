<?php
// includes/Providers/ClaudeProvider.php
declare( strict_types=1 );
namespace WP_AI_Mind\Providers;

class ClaudeProvider extends AbstractProvider {

	private const API_BASE      = 'https://api.anthropic.com/v1';
	private const API_VERSION   = '2023-06-01';
	private const DEFAULT_MODEL = 'claude-sonnet-4-6';

	private const MODELS = [
		'claude-opus-4-6'           => 'Claude Opus 4.6',
		'claude-sonnet-4-6'         => 'Claude Sonnet 4.6',
		'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
	];

	// Cost per 1M tokens (input/output) in USD.
	private const PRICING = [
		'claude-opus-4-6'           => [ 'in' => 15.0,  'out' => 75.0  ],
		'claude-sonnet-4-6'         => [ 'in' => 3.0,   'out' => 15.0  ],
		'claude-haiku-4-5-20251001' => [ 'in' => 0.25,  'out' => 1.25  ],
	];

	public function __construct( private readonly string $api_key ) {}

	public function get_slug(): string   { return 'claude'; }
	public function get_models(): array  { return self::MODELS; }
	public function is_available(): bool { return '' !== $this->api_key; }

	protected function do_complete( CompletionRequest $request ): CompletionResponse {
		$body = $this->build_body( $request );
		$raw  = $this->post( '/messages', $body );
		return $this->parse_response( $raw );
	}

	protected function do_stream( CompletionRequest $request, callable $on_chunk ): CompletionResponse {
		// Claude supports SSE; WP HTTP API doesn't support streaming natively,
		// so we fall back to non-streaming and simulate chunking.
		$response = $this->do_complete( $request );
		$words    = explode( ' ', $response->content );
		foreach ( $words as $i => $word ) {
			$on_chunk( ( $i > 0 ? ' ' : '' ) . $word );
		}
		return $response;
	}

	public function generate_image( string $prompt, array $options = [] ): int {
		throw new ProviderException(
			'Claude does not support image generation. Use Gemini or OpenAI.',
			'claude',
			0
		);
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private function build_body( CompletionRequest $request ): array {
		$body = [
			'model'      => $request->model ?: self::DEFAULT_MODEL,
			'max_tokens' => $request->max_tokens,
			'messages'   => $request->messages,
		];
		if ( '' !== $request->system ) {
			$body['system'] = $request->system;
		}
		return $body;
	}

	private function post( string $path, array $body ): array {
		$response = wp_remote_post( self::API_BASE . $path, [
			'timeout' => WP_AI_MIND_HTTP_TIMEOUT,
			'headers' => [
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::API_VERSION,
				'content-type'      => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( $response->get_error_message(), 'claude' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code < 200 || $code >= 300 ) {
			$msg = $data['error']['message'] ?? "HTTP {$code}";
			throw new ProviderException( $msg, 'claude', $code, $data );
		}

		return $data;
	}

	private function parse_response( array $data ): CompletionResponse {
		$content    = $data['content'][0]['text'] ?? '';
		$model      = $data['model'] ?? self::DEFAULT_MODEL;
		$in_tokens  = (int) ( $data['usage']['input_tokens']  ?? 0 );
		$out_tokens = (int) ( $data['usage']['output_tokens'] ?? 0 );
		$pricing    = self::PRICING[ $model ] ?? self::PRICING[ self::DEFAULT_MODEL ];
		$cost       = ( $in_tokens / 1_000_000 * $pricing['in'] ) + ( $out_tokens / 1_000_000 * $pricing['out'] );

		return new CompletionResponse( $content, $model, $in_tokens, $out_tokens, $cost, $data );
	}
}
