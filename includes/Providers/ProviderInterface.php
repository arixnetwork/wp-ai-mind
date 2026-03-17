<?php
// includes/Providers/ProviderInterface.php
declare( strict_types=1 );

namespace WP_AI_Mind\Providers;

interface ProviderInterface {

	/**
	 * Single-turn completion. Returns full response.
	 *
	 * @param CompletionRequest $request The completion request.
	 * @return CompletionResponse
	 *
	 * @throws ProviderException on API error or timeout.
	 */
	public function complete( CompletionRequest $request ): CompletionResponse;

	/**
	 * Streaming completion. Calls $on_chunk( string $delta ) for each chunk.
	 * Returns the final CompletionResponse after all chunks are delivered.
	 *
	 * @param CompletionRequest $request  The completion request.
	 * @param callable          $on_chunk Callback for each chunk.
	 * @return CompletionResponse
	 *
	 * @throws ProviderException
	 */
	public function stream( CompletionRequest $request, callable $on_chunk ): CompletionResponse;

	/**
	 * Generate an image from a text prompt.
	 * Saves to WP Media Library and returns attachment ID.
	 *
	 * @param string $prompt  The image prompt.
	 * @param array  $options Image generation options.
	 * @return int Attachment ID.
	 *
	 * @throws ProviderException
	 */
	public function generate_image( string $prompt, array $options = [] ): int;

	/**
	 * Returns the canonical provider slug, e.g. 'claude', 'openai'.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Returns available model IDs for this provider.
	 *
	 * @return array
	 */
	public function get_models(): array;

	/**
	 * Returns true if the provider is configured (API key present / reachable).
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Returns true if this provider supports function/tool calling.
	 *
	 * @return bool
	 */
	public function supports_tools(): bool;
}
