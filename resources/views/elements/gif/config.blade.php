@once('gif-config-'.$surface)
<div hidden data-gif-config="{{ json_encode(app(\App\Services\GifService::class)->clientConfig($surface)) }}"></div>
@endonce
