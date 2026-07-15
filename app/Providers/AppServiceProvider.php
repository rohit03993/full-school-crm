<?php

namespace App\Providers;

use App\Http\Responses\LogoutResponse;
use App\Models\Student;
use App\Services\HomeworkAssignmentService;
use App\Support\CrmLivewireErrors;
use App\Support\CrmPagination;
use App\Support\SiteContent;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Tables\Table;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        CrmLivewireErrors::register();

        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions(CrmPagination::perPageOptions())
                ->defaultPaginationPageOption(CrmPagination::PER_PAGE);
        });

        View::composer([
            'layouts.public',
            'public.*',
            'components.public.*',
            'portal.*',
            'staff.*',
        ], function ($view): void {
            $view->with('institute', SiteContent::institute());
        });

        View::composer('layouts.portal', function ($view): void {
            $portalNav = [
                'homeworkBadge' => 0,
                'hasEnrollment' => false,
                'hasAdmission' => false,
                'student' => null,
            ];

            if (session()->has('student_portal_id')) {
                $student = Student::query()
                    ->with('activeEnrollment.course')
                    ->find(session('student_portal_id'));

                if ($student) {
                    $portalNav['hasEnrollment'] = $student->activeEnrollment !== null;
                    $portalNav['hasAdmission'] = $student->admissions()->exists();
                    $portalNav['homeworkBadge'] = app(HomeworkAssignmentService::class)
                        ->unviewedCountForStudent($student);
                    $portalNav['student'] = [
                        'name' => $student->name,
                        'initials' => $student->initials(),
                        'subtitle' => $student->activeEnrollment?->enrollment_number
                            ? \App\Support\StudentLabels::rollNumberLabel().' · '.$student->activeEnrollment->enrollment_number
                            : $student->mobile,
                    ];
                }
            }

            $view->with('portalNav', $portalNav);
        });
    }
}
