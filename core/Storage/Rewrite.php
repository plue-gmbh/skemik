<?php

declare(strict_types=1);

namespace Storage;

use Domain\Props;
use Domain\Slug;
use Profile\Profile;
use Support\Json;

/* Derives every record's `sort_keys` and `words` again by the running code, the
   word table following through its triggers: after a restore, and after an
   update, since a snapshot or a database may be older than the release that now
   derives them, and a version of the derivation to bump is a version somebody
   forgets. Every record in the file is read, because the file is what was put
   back, and only one whose derivation changed is written, since the trigger
   replaces every word row of a written one, and a release that changes nothing
   would otherwise replace the whole word table while every address waits.
   Nothing else moves, neither a version nor `updated_at`. The ids are read
   first and each row after, so the cursor never walks a table being written. */
final readonly class Rewrite
{
  public function __construct(
    private \PDO $db,
    private Profile $profile,
    private Search $search,
    private SortKeys $sortKeys = new SortKeys(),
    private Json $json = new Json(),
  ) {}

  public function all(): void
  {
    $ids = $this->db->query('select id from entity')->fetchAll(\PDO::FETCH_COLUMN);
    $read = $this->db->prepare('select type, props, slug, sort_keys, words from entity where id = :id');
    $write = $this->db->prepare('update entity set sort_keys = :sort_keys, words = :words where id = :id');
    $this->db->beginTransaction();
    try {
      foreach ($ids as $id) {
        $read->execute(['id' => $id]);
        $row = $read->fetch();
        $type = $this->profile->byType((string) $row['type']);
        $props = new Props($this->json->decode((string) $row['props']));
        $derived = ['sort_keys' => $this->sortKeys->of($type, $props),
          'words' => $this->search->words($type, $props, new Slug((string) $row['slug']))];
        $derived === ['sort_keys' => $row['sort_keys'], 'words' => $row['words']]
          || $write->execute(['id' => $id, ...$derived]);
      }
      $this->db->commit();
    } catch (\Throwable $failure) {
      $this->db->rollBack();
      throw $failure;
    }
  }
}
