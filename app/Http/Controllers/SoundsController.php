<?php

namespace App\Http\Controllers;

use App\Model\Sound;
use Illuminate\Http\Request;

class SoundsController extends Controller
{
    public function trending(Request $request)
    {
        $limit = (int) $request->get('limit', 20);

        $sounds = Sound::query()
            ->available()
            ->with([
                'coverAttachment',
                'audioAttachment',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'sounds' => $this->toSelectize($sounds),
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $limit = (int) $request->get('limit', 20);

        if ($q === '') {
            return response()->json(['sounds' => []]);
        }

        $sounds = Sound::query()
            ->available()
            ->where(function ($qq) use ($q) {
                $qq->where('title', 'like', "%{$q}%")
                    ->orWhere('artist', 'like', "%{$q}%");
            })
            ->with([
                'coverAttachment',
                'audioAttachment',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'sounds' => $this->toSelectize($sounds),
        ]);
    }

    private function toSelectize($sounds): array
    {
        return $sounds
            ->filter(fn ($sound) => filled(optional($sound->coverAttachment)->path)
                && filled(optional($sound->audioAttachment)->path))
            ->map(function ($sound) {
                return [
                    'id'     => (string) $sound->id,
                    'title'  => (string) $sound->title,
                    'artist' => (string) ($sound->artist ?? ''),
                    'cover'  => (string) $sound->coverAttachment->path,
                    'url'    => (string) $sound->audioAttachment->path,
                ];
            })
            ->values()
            ->all();
    }
}
