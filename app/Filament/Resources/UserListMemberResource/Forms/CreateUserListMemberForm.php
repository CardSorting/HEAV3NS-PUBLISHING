<?php

namespace App\Filament\Resources\UserListMemberResource\Forms;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class CreateUserListMemberForm
{
    public static function schema($listId = null, $userId = null): array
    {
        return [
            Section::make(__('admin.resources.user_list_member.sections.list_association'))
                ->description(__('admin.resources.user_list_member.sections.list_association_descr'))
                ->schema([
                    Select::make('list_id')
                        ->label(__('admin.resources.user_list_member.fields.list_id'))
                        ->relationship('userList', 'id')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_list_member.placeholders.list_id'))
                        ->required()
                        ->default($listId),

                    Select::make('user_id')
                        ->label(__('admin.resources.user_list_member.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->placeholder(__('admin.resources.user_list_member.placeholders.user_id'))
                        ->required()
                        ->default($userId)
                        ->preload(true),
                ])
                ->columns(2),
        ];
    }
}
