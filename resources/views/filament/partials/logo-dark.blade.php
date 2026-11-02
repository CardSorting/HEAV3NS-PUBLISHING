@if ($src = getSetting('admin.dark_logo'))
    <img src="{{ $src }}" alt="Logo" class="h-10 w-10 object-contain shrink-0 align-middle" loading="lazy">
@endif

@if ($title = getSetting('admin.title'))
    <div class="ml-2 inline-flex items-center h-10 text-xl font-bold leading-none tracking-tight text-gray-950 dark:text-white brand-text">
        {{ $title }}
    </div>
@endif
