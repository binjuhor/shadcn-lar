<?php

namespace Modules\AiAdvisor\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\AiAdvisor\Providers\Ai\ConfiguredAiProvider;
use Tests\TestCase;

class ConfiguredAiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_actor_key_overrides_environment_without_ambient_authentication(): void
    {
        config([
            'services.smart_input.provider' => 'openai',
            'services.openai.api_key' => 'environment-key',
        ]);
        $actor = User::factory()->create([
            'ai_settings' => ['openai' => 'actor-key'],
        ]);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Done']]],
        ])]);

        app(ConfiguredAiProvider::class)->complete('System', $this->messages(), $this->tools(), $actor);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer actor-key'));
    }

    public function test_openai_compatible_provider_normalizes_tool_calls(): void
    {
        config(['services.smart_input.provider' => 'openai']);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => null, 'tool_calls' => [[
                'id' => 'call-1', 'function' => ['name' => 'getAccounts', 'arguments' => '{}'],
            ]]]]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2],
        ])]);

        $result = app(ConfiguredAiProvider::class)->complete('System', $this->messages(), $this->tools());

        $this->assertSame('getAccounts', $result['tool_calls'][0]['name']);
        Http::assertSent(fn (Request $request) => $request->data()['tools'][0]['function']['parameters']['type'] === 'object');
    }

    public function test_ollama_retries_tool_result_round_without_tools_when_endpoint_rejects_it(): void
    {
        config(['services.smart_input.provider' => 'ollama']);
        Http::fakeSequence()
            ->push(['error' => 'tool messages are unsupported'], 400)
            ->push(['choices' => [['message' => ['content' => 'You have no accounts.']]]]);

        $result = app(ConfiguredAiProvider::class)->complete('System', [
            ['role' => 'user', 'content' => 'Show accounts'],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [[
                'id' => 'call-1', 'name' => 'getAccounts', 'arguments' => [],
            ]]],
            ['role' => 'tool', 'tool_call_id' => 'call-1', 'name' => 'getAccounts', 'content' => '{}'],
        ], $this->tools());

        $this->assertSame('You have no accounts.', $result['content']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ollama.com/v1/chat/completions'
            && $request->data()['tools'] === []);
    }

    public function test_ollama_retry_folds_tool_result_into_user_message_and_strips_tool_calls(): void
    {
        config(['services.smart_input.provider' => 'ollama']);
        Http::fakeSequence()
            ->push(['error' => 'invalid tool call arguments'], 400)
            ->push(['choices' => [['message' => ['content' => 'Final answer.']]]]);

        app(ConfiguredAiProvider::class)->complete('System', [
            ['role' => 'user', 'content' => 'Show accounts'],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [[
                'id' => 'call-1', 'name' => 'getAccounts', 'arguments' => [],
            ]]],
            ['role' => 'tool', 'tool_call_id' => 'call-1', 'name' => 'getAccounts', 'content' => '{}'],
        ], $this->tools());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ollama.com/v1/chat/completions'
            && $request->data()['tools'] === []
            && collect($request->data()['messages'])->pluck('role')->all() === ['system', 'user', 'assistant', 'user']
            && str_contains($request->data()['messages'][3]['content'], 'Tool result for getAccounts'));
    }

    public function test_ollama_empty_tool_call_arguments_are_encoded_as_object(): void
    {
        config(['services.smart_input.provider' => 'ollama']);
        Http::fake(['https://ollama.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Done']]],
        ])]);

        app(ConfiguredAiProvider::class)->complete('System', [
            ['role' => 'user', 'content' => 'Show balances'],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [[
                'id' => 'call-1', 'name' => 'getAccountBalances', 'arguments' => [],
            ]]],
            ['role' => 'tool', 'tool_call_id' => 'call-1', 'name' => 'getAccountBalances', 'content' => '{"success":true}'],
        ], $this->tools());

        Http::assertSent(fn (Request $request): bool => $request->data()['messages'][2]['tool_calls'][0]['function']['arguments'] === '{}');
    }

    public function test_claude_provider_normalizes_tool_use(): void
    {
        config(['services.smart_input.provider' => 'claude']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'id' => 'call-2', 'name' => 'getAccounts', 'input' => []]],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
        ])]);

        $result = app(ConfiguredAiProvider::class)->complete('System', $this->messages(), $this->tools());

        $this->assertSame('call-2', $result['tool_calls'][0]['id']);
        Http::assertSent(fn (Request $request) => $request->data()['tools'][0]['input_schema']['type'] === 'object');
    }

    public function test_gemini_provider_translates_json_schema_types(): void
    {
        config(['services.smart_input.provider' => 'gemini']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Done']]]]],
            'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2],
        ])]);

        $result = app(ConfiguredAiProvider::class)->complete('System', $this->messages(), $this->tools());

        $this->assertSame('Done', $result['content']);
        Http::assertSent(function (Request $request): bool {
            $schema = $request->data()['tools'][0]['functionDeclarations'][0]['parameters'];

            return $schema['type'] === 'OBJECT'
                && $schema['properties']['account_id']['type'] === 'INTEGER'
                && ! isset($schema['additionalProperties']);
        });
    }

    /** @return array<int, array<string, string>> */
    private function messages(): array
    {
        return [['role' => 'user', 'content' => 'Show accounts']];
    }

    /** @return array<int, array<string, mixed>> */
    private function tools(): array
    {
        return [[
            'name' => 'getAccounts',
            'description' => 'Get accounts',
            'input_schema' => [
                'type' => 'object',
                'properties' => ['account_id' => ['type' => ['integer', 'null']]],
                'additionalProperties' => false,
            ],
        ]];
    }
}
