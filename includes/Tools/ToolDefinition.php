<?php
declare( strict_types=1 );

namespace WP_AI_Mind\Tools;

/**
 * Immutable value object describing a single AI tool.
 */
final class ToolDefinition {

	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly array $parameters,        // JSON Schema object for the tool's params.
		public readonly string $capability = 'edit_posts',
		public readonly bool $requires_write_tools = false,
	) {}
}
