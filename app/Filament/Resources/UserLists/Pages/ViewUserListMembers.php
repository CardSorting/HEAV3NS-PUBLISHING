<?php

namespace App\Filament\Resources\UserLists\Pages;

use App\Filament\Resources\UserListMemberResource\Forms\CreateUserListMemberForm;
use App\Filament\Resources\UserLists\UserListResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Support\Facades\Gate;

class ViewUserListMembers extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = UserListResource::class;

    protected static string $relationship = 'members';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-s-user-circle';

    public function getTitle(): string | Htmlable
    {
        return __('admin.resources.user_list_member.plural');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.user_list.members.breadcrumb');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.user_list.members.navigation_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // root grid; keeps inner sections full-width
            ->components(
                CreateUserListMemberForm::schema((int) $this->getRecord()->getKey())
            );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('id')
                        ->label(__('admin.resources.user_list.members.fields.id')),

                    TextEntry::make('user.username')
                        ->label(__('admin.resources.user_list.members.fields.username')),

                    TextEntry::make('created_at')
                        ->dateTime()
                        ->label(__('admin.resources.user_list.members.fields.created_at')),
                ])
                ->columns(1), // optional
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('member')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.user_list.members.fields.id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.user_list.members.fields.username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('admin.resources.user_list.members.fields.created_at'))
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.resources.user_list_member.actions.create'))
                    ->modalHeading(__('admin.resources.user_list_member.actions.create')),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
