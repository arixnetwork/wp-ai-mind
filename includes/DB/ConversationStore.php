<?php
// includes/DB/ConversationStore.php
declare( strict_types=1 );
namespace WP_AI_Mind\DB;

class ConversationStore {

	public function create( string $title = '', ?int $post_id = null ): int {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'conversations' ),
			[
				'user_id' => get_current_user_id(),
				'title'   => sanitize_text_field( $title ),
				'post_id' => $post_id,
			],
			[ '%d', '%s', '%d' ]
		);
		return (int) $wpdb->insert_id;
	}

	public function add_message( int $conversation_id, string $role, string $content, string $model = '', int $tokens = 0 ): int {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'messages' ),
			[
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => wp_kses_post( $content ),
				'model'           => sanitize_text_field( $model ),
				'tokens'          => $tokens,
			],
			[ '%d', '%s', '%s', '%s', '%d' ]
		);
		return (int) $wpdb->insert_id;
	}

	public function get_messages( int $conversation_id ): array {
		global $wpdb;
		$table   = Schema::table( 'messages' );
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC", $conversation_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return ! empty( $results ) ? $results : [];
	}

	public function list_for_user( int $user_id, int $limit = 50 ): array {
		global $wpdb;
		$table   = Schema::table( 'conversations' );
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$limit
			),
			ARRAY_A
		);
		return ! empty( $results ) ? $results : [];
	}

	public function get_conversation( int $conversation_id ): ?array {
		global $wpdb;
		$table = Schema::table( 'conversations' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $conversation_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return ! empty( $row ) ? $row : null;
	}

	public function delete( int $conversation_id ): void {
		global $wpdb;
		$wpdb->delete( Schema::table( 'messages' ), [ 'conversation_id' => $conversation_id ], [ '%d' ] );
		$wpdb->delete( Schema::table( 'conversations' ), [ 'id' => $conversation_id ], [ '%d' ] );
	}

	public function update_title( int $conversation_id, string $title ): void {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'conversations' ),
			[ 'title' => sanitize_text_field( $title ) ],
			[ 'id' => $conversation_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}
}
