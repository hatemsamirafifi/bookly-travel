<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EGM-003 additive tour-content governance storage (ADR 0024, design v3 §3).
 *
 * `tour_content_revisions` keeps one immutable full snapshot per tour revision
 * (`kind` legacy_baseline|partner_edit|admin_correction|publication) with a
 * monotonic `revision_number` per tour. A PostgreSQL partial unique index
 * guarantees at most one `draft`/`proposed` candidate per tour, so replacements
 * always insert a NEW row and supersede the old one instead of mutating a
 * reviewed snapshot.
 *
 * `tour_revision_requests` keeps the review/correction conversation in its own
 * history table. Old requests are never reused for another revision.
 *
 * `tours` gains the approved `published_revision_id` pointer (+ `generation_epoch`
 * and `content_version`) and `tour_translation_states` gains `generation_epoch`
 * so derivative work is fenced by source hash AND epoch. No publication epoch:
 * every first/re-publication establishes a NEW approved revision ID.
 *
 * No existing column is removed or repurposed, and no existing row is rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            // Monotonic per-tour sequence: 1 for the captured baseline, then
            // strictly increasing for every new candidate/approved snapshot.
            $table->unsignedInteger('revision_number');
            $table->string('kind', 20);
            $table->string('state', 20);
            // The canonical source locale the snapshot was captured from. The
            // fixed en/es/it content locales have no other storage home.
            $table->string('source_locale', 5);
            // Immutable full snapshot: source (nine canonical fields),
            // presentation (governed fields), options (stable ids in order,
            // authored text only) and media (stored/public projection).
            // No derivative text, currency, prices, stock, partner ID,
            // lifecycle or provider state.
            //
            // `json` (not jsonb): the payload is a CAS token source and array
            // order participates in identity, so the stored text must keep the
            // exact submitted key order instead of jsonb's key normalization.
            $table->json('payload');
            // Whole-snapshot CAS token exposed to owners for replacement.
            $table->string('payload_hash', 64);
            // Canonical source identity (existing source-hash algorithm over
            // source text + option text). Internal, never exposed as a token.
            $table->string('content_hash', 64);
            $table->string('change_summary', 500)->nullable();
            $table->foreignId('base_revision_id')
                ->nullable()
                ->constrained('tour_content_revisions')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'revision_number']);
            $table->index(['tour_id', 'state']);
            $table->index(['tour_id', 'kind', 'state']);
        });

        Schema::create('tour_revision_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')
                ->constrained('tours')
                ->cascadeOnDelete();
            // initial_review | published_review | correction
            $table->string('context', 20);
            $table->foreignId('base_revision_id')
                ->nullable()
                ->constrained('tour_content_revisions')
                ->cascadeOnDelete();
            $table->foreignId('revision_id')
                ->nullable()
                ->constrained('tour_content_revisions')
                ->cascadeOnDelete();
            // open | answered | approved | rejected | closed | superseded
            $table->string('state', 20);
            $table->text('reason');
            // Field references are ordered objects (path plus optional note);
            // a JSON column keeps the submitted order for the bounded review.
            $table->json('field_refs');
            $table->text('response_note')->nullable();
            $table->integer('response_generation_epoch')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('requested_content_hash', 64);
            $table->timestamps();

            $table->index(['tour_id', 'state']);
            $table->index(['tour_id', 'context', 'state']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('published_revision_id')->nullable();
            $table->unsignedInteger('generation_epoch')->default(0);
            $table->unsignedInteger('content_version')->default(0);
        });

        Schema::table('tour_translation_states', function (Blueprint $table) {
            $table->unsignedInteger('generation_epoch')->default(0);
        });

        // Same-tour pointer invariant (design v3 §3) is enforced in the locked
        // domain service, so the pointer carries no foreign key and no cascade.
        // The partial index expresses "at most one live candidate per tour".
        DB::statement(
            'CREATE UNIQUE INDEX tour_content_revisions_one_live_candidate '
            . 'ON tour_content_revisions (tour_id) '
            . "WHERE state IN ('draft', 'proposed')"
        );
    }

    public function down(): void
    {
        // Tested only on a disposable pre-adoption schema; accepted revisions
        // are never rolled back in place.
        DB::statement('DROP INDEX IF EXISTS tour_content_revisions_one_live_candidate');

        Schema::table('tour_translation_states', function (Blueprint $table) {
            $table->dropColumn('generation_epoch');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['published_revision_id', 'generation_epoch', 'content_version']);
        });

        Schema::dropIfExists('tour_revision_requests');
        Schema::dropIfExists('tour_content_revisions');
    }
};
