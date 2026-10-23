<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Category, Currency};
use Spatie\Permission\Models\Role;
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

    public function test_regular_user_cannot_edit_or_update_system_category(): void
    {
        $system = $this->systemCategory();

        $this->get(route('dashboard.finance.categories.edit', $system->id))->assertForbidden();
        $this->put(route('dashboard.finance.categories.update', $system->id), ['icon' => 'coffee'])
            ->assertForbidden();

        $this->assertDatabaseHas('finance_categories', ['id' => $system->id, 'icon' => 'wallet']);
    }

    public function test_super_admin_can_update_system_category_icon(): void
    {
        $this->user->assignRole(Role::findOrCreate('Super Admin'));
        $system = $this->systemCategory();

        $this->get(route('dashboard.finance.categories.edit', $system->id))->assertOk();
        $this->put(route('dashboard.finance.categories.update', $system->id), ['icon' => 'coffee'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('finance_categories', ['id' => $system->id, 'icon' => 'coffee', 'user_id' => null]);
    }

    public function test_system_category_cannot_be_nested_under_a_private_category(): void
    {
        $this->user->assignRole(Role::findOrCreate('Super Admin'));
        $system = $this->systemCategory();
        $private = Category::create([
            'user_id' => $this->user->id,
            'name' => 'Private',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $this->put(route('dashboard.finance.categories.update', $system->id), ['parent_id' => $private->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseHas('finance_categories', ['id' => $system->id, 'parent_id' => null]);
    }

    private function systemCategory(): Category
    {
        return Category::create([
            'user_id' => null,
            'name' => 'Food',
            'type' => 'expense',
            'icon' => 'wallet',
            'is_active' => true,
        ]);
    }
}
