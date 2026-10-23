<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Category, Currency};
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_default' => true,
        ]);

        $this->actingAs($this->user);
    }

    public function test_store_persists_expense_type_and_is_loan(): void
    {
        $response = $this->post(route('dashboard.finance.categories.store'), [
            'name' => 'Groceries',
            'type' => 'expense',
            'expense_type' => 'essential',
            'is_loan' => false,
        ]);

        $response->assertRedirect(route('dashboard.finance.categories.index'));

        $this->assertDatabaseHas('finance_categories', [
            'user_id' => $this->user->id,
            'name' => 'Groceries',
            'type' => 'expense',
            'expense_type' => 'essential',
            'is_loan' => false,
        ]);
    }

    public function test_update_persists_expense_type_and_is_loan(): void
    {
        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Misc',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $response = $this->put(route('dashboard.finance.categories.update', $category->id), [
            'name' => 'Misc',
            'type' => 'expense',
            'expense_type' => 'discretionary',
            'is_loan' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('finance_categories', [
            'id' => $category->id,
            'expense_type' => 'discretionary',
            'is_loan' => true,
        ]);
    }

    public function test_update_persists_type_change_and_clears_expense_fields(): void
    {
        $category = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Salary',
            'type' => 'expense',
            'expense_type' => 'essential',
            'is_loan' => true,
            'is_active' => true,
        ]);

        $response = $this->put(route('dashboard.finance.categories.update', $category->id), [
            'name' => 'Salary',
            'type' => 'income',
            'expense_type' => null,
            'is_loan' => false,
            'is_passive' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('finance_categories', [
            'id' => $category->id,
            'type' => 'income',
            'expense_type' => null,
            'is_loan' => false,
        ]);
    }
}
