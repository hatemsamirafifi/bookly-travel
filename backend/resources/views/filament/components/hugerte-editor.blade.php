<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        data-hugerte-wrapper
        data-state-path="{{ $field->getStatePath() }}"
        data-toolbar-profile="{{ $field->getToolbarProfile() }}"
        data-toolbar-buttons="{{ implode(',', $field->getToolbarButtons()) }}"
        {{ $attributes->merge() }}
    >
        <textarea
            hidden
            data-hugerte-input
            {{ $applyStateBindingModifiers('wire:model') }}="{{ $field->getStatePath() }}"
        ></textarea>

        <div class="hugerte-toolbar fi-input-wrp" data-hugerte-toolbar aria-label="{{ $field->getLabel() }} toolbar"></div>

        <div
            class="hugerte-editor fi-input"
            data-hugerte-editor
            contenteditable="true"
            tabindex="0"
            aria-multiline="true"
        >{!! $field->getState() !!}</div>
    </div>
</x-dynamic-component>

@once
    <script src="{{ asset('vendor/hugerte/hugerte.min.js') }}"></script>
@endonce

@once
    <script>
        (function () {
            var boot = function () {
                if (window.HugeRte) {
                    window.HugeRte.mountAll();
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }

            if (window.Livewire) {
                Livewire.hook('morphed', function () {
                    if (window.HugeRte) {
                        window.HugeRte.mountAll();
                    }
                });
            }
        })();
    </script>
@endonce
