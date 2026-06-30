<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\BulkActivityMarksImportPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Pages\MyAccountPage;
use App\Filament\Pages\TestMarksReviewPage;
use App\Support\InstituteSettings;
use App\Support\CrmNavigation;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class RohitPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('My account')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => MyAccountPage::getUrl()),
            ])
            ->brandName(fn (): string => InstituteSettings::brandName())
            ->brandLogo(fn (): ?string => InstituteSettings::panelLogoUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups()
            ->navigationGroups(CrmNavigation::groups())
            ->maxContentWidth(Width::Full);

        if (file_exists(public_path('build/manifest.json'))) {
            $panel->viteTheme('resources/css/filament/admin/theme.css');
        }

        return $panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                FeesDashboardPage::class,
                BulkActivityMarksImportPage::class,
                TestMarksReviewPage::class,
                MyAccountPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureInstituteOnboardingComplete::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<x-crm.media-preview-dialog />')
                    .view('filament.partials.mobile-bottom-nav')->render()
                    .view('filament.partials.pending-call-flow')->render(),
            );
    }
}
