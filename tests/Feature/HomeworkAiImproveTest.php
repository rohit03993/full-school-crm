<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Filament\Pages\HomeworkReviewPage;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\User;
use App\Services\HomeworkAiService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class HomeworkAiImproveTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_gemini_returns_a_suggestion_and_does_not_save_homework(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
        $this->useGemini();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"title":"Parabola – Exercise 1 and 2","description":"Complete Exercise 1 and Exercise 2 from the Parabola chapter."}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $data = $this->seedClass();
        $teacher = User::factory()->create([
            'name' => 'Sunil Rana',
            'is_active' => true,
        ]);
        $otherTeacher = User::factory()->create([
            'name' => 'Parul',
            'is_active' => true,
        ]);
        BatchStaffAssignment::query()->create([
            'batch_id' => $data['batch']->id,
            'user_id' => $teacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $data['maths']->id,
        ]);
        BatchStaffAssignment::query()->create([
            'batch_id' => $data['batch']->id,
            'user_id' => $otherTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $data['maths']->id,
        ]);
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('startAdd', $data['batch']->id, $data['maths']->id, 0, $teacher->id)
            ->setActionData([
                'title' => 'Parabola',
                'description' => 'Exercise 1 and 2',
            ])
            ->call('improveHomeworkWithAi')
            ->assertSet('homeworkAiOriginalTitle', 'Parabola')
            ->assertSet('homeworkAiOriginalDescription', 'Exercise 1 and 2')
            ->assertSet('homeworkAiSuggestedTitle', 'Parabola – Exercise 1 and 2')
            ->assertSet('mountedActions.0.data.title', 'Parabola')
            ->assertSet('mountedActions.0.data.description', 'Exercise 1 and 2');

        $this->assertSame(0, HomeworkAssignment::query()->count());

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && str_contains($request->url(), 'gemini-3.5-flash-lite')
                && str_contains($body, 'Exercise 1 and 2')
                && str_contains($body, 'Dear Students')
                && str_contains($body, 'Mathematics')
                && ! str_contains($body, '(MATH)')
                && str_contains($body, 'Sunil Rana')
                && ! str_contains($body, 'Parul')
                && str_contains($body, 'Do not add a test')
                && ! str_contains($body, 'temperature')
                && ! str_contains($body, 'test-gemini-key');
        });
    }

    public function test_use_this_copies_the_suggestion_and_save_stores_only_one_homework(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
        $this->useGemini();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"title":"Parabola – Exercise 1 and 2","description":"Complete Exercise 1 and Exercise 2 from the Parabola chapter."}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $data = $this->seedClass();
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->setActionData([
                'title' => 'Parabola',
                'description' => 'Exercise 1 and 2',
            ])
            ->call('improveHomeworkWithAi')
            ->call('useHomeworkAiSuggestion')
            ->assertSet('homeworkAiSuggestedTitle', null)
            ->assertSet('mountedActions.0.data.title', 'Parabola – Exercise 1 and 2')
            ->assertSet('mountedActions.0.data.description', 'Complete Exercise 1 and Exercise 2 from the Parabola chapter.')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(1, HomeworkAssignment::query()->count());
        $this->assertDatabaseHas('homework_assignments', [
            'title' => 'Parabola – Exercise 1 and 2',
            'description' => 'Complete Exercise 1 and Exercise 2 from the Parabola chapter.',
        ]);
    }

    public function test_try_again_sends_the_original_words_and_stops_after_the_try_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
        config(['ai.homework.tries_per_open' => 2]);
        $this->useGemini();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"title":"Parabola – Exercise 1 and 2","description":"Complete Exercise 1 and Exercise 2 from the Parabola chapter."}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $data = $this->seedClass();
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->setActionData([
                'title' => 'Parabola',
                'description' => 'Exercise 1 and 2',
            ])
            ->call('improveHomeworkWithAi')
            ->setActionData([
                'title' => 'Changed by hand',
                'description' => 'Different text',
            ])
            ->call('retryHomeworkAi')
            ->call('retryHomeworkAi')
            ->assertSet('homeworkAiTries', 2);

        $this->assertSame(0, HomeworkAssignment::query()->count());
        Http::assertSentCount(2);

        $bodies = Http::recorded()->map(fn (array $pair): string => $pair[0]->body())->all();
        $this->assertStringContainsString('Exercise 1 and 2', $bodies[1]);
        $this->assertStringNotContainsString('Different text', $bodies[1]);
    }

    public function test_daily_limit_blocks_another_call_and_a_failure_does_not_use_the_allowance(): void
    {
        $this->useGemini();
        config(['ai.homework.daily_limit' => 1]);

        $admin = User::factory()->create();
        $service = app(HomeworkAiService::class);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push('provider down test-gemini-key', 500)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => "```json\n{\"title\":\"Parabola – Exercise 1 and 2\",\"description\":\"Complete Exercise 1 and Exercise 2 from the Parabola chapter.\"}\n```",
                            ]],
                        ],
                    ]],
                ])
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => '{"title":"Again","description":"Again"}',
                            ]],
                        ],
                    ]],
                ]),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message.' '.json_encode($event->context);
        });

        $failed = $service->improve($admin, 'Parabola', 'Exercise 1 and 2');

        $this->assertFalse($failed->ok);
        $this->assertTrue($failed->temporary);
        $this->assertSame(0, $failed->usedToday);
        $this->assertSame('AI service is temporarily unavailable. Please try again.', $failed->message);
        $this->assertNotSame([], $logged);

        foreach ($logged as $line) {
            $this->assertStringNotContainsString('test-gemini-key', $line);
        }

        $first = $service->improve($admin, 'Parabola', 'Exercise 1 and 2');
        $second = $service->improve($admin, 'Parabola', 'Exercise 1 and 2');

        $this->assertTrue($first->ok, $first->message);
        $this->assertSame(1, $first->usedToday);
        $this->assertFalse($second->ok);
        $this->assertStringContainsString('1 / 1', $second->message);
    }

    public function test_admin_and_academic_coordinator_are_not_stopped_by_the_daily_limit(): void
    {
        $this->useGemini();
        config(['ai.homework.daily_limit' => 1]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"title":"Parabola – Exercise 1 and 2","description":"Complete Exercise 1 and Exercise 2 from the Parabola chapter."}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $coordinator = User::factory()->create(['is_active' => true]);
        $coordinator->assignRole(StaffJobRole::AcademicCoordinator->value);

        $service = app(HomeworkAiService::class);

        $this->assertTrue($service->hasUnlimitedDailyUse($admin));
        $this->assertTrue($service->hasUnlimitedDailyUse($coordinator));

        $service->improve($admin, 'Parabola', 'Exercise 1 and 2');
        $adminSecond = $service->improve($admin, 'Parabola', 'Exercise 1 and 2');

        $service->improve($coordinator, 'Parabola', 'Exercise 1 and 2');
        $coordinatorSecond = $service->improve($coordinator, 'Parabola', 'Exercise 1 and 2');

        $this->assertTrue($adminSecond->ok, $adminSecond->message);
        $this->assertTrue($coordinatorSecond->ok, $coordinatorSecond->message);
        $this->assertTrue($service->usage($admin)['unlimited']);
        $this->assertNull($service->usage($coordinator)['limit']);
    }

    public function test_button_stays_hidden_when_the_key_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
        config([
            'ai.provider' => 'gemini',
            'ai.homework.enabled' => true,
            'ai.gemini.key' => null,
        ]);

        $data = $this->seedClass();
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->assertDontSee('Improve with AI')
            ->assertSee('Speak')
            ->assertSee('data-homework-speech');
    }

    public function test_speak_sits_with_the_homework_box_and_leaves_improve_with_ai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
        $this->useGemini();

        $data = $this->seedClass();
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->assertSee('Speak')
            ->assertSee('Improve with AI')
            ->assertSee('data-homework-speech');
    }

    public function test_openai_provider_is_used_when_the_setting_says_openai(): void
    {
        config([
            'ai.provider' => 'openai',
            'ai.openai.key' => 'sk-test-openai',
            'ai.openai.model' => 'gpt-4.1-mini',
            'ai.gemini.key' => null,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"title":"Parabola – Exercise 1 and 2","description":"Complete Exercise 1 and Exercise 2 from the Parabola chapter."}',
                    ],
                ]],
            ]),
        ]);

        $admin = User::factory()->create();
        $result = app(HomeworkAiService::class)->improve($admin, 'Parabola', 'Exercise 1 and 2');

        $this->assertTrue($result->ok);
        $this->assertSame('Parabola – Exercise 1 and 2', $result->title);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && str_contains($request->body(), 'Exercise 1 and 2')
                && ! str_contains($request->body(), 'sk-test-openai');
        });

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    private function useGemini(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.homework.enabled' => true,
            'ai.gemini.key' => 'test-gemini-key',
            'ai.gemini.model' => 'gemini-3.5-flash-lite',
        ]);
    }

    /**
     * @return array{admin: User, batch: Batch, maths: CourseSubject}
     */
    private function seedClass(): array
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'CLS-11',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 1000,
            'status' => CourseStatus::Active,
        ]);

        $maths = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Class 11 - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $batch->subjects()->attach($maths->id, ['sort_order' => 1]);

        return [
            'admin' => $admin,
            'batch' => $batch,
            'maths' => $maths,
        ];
    }
}
