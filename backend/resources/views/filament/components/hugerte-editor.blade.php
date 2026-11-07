<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        class="bookly-hugerte"
        data-bookly-hugerte
        data-state-path="{{ $field->getStatePath() }}"
        data-editor-toolbar="{{ implode(' ', $field->getToolbarButtons()) }}"
        data-editor-profile="{{ $field->getToolbarProfile() }}"
    >
        <textarea
            data-hugerte-source
            id="hugerte-{{ $getId() }}"
            aria-label="{{ $field->getLabel() }}"
        >{{ $field->getState() }}</textarea>
        <p data-hugerte-error hidden role="alert">Rich text editor could not load. Your original text is preserved below.</p>
    </div>
    {{-- Livewire model stays OUTSIDE wire:ignore. Plugin updates dispatch input events. --}}
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
