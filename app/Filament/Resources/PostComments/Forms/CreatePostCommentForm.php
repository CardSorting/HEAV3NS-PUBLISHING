<?php

namespace App\Filament\Resources\PostComments\Forms;

use App\Filament\Support\GifContent;
use App\Model\PostComment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;

class CreatePostCommentForm
{
    public static function schema($postId = null): array
    {
        return [
            Textarea::make('message')
                ->label(__('admin.resources.post_comment.fields.message'))
                ->required(fn (Get $get, ?PostComment $record) => GifContent::reference($record) === null || $get('remove_gif'))
                ->minLength(1),

            ...GifContent::fields('post_comments'),

            Select::make('user_id')
                ->label(__('admin.resources.post_comment.fields.author'))
                ->relationship('author', 'username')
                ->searchable()
                ->required()
                ->preload(true),

            Select::make('post_id')
                ->label(__('admin.resources.post_comment.fields.post_id'))
                ->relationship('post', 'id')
                ->searchable()
                ->default($postId)
                ->preload(true)
                ->required(),
        ];
    }
}
