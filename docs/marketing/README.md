# Marketing materials

Draft copy and images for announcing Surf4Miles, kept out of the app itself.

## Contents

Each platform's copy exists as an editable `.md` source plus a ready-to-use output, since **Facebook, LinkedIn and X don't render Markdown at all** - pasted `**bold**`/`` `code` `` shows up as literal asterisks and backticks, not formatting:

- `facebook-post.md` / `facebook-post.txt` — copy-paste the `.txt` directly into the post box; the bare URL auto-links.
- `linkedin-post.md` / `linkedin-post.txt` — same. The `- ` bullet lines read fine as plain text on LinkedIn as-is.
- `twitter-x-post.md` / `twitter-x-post.txt` — same. Currently 261 characters as X counts it (URLs count as a flat 23 chars regardless of real length) against the 280 limit - only 19 characters of headroom if you edit the wording.
- `blog-post.md` / `blog-post.html` — your blog wants HTML, so `blog-post.html` is a ready-to-paste fragment (`<h1>`/`<h2>`/`<p>`/`<ul>`/`<img>`, no `<html>`/`<body>` wrapper) for a "paste HTML" or source-code editor view. It references `screenshot-onboarding.png` and `screenshot-report.png` by filename only - upload them to your blog's own media library first and update the two `<img src>` paths to match wherever it puts them.

If you edit the copy, edit the `.md` first and re-derive the `.txt`/`.html` from it, so they don't drift apart.

- `screenshot-onboarding.png` — the empty upload screen (no trip data).
- `screenshot-report.png` — a report rendered from **synthetic demo data**, cropped to end after the "All-Time Summary" stat cards. Deliberately excludes the "Recent Trips Summary" and "Recent Trips Detail" table, so no real (or even realistic-looking, row-by-row) trip data appears in anything public.
- `social-preview.png` — the 1200×630 Open Graph/Twitter Card image already wired into `public/index.html`'s `<head>` (served from `public/assets/img/social-preview.png`).
- `social-preview-template.html` — the source for `social-preview.png`, in case it ever needs regenerating (e.g. a copy update). Render it with a screenshot tool at exactly 1200×630.

The live URL (https://surf4miles.z-add.co.uk/) is already filled in throughout - both in these drafts and in `og:url`/`og:image` in `public/index.html`'s `<head>`.

## Before posting anything

1. Test the actual OG preview - e.g. paste the live URL into Facebook's [Sharing Debugger](https://developers.facebook.com/tools/debug/) or LinkedIn's [Post Inspector](https://www.linkedin.com/post-inspector/) to confirm the card renders as expected (these tools fetch and cache the preview, so re-check here after any future copy/image change).
2. Double-check the GitHub repo's visibility/README are in a state you're happy for people to land on.
