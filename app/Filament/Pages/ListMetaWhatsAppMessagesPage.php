<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppPricingCategory;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\MetaWhatsAppMessage;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use UnitEnum;

class ListMetaWhatsAppMessagesPage extends Page
{
    use RequiresCrmPermission;
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappOps;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppMessageLog();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::whatsAppMessageLog();
    }

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public int $perPage = CrmPagination::PER_PAGE;

    public string $categoryFilter = '';

    public string $chargeFilter = '';

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedChargeFilter(): void
    {
        $this->resetPage();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('meta_whatsapp.messages');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.meta-whatsapp-messages')
                ->viewData(fn (): array => [
                    'messages' => $this->messages(),
                    'showMessageCost' => CrmAccess::canSeeMessageCost(auth()->user()),
                ]),
        ]);
    }

    public function messages(): LengthAwarePaginator
    {
        $query = MetaWhatsAppMessage::query()
            ->with(['student:id,name', 'sentBy:id,name'])
            ->latest('id');

        if ($this->categoryFilter !== '') {
            $query->where('conversation_category', $this->categoryFilter);
        }

        if ($this->chargeFilter === 'charged') {
            $query->where('direction', 'outbound')
                ->where('status', '!=', 'failed')
                ->where('estimated_cost_inr', '>', 0);
        }

        if ($this->chargeFilter === 'free') {
            $query->where(function ($inner): void {
                $inner->where('direction', 'inbound')
                    ->orWhere('status', 'failed')
                    ->orWhereNull('estimated_cost_inr')
                    ->orWhere('estimated_cost_inr', '<=', 0);
            });
        }

        return $query->paginate($this->perPage);
    }

    public function categoryLabel(?string $category): string
    {
        if (blank($category)) {
            return '—';
        }

        return WhatsAppPricingCategory::tryFromMeta($category)->label();
    }

    public function chargeLabel(MetaWhatsAppMessage $message): string
    {
        if ($message->direction === 'inbound' || $message->estimated_cost_inr === null) {
            return '—';
        }

        $amount = $message->status === 'failed' ? 0.0 : (float) $message->estimated_cost_inr;
        $formatted = number_format($amount, 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return '₹'.($formatted === '' ? '0' : $formatted);
    }
}
