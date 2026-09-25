<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Livewire\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalPeopleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bar_search_returns_a_lead_after_two_letters(): void
    {
        $this->actingAsSuperAdmin();

        Student::query()->create([
            'name' => 'Arjun Lead',
            'father_name' => 'Parent',
            'mobile' => '9000000099',
            'status' => StudentStatus::Enquiry,
        ]);

        $results = StudentResource::getGlobalSearchResults('ar');

        $this->assertTrue($results->every(fn ($result): bool => $result instanceof GlobalSearchResult));
        $this->assertTrue($results->contains(fn (GlobalSearchResult $result): bool => $result->title === 'Arjun Lead'));
        $this->assertInstanceOf(GlobalSearchResult::class, $results->first());
        $results->first()->getVisibleActions();

        Livewire::test(GlobalSearch::class)
            ->set('search', 'ar')
            ->assertSuccessful()
            ->assertSee('Arjun Lead')
            ->assertSee('Lead')
            ->assertDontSee('9000000099')
            ->assertDontSee('Mobile');
    }

    public function test_top_bar_two_word_search_shows_the_person_name(): void
    {
        $this->actingAsSuperAdmin();

        Student::query()->create([
            'name' => 'Tanmay Agarwal',
            'father_name' => 'Parent',
            'mobile' => '9000000033',
            'status' => StudentStatus::Enquiry,
        ]);

        Livewire::test(GlobalSearch::class)
            ->set('search', 'TANMAY ADARWAL')
            ->assertSuccessful()
            ->assertSee('Tanmay Agarwal')
            ->assertSee('Lead')
            ->assertDontSee('9000000033');
    }

    protected function actingAsSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }
}
