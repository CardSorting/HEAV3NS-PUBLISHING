<div class="version-badge-wrapper" style="--vb-bg: {{ $bgColor }};">
    <div class="version-badge">
        v{{ $version }}
        <div class="version-tooltip">
            {{ str_rot13($label) }} {{ $license }}<br>
            Build: v{{ $version }}
        </div>
    </div>
</div>
