<?php
declare( strict_types=1 );
namespace WP_AI_Mind\DB;

class Schema {

	private const PREFIX = 'wpaim_';

	private const TABLES = [
		'usage_log'     => 'usage_log',
		'conversations' => 'conversations',
		'messages'      => 'messages',
	];

	public static function table( string $name ): string {
		global $wpdb;
		if ( ! isset( self::TABLES[ $name ] ) ) {
			throw new \InvalidArgumentException( "Unknown table: {$name}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $wpdb->prefix . self::PREFIX . self::TABLES[ $name ];
	}

	/**
	 * Run on plugin activation via dbDelta().
	 */
	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$usage_log = self::table( 'usage_log' );
		dbDelta(
			"CREATE TABLE {$usage_log} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            feature       VARCHAR(50)     NOT NULL,
            provider      VARCHAR(50)     NOT NULL,
            model         VARCHAR(100)    NOT NULL,
            prompt_tokens INT UNSIGNED    NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            total_tokens  INT UNSIGNED    NOT NULL DEFAULT 0,
            cost_usd      DECIMAL(10,6)   NOT NULL DEFAULT 0.000000,
            post_id       BIGINT UNSIGNED          DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY feature (feature),
            KEY created_at (created_at)
        ) {$charset};"
		);

		$conversations = self::table( 'conversations' );
		dbDelta(
			"CREATE TABLE {$conversations} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            user_id    BIGINT UNSIGNED NOT NULL,
            title      VARCHAR(255)             DEFAULT NULL,
            post_id    BIGINT UNSIGNED          DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset};"
		);

		$messages = self::table( 'messages' );
		dbDelta(
			"CREATE TABLE {$messages} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED NOT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            role            ENUM('user','assistant') NOT NULL,
            content         LONGTEXT        NOT NULL,
            model           VARCHAR(100)             DEFAULT NULL,
            tokens          INT UNSIGNED             DEFAULT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) {$charset};"
		);
	}

	public static function drop_tables(): void {
		global $wpdb;
		foreach ( array_keys( self::TABLES ) as $name ) {
			$table = self::table( $name );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}
