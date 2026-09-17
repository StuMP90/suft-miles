# Marketing materials

Draft copy and images for announcing Surf4Miles, kept out of the app itself.

## Contents

- `facebook-post.md`, `linkedin-post.md`, `twitter-x-post.md`, `blog-post.md` — platform-specific draft copy. Each names which image to use.
- `screenshot-onboarding.png` — the empty upload screen (no trip data).
- `screenshot-report.png` — a report rendered from **synthetic demo data**, cropped to end after the "All-Time Summary" stat cards. Deliberately excludes the "Recent Trips Summary" and "Recent Trips Detail" table, so no real (or even realistic-looking, row-by-row) trip data appears in anything public.
- `social-preview.png` — the 1200×630 Open Graph/Twitter Card image already wired into `public/index.html`'s `<head>` (served from `public/assets/img/social-preview.png`).
- `social-preview-template.html` — the source for `social-preview.png`, in case it ever needs regenerating (e.g. a copy update). Render it with a screenshot tool at exactly 1200×630.

The live URL (https://surf4miles.z-add.co.uk/) is already filled in throughout - both in these drafts and in `og:url`/`og:image` in `public/index.html`'s `<head>`.

## Before posting anything

1. Test the actual OG preview - e.g. paste the live URL into Facebook's [Sharing Debugger](https://developers.facebook.com/tools/debug/) or LinkedIn's [Post Inspector](https://www.linkedin.com/post-inspector/) to confirm the card renders as expected (these tools fetch and cache the preview, so re-check here after any future copy/image change).
2. Double-check the GitHub repo's visibility/README are in a state you're happy for people to land on.
