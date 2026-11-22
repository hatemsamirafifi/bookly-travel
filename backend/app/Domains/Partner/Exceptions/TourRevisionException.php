<?php

namespace App\Domains\Partner\Exceptions;

use RuntimeException;

/**
 * Domain-level revision governance failure with a machine-readable contract
 * error code v3 (ADR 0024, design v3 §6).
 *
 * The error code travels to the partner/editor/filament boundary untouched;
 * HTTP status mapping and localized envelopes stay at the boundary.
 */
class TourRevisionException extends RuntimeException
{
    public const CONTENT_REQUIRES_REVISION = 'content_requires_revision';

    public const OPTION_STRUCTURE_NOT_SUPPORTED = 'option_structure_change_not_supported';

    public const REVISION_CONFLICT = 'REVISION_CONFLICT';

    public const REVISION_ALREADY_OPEN = 'REVISION_ALREADY_OPEN';

    public const REVISION_SUPERSEDED = 'REVISION_SUPERSEDED';

    public const REVISION_TERMINAL = 'REVISION_TERMINAL';

    public const REVISION_CONTENT_INVALID = 'REVISION_CONTENT_INVALID';

    public const REQUEST_NOT_OPEN = 'REQUEST_NOT_OPEN';

    public const UNKNOWN_FIELD_REFERENCE = 'unknown_field_reference';

    public const UNDECLARED_FIELD_CHANGE = 'undeclared_field_change';

    public const STATUS_CONFLICT = 409;

    public const STATUS_INVALID = 422;

    /**
     * @param  string  $errorCode  one of the documented error codes
     * @param  array<string, string>  $paths  offending input path => message
     * @param  int  $status  HTTP status the boundary should respond with
     * @param  string|null  $message  safe public message (no internals)
     * @param  array<string, mixed>  $identity  safe identifiers for the boundary
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly array $paths = [],
        public readonly int $status = self::STATUS_CONFLICT,
        ?string $message = null,
        public readonly array $identity = [],
    ) {
        parent::__construct($message ?? $this->defaultMessage());
    }

    public static function conflict(string $errorCode, ?string $message = null, array $paths = [], array $identity = []): self
    {
        return new self($errorCode, $paths, self::STATUS_CONFLICT, $message, $identity);
    }

    public static function invalid(string $errorCode, array $paths = [], ?string $message = null, array $identity = []): self
    {
        return new self($errorCode, $paths, self::STATUS_INVALID, $message, $identity);
    }

    /**
     * The safe contract payload for API/Filament envelopes. Provider, hash and
     * content internals never leave the domain.
     *
     * @return array<string, mixed>
     */
    public function contractPayload(): array
    {
        $payload = [
            'error_code' => $this->errorCode,
            'paths' => $this->paths,
            'identity' => $this->identity,
        ];

        if ($this->paths !== []) {
            $errors = [];

            foreach ($this->paths as $path => $message) {
                $errors[(string) $path] = [(string) $message];
            }

            $payload['errors'] = $errors;
        }

        $revisionId = $this->identity['revision_id']
            ?? $this->identity['conflicting_revision_id']
            ?? $this->identity['superseded_revision_id']
            ?? null;

        if (is_int($revisionId) || (is_string($revisionId) && ctype_digit($revisionId))) {
            $payload['revision_id'] = (int) $revisionId;
        }

        return $payload;
    }

    private function defaultMessage(): string
    {
        return match ($this->errorCode) {
            self::CONTENT_REQUIRES_REVISION => 'This published content requires a revision request; the governed write was refused whole.',
            self::OPTION_STRUCTURE_NOT_SUPPORTED => 'Published option structure changes are not supported yet; unchanged option text and commercial values round-trip correctly.',
            self::REVISION_CONFLICT => 'The referenced revision does not match the current state.',
            self::REVISION_ALREADY_OPEN => 'This tour already has an active content revision.',
            self::REVISION_SUPERSEDED => 'The referenced revision was replaced by a newer one.',
            self::REVISION_TERMINAL => 'The referenced revision is already decided.',
            self::REVISION_CONTENT_INVALID => 'The revision content does not satisfy the current required field bounds.',
            self::REQUEST_NOT_OPEN => 'The referenced request is no longer open.',
            self::UNKNOWN_FIELD_REFERENCE => 'A referenced field does not exist on the captured content.',
            self::UNDECLARED_FIELD_CHANGE => 'A changed field was not declared in the request.',
            default => 'The tour content governance write was refused.',
        };
    }
}
