<?php

declare(strict_types=1);

namespace Profile\Seed;

use Profile\ClosedList;
use Profile\Erasure;
use Profile\FieldDefinition;
use Profile\LocaleMode;
use Profile\Reach;
use Profile\TypeDefinition;

/* What other people send you. All three are somebody else's words, held
   under their author and their date, which is why none of them forks per
   locale. None starts with a description either: the words are the body, and a
   sentence about them beside it is a column nobody fills. */
final readonly class Inbound
{
  public function __construct(
    private Base $base = new Base(),
    private Fields $f = new Fields(),
    private ValueObjects $vo = new ValueObjects(),
  ) {}

  /** @return list<TypeDefinition> */
  public function types(): array
  {
    return [
      /* None draws on the CreativeWork block: a licence, a rating or a citation on
         a comment is a field nobody adds, and the few work fields a comment or a
         message has are its own rows. */
      new TypeDefinition('Review', Erasure::Purge, LocaleMode::None, Reach::Public,
        $this->review(), $this->base->thing()),
      new TypeDefinition('Comment', Erasure::Purge, LocaleMode::None, Reach::Public,
        $this->comment(), $this->base->thing()),
      new TypeDefinition('Message', Erasure::Purge, LocaleMode::None, Reach::House,
        $this->message(), $this->base->thing()),
    ];
  }

  /** @return list<FieldDefinition> */
  private function review(): array
  {
    $f = $this->f;
    $vo = $this->vo;

    return [
      $f->text('name', 'Name')->words(),               // a review is not titled
      $f->longText('review_body', 'Review')->starter(),
      ...$vo->flatten('Rating', 'reviewRating', 'Rating', $f, starter: true),
      $f->term('review_aspect', 'Aspect reviewed'),
      $f->longText('positive_notes', 'In favour'),
      $f->longText('negative_notes', 'Against'),
      $f->date('date_published', 'Published')->starter(),
      $f->ref('author', 'Author', 'Person', 'Organization')->starter(),
      $f->ref('item_reviewed', 'Item reviewed', '*')->required(),
      $f->ref('publisher', 'Publisher', 'Person', 'Organization'),
      $this->base->description(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function comment(): array
  {
    $f = $this->f;

    return [
      $f->text('name', 'Name')->words(),
      $f->markdown('text', 'Text')->required(),
      $f->dateTime('date_created', 'Created')->starter(),
      $f->dateTime('date_modified', 'Modified'),
      $f->listed('in_language', 'Language', ClosedList::Language)->starter(),
      $f->number('upvote_count', 'Upvotes')->whole(),
      $f->number('downvote_count', 'Downvotes')->whole(),
      $f->term('keywords', 'Keywords')->repeatable(),
      $f->ref('author', 'Author', 'Person')->starter(),
      $f->ref('parent_item', 'In reply to', 'Comment')->starter(),
      /* Not required: a reply names its parent, and the parent names the work. */
      $f->ref('about', 'About', '*')->starter(),
      $f->refs('mentions', 'Mentions', '*'),
      $f->ref('is_part_of', 'Part of', 'CreativeWork'),
      $f->ref('shared_content', 'Shared content', 'CreativeWork'),
      $this->base->description(),
    ];
  }

  /** @return list<FieldDefinition> */
  private function message(): array
  {
    $f = $this->f;

    return [
      $f->text('name', 'Subject')->starter()->words(),
      $f->markdown('text', 'Message')->required(),
      $f->dateTime('date_sent', 'Sent')->required(),
      $f->dateTime('date_received', 'Received'),
      $f->dateTime('date_modified', 'Modified'),
      /* A starter, since its absence is what the list marks as unread. */
      $f->dateTime('date_read', 'Read')->starter(),
      /* The language it was written in, as a German and an English contact form
         each post it; a fact about the words and never a second version of them,
         since the type has no second language. The same list as an event's. */
      $f->listed('in_language', 'Language', ClosedList::Language)->starter(),
      /* Empty for a stranger at the contact form. */
      $f->ref('sender', 'Sender', 'Person', 'Organization')->starter(),
      $f->refs('recipient', 'Recipient', 'Person', 'Organization')->starter(),
      $f->refs('cc_recipient', 'Cc', 'Person'),
      $f->refs('bcc_recipient', 'Bcc', 'Person'),
      $f->ref('about', 'Concerns', '*'),
      $f->refs('message_attachment', 'Attachments', 'MediaObject'),
      $this->base->description(),
    ];
  }
}
