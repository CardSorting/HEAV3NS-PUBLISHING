<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
//                Section::make(__('Filters'))
//                    ->columnSpanFull()
//                    ->collapsible()
//                    ->collapsed() // This keeps the section hidden by default
//                    ->schema([
//                        DatePicker::make('startDate')
//                            ->label(__('admin.filters.start_date'))
//                            ->maxDate(fn (Get $get) => $get('endDate') ?: now()),
//                        DatePicker::make('endDate')
//                            ->label(__('admin.filters.end_date'))
//                            ->minDate(fn (Get $get) => $get('startDate') ?: now())
//                            ->maxDate(now()),
//                    ]),
            ]);
    }
}
