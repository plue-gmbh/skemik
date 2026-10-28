<?php

declare(strict_types=1);

namespace Domain;

/* Why a record does not fit, closed, so a screen words it in the colleague's
   language and nothing matches a reason by its text. The value is the feed's
   English, its names filled from the violation's values (`Violation::said`),
   so a key's script reads what it always read. What only a developer or a
   key's script reads, the profile against the vocabulary, a payload's shape,
   an exception's message, is `Stated` and stays as it was said, as does a
   sentence its screen already worded (`Admin\SpineEdits`). */
enum Reason: string
{
  case Required = 'required';
  case NotDialled = 'not a number to dial, written like +4930123123';
  case KeyNotDialled = '{key} is not a number to dial, written like +4930123123';
  case NotLocalising = 'not a map of locales on a type that localises';
  case NotALocale = 'not a locale holding words';
  case NotTranslatable = '{key} is not translatable';
  case NotAList = 'not a list';
  case NotAListOfObjects = 'not a list of value objects';
  case NotAChoice = 'not one of this field\'s values';
  case KeyNotAChoice = '{key} is not one of its values';
  case NotAWordOrRecord = 'not a word or a record';
  case KeyNotAWordOrRecord = '{key} is neither a word nor a record';
  case Schemed = 'an email address is a bare address, without mailto:';
  case KeySchemed = '{key} is a bare address, without mailto:';
  case NotAKind = 'not a {kind}';
  case KeyNotAKind = '{key} is not a {kind}';
  case NotWhole = 'not a whole number';
  case KeyNotWhole = '{key} is not a whole number';
  case NotAnObject = 'not a value object';
  case Inline = '{object} is written inline, a row of {keys}, never a record named by @id';
  case Unborne = '{object} needs {key}';
  case NoSuchKey = '{object} has no key {key}';
  case NotCarried = '{field} carries no {key}';
  case NoSuchEdge = '{type} has no such edge';
  case NotMarked = 'a {kind} holds this, and the record is not marked as one';
  case NoSuchKind = '{kind} is no kind of {type} schema.org knows';
  case EdgeInProps = 'an edge, held as a relation and never in props';
  case OneTarget = 'holds one target, not {count}';
  case QualifierNotWords = '{key} is not a map of locales holding words';
  case WrongTarget = 'points at {target} and takes {targets}';
  case HeldInHouse = 'another {type} in this house holds it';
  case HeldInHouseTrashed = 'another {type} in this house holds it, in the trash';
  case HeldInScope = 'another {type} in this {scope} holds it';
  case HeldInScopeTrashed = 'another {type} in this {scope} holds it, in the trash';
  case HeldInGroup = 'another {type} in this group holds it';
  case HeldInGroupTrashed = 'another {type} in this group holds it, in the trash';
  case NotShown = 'not {kinds}/ where the file is shown as one';
  case KeyNotNamed = 'a key {type} does not name';
  case EdgeNotNamed = 'an edge {type} does not name';
  case Stale = 'derived by an older version';
  case Stated = '{sentence}';

  /* The words' key under `violation.`, `NotDialled` as `not_dialled`. */
  public function key(): string
  {
    return strtolower((string) preg_replace('/(?<!^)(?=[A-Z])/', '_', $this->name));
  }
}
