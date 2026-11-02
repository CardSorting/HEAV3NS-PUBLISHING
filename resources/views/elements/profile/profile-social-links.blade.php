@if(!empty($items))
    <div class="profile-social-links-row">
        @foreach($items as $item)
            <a
                href="{{ $item['url'] }}"
                class="profile-social-chip social-{{ $item['key'] }}"
                target="_blank"
                rel="noopener nofollow"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                title="{{ $item['label'] }}"
                aria-label="{{ $item['label'] }}"
            >
                @if(($item['icon']['type'] ?? 'ion') === 'img')
                    <img
                        src="{{ asset($item['icon']['src']) }}"
                        class="{{ $item['icon']['class'] ?? 'social-media-icon' }}"
                        alt="{{ $item['icon']['alt'] ?? $item['label'] }}"
                    >
                @else
                    <ion-icon name="{{ $item['icon']['name'] ?? 'link-outline' }}"></ion-icon>
                @endif
            </a>
        @endforeach
    </div>
@endif
