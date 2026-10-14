<?php

declare(strict_types=1);

/* The drawn tool glyphs: one hand, a 16 grid, a 1.5 stroke, which the
   collections' glyphs share (`partials/glyphs`). A character from a font sits at
   another height on every machine. */

if (!isset($template)) {
  return;
}
$up = '<path d="M8 13V3M4.5 6.5 8 3l3.5 3.5"/>';
$down = '<path d="M8 3v10M4.5 9.5 8 13l3.5-3.5"/>';
$paths = [
  'menu' => '<path d="M2.75 4.4h10.5M2.75 8h10.5M2.75 11.6h10.5"/>',
  'list' => '<circle cx="3.1" cy="4.25" r=".95" fill="currentColor" stroke="none"/>'
    . '<circle cx="3.1" cy="8" r=".95" fill="currentColor" stroke="none"/>'
    . '<circle cx="3.1" cy="11.75" r=".95" fill="currentColor" stroke="none"/>'
    . '<path d="M6.6 4.25h6.6M6.6 8h6.6M6.6 11.75h6.6"/>',
  'table' => '<rect x="2.25" y="2.75" width="11.5" height="10.5" rx="2"/><path d="M2.25 6.4h11.5M7.6 6.4v6.85"/>',
  'cards' => '<path d="M5.4 3.25h5.85A1.9 1.9 0 0 1 13.25 5.15v5.6"/>'
    . '<rect x="2.5" y="5.6" width="8.4" height="7.65" rx="1.9"/>',
  'grid' => '<rect x="2.4" y="2.4" width="5" height="5" rx="1.5"/>'
    . '<rect x="8.6" y="2.4" width="5" height="5" rx="1.5"/>'
    . '<rect x="2.4" y="8.6" width="5" height="5" rx="1.5"/>'
    . '<rect x="8.6" y="8.6" width="5" height="5" rx="1.5"/>',
  'timeline' => '<path d="M4.6 2.75v10.5"/>'
    . '<circle cx="4.6" cy="5.15" r="1.7"/>'
    . '<circle cx="4.6" cy="11.4" r="1.7"/>'
    . '<path d="M8.1 5.15h5.3M8.1 11.4h3.6"/>',
  'descending' => $down,
  'down' => $down,
  'sort' => '<path d="M5.25 13V3M2.75 5.5 5.25 3l2.5 2.5M10.75 3v10M8.25 10.5l2.5 2.5 2.5-2.5"/>',
  'ascending' => $up,
  'up' => $up,
  'left' => '<path d="M13 8H3M6.5 4.5 3 8l3.5 3.5"/>',
  'right' => '<path d="M3 8h10M9.5 4.5 13 8l-3.5 3.5"/>',
  'close' => '<path d="m4 4 8 8M12 4l-8 8"/>',
  'check' => '<path d="m3 8.5 3 3 7-7"/>',
  'link' => '<path d="m6.5 9.5 3-3M7.25 4.5l.9-.9a2.6 2.6 0 0 1 3.7 3.7l-.9.9M8.75 11.5l-.9.9a2.6 2.6 0 0'
    . ' 1-3.7-3.7l.9-.9"/>',
  'open' => '<path d="M9.5 2.75h3.75V6.5M13.25 2.75 7.6 8.4M11.5 9.6v2.4a1.25 1.25 0 0 1-1.25 1.25h-6.25a1.25'
    . ' 1.25 0 0 1-1.25-1.25v-6.25a1.25 1.25 0 0 1 1.25-1.25h2.4"/>',
  'back' => '<path d="M9.75 3.5 5.25 8l4.5 4.5"/>',
  'previous' => '<path d="M3.5 9.75 8 5.25l4.5 4.5"/>',
  'next' => '<path d="M3.5 6.25 8 10.75l4.5-4.5"/>',
  'trash' => '<path d="M2.75 4.25h10.5M6.25 4.25V2.75h3.5v1.5M4.1 4.25l.65 8.2a1.2 1.2 0 0 0 1.2 1.1h4.1a1.2 1.2'
    . ' 0 0 0 1.2-1.1l.65-8.2"/>',
  'read' => '<path d="M1.75 8s2.3-4.25 6.25-4.25S14.25 8 14.25 8 11.95 12.25 8 12.25 1.75 8 1.75 8Z"/>'
    . '<circle cx="8" cy="8" r="1.9"/>',
  'edit' => '<path d="M10.4 2.9 13.1 5.6 5.6 13.1H2.9v-2.7L10.4 2.9Z"/>',
  'heading' => '<path d="M4 3v10M12 3v10M4 8h8"/>',
  'bold' => '<path d="M5 3v10M5 3h4.25a2.5 2.5 0 0 1 0 5H5M5 8h4.75a2.5 2.5 0 0 1 0 5H5"/>',
  'italic' => '<path d="M6.75 3h5M4.25 13h5M9.5 3l-3 10"/>',
  'quote' => '<path d="M3 3.5v9M6.5 5.25h6.75M6.5 8h6.75M6.5 10.75h4.5"/>',
  'code' => '<path d="m5.25 5-3 3 3 3M10.75 5l3 3-3 3"/>',
  'plus' => '<path d="M8 3v10M3 8h10"/>',
  'restore' => '<path d="M2.75 2.75v3.5h3.5M3.05 6.25A5.25 5.25 0 1 1 3.5 10.6"/>',
  'upload' => '<path d="M8 9.5V2.6M5.3 5.3 8 2.6l2.7 2.7M2.9 10.6v1.5a1.3 1.3 0 0 0 1.3 1.3h7.6a1.3 1.3 0 0 0'
    . ' 1.3-1.3v-1.5"/>',
  'export' => '<path d="M8 2.6v6.9M5.3 6.8 8 9.5l2.7-2.7M2.9 10.6v1.5a1.3 1.3 0 0 0 1.3 1.3h7.6a1.3 1.3 0 0 0'
    . ' 1.3-1.3v-1.5"/>',
  'copy' => '<rect x="5.75" y="5.75" width="7.5" height="7.5" rx="1.6"/>'
    . '<path d="M10.25 5.75V4.1a1.35 1.35 0 0 0-1.35-1.35H4.1A1.35 1.35 0 0 0 2.75 4.1v4.8a1.35 1.35 0 0 0'
    . ' 1.35 1.35h1.65"/>',
  'unlocked' => '<rect x="3.25" y="7.25" width="9.5" height="6.5" rx="1.6"/>'
    . '<path d="M5.6 7.25V5.1a2.4 2.4 0 0 1 4.65-.85"/>',
];
?>
<svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
  stroke-linejoin="round" aria-hidden="true"><?= $paths[$name] ?></svg>
