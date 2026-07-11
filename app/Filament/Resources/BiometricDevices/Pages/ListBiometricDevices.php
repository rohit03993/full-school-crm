<?php

namespace App\Filament\Resources\BiometricDevices\Pages;

use App\Filament\Resources\BiometricDevices\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ListBiometricDevices extends ListRecords
{
    protected static string $resource = BiometricDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.biometric-devices.adms-status-banner')
                    ->viewData(fn (): array => $this->admsStatusBannerData()),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function admsStatusBannerData(): array
    {
        $devices = BiometricDevice::query()->orderBy('name')->get();
        $onlineWindowMinutes = 5;
        $online = $devices->filter(
            fn (BiometricDevice $device): bool => $device->last_seen_at?->gt(now()->subMinutes($onlineWindowMinutes)) ?? false,
        )->count();
        $active = $devices->where('is_active', true)->count();
        $todayPunches = $devices->sum(
            fn (BiometricDevice $device): int => $device->today_punch_count_date?->isToday()
                ? (int) $device->today_punch_count
                : 0,
        );
        $latestSeen = $devices->max('last_seen_at');

        $overall = match (true) {
            $active === 0 => 'none',
            $online > 0 => 'online',
            $latestSeen && $latestSeen->gt(now()->subHour()) => 'idle',
            default => 'offline',
        };

        return [
            'admsUrl' => url('/'.trim((string) config('biometric.route_prefix', 'iclock'), '/')),
            'admsEnabled' => (bool) config('biometric.enabled', true),
            'overall' => $overall,
            'online' => $online,
            'active' => $active,
            'total' => $devices->count(),
            'todayPunches' => $todayPunches,
            'latestSeen' => $latestSeen,
            'onlineWindowMinutes' => $onlineWindowMinutes,
        ];
    }
}
