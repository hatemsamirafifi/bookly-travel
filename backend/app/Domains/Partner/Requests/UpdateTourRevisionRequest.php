<?php

namespace App\Domains\Partner\Requests;

/**
 * Immutable candidate replacement (design v3 §5/§6 `PUT tours/{id}/revisions/
 * {revisionId}`). The endpoint name does not mean mutable snapshot storage: the
 * observed base id, the observed payload hash and the patch are merged onto the
 * stored candidate and stored as a NEW immutable draft row.
 *
 * Shares its validation with StoreTourRevisionRequest; the duplication keeps
 * each owned request contract explicitly executable without a shared base.
 */
class UpdateTourRevisionRequest extends StoreTourRevisionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'observed_payload_hash' => 'required|string|size:64|regex:/^[0-9a-f]{64}$/',
        ]);
    }

    /** @return list<string> */
    protected function observedInputKeys(): array
    {
        return [...parent::observedInputKeys(), 'observed_payload_hash'];
    }

    /**
     * The payload hash the owner observed on the stored candidate.
     */
    public function observedPayloadHash(): ?string
    {
        $value = $this->input('observed_payload_hash');

        return is_string($value) && preg_match('/^[0-9a-f]{64}$/', $value) === 1 ? $value : null;
    }
}
