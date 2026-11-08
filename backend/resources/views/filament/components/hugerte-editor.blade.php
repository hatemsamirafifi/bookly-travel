<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="booklyHugeRteEditor({
            statePath: @js($field->getStatePath()),
            editorId: @js('hugerte-' . $getId()),
            toolbar: @js(implode(' ', $field->getToolbarButtons())),
            profile: @js($field->getToolbarProfile())
        })"
        wire:ignore
        class="bookly-hugerte"
        data-bookly-hugerte
        data-state-path="{{ $field->getStatePath() }}"
        data-editor-toolbar="{{ implode(' ', $field->getToolbarButtons()) }}"
        data-editor-profile="{{ $field->getToolbarProfile() }}"
    >
        <textarea
            x-ref="source"
            data-hugerte-source
            id="hugerte-{{ $getId() }}"
            aria-label="{{ $field->getLabel() }}"
            style="visibility: hidden; height: 0; min-height: 0; padding: 0; border: 0;"
        >{{ $field->getState() }}</textarea>
        <p data-hugerte-error hidden role="alert">Rich text editor could not load. Your original text is preserved below.</p>
    </div>
    {{-- Livewire model stays OUTSIDE wire:ignore. Updates dispatch input/change events. --}}
    <textarea
        data-hugerte-model="{{ $getId() }}"
        {{ $applyStateBindingModifiers('wire:model') }}="{{ $field->getStatePath() }}"
        hidden
        aria-hidden="true"
    >{{ $field->getState() }}</textarea>
</x-dynamic-component>

@once
    <script src="{{ asset('vendor/hugerte/hugerte.min.js') }}" defer></script>
    <script src="{{ asset('js/bookly-hugerte-adapter.js') }}" defer></script>
@endonce
