@php
    /** @var \App\Services\FollowUpWorklistService $worklist */
@endphp

<div class="space-y-6 pb-24 lg:pb-6">
    @include('filament.pages.partials.follow-ups-call-table', [
        'title' => 'Call callbacks — due & overdue',
        'description' => 'Students with a scheduled call follow-up on or before today.',
        'students' => $dueCallFollowUps,
        'empty' => 'No call callbacks due right now.',
        'worklist' => $worklist,
        'shown' => $dueCallFollowUps->count(),
        'total' => $dueCallFollowUpsTotal,
        'listLimit' => $listLimit,
    ])

    @include('filament.pages.partials.follow-ups-table', [
        'title' => 'Visit follow-ups — due & overdue',
        'description' => 'Visits with a follow-up date on or before today.',
        'visits' => $dueVisits,
        'empty' => 'No visit follow-ups due right now.',
        'worklist' => $worklist,
        'shown' => $dueVisits->count(),
        'total' => $dueVisitsTotal,
        'listLimit' => $listLimit,
    ])

    @include('filament.pages.partials.follow-ups-call-table', [
        'title' => 'Call callbacks — upcoming (next 7 days)',
        'description' => 'Scheduled call follow-ups so staff can plan ahead.',
        'students' => $upcomingCallFollowUps,
        'empty' => 'No call callbacks scheduled for the next week.',
        'worklist' => $worklist,
        'shown' => $upcomingCallFollowUps->count(),
        'total' => $upcomingCallFollowUpsTotal,
        'listLimit' => $listLimit,
    ])

    @include('filament.pages.partials.follow-ups-table', [
        'title' => 'Visit follow-ups — upcoming (next 7 days)',
        'description' => 'Scheduled visit follow-ups so staff can plan ahead.',
        'visits' => $upcomingVisits,
        'empty' => 'No visit follow-ups scheduled for the next week.',
        'worklist' => $worklist,
        'shown' => $upcomingVisits->count(),
        'total' => $upcomingVisitsTotal,
        'listLimit' => $listLimit,
    ])
</div>
