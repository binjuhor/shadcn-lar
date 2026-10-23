<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\AdvisorConversation;
use Tests\TestCase;

class FinancialAdvisorConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['aiadvisor.enabled' => true]);
    }

    public function test_user_can_rename_their_advisor_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = AdvisorConversation::create([
            'user_id' => $user->id,
            'title' => 'Old title',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('dashboard.finance.assistant.conversations.rename', $conversation->id),
            ['title' => 'Debt check']
        );

        $response->assertOk()
            ->assertJsonPath('conversation.title', 'Debt check');

        $this->assertDatabaseHas('finance_advisor_conversations', [
            'id' => $conversation->id,
            'title' => 'Debt check',
        ]);
    }

    public function test_user_cannot_rename_another_users_advisor_conversation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AdvisorConversation::create([
            'user_id' => $otherUser->id,
            'title' => 'Private',
        ]);

        $this->actingAs($user)->patchJson(
            route('dashboard.finance.assistant.conversations.rename', $conversation->id),
            ['title' => 'Debt check']
        )->assertNotFound();
    }
}
