@php
    $basePayload = $base?->payload ?? [];
    $candidatePayload = $candidate?->payload ?? [];
    $tiers = [
        'source' => 'Canonical source content',
        'presentation' => 'Tour presentation and spoken guide languages',
        'options' => 'Option titles and pickup instructions',
        'media' => 'Ordered images, covers and alternative text',
    ];
    $display = static function (mixed $value): string {
        if ($value === null) {
            return 'Not set';
        }
        if ($value === []) {
            return 'Empty list';
        }
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    };
@endphp

@if (! $candidate || ! $base)
    <p role="status" class="rounded-lg border border-gray-200 p-4 text-sm dark:border-gray-700">
        The captured revision or its approved base is unavailable. Reload before reviewing.
    </p>
@else
    <div class="space-y-5 text-sm">
        <p class="rounded-lg bg-gray-100 p-3 dark:bg-gray-800">
            Revision #{{ $candidate->revision_number }} · ID {{ $candidate->id }} ·
            Source language: {{ strtoupper($candidate->source_locale) }} ·
            Approved base: {{ $base->id }}
        </p>
        @if (is_string($candidate->change_summary) && trim($candidate->change_summary) !== '')
            <p class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <span class="font-semibold">Change summary:</span>
                {{ $candidate->change_summary }}
            </p>
        @endif
        @if (($requests ?? collect())->isNotEmpty())
            <section class="space-y-2" aria-labelledby="revision-linked-requests">
                <h3 id="revision-linked-requests" class="font-semibold">Linked review &amp; correction requests</h3>
                <ul class="space-y-2">
                    @foreach ($requests as $revRequest)
                        <li class="rounded-lg border border-gray-200 p-3 space-y-1 dark:border-gray-700">
                            <p class="text-xs uppercase tracking-wide text-gray-500">
                                {{ str_replace('_', ' ', $revRequest->context) }} · {{ $revRequest->state }}
                            </p>
                            <p><span class="font-semibold">Reason:</span> {{ $revRequest->reason }}</p>
                            @if (is_array($revRequest->field_refs) && $revRequest->field_refs !== [])
                                <ul class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    @foreach ($revRequest->field_refs as $ref)
                                        @if (is_array($ref) && isset($ref['path']))
                                            <li>
                                                <code>{{ $ref['path'] }}</code>
                                                @if (! empty($ref['note']))
                                                    <span>({{ $ref['note'] }})</span>
                                                @endif
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                            @if (is_string($revRequest->response_note) && trim($revRequest->response_note) !== '')
                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold">Partner response:</span> {{ $revRequest->response_note }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if ($candidate->state !== \App\Models\TourContentRevision::STATE_PROPOSED)
            <p role="alert" class="rounded-lg border border-amber-400 p-3">
                This captured revision is {{ $candidate->state }}. It cannot be approved or rejected again.
            </p>
        @endif
        @foreach ($tiers as $tier => $heading)
            @php
                $approved = \Illuminate\Support\Arr::dot($basePayload[$tier] ?? []);
                $proposed = \Illuminate\Support\Arr::dot($candidatePayload[$tier] ?? []);
                $paths = array_values(array_unique(array_merge(array_keys($approved), array_keys($proposed))));
            @endphp
            <section class="space-y-2" aria-labelledby="revision-tier-{{ $tier }}">
                <h3 id="revision-tier-{{ $tier }}" class="font-semibold">{{ $heading }}</h3>
                @if ($paths === [])
                    <p class="text-gray-500">No entries in either version.</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700" tabindex="0" role="region" aria-label="{{ $heading }} comparison">
                        <table class="w-full table-fixed text-left text-sm">
                            <caption class="sr-only">{{ $heading }}: approved and proposed values</caption>
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="w-1/4 p-3">Field</th>
                                    <th scope="col" class="p-3">Approved</th>
                                    <th scope="col" class="p-3">Proposed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($paths as $path)
                                    @php
                                        $old = $approved[$path] ?? null;
                                        $new = $proposed[$path] ?? null;
                                        $changed = ! array_key_exists($path, $approved) || ! array_key_exists($path, $proposed) || $old !== $new;
                                    @endphp
                                    <tr class="{{ $changed ? 'bg-amber-50 dark:bg-amber-950' : '' }}">
                                        <th scope="row" class="break-words p-3 align-top font-normal">
                                            <code>{{ $tier }}.{{ $path }}</code>
                                            @if ($changed)
                                                <span class="block font-semibold">Changed</span>
                                            @endif
                                        </th>
                                        <td class="break-words p-3 align-top whitespace-pre-wrap">{{ array_key_exists($path, $approved) ? $display($old) : 'Absent' }}</td>
                                        <td class="break-words p-3 align-top whitespace-pre-wrap">{{ array_key_exists($path, $proposed) ? $display($new) : 'Absent' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endif
