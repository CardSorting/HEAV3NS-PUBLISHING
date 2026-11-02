<?php

namespace App\Filament\Resources\ManualPaymentMethods;

use App\Filament\Resources\ManualPaymentMethods\Pages\CreateManualPaymentMethod;
use App\Filament\Resources\ManualPaymentMethods\Pages\EditManualPaymentMethod;
use App\Filament\Resources\ManualPaymentMethods\Pages\ListManualPaymentMethods;
use App\Model\ManualPaymentMethod;
use App\Services\ManualPayments\ManualPaymentFields;
use App\Services\ManualPayments\ManualPaymentReceivingImages;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class ManualPaymentMethodResource extends Resource
{
    protected static ?string $model = ManualPaymentMethod::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static UnitEnum|string|null $navigationGroup = 'Finances';

    public static function getModelLabel(): string
    {
        return __('Manual payment method');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Manual payment methods');
    }

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $user = Filament::auth()->user();
        $allowed = in_array($action, ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny'], true)
            && $user
            && $user->can('View:ManagePaymentsSettings')
            && DatabaseSchema::hasTable('manual_payment_methods');

        if (!$allowed) {
            return Response::deny();
        }

        if ($action === 'delete') {
            if (!($record instanceof ManualPaymentMethod) || !$record->exists) {
                return Response::deny();
            }
            if ($record->hasLinkedRecords()) {
                return Response::deny(__('This method has saved payout accounts or payment records. Disable it instead.'));
            }
        }

        return Response::allow();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Method settings'))->columns(2)->columnSpanFull()->schema([
                Select::make('direction')->label(__('Payment area'))
                    ->options([
                        ManualPaymentMethod::DEPOSIT => __('Deposits'),
                        ManualPaymentMethod::WITHDRAWAL => __('Withdrawals'),
                    ])
                    ->default(fn () => request('direction') === ManualPaymentMethod::WITHDRAWAL
                        ? ManualPaymentMethod::WITHDRAWAL : ManualPaymentMethod::DEPOSIT)
                    ->required()->live()->disabledOn('edit')->dehydrated()
                    ->helperText(__('Create a separate method for deposits and withdrawals.')),
                TextInput::make('name')->label(__('Name shown to users'))->required()->maxLength(150),
                Toggle::make('enabled')->label(__('Enabled'))->default(true)->live()
                    ->helperText(__('Disabling a method stops new submissions and keeps existing requests.')),
                TextInput::make('sort_order')->label(__('Display order'))->integer()->minValue(0)->default(0),
                Textarea::make('instructions')->label(__('Instructions'))->rows(3)->maxLength(10000)->columnSpanFull(),
            ]),
            Section::make(__('Receiving details'))->columnSpanFull()
                ->description(__('Enter the site details users need to send a deposit. Fill in or remove empty details before enabling the method.'))
                ->visible(fn (Get $get) => $get('direction') === ManualPaymentMethod::DEPOSIT)
                ->schema([
                    Repeater::make('receiving_details')->hiddenLabel()->columns(2)
                        ->defaultItems(0)->maxItems(50)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->addActionLabel(__('Add receiving detail'))->schema([
                            Hidden::make('key')->default(fn () => 'detail_'.Str::lower(Str::random(12))),
                            TextInput::make('label')->label(__('Label'))->required()->maxLength(150),
                            Select::make('type')->label(__('Detail type'))->default('text')->required()->live()
                                ->options(['text' => __('Text'), 'image' => __('Image / QR code')])
                                ->afterStateHydrated(fn (Select $component, $state) => $component->state($state ?? 'text'))
                                ->afterStateUpdated(fn (Set $set) => $set('value', null)),
                            Group::make()->columnSpanFull()
                                ->schema(fn (Get $get): array => [static::receivingValueField($get('type') ?? 'text')]),
                        ]),
                ]),
            Section::make(__('Saved payout account fields'))->columnSpanFull()
                ->description(__('Users fill these fields when adding a receiving account for this withdrawal method.'))
                ->visible(fn (Get $get) => $get('direction') === ManualPaymentMethod::WITHDRAWAL)
                ->schema([static::fieldsRepeater('account_fields')->minItems(1)]),
            Section::make(__('Request fields'))->columnSpanFull()
                ->description(__('Users fill these fields for each deposit or withdrawal request.'))
                ->schema([static::fieldsRepeater('request_fields')]),
            Section::make(__('Deposit proof requirements'))->columns(2)->columnSpanFull()
                ->visible(fn (Get $get) => $get('direction') === ManualPaymentMethod::DEPOSIT)
                ->schema([
                    TextInput::make('submission_settings.minimum_attachments')->label(__('Minimum receipts'))
                        ->integer()->minValue(0)->default(0)
                        ->helperText(__('Counts completed uploads across the File upload fields configured above.'))
                        ->rules([fn (Get $get) => function (string $attribute, $value, $fail) use ($get): void {
                            $capacity = array_sum(array_map(fn ($field) => ($field['type'] ?? '') === 'file'
                                ? (int) ($field['max_files'] ?? ManualPaymentFields::MAX_FILES) : 0, $get('request_fields') ?? []));
                            if ((int) $value > $capacity) {
                                $fail(__('Add enough File upload fields to accept the minimum number of receipts.'));
                            }
                        }]),
                ]),
        ]);
    }

    protected static function receivingValueField(string $type): Field
    {
        if ($type !== 'image') {
            return Textarea::make('value')->label(__('Value'))->rows(2)->maxLength(2000)
                ->required(fn (Get $get): bool => (bool) $get('../../enabled'));
        }

        return FileUpload::make('value')->label(__('Image / QR code'))->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->maxSize(fn (): int => ManualPaymentReceivingImages::maxSizeKb())
            ->rules(['dimensions:max_width=3000,max_height=3000'])
            ->required(fn (Get $get): bool => (bool) $get('../../enabled'))
            ->imagePreviewHeight('220px')
            ->fetchFileInformation(false)
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => ManualPaymentReceivingImages::metadata($file) !== null)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ManualPaymentReceivingImages::class)->upload($file))
            ->getUploadedFileUsing(function (string $file): ?array {
                $image = ManualPaymentReceivingImages::metadata($file);
                if ($image === null) {
                    return null;
                }

                return [
                    'name' => __('Payment image').'.png', 'size' => $image['size'], 'type' => $image['mime'],
                    'url' => ManualPaymentReceivingImages::url($file),
                ];
            })
            ->helperText(__('PNG, JPEG or WebP, up to 2 MB and 3000 × 3000 pixels. Images are not cropped. Replaced images remain available in existing deposit requests.'));
    }

    protected static function fieldsRepeater(string $name): Repeater
    {
        return Repeater::make($name)->hiddenLabel()->columns(2)->defaultItems(0)->maxItems(50)
            ->reorderable()->collapsible()->addActionLabel(__('Add field'))
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->schema([
                Hidden::make('key')->default(fn () => 'field_'.Str::lower(Str::random(12))),
                Hidden::make('max_length'),
                TextInput::make('label')->label(__('Label'))->required()->maxLength(150),
                Select::make('type')->label(__('Input type'))->required()->default('text')->live()->options([
                    'text' => __('Text'),
                    'textarea' => __('Long text'),
                    'email' => __('Email'),
                    'phone' => __('Phone number'),
                    'select' => __('Dropdown'),
                ] + ($name === 'request_fields' ? ['file' => __('File upload')] : [])),
                Toggle::make('required')->label(__('Required'))->default(false),
                TextInput::make('help_text')->label(__('Help text'))->maxLength(1000),
                Select::make('allowed_extensions')->label(__('Allowed file formats'))->multiple()
                    ->options(['jpg' => 'JPG', 'jpeg' => 'JPEG', 'png' => 'PNG', 'pdf' => 'PDF', 'xlsx' => 'XLSX'])
                    ->default(ManualPaymentFields::FILE_EXTENSIONS)->required()
                    ->visible(fn (Get $get) => $name === 'request_fields' && $get('type') === 'file')->columnSpanFull(),
                TextInput::make('max_size_kb')->label(__('Maximum file size (KB)'))->integer()
                    ->default(ManualPaymentFields::MAX_FILE_SIZE_KB)->minValue(1)->maxValue(ManualPaymentFields::MAX_FILE_SIZE_KB)->required()
                    ->helperText(__('Up to 5 MB per file, limited by the site upload setting.'))
                    ->visible(fn (Get $get) => $name === 'request_fields' && $get('type') === 'file'),
                TextInput::make('max_files')->label(__('Maximum files'))->integer()
                    ->default(ManualPaymentFields::MAX_FILES)->minValue(1)->maxValue(ManualPaymentFields::MAX_FILES)->required()
                    ->visible(fn (Get $get) => $name === 'request_fields' && $get('type') === 'file'),
                Repeater::make('options')->label(__('Dropdown options'))->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === 'select')
                    ->minItems(fn (Get $get) => $get('type') === 'select' ? 1 : 0)
                    ->defaultItems(0)->maxItems(100)->reorderable()->addActionLabel(__('Add option'))
                    ->schema([
                        Hidden::make('value')->default(fn () => 'option_'.Str::lower(Str::random(12))),
                        TextInput::make('label')->label(__('Option label'))->required()->maxLength(150),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
            TextColumn::make('direction')->label(__('Payment area'))->badge()
                ->formatStateUsing(fn ($state) => $state === ManualPaymentMethod::DEPOSIT ? __('Deposits') : __('Withdrawals')),
            IconColumn::make('enabled')->label(__('Enabled'))->boolean(),
            TextColumn::make('sort_order')->label(__('Display order'))->sortable(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->authorizationTooltip(),
        ])->toolbarActions([
            DeleteBulkAction::make()->authorizeIndividualRecords()
                ->modalDescription(__('Only methods without saved payout accounts or payment records will be deleted. Disable methods in use to stop new submissions.')),
        ])->defaultSort('sort_order')->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualPaymentMethods::route('/'),
            'create' => CreateManualPaymentMethod::route('/create'),
            'edit' => EditManualPaymentMethod::route('/{record}/edit'),
        ];
    }
}
