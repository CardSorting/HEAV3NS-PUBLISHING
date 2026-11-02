<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AdminHelperProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        FileUpload::macro('asNullableImageField', function (string $label = null, array $types = [], int $previewHeight = 80) {
            /* @var \Filament\Forms\Components\FileUpload $this */
            return $this
                // @phpstan-ignore-next-line method.notFound (Filament binds macro closures to the FileUpload instance.)
                ->label($label)
                ->directory('assets')
                ->image()
                ->imagePreviewHeight($previewHeight)
                ->acceptedFileTypes($types)
                /*TODO: Review this one*/
                ->maxSize(getSetting('media.max_logo_file_size') ? (int) getSetting('media.max_logo_file_size') * 1024 : 2048)
                ->nullable()
                ->dehydrated()
                ->dehydrateStateUsing(fn ($state) => is_array($state) ? reset($state) : ($state ?: null))
                ->deleteUploadedFileUsing(fn (?string $file) => $file && Storage::exists($file) ? Storage::delete($file) : null);
        });
    }

    /**
     * Checks if user has access to a resources page via navigation.
     * @param string|object $permissionOrResource
     * @param string|null $resource
     * @return array
     */
    public static function resourceNavIfCan(string|object $permissionOrResource, ?string $resource = null): array
    {
        $user = Filament::auth()->user();
        if (!$user) {
            return [];
        }

        // When called like: resourceNavIfCan(UserResource::class)
        $resourceClass = is_string($permissionOrResource)
            ? $permissionOrResource
            : get_class($permissionOrResource);

        // ✅ Let Filament decide via policy/Shield:
        if (method_exists($resourceClass, 'canViewAny') && !$resourceClass::canViewAny()) {
            return [];
        }

        if (method_exists($resourceClass, 'shouldRegisterNavigation') && !$resourceClass::shouldRegisterNavigation()) {
            return [];
        }

        // Try the resource’s navigation first:
        $items = method_exists($resourceClass, 'getNavigationItems')
            ? $resourceClass::getNavigationItems()
            : [];

        // Some resources may return [] (e.g. shouldRegisterNavigation() === false).
        // Fallback: synthesize a single item from the resource metadata:
        if (empty($items) && method_exists($resourceClass, 'getUrl')) {
            $label = method_exists($resourceClass, 'getNavigationLabel')
                ? $resourceClass::getNavigationLabel()
                : (method_exists($resourceClass, 'getModelLabel')
                    ? $resourceClass::getModelLabel()
                    : class_basename($resourceClass));

            $icon = method_exists($resourceClass, 'getNavigationIcon')
                ? $resourceClass::getNavigationIcon()
                : null;

            $items = [
                NavigationItem::make($label)
//                    ->icon($icon)
                    ->url($resourceClass::getUrl())
                    ->isActiveWhen(fn () => request()->fullUrlIs($resourceClass::getUrl().'*')),
            ];
        }

        $items = array_map(
            fn (NavigationItem $item) => $item->icon(null),
            $items
        );

        return $items;
    }

    /**
     * Checks if user has access to a settings page via navigation.l.
     */
    public static function settingsNavItem(
        string $label,
        ?string $icon,
        string $pageClass // e.g. ManageEmailsSettings::class
    ): ?NavigationItem {
        $user = Filament::auth()->user();
        if (!$user) {
            return null;
        }

        // Respect Filament’s own gate for pages
        if (method_exists($pageClass, 'canAccess') && !$pageClass::canAccess()) {
            return null;
        }

        // If the page isn’t registered on this panel, bail out
        if (!method_exists($pageClass, 'getUrl') || !method_exists($pageClass, 'getRouteName')) {
            return null;
        }

        return NavigationItem::make($label)
            ->icon($icon)                               // pass null if your group has an icon
            ->url($pageClass::getUrl())                 // derives URL from the page class
            ->isActiveWhen(fn () => request()->routeIs($pageClass::getRouteName()));
    }

    public static function formatMoney(float|int $amount): string
    {
        $code = getSetting('payments.currency_code');
        $symbol = getSetting('payments.currency_symbol') ?: '$';
        $locale = app()->getLocale();

        // Best: format with ISO currency code (handles separators, symbol placement, spacing)
        if (!empty($code)) {
            return Number::currency($amount, $code, $locale);
        }

        // Fallback: symbol + locale-aware number formatting
        $number = Number::format($amount, locale: $locale, maxPrecision: 2);
        return "{$symbol}{$number}";
    }
}
