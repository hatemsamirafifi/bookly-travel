# Related tours: explicit many-to-many pivot

Blog posts link to tours via a `blog_post_tours` pivot (`post_id`, `tour_id`, `sort_order`),
managed by the admin in the Filament `BlogPostResource`. The public blog detail surfaces
only `Tour::published()` related tours, up to a maximum of 6 ordered by `sort_order`
(admins may attach more, but only the first 6 eligible published tours are rendered),
transformed via the existing `TourCardTransformer` and rendered with the existing tour
card/listing components on the frontend (cap resolved in Spec 016 clarification,
2026-08-22).

We rejected auto-derivation from category/destination (no editorial control, risks
irrelevant tours) and "no related tours" (loses the spec-required tour discovery
integration). Explicit editorial control is the point of the blog's discovery integration.