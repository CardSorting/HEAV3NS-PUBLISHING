<div class="admin-command" wire:key="command-{{ $id }}-{{ hash('sha256', $command) }}" x-data="{
    copied: false,
    copyFailed: false,
    async copyCommand() {
        this.copied = false; this.copyFailed = false;
        const field = this.$refs.command;
        try {
            if (navigator.clipboard &amp;&amp; window.isSecureContext) {
                await navigator.clipboard.writeText(field.value);
            } else {
                field.focus(); field.select();
                if (!document.execCommand('copy')) throw new Error('clipboard');
            }
            this.copied = true;
        } catch (error) {
            this.copyFailed = true; field.focus(); field.select();
        }
    }
}">
    <div class="admin-command-header">
        <label class="admin-command-label" for="{{ $id }}">{{ $label }}</label>
        <x-filament::link tag="button" color="gray" size="sm" icon="heroicon-o-clipboard-document" x-on:click="copyCommand()">{{ __('Copy command') }}</x-filament::link>
    </div>
    <x-filament::input.wrapper>
        <x-filament::input type="text" :id="$id" x-ref="command" :value="$command" readonly class="admin-command-input" spellcheck="false" dir="ltr" />
    </x-filament::input.wrapper>
    <p x-cloak x-show="copied" role="status" class="admin-command-feedback">{{ __('Command copied.') }}</p>
    <p x-cloak x-show="copyFailed" role="status" class="admin-command-feedback">{{ __('Select and copy the command manually.') }}</p>
</div>
