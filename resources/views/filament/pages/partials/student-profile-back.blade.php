{{-- Same Back control as every other inner page, rendered on the card so Filament page padding cannot sit between Back and the student. --}}
<div class="fi-student-profile-inpage-back">
    @include('filament.partials.page-back-link', [
        'scopes' => [
            \App\Filament\Pages\StudentProfilePage::class,
            \App\Filament\Resources\Students\StudentResource::class,
        ],
    ])
</div>
