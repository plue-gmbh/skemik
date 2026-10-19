<?php

declare(strict_types=1);

namespace Storage;

/* The five views. A collection opens on the table, the one view that shows and
   edits every field it holds, as the spreadsheet a house comes from did, and
   switching is the viewer's (`Admin\CollectionController`): a view chosen per
   type guessed for a house that had not yet said what it reads by. */
enum CollectionView: string
{
  case List = 'list';
  case Table = 'table';
  case Cards = 'cards';
  case Grid = 'grid';
  case Timeline = 'timeline';
}
