# Current-source visual and interaction review

Reviewed 2026-10-17 by root against build PWRC5Senrh_71s0M1xvjU. The matrix uses actual built application/API and existing owned CDN fixtures, fresh anonymous contexts and no media adapter. Capture completed 2026-10-17T15:09:41.656Z; all12 runtime checks passed. Root opened and visually inspected all12 full-page captures, all9 mobile modal captures, all3 mobile top/review pairs and desktop sticky-review views at1024 plus Italian1440. Screenshots are retained as45 PNGs.

| Locale | Width | Evidence | Result |
|---|---|---|---|
| en | 390 | [Full](en-390-full.png), [top](en-390-top.png), [reviews](en-390-reviews.png) | Accepted layout |
| en | 768 | [Full](en-768-full.png), [top](en-768-top.png), [reviews](en-768-reviews.png) | Accepted layout |
| en | 1024 | [Full](en-1024-full.png), [top](en-1024-top.png), [reviews](en-1024-reviews.png) | Accepted layout |
| en | 1440 | [Full](en-1440-full.png), [top](en-1440-top.png), [reviews](en-1440-reviews.png) | Accepted layout |
| es | 390 | [Full](es-390-full.png), [top](es-390-top.png), [reviews](es-390-reviews.png) | Accepted layout |
| es | 768 | [Full](es-768-full.png), [top](es-768-top.png), [reviews](es-768-reviews.png) | Accepted layout |
| es | 1024 | [Full](es-1024-full.png), [top](es-1024-top.png), [reviews](es-1024-reviews.png) | Accepted layout |
| es | 1440 | [Full](es-1440-full.png), [top](es-1440-top.png), [reviews](es-1440-reviews.png) | Accepted layout |
| it | 390 | [Full](it-390-full.png), [top](it-390-top.png), [reviews](it-390-reviews.png) | Accepted layout |
| it | 768 | [Full](it-768-full.png), [top](it-768-top.png), [reviews](it-768-reviews.png) | Accepted layout |
| it | 1024 | [Full](it-1024-full.png), [top](it-1024-top.png), [reviews](it-1024-reviews.png) | Accepted layout |
| it | 1440 | [Full](it-1440-full.png), [top](it-1440-top.png), [reviews](it-1440-reviews.png) | Accepted layout |

## Observations

- Titles wrap naturally, essential page text is readable, and the gallery/navigation/content order is consistent. Optional itinerary/operator sections are absent where the actual seeded response omits them; complete/sparse and privacy/fallback behavior is separately proved by the full browser suite.
- At390/768 the single column uses the measured mobile booking bar; ES/IT labels wrap while the action and edit link remain readable. At1024/1440 the gallery has its large-cover/four-tile arrangement and the card occupies a distinct sidebar. Desktop sticky review views keep the card below the nav. The horizontally scrollable nav intentionally crops offscreen entries; real keyboard/active-entry reveal and page overflow tests passed.
- Each locale's390 gallery, wishlist and booking-dialog screenshots was opened and inspected. Close/action labels, visible date/count, estimate, guest explanation and account links fit; modal backdrops cover underlying controls. Capture checks independently verified Escape and actual-trigger focus return for all9 modal states.
- Full-page screenshots show a fixed bar at its capture-viewport position; use the matching top/review viewport screenshots for actual obstruction assessment. Mobile lower content remains scrollable above that bar. CSS safe-area accommodation and measured reserved space are retained; these desktop-browser viewport checks do not claim physical notched-device testing.
- The gallery loads the actual owned test CDN color/label assets, not tourism photography. Some seeded related-tour URLs are unavailable and show the existing image/alt fallback. Card text/links remain readable and geometry stable. No replacement or stock imagery was invented. This review accepts layout/interactions, not production asset availability or photographic quality. Supplied English review text, category titles and duration strings remain supplied facts; page UI controls are localized.

## Independent interactive keyboard/focus spot check

Root additionally used the in-app browser on the real English localhost route, after automated a11y passed. Opening View image1 focused Close lightbox. Right changed1of5 to2of5 while close retained focus. Shift+Tab wrapped to Next image; Tab/Escape closed and returned focus to View image1. Opening the header wishlist checkbox focused Not now, Shift+Tab wrapped to the final Not now control, and Escape returned focus to that checkbox. Activating Reviews focused the real H2 reviews-title; its visible heading cleared the nav. Manual upward scrolling kept that H2 focused. The temporary tab was closed before performance measurement. This spot check supplements the retained all-locale/all-width automated and visual evidence.

No observed layout/focus defect blocks T070 in this acceptance matrix. CI, release and production media readiness remain separate.
