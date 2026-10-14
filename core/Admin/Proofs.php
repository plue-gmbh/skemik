<?php

declare(strict_types=1);

namespace Admin;

use Support\Fetch;
use Support\Fetched;
use Support\FetchFailed;
use Support\Words;

/* What the first run asks of its own address from outside, since what a
   stranger gets is the proof: a folder inside the domain root is taken only
   refused, `config.php` only once its sentinel stays unseen, and a `PATCH` is
   asked because every save is one, so a front that refuses the method is learnt
   here and not at the house's first save. Let through, it answers Skemik's own
   404, as any address nobody routes does before the first run; a front's
   refusal is its own 403, 405 or 501. */
final readonly class Proofs
{
  public function __construct(private Fetch $fetch, private Words $words) {}

  /* The host's line on the method, with what came back where it is not met. */
  public function patched(string $origin): Requirement
  {
    try {
      $found = (string) $this->fetch->patch($origin . '/install')->status;
    } catch (FetchFailed $failure) {
      $found = $failure->getMessage();
    }

    return new Requirement($this->words->get($found === '404' ? 'install.patch' : 'install.patch_unmet',
      ['found' => $found]), $found === '404');
  }

  /* Only a refusal, 403 or 404, proves the folder is not served. */
  public function refused(string $url): void
  {
    $status = $this->fetched($url)->status;
    if ($status !== 403 && $status !== 404) {
      throw InstallRefused::because($this->words->get($status === 200 ? 'install.refused.served'
        : 'install.refused.unproven', ['status' => (string) $status]));
    }
  }

  /* `config.php` served as text hands out the sentinel written into it. */
  public function hidden(string $url, string $sentinel): void
  {
    if (str_contains($this->fetched($url)->body, $sentinel)) {
      throw InstallRefused::because($this->words->get('install.refused.config_served'));
    }
  }

  private function fetched(string $url): Fetched
  {
    try {
      return $this->fetch->get($url);
    } catch (FetchFailed $failure) {
      throw InstallRefused::because($this->words->get('install.refused.unreachable',
        ['reason' => $failure->getMessage()]));
    }
  }
}
