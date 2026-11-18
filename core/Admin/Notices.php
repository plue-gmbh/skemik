<?php

declare(strict_types=1);

namespace Admin;

use Auth\Account;
use Auth\AccountRepository;
use Auth\Authorizer;
use Auth\Capability;
use Auth\Mail;
use Auth\Mailer;
use Auth\MailRefused;
use Auth\RelayFailed;
use Auth\RelayFault;
use Domain\Entity;
use Http\Response;
use Profile\Promises;
use Storage\Collection;
use Storage\CollectionStore;
use Storage\EntityRepository;
use Storage\Oversights;
use Storage\SettingStore;
use Support\Template;
use Support\Words;

/* The mails that tell of what would otherwise wait for somebody to open the home
   screen, there being no daemon. A line of Needs a look that a key's write came
   to stand on goes to every colleague who reads its type as staff, the card's
   own scope, as the line counts there and once an hour (`Arrivals`); a failure
   logged while answering and an update installed or refused go to whoever reads
   those screens. A mail holds a count or a version and links a screen, never a
   record's value or its address, whose slug is cut from its name, so what a
   message says stays in the install. It speaks the install's language, since no
   browser asked, and leaves after an answer. One that fails is thrown, so it is
   a line of the log and never a mail about it. */
final readonly class Notices
{
  private const ARRIVING = ['Message' => Overlooked::Unread, 'Order' => Overlooked::Orders,
    'Reservation' => Overlooked::Reservations];
  /* What the home screen's line narrows its collection by. */
  private const FILTER = ['Order' => 'orderStatus', 'Reservation' => 'reservationStatus'];

  public function __construct(
    private AccountRepository $accounts,
    private EntityRepository $entities,
    private Authorizer $authorizer,
    private CollectionStore $collections,
    private SettingStore $settings,
    private ErrorLog $errors,
    private Oversights $oversights,
    private Arrivals $arrivals,
    private Mailer $mailer,
    private Template $template,
    private string $languages,
    private string $origin,
  ) {}

  /* The answer to a key's write, followed by its line's mail where the record stands on one and the line's hour
     is free. The arrival is noted before the answer, so one followed by no mail keeps its place for other work. */
  public function after(Response $answer, Entity $record): Response
  {
    $line = self::arrival($record);

    return $line !== null && $this->arrivals->arrived($line) ? $answer->after(fn () => $this->told($line)) : $answer;
  }

  /* Any answer nobody waits for, followed by the mail of each line whose hour is out with something come since. */
  public function later(Response $answer): Response
  {
    $due = $answer->takesWork() && array_any(self::ARRIVING, $this->arrivals->due(...));

    return $due ? $answer->after($this->waited(...)) : $answer;
  }

  private function waited(): void
  {
    foreach (self::ARRIVING as $line) {
      $this->arrivals->waited($line) && $this->told($line);
    }
  }

  /* The line as the home screen counts it, and nothing where it stands empty by now. */
  private function told(Overlooked $line): void
  {
    $name = (string) array_search($line, self::ARRIVING, true);
    $holder = array_find($this->collections->all(), fn (Collection $held): bool => basename($held->type) === $name);
    $count = $holder === null ? 0 : $this->standing($name, $holder->type);
    if ($holder === null || $count === 0) {
      return;
    }
    $filter = isset(self::FILTER[$name]) ? '?' . self::FILTER[$name] . '=' . implode(',', NeedsLook::OPEN[$name]) : '';
    $this->prepared(fn (Account $account): bool => $account->disabledAt === null
      && !$this->authorizer->readsAsPublic($account, $holder->type), 'notice.arrived',
      ['line' => $this->words()->count('look', $line->value, $count)], '/c/' . $holder->slug->value . $filter)();
  }

  private function standing(string $name, string $type): int
  {
    $tallies = $name === 'Message' ? $this->oversights->unread($type)
      : $this->oversights->standing($type, Promises::STANDING[$name], NeedsLook::OPEN[$name]);

    return ($tallies[$type] ?? null)?->count ?? 0;
  }

  public function failed(): void
  {
    if ($this->errors->mailing()) {
      $this->prepared(fn (Account $account): bool => $account->can(Capability::ReadLog), 'notice.failed', [],
        '/settings/errors')();
    }
  }

  /** Written while the running release stands and sent once the next is in, whose
      templates and words would otherwise be the ones read. What a mail that fails
      first meets is loaded here for the same reason.
      @return \Closure(): void */
  public function installing(string $version): \Closure
  {
    array_map(class_exists(...), [RelayFailed::class, RelayFault::class, MailRefused::class]);

    return $this->prepared($this->updating(...), 'notice.installed', ['version' => $version], '/settings/update');
  }

  public function refused(string $version): void
  {
    $this->prepared($this->updating(...), 'notice.refused', ['version' => $version], '/settings/update')();
  }

  private function updating(Account $account): bool
  {
    return $account->can(Capability::RunUpdate);
  }

  /* What a till writes delivered or a contact form writes read never waits. */
  private static function arrival(Entity $record): ?Overlooked
  {
    $name = basename($record->type);
    $line = self::ARRIVING[$name] ?? null;
    $open = match ($line) {
      null => false,
      Overlooked::Unread => $record->props->string('date_read') === null,
      default => in_array($record->props->string(Promises::STANDING[$name]), NeedsLook::OPEN[$name], true),
    };

    return $open ? $line : null;
  }

  /** One mail for each account the test admits, written now and sent when the closure is called.
      @param \Closure(Account): bool $to
      @param array<string, string> $values
      @return \Closure(): void */
  private function prepared(\Closure $to, string $kind, array $values, string $path): \Closure
  {
    $words = $this->words();
    $values = [...$values, 'host' => (string) preg_replace('#^https?://#', '', $this->origin)];
    $mails = [];
    foreach (array_filter($this->accounts->all(), $to) as $account) {
      $name = (string) $this->entities->find($account->person)->props->string('name');
      $mails[$account->email] = $this->written($words, $name, $kind, $values, $this->origin . $path);
    }

    return fn () => $this->sent($mails);
  }

  private function words(): Words
  {
    return new Words($this->languages, '', $this->settings->chromeLocale()?->tag);
  }

  /** @param array<string, string> $values */
  private function written(Words $words, string $name, string $kind, array $values, string $link): Mail
  {
    $lines = [
      'greeting' => $name === '' ? $words->get('login.mail.greeting_unnamed')
        : $words->get('login.mail.greeting', ['name' => $name]),
      'lead' => $words->get($kind . '.lead', $values),
      'open' => $words->get('notice.open'),
    ];
    $copyright = $words->get('footer.copyright', ['company' => 'plue GmbH', 'product' => 'Skemik™']);
    $text = sprintf("%s\n\n%s\n\n%s:\n%s\n\n-- \n%s\nhttps://skemik.org\n", $lines['greeting'], $lines['lead'],
      $lines['open'], $link, $copyright);
    $subject = $words->get($kind . '.subject', $values);
    $html = $this->template->render('pages/notice-mail',
      [...$lines, 'words' => $words, 'subject' => $subject, 'link' => $link]);

    return new Mail($name, $subject, $text, $html);
  }

  /** Every mail is tried, and the first that failed is thrown after the last.
      @param array<string, Mail> $mails by address */
  private function sent(array $mails): void
  {
    $failed = null;
    foreach ($mails as $address => $mail) {
      try {
        $this->mailer->send((string) $address, $mail);
      } catch (RelayFailed | MailRefused $failure) {
        $failed ??= $failure;
      }
    }
    if ($failed !== null) {
      throw $failed;
    }
  }
}
