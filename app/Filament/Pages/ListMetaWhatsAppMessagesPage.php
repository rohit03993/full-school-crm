<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\MetaWhatsAppMessage;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\WithPagination;
use UnitEnum;

class ListMetaWhatsAppMessagesPage extends Page
{
    use RequiresCrmPermission;
    use WithPagination;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::MetaWhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Message log';

    protected static ?string $title = 'Meta message log';

    protected static ?int $navigationSort = 20;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public int $perPage = CrmPagination::PER_PAGE;

    public function getSubheading(): ?string
    {
        return CrmHint::text('meta_whatsapp.messages');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.meta-whatsapp-messages')
                ->viewData(fn (): array => [
                    'messages' => MetaWhatsAppMessage::query()
                        ->with('student:id,name')
                        ->latest('id')
                        ->paginate($this->perPage),
                ]),
        ]);
    }
}
