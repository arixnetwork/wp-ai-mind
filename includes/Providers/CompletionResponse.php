<?php
// includes/Providers/CompletionResponse.php
declare( strict_types=1 );

namespace WP_AI_Mind\Providers;

final class CompletionResponse {
	public readonly int $total_tokens;

	/**
	 * Constructor.
	 *
	 * @param string $content             The completion content.
	 * @param string $model               The model used.
	 * @param int    $prompt_tokens       Tokens used in the prompt.
	 * @param int    $completion_tokens   Tokens used in the completion.
	 * @param float  $cost_usd            USD cost of the request.
	 * @param array  $raw                 Raw API response.
	 */
	public function __construct(
		public readonly string $content,
		public readonly string $model,
		public readonly int    $prompt_tokens,
		public readonly int    $completion_tokens,
		public readonly float  $cost_usd = 0.0,
		public readonly array  $raw = [],
	) {
		$this->total_tokens = $prompt_tokens + $completion_tokens;
	}
}
