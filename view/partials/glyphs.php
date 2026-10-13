<?php

declare(strict_types=1);

/* The drawn glyphs of the thirty types, of a file's kind, of a record gone, of
   one still to make, of a key, of a finding and of an action, in the tool icons' hand
   (`partials/icon`): a 16 grid, a 1.5 stroke. Drawn and not typed, since a
   character comes from whatever symbol font a device has and sits at another
   size and height on each. One hidden sprite per page, so a glyph is its name
   wherever it travels, in a page, in an island's JSON or in a script, and
   `partials/glyph` draws it. */

if (!isset($template)) {
  return;
}
$frame = '<rect x="2.25" y="2.75" width="11.5" height="10.5" rx="1.75"/>';
$picture = $frame . '<circle cx="5.75" cy="6.25" r="1.1"/><path d="M2.75 11.75 6.25 8.5l2.5 2.25 1.75-1.5 3 2.75"/>';
$shapes = [
  'Action' => '<path d="M9 1.75 3.75 9h4l-1 5.25L12.25 7h-4L9 1.75Z"/>',
  'Article' => '<rect x="3" y="2.25" width="10" height="11.5" rx="1.5"/><path d="M5.5 5.5h5M5.5 8h5M5.5 10.5h3"/>',
  'Book' => '<path d="M3 12.75v-9.5A1.25 1.25 0 0 1 4.25 2H13v9.5H4.25A1.25 1.25 0 0 0 3 12.75a1.25 1.25 0 0 0'
    . ' 1.25 1.25H13"/>',
  'Brand' => '<path d="M4.5 2.75h7l2.5 3.5L8 13.5 2 6.25l2.5-3.5ZM2 6.25h12M6.25 2.75 5 6.25l3 7.25 3-7.25-1.25-3.5"/>',
  'Comment' => '<path d="M3.75 3h8.5A1.75 1.75 0 0 1 14 4.75v5.5A1.75 1.75 0 0 1 12.25 12H7l-3 2.25V12h-.25A1.75'
    . ' 1.75 0 0 1 2 10.25v-5.5A1.75 1.75 0 0 1 3.75 3Z"/>',
  'Course' => '<path d="M1.75 6.25 8 3.25l6.25 3L8 9.25 1.75 6.25ZM4.5 7.9V11c0 .9 1.6 1.75 3.5 1.75s3.5-.85'
    . ' 3.5-1.75V7.9M14.25 6.25v3.5"/>',
  'Dataset' => '<ellipse cx="8" cy="4" rx="5" ry="1.75"/><path d="M3 4v8c0 .97 2.24 1.75 5 1.75s5-.78 5-1.75V4M3'
    . ' 8c0 .97 2.24 1.75 5 1.75S13 8.97 13 8"/>',
  'DefinedTerm' => '<path d="M8 4.5C6.5 3.3 4.5 3 2.25 3.25v9.5c2.25-.25 4.25.05 5.75 1.25 1.5-1.2 3.5-1.5'
    . ' 5.75-1.25v-9.5C11.5 3 9.5 3.3 8 4.5ZM8 4.5V14"/>',
  'Event' => '<rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.75"/><path d="M2.25 6.75h11.5M5.5 1.75v3M10.5'
    . ' 1.75v3"/>',
  'HowTo' => '<path d="M2.5 13.25H6V9.85h3.5V6.45H13V3"/>',
  'Invoice' => '<path d="M4 2h8v12l-1.35-1-1.3 1L8 13l-1.35 1-1.3-1L4 14V2ZM6 5.5h4M6 8h4M6 10.5h2.5"/>',
  'ItemList' => '<path d="m2.75 4.25 1 1 1.75-1.75M2.75 10l1 1 1.75-1.75M8 4.75h5.25M8 10.5h5.25"/>',
  'JobPosting' => '<rect x="2" y="5" width="12" height="8.5" rx="1.75"/><path d="M5.75 5V3.75a1 1 0 0 1 1-1h2.5a1'
    . ' 1 0 0 1 1 1V5M2 8.75h12"/>',
  'LocalBusiness' => '<path d="M2.75 7.25 8 2.75l5.25 4.5v6H2.75v-6ZM6.5 13.25v-3.5h3v3.5"/>',
  'MediaObject' => $picture,
  'MemberProgram' => '<rect x="1.75" y="3.5" width="12.5" height="9" rx="1.75"/><circle cx="5.5" cy="7.25" r="1.25"/>'
    . '<path d="M3.75 10.5c.4-1 1-1.5 1.75-1.5s1.35.5 1.75 1.5M9 6.75h3M9 9.25h2"/>',
  'Message' => '<rect x="2" y="3.5" width="12" height="9" rx="1.75"/><path d="M2.5 4.5 8 8.75l5.5-4.25"/>',
  'Offer' => '<path d="M2.75 2.75h5l5.5 5.5-5 5-5.5-5.5v-5ZM5.5 5.5h.01"/>',
  'Order' => '<path d="M3.5 5.5h9l-.75 8h-7.5l-.75-8ZM6 5.5v-1a2 2 0 0 1 4 0v1"/>',
  'Organization' => '<rect x="3" y="2.5" width="10" height="11" rx="1.5"/><path d="M6 5.75h.01M10 5.75h.01M6'
    . ' 8.5h.01M10 8.5h.01M7 13.5v-2h2v2"/>',
  'Person' => '<circle cx="8" cy="5.5" r="2.5"/><path d="M3 13.5c.6-2.6 2.6-4 5-4s4.4 1.4 5 4"/>',
  'Place' => '<path d="M8 14s4.5-4.2 4.5-7.75a4.5 4.5 0 0 0-9 0C3.5 9.8 8 14 8 14Z"/>'
    . '<circle cx="8" cy="6.25" r="1.6"/>',
  'Product' => '<path d="M8 2l5.5 2.75v6.5L8 14l-5.5-2.75v-6.5L8 2ZM2.5 4.75 8 7.5l5.5-2.75M8 7.5V14"/>',
  'Question' => '<circle cx="8" cy="8" r="5.75"/><path d="M6.25 6.25a1.75 1.75 0 1 1 2.5 1.6c-.5.25-.75.65-.75'
    . ' 1.15v.5M8 11.5h.01"/>',
  'Reservation' => '<path d="M2.25 4.5a1.25 1.25 0 0 1 1.25-1.25h9a1.25 1.25 0 0 1 1.25 1.25v2a1.5 1.5 0 0 0 0'
    . ' 3v2a1.25 1.25 0 0 1-1.25 1.25h-9a1.25 1.25 0 0 1-1.25-1.25v-2a1.5 1.5 0 0 0 0-3v-2ZM9.75 3.25v9.5"/>',
  'Review' => '<path d="m8 2.5 1.7 3.45 3.8.55-2.75 2.68.65 3.78L8 11.18l-3.4 1.78.65-3.78L2.5 6.5l3.8-.55L8'
    . ' 2.5Z"/>',
  'Service' => '<path d="M2.5 12h11M3.5 12a4.5 4.5 0 0 1 9 0M8 7.5V6M6.75 6h2.5"/>',
  'SoftwareApplication' => $frame . '<path d="M6.25 6.25 4.5 8l1.75 1.75M9.75 6.25 11.5 8l-1.75 1.75"/>',
  'WebPage' => $frame . '<path d="M2.25 5.75h11.5"/>',
  'WebSite' => '<circle cx="8" cy="8" r="5.75"/><path d="M2.25 8h11.5M8 2.25c1.7 1.6 2.5 3.5 2.5 5.75S9.7 12.15'
    . ' 8 13.75C6.3 12.15 5.5 10.25 5.5 8S6.3 3.85 8 2.25Z"/>',
  'image' => $picture,
  'video' => $frame . '<path d="M6.75 5.75v4.5L10.5 8 6.75 5.75Z"/>',
  'audio' => '<path d="M6 12V3.75l7-1.5v8.25"/><circle cx="4.5" cy="12" r="1.5"/>'
    . '<circle cx="11.5" cy="10.5" r="1.5"/>',
  'file' => '<path d="M9 2H4.75A1.25 1.25 0 0 0 3.5 3.25v9.5A1.25 1.25 0 0 0 4.75 14h6.5a1.25 1.25 0 0 0'
    . ' 1.25-1.25V5.5L9 2Zm0 0v3.5h3.5"/>',
  'new' => '<path d="M8 3v10M3 8h10"/>',
  'kind-text' => '<path d="M3.5 3.5h9M8 3.5v9"/>',
  'kind-longtext' => '<path d="M7 13V3h5.5M10.25 3v10M7 3a2.5 2.5 0 0 0 0 5"/>',
  'kind-markdown' => '<path d="M2.25 11.5v-7l2.75 3.25 2.75-3.25v7M11.75 4.5v7M9.75 9.25l2 2.25 2-2.25"/>',
  'kind-number' => '<path d="M6.25 2.75 5 13.25M11 2.75l-1.25 10.5M3 6h10.25M2.75 10h10.25"/>',
  'kind-boolean' => '<rect x="1.75" y="4.5" width="12.5" height="7" rx="3.5"/><circle cx="10.75" cy="8" r="1.75"/>',
  'kind-date' => '<rect x="2.25" y="3.25" width="11.5" height="10.5" rx="1.75"/><path d="M2.25 6.75h11.5M5.5'
    . ' 1.75v3M10.5 1.75v3"/>',
  'kind-date_time' => '<path d="M13.75 7.25v-2.25a1.75 1.75 0 0 0-1.75-1.75H4A1.75 1.75 0 0 0 2.25 5v7A1.75 1.75'
    . ' 0 0 0 4 13.75h3.25M2.25 6.75h5.5M5.5 1.75v3M10.5 1.75v2"/><circle cx="11.5" cy="11.5" r="3"/><path'
    . ' d="M11.5 10.25v1.25l.85.6"/>',
  'kind-time' => '<circle cx="8" cy="8" r="5.75"/><path d="M8 4.75V8l2.25 1.5"/>',
  'kind-duration' => '<path d="M4 2.5h8M4 13.5h8M5.25 2.5c0 3 5.5 3.6 5.5 5.5s-5.5 2.5-5.5 5.5M10.75 2.5c0 3-5.5'
    . ' 3.6-5.5 5.5s5.5 2.5 5.5 5.5"/>',
  'kind-select' => '<rect x="1.75" y="4" width="12.5" height="8" rx="2"/><path d="m8.5 7 1.75 1.75L12 7M4.25'
    . ' 8h1.5"/>',
  'kind-image' => $picture,
  'kind-link' => '<path d="m6.5 9.5 3-3M7.25 4.5l.9-.9a2.6 2.6 0 0 1 3.7 3.7l-.9.9M8.75 11.5l-.9.9a2.6 2.6 0 0'
    . ' 1-3.7-3.7l.9-.9"/>',
  'kind-ref' => '<path d="M2.75 8h10M9 4.25 12.75 8 9 11.75"/>',
  'kind-refs' => '<path d="M2.25 5.25h9M8.5 2.5l2.75 2.75L8.5 8M4.75 10.75h9M11 8l2.75 2.75L11 13.5"/>',
  'kind-objects' => '<rect x="2.5" y="2.75" width="11" height="4.25" rx="1.25"/><rect x="2.5" y="9" width="11"'
    . ' height="4.25" rx="1.25"/>',
  'alert' => '<path d="M8 2.75 14 13.25H2L8 2.75ZM8 6.75v3M8 11.5h.01"/>',
  'key' => '<circle cx="5.25" cy="10.75" r="2.75"/><path d="M7.25 8.75 13 3M10.75 5.25l1.75 1.75M12.25 3.75l1.5'
    . ' 1.5"/>',
  'gone' => '<path d="M2.5 8h7" stroke-dasharray="1.5 2.25"/><path d="M9.75 4.75 13 8l-3.25 3.25"/>',
  'activity' => '<path d="M1.75 8h2.5l1.75-4.5 4 9 1.75-4.5h2.5"/>',
];
?>
<svg class="glyph-sprite" aria-hidden="true"><?php foreach ($shapes as $name => $shape): ?><symbol
  id="glyph-<?= $name ?>" viewBox="0 0 16 16"><?= $shape ?></symbol><?php endforeach ?></svg>
