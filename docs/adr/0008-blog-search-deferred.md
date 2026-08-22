# Blog search: deferred from v1

Spec 016 v1 ships NO blog search. The blog listing supports category filter + pagination
only, matching the spec's explicit deliverables (frontend-implementation-plan.md:454-466
lists listing, detail, category filtering, SEO, i18n, related tours — but NOT search).

Blog search will be revisited when analytics justify it; at that point we evaluate
Meilisearch (mirroring the tours index) vs PostgreSQL FTS on the JSONB body. Adding a
second Meilisearch index now is over-engineering for unproven demand.