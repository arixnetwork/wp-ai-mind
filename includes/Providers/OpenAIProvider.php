<?php
// includes/Providers/OpenAIProvider.php
declare( strict_types=1 );
namespace WP_AI_Mind\Providers;

class OpenAIProvider extends AbstractProvider {

	private const API_BASE      = 'https://api.openai.com/v1';
	private const DEFAULT_MODEL = 'gpt-4o';

	private const MODELS = [
		'gpt-4o'      => 'GPT-4o',
		'gpt-4o-mini' => 'GPT-4o Mini',
		'o3'          => 'o3',
		'o4-mini'     => 'o4 Mini',
	];

	private const PRICING = [
		'gpt-4o'      => [ 'in' => 2.5,  'out' => 10.0 ],
		'gpt-4o-mini' => [ 'in' => 0.15, 'out' => 0.60 ],
		'o3'          => [ 'in' => 10.0, 'out' => 40.0 ],
		'o4-mini'     => [ 'in' => 1.1,  'out' => 4.4  ],
	];

	public function __construct( private readonly string $api_key ) {}

	public function get_slug(): string   { return 'openai'; }
	public function get_models(): array  { return self::MODELS; }
	public function is_available(): bool { return '' !== $this->api_key; }

	protected function do_complete( CompletionRequest $request ): CompletionResponse {
		$messages = $request->messages;
		if ( '' !== $request->system ) {
			array_unshift( $messages, [ 'role' => 'system', 'content' => $request->system ] );
		}
		$model = $request->model ?: self::DEFAULT_MODEL;
		$body  = [
			'model'       => $model,
			'messages'    => $messages,
			'max_tokens'  => $request->max_tokens,
			'temperature' => $request->temperature,
		];
		$raw = $this->post( '/chat/completions', $body );
		return $this->parse_response( $raw, $model );
	}

	protected function do_stream( CompletionRequest $request, callable $on_chunk ): CompletionResponse {
		$response = $this->do_complete( $request );
		$words    = explode( ' ', $response->content );
		foreach ( $words as $i => $word ) {
			$on_chunk( ( $i > 0 ? ' ' : '' ) . $word );
		}
		return $response;
	}

	public function generate_image( string $prompt, array $options = [] ): int {
		$body = [
			'model'   => 'dall-e-3',
			'prompt'  => $prompt,
			'n'       => 1,
			'size'    => $options['size']    ?? '1024x1024',
			'quality' => $options['quality'] ?? 'hd',
		];
		$raw = $this->post( '/images/generations', $body );
		$url = $raw['data'][0]['url'] ?? '';
		if ( empty( $url ) ) {
			throw new ProviderException( 'No image URL in response', 'openai' );
		}
		return $this->save_image_to_media_library( $url, 'dalle-' . time(), $prompt );
	}

	private function post( string $path, array $body ): array {
		$response = wp_remote_post( self::API_BASE . $path, [
			'timeout' => WP_AI_MIND_HTTP_TIMEOUT,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( $response->get_error_message(), 'openai' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code < 200 || $code >= 300 ) {
			$msg = $data['error']['message'] ?? "HTTP {$code}";
			throw new ProviderException( $msg, 'openai', $code, $data );
		}

		return $data;
	}

	private function parse_response( array $data, string $model ): CompletionResponse {
		$content    = $data['choices'][0]['message']['content'] ?? '';
		$in_tokens  = (int) ( $data['usage']['prompt_tokens']     ?? 0 );
		$out_tokens = (int) ( $data['usage']['completion_tokens'] ?? 0 );
		$pricing    = self::PRICING[ $model ] ?? self::PRICING[ self::DEFAULT_MODEL ];
		$cost       = ( $in_tokens / 1_000_000 * $pricing['in'] ) + ( $out_tokens / 1_000_000 * $pricing['out'] );

		return new CompletionResponse( $content, $model, $in_tokens, $out_tokens, $cost, $data );
	}
}
