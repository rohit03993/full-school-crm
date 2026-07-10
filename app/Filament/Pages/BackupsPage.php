<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\AuditService;
use App\Services\CrmBackupService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class BackupsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 55;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::backups();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::backups();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('setup.backups');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function createBackup(CrmBackupService $backups, AuditService $audit): void
    {
        try {
            $result = $backups->create();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Backup failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $audit->log(
            action: 'Full Backup Created',
            newValues: [
                'filename' => $result['filename'],
                'size_bytes' => $result['size_bytes'],
                'source' => 'admin_ui',
            ],
            user: Auth::user(),
        );

        Notification::make()
            ->title('Full backup ready')
            ->body($result['filename'].' ('.$backups->formatBytes($result['size_bytes']).')')
            ->success()
            ->send();
    }

    public function deleteBackup(string $filename, CrmBackupService $backups, AuditService $audit): void
    {
        if (! $backups->deleteBackup($filename)) {
            Notification::make()
                ->title('Could not delete backup')
                ->warning()
                ->send();

            return;
        }

        $audit->log(
            action: 'Full Backup Deleted',
            newValues: ['filename' => $filename],
            user: Auth::user(),
        );

        Notification::make()
            ->title('Backup deleted')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.backups')
                ->viewData(fn (): array => [
                    'backups' => app(CrmBackupService::class)->listBackups(),
                    'retain' => (int) config('crm-backup.retain', 14),
                    'scheduleAt' => (string) config('crm-backup.schedule_at', '02:15'),
                    'formatBytes' => fn (int $bytes): string => app(CrmBackupService::class)->formatBytes($bytes),
                ]),
        ]);
    }
}
