<?php
// tests/Unit/Modules/Chat/SettingsRestControllerTest.php
declare( strict_types=1 );

namespace WP_AI_Mind\Tests\Unit\Modules\Chat;

use Brain\Monkey;
use Brain\Monkey\Functions;
use WP_AI_Mind\Modules\Chat\SettingsRestController;
use PHPUnit\Framework\TestCase;

class SettingsRestControllerTest extends TestCase {

    protected function setUp(): void    { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    // ── Route registration ────────────────────────────────────────────────────

    public function test_register_routes_adds_settings_route(): void {
        $registered = [];
        Functions\when( 'register_rest_route' )->alias(
            function( $ns, $route ) use ( &$registered ) {
                $registered[] = $ns . $route;
            }
        );
        Functions\when( 'get_option' )->justReturn( [] );

        $controller = new SettingsRestController();
        $controller->register_routes();

        $this->assertContains( 'wp-ai-mind/v1/settings', $registered );
    }

    // ── GET /settings — masked keys ───────────────────────────────────────────

    public function test_get_settings_returns_masked_keys_when_set(): void {
        Functions\when( 'sanitize_text_field' )->alias( fn( $v ) => $v );
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            $map = [
                'wp_ai_mind_default_provider' => 'claude',
                'wp_ai_mind_image_provider'   => 'gemini',
                'wp_ai_mind_site_voice'        => 'friendly',
                'wp_ai_mind_enabled_modules'  => [ 'chat' ],
                'wp_ai_mind_ollama_url'        => 'http://localhost:11434',
            ];
            return $map[ $key ] ?? $default;
        } );

        // has_key() calls get_api_key() which calls get_option(OPTION_KEY) — already handled above.
        // We mock ProviderSettings directly by controlling get_option for the keys option.
        // The encrypted store is not present, so has_key returns false — we test the "set" path
        // by using a partial approach: instantiate real class but inject a fake ProviderSettings
        // via a subclass.

        // Simpler: test mask() logic indirectly through get_settings with a stubbed ProviderSettings.
        // We'll use a test-double subclass.
        $controller = new class extends SettingsRestController {
            protected function make_provider_settings(): \WP_AI_Mind\Settings\ProviderSettings {
                $stub = new class extends \WP_AI_Mind\Settings\ProviderSettings {
                    public function __construct() {} // Skip get_option in constructor.
                    public function has_key( string $provider ): bool {
                        return in_array( $provider, [ 'claude', 'gemini' ], true );
                    }
                    public function get_api_key( string $provider ): string { return ''; }
                };
                return $stub;
            }
        };

        $request  = new \WP_REST_Request();
        $response = $controller->get_settings( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $response );
        $data = $response->data;

        // Providers that "have a key" should be masked.
        $this->assertSame( '••••••', $data['api_keys']['claude'] );
        $this->assertSame( '••••••', $data['api_keys']['gemini'] );
        // Providers without a key should be empty string.
        $this->assertSame( '', $data['api_keys']['openai'] );
    }

    public function test_get_settings_returns_empty_string_when_key_not_set(): void {
        Functions\when( 'sanitize_text_field' )->alias( fn( $v ) => $v );
        Functions\when( 'get_option' )->justReturn( '' );

        $controller = new class extends SettingsRestController {
            protected function make_provider_settings(): \WP_AI_Mind\Settings\ProviderSettings {
                $stub = new class extends \WP_AI_Mind\Settings\ProviderSettings {
                    public function __construct() {}
                    public function has_key( string $provider ): bool { return false; }
                    public function get_api_key( string $provider ): string { return ''; }
                };
                return $stub;
            }
        };

        $request  = new \WP_REST_Request();
        $response = $controller->get_settings( $request );
        $data     = $response->data;

        $this->assertSame( '', $data['api_keys']['claude'] );
        $this->assertSame( '', $data['api_keys']['openai'] );
        $this->assertSame( '', $data['api_keys']['gemini'] );
    }

    // ── POST /settings — saves options ────────────────────────────────────────

    public function test_save_settings_updates_options(): void {
        $stored = [];
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$stored ) {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'sanitize_text_field' )->alias( fn( $v ) => $v );
        Functions\when( 'esc_url_raw' )->alias( fn( $v ) => $v );

        $api_key_calls = [];
        $controller = new class( $api_key_calls ) extends SettingsRestController {
            private array $calls;
            public function __construct( array &$calls ) { $this->calls = &$calls; }
            protected function make_provider_settings(): \WP_AI_Mind\Settings\ProviderSettings {
                $calls = &$this->calls;
                $stub  = new class( $calls ) extends \WP_AI_Mind\Settings\ProviderSettings {
                    private array $calls;
                    public function __construct( array &$calls ) {
                        $this->calls = &$calls;
                    }
                    public function set_api_key( string $provider, string $key ): void {
                        $this->calls[] = [ $provider, $key ];
                    }
                };
                return $stub;
            }
        };

        $request = new \WP_REST_Request();
        $request->set_body_params( [
            'default_provider' => 'claude',
            'image_provider'   => 'gemini',
            'site_voice'       => 'professional',
            'enabled_modules'  => [ 'chat', 'summaries' ],
            'api_keys'         => [
                'claude'     => 'sk-new-key',
                'openai'     => '••••••',   // masked — must be skipped
                'gemini'     => 'new-gem-key',
                'ollama_url' => 'http://localhost:11434',
            ],
        ] );

        $response = $controller->save_settings( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $response );
        $this->assertTrue( $response->data['saved'] );

        // Options updated.
        $this->assertSame( 'claude',                     $stored['wp_ai_mind_default_provider'] );
        $this->assertSame( 'gemini',                     $stored['wp_ai_mind_image_provider'] );
        $this->assertSame( 'professional',               $stored['wp_ai_mind_site_voice'] );
        $this->assertSame( [ 'chat', 'summaries' ],      $stored['wp_ai_mind_enabled_modules'] );
        $this->assertSame( 'http://localhost:11434',      $stored['wp_ai_mind_ollama_url'] );

        // set_api_key called for non-masked keys only.
        $providers_saved = array_column( $api_key_calls, 0 );
        $this->assertContains( 'claude', $providers_saved );
        $this->assertContains( 'gemini', $providers_saved );
        $this->assertNotContains( 'openai', $providers_saved );   // masked — skipped.
    }

    public function test_save_settings_skips_masked_api_keys(): void {
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->alias( fn( $v ) => $v );

        $api_key_calls = [];
        $controller = new class( $api_key_calls ) extends SettingsRestController {
            private array $calls;
            public function __construct( array &$calls ) { $this->calls = &$calls; }
            protected function make_provider_settings(): \WP_AI_Mind\Settings\ProviderSettings {
                $calls = &$this->calls;
                $stub  = new class( $calls ) extends \WP_AI_Mind\Settings\ProviderSettings {
                    private array $calls;
                    public function __construct( array &$calls ) { $this->calls = &$calls; }
                    public function set_api_key( string $provider, string $key ): void {
                        $this->calls[] = [ $provider, $key ];
                    }
                };
                return $stub;
            }
        };

        $request = new \WP_REST_Request();
        $request->set_body_params( [
            'api_keys' => [
                'claude' => '••••••',
                'openai' => '••••••',
                'gemini' => '••••••',
            ],
        ] );

        $controller->save_settings( $request );

        // No provider keys should be saved when all values are masked.
        $this->assertEmpty( $api_key_calls );
    }
}
