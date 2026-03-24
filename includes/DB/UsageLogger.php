<?php
// includes/DB/UsageLogger.php
declare( strict_types=1 );
namespace WP_AI_Mind\DB;

use WP_AI_Mind\Providers\CompletionResponse;

class UsageLogger {

	public static function log(
		string $feature,
		string $provider,
		CompletionResponse $response,
		int $user_id = 0,
		?int $post_id = null
	): void {
		global $wpdb;
		$table = Schema::table( 'usage_log' );
		$wpdb->insert(
			$table,
			[
				'user_id'           => ! empty( $user_id ) ? $user_id : get_current_user_id(),
				'feature'           => sanitize_key( $feature ),
				'provider'          => sanitize_key( $provider ),
				'model'             => sanitize_text_field( $response->model ),
				'prompt_tokens'     => $response->prompt_tokens,
				'completion_tokens' => $response->completion_tokens,
				'total_tokens'      => $response->total_tokens,
				'cost_usd'          => $response->cost_usd,
				'post_id'           => $post_id,
			],
			[ '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%d' ]
		);
	}
}
