<?php

declare(strict_types=1);

namespace Profile;

/* What a value is — never who may write it (that is WrittenBy).
   Fifteen kinds and no sixteenth without a discussion: a new kind is a new
   editor, a new validator, a new serialiser and a new cell renderer.
   The fifteenth had it: a time of day, because an opening hour
   that reads *eight* is a broken fact in every consumer, and a check beside the
   kinds would have been a kind nobody could declare on a field. The fourteenth
   had that discussion too. A kind meaning "an object" would
   have been one editor per value object behind one enum value; Objects is
   one, because it renders no value object in particular — it reads the key set
   from `Seed\ValueObjects` and draws a line-item list whose columns are the
   other kinds. A single-valued value object needs no kind at all: it
   resolves into fields of those. */
enum FieldKind: string
{
  case Text = 'text';
  case LongText = 'longtext';
  case Markdown = 'markdown';
  case Date = 'date';              // YYYY-MM-DD, YYYY-MM or YYYY, no zone
  case DateTime = 'date_time';     // 2026-08-19T14:03:00Z, UTC only
  case Duration = 'duration';      // ISO 8601, PT4H / P1M
  case Time = 'time';              // 08:00 or 08:00:00, of the place and never of a zone
  case Number = 'number';
  case Boolean = 'boolean';
  case Select = 'select';          // closed option list, shipped with the profile
  case Link = 'link';
  case Image = 'image';
  case Ref = 'ref';
  case Refs = 'refs';
  case Objects = 'objects';        // a repeatable value object; `ref` names which

  /* An image field is a reference too: the target is the asset, and what lands
     in the JSON-LD is the asset's own @type, not the field's. */
  public function isReference(): bool
  {
    return $this === self::Ref || $this === self::Refs || $this === self::Image;
  }

  /* The schema.org data type a value of the kind is, which `SchemaOrg::holds`
     narrows for a count and a fixed list: a Markdown field leaves as its text and
     a select as its token, so both are text. */
  public function datatype(): string
  {
    return match ($this) {
      self::Date => 'Date',
      self::DateTime => 'DateTime',
      self::Duration => 'Duration',
      self::Time => 'Time',
      self::Number => 'Number',
      self::Boolean => 'Boolean',
      self::Link => 'URL',
      default => 'Text',
    };
  }

  /* What somebody types into a search box: the words, every short text beside
     them, since an order number and a town are looked for too, a link, since
     a customer is looked for by an email address, and rows by the value each
     holds, since a register entry is looked for by its number. Never a number
     or a date, which a filter finds. */
  public function isSearched(): bool
  {
    return $this === self::Text || $this === self::Link || $this === self::Objects || $this->isWords();
  }

  /* Longtext and markdown are always words a reader reads; a Text field
     is words only by its field's mark, because `name` is and `sku` is not. */
  public function isWords(): bool
  {
    return $this === self::LongText || $this === self::Markdown;
  }
}
