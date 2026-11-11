# Blog

<!-- EGM contract -->

## Overview

Renders localized article cards, featured and detail views, related content, pagination and unavailable states.

## Interface

- [AuthorByline.tsx](./AuthorByline.tsx#L14) declares `default AuthorByline`.
- [BlogCard.tsx](./BlogCard.tsx#L12) declares `default BlogCard`.
- [BlogDetail.tsx](./BlogDetail.tsx#L17) declares `default BlogDetail`.
- [BlogDetailSkeleton.tsx](./BlogDetailSkeleton.tsx#L1) declares `BlogDetailSkeleton`.
- [BlogFeaturedHero.tsx](./BlogFeaturedHero.tsx#L10) declares `default BlogFeaturedHero`.
- [BlogList.tsx](./BlogList.tsx#L17) declares `default BlogList`.
- [BlogListSkeleton.tsx](./BlogListSkeleton.tsx#L1) declares `BlogListSkeleton`.
- [BlogUnavailable.tsx](./BlogUnavailable.tsx#L15) declares `BlogUnavailable`.
- [RelatedPosts.tsx](./RelatedPosts.tsx#L12) declares `default RelatedPosts`.
- [RelatedTours.tsx](./RelatedTours.tsx#L10) declares `default RelatedTours`.

## Invariants

- `BlogDetail` sanitizes the post body before injecting HTML. Preview and partial translation indicators depend on separate inputs. [BlogDetail.tsx](./BlogDetail.tsx#L17) [BlogDetail.tsx](./BlogDetail.tsx#L85)
- The article list reuses the search paginator when the supplied metadata has more than one page. HTTP 410 or the explicit removed flag selects the removed-article state. [BlogList.tsx](./BlogList.tsx#L30) [BlogUnavailable.tsx](./BlogUnavailable.tsx#L45)

## Side Effects

- The rate-limit countdown invokes `onRetry` or reloads the page at zero and clears its interval on cleanup. [BlogUnavailable.tsx](./BlogUnavailable.tsx#L26)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
