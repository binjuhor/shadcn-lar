<?php

namespace Modules\Settings\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Modules\Finance\Services\OllamaTransactionParser;
use Modules\Finance\Services\OpenAiTransactionParser;
use Modules\Finance\Services\TransactionParserFactory;
use Modules\Settings\Services\AiCredentialResolver;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_store_encrypted_ai_keys_without_exposing_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'keys' => ['openai' => 'sk-user-secret'],
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('sk-user-secret', $user->ai_settings['openai']);
        $this->assertStringNotContainsString(
            'sk-user-secret',
            (string) DB::table('users')->where('id', $user->id)->value('ai_settings'),
        );
        $this->assertArrayNotHasKey('ai_settings', $user->toArray());

        $this->actingAs($user)
            ->get(route('dashboard.settings.ai'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/ai/index', false)
                ->where('providers.openai.stored', true)
                ->missing('keys')
                ->missing('ai_settings'));
    }

    public function test_blank_keys_are_unchanged_and_clear_restores_environment_fallback(): void
    {
        config(['services.openai.api_key' => 'env-openai-key']);
        $user = User::factory()->create([
            'ai_settings' => ['openai' => 'user-openai-key'],
        ]);

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'keys' => ['openai' => ''],
            ]);

        $this->assertSame('user-openai-key', $user->fresh()->ai_settings['openai']);

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'clear' => ['openai'],
            ]);

        $user->refresh();

        $this->assertNull($user->ai_settings);
        $this->assertSame('env-openai-key', app(AiCredentialResolver::class)->apiKey('openai', $user));
    }

    public function test_ai_keys_are_isolated_between_users(): void
    {
        config(['services.claude.api_key' => 'env-claude-key']);
        $firstUser = User::factory()->create([
            'ai_settings' => ['claude' => 'first-user-key'],
        ]);
        $secondUser = User::factory()->create();
        $resolver = app(AiCredentialResolver::class);

        $this->assertSame('first-user-key', $resolver->apiKey('claude', $firstUser));
        $this->assertSame('env-claude-key', $resolver->apiKey('claude', $secondUser));
    }

    public function test_guest_cannot_access_ai_settings(): void
    {
        $this->get(route('dashboard.settings.ai'))->assertRedirect(route('login'));
        $this->patch(route('dashboard.settings.ai.update'))->assertRedirect(route('login'));
    }

    public function test_smart_input_resolves_the_user_key_at_call_time(): void
    {
        config(['services.openai.api_key' => 'environment-key']);
        $parser = new OpenAiTransactionParser;
        $user = User::factory()->create([
            'ai_settings' => ['openai' => 'user-key'],
        ]);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"transaction_type":"expense","amount":1000,"description":"Coffee"}']]],
        ])]);

        $this->actingAs($user);
        $parser->parseText('Coffee 1000');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer user-key'));
    }

    public function test_unknown_provider_keys_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'keys' => ['unknown' => 'secret'],
            ])
            ->assertSessionHasErrors('keys');
    }

    public function test_user_can_select_a_smart_input_provider_with_environment_fallback(): void
    {
        config(['services.smart_input.provider' => 'claude']);
        $user = User::factory()->create();
        $resolver = app(AiCredentialResolver::class);

        $this->assertSame('claude', $resolver->provider($user));

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'provider' => 'openai',
                'keys' => ['openai' => 'user-openai-key'],
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('openai', $resolver->provider($user));
        $this->assertSame('openai', $user->ai_settings['provider']);
        $this->assertInstanceOf(OpenAiTransactionParser::class, TransactionParserFactory::make());
    }

    public function test_provider_without_a_saved_key_falls_back_to_environment_provider(): void
    {
        config([
            'services.smart_input.provider' => 'claude',
            'services.claude.api_key' => 'env-claude-key',
        ]);
        $user = User::factory()->create([
            'ai_settings' => ['provider' => 'openai'],
        ]);
        $resolver = app(AiCredentialResolver::class);

        $this->assertSame('claude', $resolver->provider($user));
        $this->assertSame('env-claude-key', $resolver->apiKey('claude', $user));
    }

    public function test_user_can_configure_ollama_base_url_and_model(): void
    {
        config([
            'services.ollama.base_url' => 'https://ollama.com',
            'services.ollama.model' => 'environment-model',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'provider' => 'ollama',
                'ollama_base_url' => 'http://127.0.0.1:11434/',
                'ollama_model' => 'qwen3:8b',
                'keys' => ['ollama' => ''],
            ])
            ->assertRedirect();

        $user->refresh();
        $resolver = app(AiCredentialResolver::class);

        $this->assertSame('http://127.0.0.1:11434', $resolver->ollamaBaseUrl($user));
        $this->assertSame('qwen3:8b', $resolver->ollamaModel($user));

        Http::fake(['127.0.0.1:11434/*' => Http::response([
            'choices' => [['message' => ['content' => '{"transaction_type":"expense","amount":1000,"description":"Coffee"}']]],
        ])]);

        (new OllamaTransactionParser)->parseText('Coffee 1000');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://127.0.0.1:11434/v1/chat/completions'
            && $request->data()['model'] === 'qwen3:8b');
    }

    public function test_ollama_connection_falls_back_to_environment_settings(): void
    {
        config([
            'services.ollama.base_url' => 'http://environment-ollama:11434/',
            'services.ollama.model' => 'environment-model',
        ]);
        $user = User::factory()->create();
        $resolver = app(AiCredentialResolver::class);

        $this->assertSame('http://environment-ollama:11434', $resolver->ollamaBaseUrl($user));
        $this->assertSame('environment-model', $resolver->ollamaModel($user));
    }

    public function test_blank_ollama_settings_clear_overrides_and_fall_back_to_environment(): void
    {
        config([
            'services.ollama.base_url' => 'https://ollama.com',
            'services.ollama.model' => 'environment-model',
        ]);
        $user = User::factory()->create([
            'ai_settings' => [
                'provider' => 'ollama',
                'ollama_base_url' => 'http://127.0.0.1:11434',
                'ollama_model' => 'user-model',
            ],
        ]);

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'provider' => 'ollama',
                'ollama_base_url' => '',
                'ollama_model' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $resolver = app(AiCredentialResolver::class);

        $this->assertSame('ollama', $user->ai_settings['provider']);
        $this->assertArrayNotHasKey('ollama_base_url', $user->ai_settings);
        $this->assertArrayNotHasKey('ollama_model', $user->ai_settings);
        $this->assertSame('https://ollama.com', $resolver->ollamaBaseUrl($user));
        $this->assertSame('environment-model', $resolver->ollamaModel($user));
    }

    public function test_ollama_base_url_must_match_the_server_allowlist(): void
    {
        config([
            'services.ollama.base_url' => 'https://ollama.com',
            'services.ollama.allowed_origins' => ['http://127.0.0.1:11434'],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'provider' => 'ollama',
                'ollama_base_url' => 'http://169.254.169.254',
                'ollama_model' => 'qwen3:8b',
            ])
            ->assertSessionHasErrors('ollama_base_url');

        $this->actingAs($user)
            ->patch(route('dashboard.settings.ai.update'), [
                'provider' => 'ollama',
                'ollama_base_url' => 'http://user:password@127.0.0.1:11434',
                'ollama_model' => 'qwen3:8b',
            ])
            ->assertSessionHasErrors('ollama_base_url');
    }

    public function test_ollama_environment_connection_details_are_not_exposed_to_the_browser(): void
    {
        config([
            'services.ollama.base_url' => 'http://internal-ollama.service:11434',
            'services.ollama.model' => 'private-model-name',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.settings.ai'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('ollama.baseUrl', '')
                ->where('ollama.model', ''));
    }
}
