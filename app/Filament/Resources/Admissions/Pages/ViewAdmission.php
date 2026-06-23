<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Admission;
use App\Services\AdmissionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewAdmission extends ViewRecord
{
    protected static string $resource = AdmissionResource::class;

    protected string $view = 'filament.resources.admissions.pages.view-admission';

    public string $returnRemarks = '';

    public ?string $discountAmount = null;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'student',
            'enquiry.course',
            'documents',
            'enrollment',
            'approvedBy',
        ]);

        $this->discountAmount = (string) ($this->record->discount_amount ?? 0);
    }

    public function getAdmissionNetFeeProperty(): string
    {
        $courseFee = (float) ($this->record->course_fee ?? 0);
        $discount = max(0, (float) ($this->discountAmount ?? 0));

        return number_format(max(0, $courseFee - $discount), 2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('studentProfile')
                ->label('Open Student Profile')
                ->icon('heroicon-o-user')
                ->url(fn (): string => StudentProfilePage::getUrl([
                    'record' => $this->record->student_id,
                ]).'?tab=admission'),
            Action::make('approve')
                ->label('Approve Admission')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(fn (): bool => Auth::user()?->can('approve', $this->record) ?? false)
                ->action(fn (AdmissionService $admissions) => $this->approve($admissions)),
            Action::make('return')
                ->label('Return for Correction')
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => Auth::user()?->can('returnForCorrection', $this->record) ?? false)
                ->form([
                    \Filament\Forms\Components\Textarea::make('returnRemarks')
                        ->label('Remarks for student / staff')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3),
                ])
                ->action(function (array $data, AdmissionService $admissions): void {
                    $this->returnForCorrection($admissions, $data['returnRemarks']);
                }),
        ];
    }

    public function saveAdmissionDiscount(AdmissionService $admissions): void
    {
        /** @var Admission $admission */
        $admission = $this->record;

        if (! $admission->canAdjustFees()) {
            return;
        }

        $this->validate([
            'discountAmount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->record = $admissions->updateFees(
                $admission,
                (float) $this->discountAmount,
                Auth::user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        Notification::make()
            ->title('Discount saved')
            ->body('Net fee is now ₹'.$this->admissionNetFee.'.')
            ->success()
            ->send();
    }

    protected function approve(AdmissionService $admissions): void
    {
        /** @var Admission $admission */
        $admission = $this->record;

        $enrollment = $admissions->approve($admission, Auth::user());

        $this->record = $admission->fresh([
            'student',
            'enquiry.course',
            'documents',
            'enrollment',
            'approvedBy',
        ]);

        Notification::make()
            ->title('Admission approved')
            ->body('Roll No. '.$enrollment->enrollment_number.' created.')
            ->success()
            ->send();
    }

    protected function returnForCorrection(AdmissionService $admissions, string $remarks): void
    {
        /** @var Admission $admission */
        $admission = $this->record;

        $this->record = $admissions->returnForCorrection($admission, Auth::user(), $remarks);

        Notification::make()
            ->title('Returned for correction')
            ->success()
            ->send();

        $this->redirect(AdmissionResource::getUrl('index'));
    }
}
