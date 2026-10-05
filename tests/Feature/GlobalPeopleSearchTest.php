<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
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
            ->assertSee('No class yet')
            ->assertDontSee('9000000099')
            ->assertDontSee('Mobile');
    }

    public function test_top_bar_search_shows_the_class_for_each_student_with_the_same_name(): void
    {
        $admin = $this->actingAsSuperAdmin();

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27-search',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'name' => 'Class 11 JEE',
            'code' => 'CLS-11-SEARCH',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $sectionA = $this->makeSection($course, $session, $admin, 'A');
        $sectionB = $this->makeSection($course, $session, $admin, 'B');

        $first = Student::query()->create([
            'name' => 'Aryan Jain',
            'mobile' => '9000000111',
            'status' => StudentStatus::Enrolled,
        ]);
        $second = Student::query()->create([
            'name' => 'Aryan Jain',
            'mobile' => '9000000222',
            'status' => StudentStatus::Enrolled,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $sectionA->id,
            'student_id' => $first->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $admin->id,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $sectionB->id,
            'student_id' => $second->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $admin->id,
        ]);

        Livewire::test(GlobalSearch::class)
            ->set('search', 'aryan jain')
            ->assertSuccessful()
            ->assertSee('Class 11 JEE · Section A')
            ->assertSee('Class 11 JEE · Section B');
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

    protected function makeSection(Course $course, AcademicSession $session, User $staff, string $section): Batch
    {
        return Batch::query()->create([
            'name' => 'Class 11 JEE - '.$section,
            'section' => $section,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);
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
