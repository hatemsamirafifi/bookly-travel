@php
    $record = $getRecord();
@endphp

@if ($record instanceof \App\Models\Tour)
    <div class="overflow-x-auto">
        {!! app(\App\Domains\Admin\Services\AvailabilitySlotService::class)->renderSlotsTable($record) !!}
    </div>
@endif