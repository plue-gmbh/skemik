<?php

declare(strict_types=1);

namespace Auth;

/* The whole authorisation matrix, in one readable place. The role answers
   what kind of act and never in which type — that is the account's two
   `TypeScope`s, and keeping the questions apart stops the matrix growing a second
   dimension. `exportHoldings` belongs to every role because a dump is the reader's
   own screen serialised (`Feed\Export`); the lever is the read scope. */
enum Role: string
{
  case Owner = 'owner';
  case Editor = 'editor';
  case Viewer = 'viewer';

  private const EDITOR = [Capability::ReadContent, Capability::WriteContent,
    Capability::ManageFields, Capability::ExportHoldings];
  private const VIEWER = [Capability::ReadContent, Capability::ExportHoldings];

  public function can(Capability $capability): bool
  {
    return match ($this) {
      self::Owner => true,
      self::Editor => in_array($capability, self::EDITOR, true),
      self::Viewer => in_array($capability, self::VIEWER, true),
    };
  }
}
