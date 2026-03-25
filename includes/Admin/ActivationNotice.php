<?php
/**
 * Activation notice: one-time external-services disclosure.
 *
 * @package WP_AI_Mind
 */

declare( strict_types=1 );

namespace WP_AI_Mind\Admin;

/**
 * Displays a one-time admin notice after plugin activation disclosing
 * that the plugin transmits data to third-party AI services.
 *
 * Uses the wp_ai_mind_just_activated option as a single-use flag.
 * The option is deleted before rendering so it cannot be displayed twice,
 * even if the page is reloaded.
 */
class ActivationNotice {

	private const OPTION = 'wp_ai_mind_just_activated';

	/**
	 * Register the admin_notices hook.
	 */
	public static function register(): void {
		\add_action( 'admin_notices', [ self::class, 'maybe_display' ] );
	}

	/**
	 * Display the notice if the activation flag is set and the current user
	 * has manage_options capability. Deletes the flag before rendering.
	 */
	public static function maybe_display(): void {
		if ( ! \get_option( self::OPTION ) ) {
			return;
		}
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		// Delete before rendering — single-use flag, prevents re-display on reload.
		\delete_option( self::OPTION );
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php \esc_html_e( 'WP AI Mind — External Services Notice', 'wp-ai-mind' ); ?></strong>
			</p>
			<p>
				<?php
				\esc_html_e(
					'WP AI Mind sends the content you submit to third-party AI providers (OpenAI, Anthropic Claude, Google Gemini, Ollama). Your data is governed by each provider\'s privacy policy. Configure your active provider under WP AI Mind → Settings.',
					'wp-ai-mind'
				);
				?>
			</p>
		</div>
		<?php
	}
}
