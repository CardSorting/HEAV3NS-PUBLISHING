<?php

namespace App\Filament\Pages\Settings;

use App\Services\ProductThemeGenerator;
use App\Settings\ColorsSettings;
use App\Settings\LicenseSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;
use BackedEnum;

class ManageColorsSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $slug = 'settings/colors';

    protected static string $settings = ColorsSettings::class;

    protected ProductThemeGenerator $themeGenerator;

    protected LicenseSettings $licenseSettings;

    public static function getNavigationLabel(): string
    {
        return __('Theme Settings');
    }

    public function getTitle(): string
    {
        return __('Theme Settings');
    }

    public function boot(ProductThemeGenerator $themeGenerator, LicenseSettings $licenseSettings): void
    {
        $this->themeGenerator = $themeGenerator;
        $this->licenseSettings = $licenseSettings;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetTheme')
                ->label(__('Reset to default'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->canResetTheme())
                ->requiresConfirmation()
                ->modalDescription(__('Your generated theme will be backed up and the built-in colors restored. No theme server request is needed.'))
                ->action(fn (ColorsSettings $settings) => $this->resetTheme($settings)),
        ];
    }

    protected function canResetTheme(): bool
    {
        return auth()->check()
            && (int) auth()->user()->role_id !== 3
            && static::canAccess()
            && $this->canEdit();
    }

    public function resetTheme(ColorsSettings $settings): void
    {
        abort_unless($this->canResetTheme(), 403);

        $defaults = $this->defaultColors();

        try {
            $this->themeGenerator->resetToDefaults(function () use ($settings, $defaults): void {
                $this->persistColors($settings, $defaults);
            });
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title(__('Theme reset failed. Check file permissions and settings storage.'))
                ->danger()
                ->send();

            return;
        }

        $this->fillForm();
        $this->rememberData();
        Notification::make()
            ->title(__('Default theme restored.'))
            ->success()
            ->send();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($this->defaultColors() as $key => $default) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                $data[$key] = $default;
            }
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Customize your theme colors'))
                ->description(__('Customize your branding by adjusting theme colors.'))
                ->schema([

                    Placeholder::make('theme_generation')
                        ->label('')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(new HtmlString(view('filament.partials.colors')->render())),

                    ColorPicker::make('theme_color_code')
                        ->label(__('Primary color'))
                        ->helperText(__('Used for buttons, accents, and highlights.'))
                        ->required(),

                    ColorPicker::make('theme_gradient_from')
                        ->label(__('Gradient start color'))
                        ->helperText(__('Starting color for gradients and background transitions.'))
                        ->required(),

                    ColorPicker::make('theme_gradient_to')
                        ->label(__('Gradient end color'))
                        ->helperText(__('Ending color for gradients and background transitions.'))
                        ->required(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public function save(): void
    {
        if (!$this->canEdit()) {
            return;
        }

        // Validate first so Livewire retains its usual field-level error handling.
        $this->callHook('beforeValidate');
        $data = $this->form->getState();
        $this->callHook('afterValidate');
        $data = $this->mutateFormDataBeforeSave($data);
        $settings = app(static::getSettings());

        try {
            if (array_intersect(array_keys($this->defaultColors()), $settings->getLockedProperties()) !== []) {
                throw new RuntimeException('Theme color settings are locked.');
            }

            $this->callHook('beforeSave');
            $result = $this->themeGenerator->generateAndInstall(
                $data,
                (string) $this->licenseSettings->product_license_key,
                function () use ($settings, $data): void {
                    $this->persistColors($settings, $data);
                }
            );
        } catch (Halt) {
            return;
        } catch (Throwable $exception) {
            Notification::make()
                ->title(__('Theme generation failed'))
                ->body(e($exception->getMessage()))
                ->danger()
                ->send();

            return;
        }

        if (!$result['installed']) {
            $downloadUrl = e($result['download_url']);
            Notification::make()
                ->title(__('Theme ready for manual installation'))
                ->body(__('PHP ZIP support is required to apply this theme. Download :link and install it with the theme:install command on a server with ZIP support. Your saved colors have not changed.', [
                    'link' => "<a href=\"{$downloadUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">".__('the theme archive').'</a>',
                ]))
                ->warning()
                ->send();

            return;
        }

        $this->callHook('afterSave');
        $this->rememberData();
        $this->getSavedNotification()?->send();

        if ($redirectUrl = $this->getRedirectUrl()) {
            $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
        }
    }

    protected function persistColors(ColorsSettings $settings, array $colors): void
    {
        try {
            DB::transaction(function () use ($settings, $colors): void {
                // Locks may have changed while the remote request was running.
                $settings->refresh();
                if (array_intersect(array_keys($this->defaultColors()), $settings->getLockedProperties()) !== []) {
                    throw new RuntimeException('Theme color settings are locked.');
                }
                $settings->fill($colors)->save();
            });
        } catch (Throwable $exception) {
            // Reload persisted values and their cache after a rolled-back save.
            $settings->refresh();
            throw $exception;
        }
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title(__('Theme generated & applied.'))
            ->success()
            ->send();
    }

    /**
     * @return array{theme_color_code: string, theme_gradient_from: string, theme_gradient_to: string}
     */
    private function defaultColors(): array
    {
        $defaults = (array) config('theme.default_colors');

        return [
            'theme_color_code' => (string) ($defaults['theme_color_code'] ?? '#cb0c9f'),
            'theme_gradient_from' => (string) ($defaults['theme_gradient_from'] ?? '#7928CA'),
            'theme_gradient_to' => (string) ($defaults['theme_gradient_to'] ?? '#FF0080'),
        ];
    }
}
