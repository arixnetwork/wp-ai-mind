<?php
// includes/Admin/UsagePage.php
declare( strict_types=1 );

namespace WP_AI_Mind\Admin;

/**
 * Renders the WP AI Mind usage & cost admin page.
 *
 * Outputs a React mount point; assets are enqueued by UsageModule.
 */
class UsagePage {

	public static function render(): void {
		echo '<div id="wp-ai-mind-usage" class="wp-ai-mind-page"></div>';
	}
}
