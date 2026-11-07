<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        data-hugerte-wrapper
        data-state-path="{{ $field->getStatePath() }}"
        data-toolbar-profile="{{ $field->getToolbarProfile() }}"
        data-toolbar-buttons="{{ implode(',', $field->getToolbarButtons()) }}"
        x-data="{
            init() {
                this.$nextTick(() => {
                    if (window.HugeRte) {
                        window.HugeRte.mountAll();
                    }
                });
            }
        }"
        {{ $attributes->merge() }}
    >
        <textarea
            hidden
            data-hugerte-input
            {{ $applyStateBindingModifiers('wire:model') }}="{{ $field->getStatePath() }}"
        ></textarea>

        <div class="hugerte-toolbar" data-hugerte-toolbar aria-label="{{ $field->getLabel() }} toolbar"></div>

        <div
            class="hugerte-editor"
            data-hugerte-editor
            contenteditable="true"
            tabindex="0"
            aria-multiline="true"
        >{!! $field->getState() !!}</div>
    </div>
</x-dynamic-component>

@once
<style>
    .hugerte-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.5rem;
        background-color: rgb(249 250 251);
        border: 1px solid rgb(229 231 235);
        border-bottom: none;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }
    .dark .hugerte-toolbar {
        background-color: rgb(31 41 55);
        border-color: rgb(55 65 81);
    }
    .hugerte-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1rem;
        border-radius: 0.375rem;
        color: rgb(55 65 81);
        background-color: #ffffff;
        border: 1px solid rgb(209 213 219);
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        transition: all 0.15s ease-in-out;
        cursor: pointer;
        user-select: none;
    }
    .dark .hugerte-button {
        background-color: rgb(17 24 39);
        color: rgb(229 231 235);
        border-color: rgb(75 85 99);
    }
    .hugerte-button:hover {
        background-color: rgb(243 244 246);
        color: rgb(17 24 39);
        border-color: rgb(156 163 175);
    }
    .dark .hugerte-button:hover {
        background-color: rgb(55 65 81);
        color: #ffffff;
        border-color: rgb(107 114 128);
    }
    .hugerte-button:active {
        background-color: rgb(229 231 235);
        transform: translateY(1px);
    }
    .dark .hugerte-button:active {
        background-color: rgb(75 85 99);
    }
    .hugerte-editor {
        min-height: 12rem;
        max-height: 36rem;
        overflow-y: auto;
        padding: 0.75rem 1rem;
        background-color: #ffffff;
        border: 1px solid rgb(229 231 235);
        border-bottom-left-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
        outline: none;
        color: rgb(17 24 39);
        line-height: 1.5;
    }
    .dark .hugerte-editor {
        background-color: rgb(17 24 39 / 0.5);
        border-color: rgb(55 65 81);
        color: rgb(243 244 246);
    }
    .hugerte-editor:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 1px #f59e0b;
    }
    .hugerte-editor h1 { font-size: 1.875rem; font-weight: 700; margin: 0.5rem 0; }
    .hugerte-editor h2 { font-size: 1.5rem; font-weight: 700; margin: 0.5rem 0; }
    .hugerte-editor h3 { font-size: 1.25rem; font-weight: 600; margin: 0.375rem 0; }
    .hugerte-editor p { margin: 0.25rem 0; }
    .hugerte-editor ul { list-style-type: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
    .hugerte-editor ol { list-style-type: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
    .hugerte-editor blockquote { border-left: 4px solid #d1d5db; padding-left: 1rem; margin: 0.5rem 0; color: #4b5563; font-style: italic; }
    .dark .hugerte-editor blockquote { border-color: #4b5563; color: #9ca3af; }
    .hugerte-editor a { color: #2563eb; text-decoration: underline; }
    .dark .hugerte-editor a { color: #60a5fa; }
</style>
@endonce

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

            document.addEventListener('livewire:navigated', boot);
            document.addEventListener('livewire:initialized', boot);

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                try {
                    window.Livewire.hook('morph.updated', boot);
                    window.Livewire.hook('commit', function (payload) {
                        if (payload && typeof payload.succeed === 'function') {
                            payload.succeed(function () {
                                queueMicrotask(boot);
                            });
                        }
                    });
                } catch (e) {
                    try {
                        window.Livewire.hook('morphed', boot);
                    } catch (err) {}
                }
            }
        })();
    </script>
@endonce
