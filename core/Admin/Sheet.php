<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Domain\Entity;
use Domain\Locale;
use Domain\Status;
use Profile\Profile;
use Profile\Reach;
use Profile\Seed\Terms;
use Storage\CollectionStore;
use Support\Clock;
use Support\Words;

/* The table's rows, for the collection screen and for the answer to a save
   alike, so a row the island wrote comes back drawn by the same hand. */
final readonly class Sheet
{
  public const OWN_STATUS = ['payment_status', 'order_status', 'reservation_status'];
  private const READ = 'date_read';

  public function __construct(
    private Rows $rows,
    private Cells $cells,
    private CollectionStore $collections,
    private Terms $terms,
    private Profile $profile,
    private Clock $clock,
    private Moments $moments,
    private Words $words,
    private Label $label = new Label(),
  ) {}

  /**
   * @param list<Column> $columns
   * @param list<Entity> $records
   * @return list<SheetRow>
   */
  public function rows(Account $account, array $columns, array $records): array
  {
    $held = $this->collections->all();
    $stating = $this->rows->stating($account, $columns, $held);

    return array_map(function (Entity $entity) use ($account, $columns, $held, $stating): SheetRow {
      $chips = $this->rows->chips($account, $entity, $held);
      $stated = $stating($entity);
      $cells = [];
      foreach ($columns as $column) {
        $key = $column->field->key;
        $cells[$key] = $this->cells->html($stated, $column->field, $chips[$key] ?? [], !$column->editable);
      }
      $label = $this->label->of($entity, $this->profile->byType($entity->type));

      return new SheetRow($entity, $label, $this->state($entity), $cells, $this->own($entity), $chips,
        changed: $this->moments->day($entity->updatedAt), unread: $this->unread($entity));
    }, $records);
  }

  /* On the types that cannot publish, the type's own status property says how the record stands to a
     customer, beside the state the organisation keeps among itself. */
  private function own(Entity $entity): ?string
  {
    $type = $this->profile->byType($entity->type);
    if ($type->reach === Reach::Public) {
      return null;
    }
    $key = array_find(self::OWN_STATUS, fn (string $key): bool => $entity->props->string($key) !== null);
    $field = $key === null ? null : $type->field($key);

    return $field === null ? null
      : $this->terms->word($field, (string) $entity->props->string($field->key), new Locale($this->words->language));
  }

  /* What arrives and is read says so by a date, so the one nobody has read yet is marked on its chip. */
  private function unread(Entity $entity): bool
  {
    return $this->profile->byType($entity->type)->field(self::READ) !== null
      && $entity->props->string(self::READ) === null;
  }

  public function state(Entity $entity): string
  {
    $now = $this->clock->now();
    if ($entity->status->isPublicAt($now, $entity->publishFrom, $entity->publishTo)) {
      return Status::Published->value;
    }
    if ($entity->status !== Status::Published) {
      return $entity->status->value;
    }
    $ended = $entity->publishTo !== null && $entity->publishTo <= $now->format(Clock::STORED);

    return $ended ? 'ended' : 'scheduled';
  }
}
