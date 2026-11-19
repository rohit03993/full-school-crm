<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppMessageSource;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppMarketingContact;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppMarketingList;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppOutboundBlanks;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ManageWhatsAppMarketing extends Page
{
    use RequiresCrmPermission;
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $slug = 'whatsapp-marketing';

    protected static ?string $title = 'Marketing list';

    public $sheet = null;

    public string $search = '';

    public string $templateId = '';

    /** @var array<int, string> */
    public array $blankSources = [];

    /** @var array<int, string> */
    public array $blankText = [];

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    public function importSheet(WhatsAppMarketingList $list): void
    {
        if (! $this->sheet) {
            Notification::make()->title('Choose an Excel or CSV file')->warning()->send();

            return;
        }

        $sheets = Excel::toArray(null, $this->sheet->getRealPath());
        $grid = $sheets[0] ?? [];
        $headers = array_map(
            fn ($value): ?string => filled($value) ? trim((string) $value) : null,
            array_values($grid[0] ?? []),
        );
        $rows = [];

        foreach (array_slice($grid, 1) as $row) {
            $rows[] = array_map(
                fn ($value): ?string => filled($value) ? trim((string) $value) : null,
                array_values($row),
            );
        }

        $result = $list->importGrid($headers, $rows);
        $this->sheet = null;

        if ($result['added'] === 0 && $result['skipped'] === 0) {
            Notification::make()->title('The file needs a Mobile column')->warning()->send();

            return;
        }

        Notification::make()
            ->title($result['added'].' added, '.$result['skipped'].' skipped')
            ->body('A number already on the list is skipped. These people are not added as students.')
            ->success()
            ->send();
    }

    public function sendToList(): void
    {
        $template = $this->templateId !== '' ? MetaWhatsAppTemplate::query()->find((int) $this->templateId) : null;

        if (! $template || WhatsAppOutboundBlanks::needsFile($template)) {
            Notification::make()->title('Choose a template')->warning()->send();

            return;
        }

        $parsed = MetaWhatsAppTemplateParser::parse(is_array($template->components) ? $template->components : []);

        if (! WhatsAppOutboundBlanks::textsAreFilled($parsed['body_variables'], $this->blankSources, $this->blankText)) {
            Notification::make()->title('Fill every blank that uses the same words')->warning()->send();

            return;
        }

        $people = $this->contacts()->limit(40)->get();

        if ($people->isEmpty()) {
            Notification::make()->title('No numbers in this list')->warning()->send();

            return;
        }

        $meta = app(MetaWhatsAppService::class);
        $sent = 0;
        $failed = 0;

        foreach ($people as $person) {
            $values = WhatsAppOutboundBlanks::values($parsed['body_variables'], $this->blankSources, $this->blankText, (string) $person->name);
            $result = $meta->sendTemplate(
                (string) $person->mobile,
                $template->name,
                $values,
                filled($template->language) ? (string) $template->language : 'en',
                count($values),
                [
                    'message_source' => WhatsAppMessageSource::Campaign->value,
                ],
            );

            if (($result['status'] ?? '') === 'success') {
                $sent++;
            } else {
                $failed++;
            }
        }

        $notice = Notification::make()
            ->title($sent.' sent'.($failed > 0 ? ', '.$failed.' failed' : ''))
            ->body('These numbers are not student records. Up to 40 are sent at a time.');

        if ($sent === 0) {
            $notice->danger();
        } else {
            $notice->success();
        }

        $notice->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.whatsapp-marketing-list')
                ->viewData(fn (): array => [
                    'rows' => $this->contacts()->limit(100)->get(),
                    'count' => $this->contacts()->count(),
                    'templates' => WhatsAppOutboundBlanks::templates(),
                    'chosenTemplateId' => $this->templateId,
                    'blankSources' => $this->blankSources,
                    'joinUrl' => route('marketing.join'),
                ]),
        ]);
    }

    protected function contacts(): Builder
    {
        $search = trim($this->search);
        $query = WhatsAppMarketingContact::query()->latest('id');

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            });
        }

        return $query;
    }
}
