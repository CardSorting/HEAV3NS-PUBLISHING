@if($checkerScriptUrl = app(\App\Services\AgeCheck\AgeGate::class)->checkerScriptUrl(request()))
    <script src="{{ $checkerScriptUrl }}"></script>
@endif
