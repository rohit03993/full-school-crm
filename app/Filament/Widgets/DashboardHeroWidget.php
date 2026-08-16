<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\FeesHubPage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\MyTeachingAssignmentsPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Services\CallQueueService;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavBadges;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\InstituteSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardHeroWidget extends Widget
{
    use UsesDashboardFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.dashboard-hero';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $branding = InstituteSettings::forDocuments();
        $user = Auth::user();
        $pack = CrmNavigation::navRolePack($user);

        return [
            'userName' => $user?->name ?? 'there',
            'instituteName' => $branding['name'],
            'tagline' => $branding['tagline'],
            'todayLabel' => now()->format('l, j F Y'),
            'chips' => $this->chipsForPack($pack),
            'quickActions' => $this->visibleActions($this->actionsForPack($pack)),
        ];
    }

    /**
     * Headline numbers for whoever is looking, so no role lands on a hero that
     * describes somebody else's job.
     *
     * @return list<array{icon: string, label: string}>
     */
    protected function chipsForPack(string $pack): array
    {
        return match ($pack) {
            'owner' => $this->ownerChips(),
            'calling' => $this->callingChips(),
            'academic' => $this->academicChips(),
            'finance' => $this->financeChips(),
            'messaging' => $this->messagingChips(),
            default => $this->defaultChips(),
        };
    }

    /**
     * @return list<array{icon: string, label: string}>
     */
    protected function ownerChips(): array
    {
        $filters = $this->dashboardFilters();
        $stats = app(CrmDashboardService::class)->stats($filters);
        $user = Auth::user();
        $canViewFees = FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user);

        $chips = [
            [
                'icon' => 'heroicon-m-user-group',
                'label' => $stats['active_students'].' enrolled',
            ],
        ];

        if (FeatureGate::enabled(LicenseFeature::Attendance)) {
            $chips[] = [
                'icon' => 'heroicon-m-check-circle',
                'label' => $stats['attendance_present_today'].' present '.$stats['as_of_label'],
            ];
        }

        if ($canViewFees) {
            $chips[] = [
                'icon' => 'heroicon-m-banknotes',
                'label' => '₹'.number_format($stats['range_fee_collection'], 0).' collected · '.$stats['range_label'],
            ];
            $chips[] = [
                'icon' => 'heroicon-m-exclamation-triangle',
                'label' => '₹'.number_format($stats['pending_fees_total'], 0).' pending fees',
            ];
        }

        if (FeatureGate::enabled(LicenseFeature::Admissions) && $stats['pending_admissions'] > 0) {
            $chips[] = [
                'icon' => 'heroicon-m-clipboard-document-check',
                'label' => $stats['pending_admissions'].' pending admissions',
            ];
        }

        if (FeatureGate::enabled(LicenseFeature::Enquiries)) {
            $chips[] = [
                'icon' => 'heroicon-m-inbox-arrow-down',
                'label' => $stats['range_enquiries'].' leads · '.$stats['range_label'],
            ];
        }

        return $chips;
    }

    /**
     * @return list<array{icon: string, label: string}>
     */
    protected function callingChips(): array
    {
        $staff = Auth::user();

        if (! $staff) {
            return [];
        }

        $callStats = app(CallQueueService::class)->todayStats($staff);

        return [
            [
                'icon' => 'heroicon-m-phone',
                'label' => $callStats['calls_today'].' calls today · '.$callStats['connected_today'].' connected',
            ],
            [
                'icon' => 'heroicon-m-bars-3-bottom-left',
                'label' => $callStats['queue_count'].' in queue',
            ],
            [
                'icon' => 'heroicon-m-bell-alert',
                'label' => CrmNavBadges::followUpsDue().' follow-ups due',
            ],
        ];
    }

    /**
     * @return list<array{icon: string, label: string}>
     */
    protected function academicChips(): array
    {
        $stats = app(CrmDashboardService::class)->stats($this->dashboardFilters());
        $chips = [];

        if (FeatureGate::enabled(LicenseFeature::Attendance)) {
            $chips[] = [
                'icon' => 'heroicon-m-check-circle',
                'label' => $stats['attendance_present_today'].' present '.$stats['as_of_label'],
            ];
            $chips[] = [
                'icon' => 'heroicon-m-clipboard-document-list',
                'label' => $stats['attendance_marked_today'].' of '.$stats['attendance_students_in_batches'].' marked',
            ];
        }

        $chips[] = [
            'icon' => 'heroicon-m-academic-cap',
            'label' => $stats['active_batches'].' active classes',
        ];

        return $chips;
    }

    /**
     * @return list<array{icon: string, label: string}>
     */
    protected function financeChips(): array
    {
        $filters = $this->dashboardFilters();
        $service = app(CrmDashboardService::class);
        $stats = $service->stats($filters);
        $fees = $service->feeSummary($filters);

        return [
            [
                'icon' => 'heroicon-m-banknotes',
                'label' => '₹'.number_format($stats['range_fee_collection'], 0).' collected · '.$stats['range_label'],
            ],
            [
                'icon' => 'heroicon-m-exclamation-triangle',
                'label' => '₹'.number_format($stats['pending_fees_total'], 0).' pending fees',
            ],
            [
                'icon' => 'heroicon-m-clock',
                'label' => $fees['overdue_students_count'].' students overdue',
            ],
        ];
    }

    /**
     * @return list<array{icon: string, label: string}>
     */
    protected function messagingChips(): array
    {
        $stats = app(CrmDashboardService::class)->stats($this->dashboardFilters());

        return [
            [
                'icon' => 'heroicon-m-chat-bubble-left-right',
                'label' => 'WhatsApp workspace',
            ],
            [
                'icon' => 'heroicon-m-user-group',
                'label' => $stats['active_students'].' students reachable',
            ],
        ];
    }

    /**
     * @return list<array{icon: string, label: string}>
     */
    protected function defaultChips(): array
    {
        $staff = Auth::user();
        $chips = [];

        if ($staff && CrmAccess::can($staff, CrmPermission::CasesView)) {
            $chips[] = [
                'icon' => 'heroicon-m-briefcase',
                'label' => CrmNavBadges::myCasesOpen($staff).' open cases assigned to you',
            ];
        }

        if ($staff && FeatureGate::enabled(LicenseFeature::Enquiries)) {
            $chips[] = [
                'icon' => 'heroicon-m-bell-alert',
                'label' => CrmNavBadges::followUpsDue().' follow-ups due',
            ];
        }

        return $chips !== [] ? $chips : [[
            'icon' => 'heroicon-m-squares-2x2',
            'label' => 'Your workspace',
        ]];
    }

    /**
     * Drops tiles the viewer cannot open, so no shortcut leads to a 403.
     *
     * @param  list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>  $actions
     * @return list<array{label: string, description: string, icon: string, url: string}>
     */
    protected function visibleActions(array $actions): array
    {
        $user = Auth::user();

        return array_values(array_filter(
            $actions,
            function (array $action) use ($user): bool {
                if (($action['feature'] ?? null) !== null && ! FeatureGate::enabled($action['feature'])) {
                    return false;
                }

                if (isset($action['can']) && is_callable($action['can']) && ! $action['can']($user)) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function actionsForPack(string $pack): array
    {
        return match ($pack) {
            'owner' => $this->ownerActions(),
            'calling' => $this->callingActions(),
            'academic' => $this->academicActions(),
            'finance' => $this->financeActions(),
            'messaging' => $this->messagingActions(),
            default => $this->defaultStaffActions(),
        };
    }

    /**
     * @return array{label: string, description: string, icon: string, url: string, feature: ?LicenseFeature, can: callable}
     */
    protected function reportsAction(string $description): array
    {
        return [
            'label' => 'Reports',
            'description' => $description,
            'icon' => 'heroicon-o-document-chart-bar',
            'url' => ReportsPage::getUrl(),
            'feature' => LicenseFeature::Reports,
            'can' => fn ($user): bool => ReportsPage::canAccess(),
        ];
    }

    /**
     * @return array{label: string, description: string, icon: string, url: string, feature: ?LicenseFeature, can: callable}
     */
    protected function myWorkAction(): array
    {
        return [
            'label' => 'My work',
            'description' => 'Meetings, cases, and assigned calls',
            'icon' => 'heroicon-o-briefcase',
            'url' => MyMeetingsPage::getUrl(),
            'feature' => null,
            'can' => fn ($user): bool => $user && CrmAccess::can($user, CrmPermission::CasesView),
        ];
    }

    /**
     * @return array{label: string, description: string, icon: string, url: string, feature: ?LicenseFeature, can: callable}
     */
    protected function findStudentAction(string $description): array
    {
        return [
            'label' => 'Find student',
            'description' => $description,
            'icon' => 'heroicon-o-magnifying-glass',
            'url' => StudentSearchPage::getUrl(),
            'feature' => null,
            'can' => fn ($user): bool => StudentSearchPage::canAccess(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function ownerActions(): array
    {
        return [
            [
                'label' => 'All Leads',
                'description' => 'Full enquiry pipeline',
                'icon' => 'heroicon-o-inbox-stack',
                'url' => EnquiryResource::getUrl('index'),
                'feature' => LicenseFeature::Enquiries,
            ],
            [
                'label' => 'Admissions',
                'description' => 'Review pending forms',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => AdmissionResource::getUrl('index'),
                'feature' => LicenseFeature::Admissions,
            ],
            [
                'label' => 'Attendance',
                'description' => 'Live punches and class marking',
                'icon' => 'heroicon-o-calendar-days',
                'url' => AttendanceHubPage::getUrl(),
                'feature' => LicenseFeature::Attendance,
                'can' => fn ($user): bool => AttendanceHubPage::canAccess(),
            ],
            [
                'label' => 'Fees',
                'description' => 'Collections and bulk charges',
                'icon' => 'heroicon-o-banknotes',
                'url' => FeesHubPage::getUrl(),
                'feature' => LicenseFeature::Fees,
                'can' => fn ($user): bool => FeesHubPage::canAccess(),
            ],
            $this->reportsAction('Export CSV & PDF'),
            [
                'label' => 'All cases',
                'description' => 'Institute-wide support cases',
                'icon' => 'heroicon-o-rectangle-stack',
                'url' => MyMeetingsPage::getUrl(['tab' => 'all_cases']),
                'feature' => LicenseFeature::Cases,
                'can' => fn ($user): bool => $user && $user->hasRole(RoleName::SuperAdmin->value),
            ],
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function callingActions(): array
    {
        return [
            [
                'label' => 'Call Queue',
                'description' => 'Start calling now',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'url' => CallQueuePage::getUrl(),
                'feature' => LicenseFeature::Calls,
                'can' => fn ($user): bool => CallQueuePage::canAccess(),
            ],
            [
                'label' => 'Assigned to Call',
                'description' => 'Your admin-assigned calling list',
                'icon' => 'heroicon-o-user-group',
                'url' => MyLeadsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => MyLeadsPage::canAccess(),
            ],
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => FollowUpsPage::canAccess(),
            ],
            $this->findStudentAction('Open any profile'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function academicActions(): array
    {
        return [
            [
                'label' => 'Attendance',
                'description' => 'Live punches and class marking',
                'icon' => 'heroicon-o-calendar-days',
                'url' => AttendanceHubPage::getUrl(),
                'feature' => LicenseFeature::Attendance,
                'can' => fn ($user): bool => AttendanceHubPage::canAccess(),
            ],
            [
                'label' => 'Homework',
                'description' => 'Submit, review, and check',
                'icon' => 'heroicon-o-book-open',
                'url' => HomeworkPage::getUrl(),
                'feature' => LicenseFeature::Homework,
                'can' => fn ($user): bool => HomeworkPage::canAccess(),
            ],
            [
                'label' => CrmMenuLabels::examResults(),
                'description' => 'Enter, review, and publish marks',
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => ActivitySessionResource::getUrl('index'),
                'feature' => LicenseFeature::Marks,
                'can' => fn ($user): bool => ActivitySessionResource::canViewAny(),
            ],
            [
                'label' => 'My classes',
                'description' => 'Your teaching assignments',
                'icon' => 'heroicon-o-academic-cap',
                'url' => MyTeachingAssignmentsPage::getUrl(),
                'feature' => null,
                'can' => fn ($user): bool => MyTeachingAssignmentsPage::canAccess(),
            ],
            $this->reportsAction('Academic exports'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function financeActions(): array
    {
        return [
            [
                'label' => 'Fees',
                'description' => 'Dashboard, bulk charges, adjustments',
                'icon' => 'heroicon-o-banknotes',
                'url' => FeesHubPage::getUrl(),
                'feature' => LicenseFeature::Fees,
                'can' => fn ($user): bool => FeesHubPage::canAccess(),
            ],
            $this->findStudentAction('Collect from a profile'),
            $this->reportsAction('Fee and admission exports'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function messagingActions(): array
    {
        return [
            [
                'label' => 'WhatsApp inbox',
                'description' => 'Replies and live conversations',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => WhatsAppInboxPage::getUrl(),
                'feature' => LicenseFeature::WhatsApp,
                'can' => fn ($user): bool => WhatsAppInboxPage::canAccess(),
            ],
            $this->findStudentAction('Open any profile'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function defaultStaffActions(): array
    {
        return [
            [
                'label' => 'Assigned to Call',
                'description' => 'Your admin-assigned calling list',
                'icon' => 'heroicon-o-user-group',
                'url' => MyLeadsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => MyLeadsPage::canAccess(),
            ],
            [
                'label' => 'Call Queue',
                'description' => 'Start calling now',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'url' => CallQueuePage::getUrl(),
                'feature' => LicenseFeature::Calls,
                'can' => fn ($user): bool => CallQueuePage::canAccess(),
            ],
            $this->findStudentAction('Open any profile'),
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => FollowUpsPage::canAccess(),
            ],
            $this->myWorkAction(),
        ];
    }
}
