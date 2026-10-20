# Skemik

![Version 1.0.4](https://img.shields.io/badge/version-1.0.4-1f6feb)
![Licence BUSL-1.1](https://img.shields.io/badge/licence-BUSL--1.1-6e7781)
![PHP 8.5 or newer](https://img.shields.io/badge/PHP-8.5%2B-777bb4)

The database that ships its own data model: 30 curated schema.org types, the
same in every install, their fields named and typed before you start — no empty
tool to design first, no column called `Date2`.

![Skemik's admin](https://releases.skemik.org/screenshot.jpg?1.0.0)

Like a headless CMS or a spreadsheet database, except the model is already
there, and it is schema.org.

[skemik.de](https://skemik.de) in German,
[skemik.com](https://skemik.com) in English,
[skemik.fr](https://skemik.fr) in French.

---

[Quickstart](#quickstart) · [What it is](#what-it-is) ·
[Requirements](#requirements) · [Install](#install) ·
[Running it](#running-it) · [Updating](#updating) · [Addresses](#addresses) ·
[Collections](#collections) · [Reading the feed](#reading-the-feed) ·
[Writing through the feed](#writing-through-the-feed) ·
[Reading it from PHP](#reading-it-from-php) · [How to build a website](#how-to-build-a-website) ·
[Decisions](#decisions) · [Security](#security) · [Contributing](#contributing) ·
[Licence and trademark](#licence-and-trademark)

---

## Quickstart

From an empty folder to a record read at its address, and one written by a
program, on your own machine.

**1. Run it.** In a folder holding a copy of `index.php`, `.htaccess`, `core/`,
`view/`, `language/` and `migrations/`, start PHP's built-in server:

```sh
PHP_CLI_SERVER_WORKERS=2 php -S localhost:8080 index.php
```

**2. Install.** Make an empty data folder outside that one, open
`http://localhost:8080/install` and give it the folder, your name and your
login address. The first login code is mailed and also written to `login/` in
the data folder.

**3. Publish a record.** In the admin, open *Events*. Every field stays inside
the house until it is made public: the line under the name, *… fields · 0
public*, lists them: tick *Public* on *Name* and on *Starts*. Then add an
event with a name and a start, and set its status to *Published*.

**4. Read it, with no key.**

```sh
curl http://localhost:8080/feed/en-US/events
```

answers, for an event *Open evening* at 18:00 in Berlin:

```json
{
  "@context": "https://schema.org",
  "@id": "http://localhost:8080/feed/en-US/events",
  "@type": "ItemList",
  "name": "Event",
  "numberOfItems": 1,
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "item": {
        "@id": "http://localhost:8080/feed/en-US/events/open-evening",
        "@type": "Event",
        "name": "Open evening",
        "startDate": "2026-11-05T17:00:00Z"
      }
    }
  ]
}
```

`en-US` is the locale `/install` took from your browser, and `/feed` names
those the house holds. `.md`, `.csv` or `.html` after `events` picks another
format, and `?sort=startDate`, `?q=open` or `?startDate=2026-11` orders and
narrows the list.

**5. Write one from a program.** On *Events*, the key beside its name grants
access: name the key, tick *Create*, issue it and copy its secret, shown only
then. Then:

```sh
curl -X POST http://localhost:8080/feed/en-US/events \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/ld+json" \
  -d '{"@type":"Event","name":"Open day","startDate":"2026-11-02T10:00:00Z"}'
```

It answers `201` and lands as a draft, which the house publishes in the admin;
from then on it is read at the address in `Location`.

**6. Build on it.** A website reads the feed as *Reading it from PHP* and *How
to build a website* show, and `/feed/en-US/profile` says what each type can
hold.

## What it is

A house — the company, club or organisation that runs Skemik — types its data
once, and its website, catalogue and assistant work with it at one address.
Skemik holds the data, edits it in the admin and serves it at that address. A
frontend is not part of it: that stays the house's own, built on the address.

It is for any house whose website or shop would otherwise run on WordPress, and
whose members, courses, orders or bookings would sit in spreadsheets beside it:
a company, a shop, a club or a school, and the agency that builds their sites.

- **The model.** Thirty schema.org types, each a collection with its own
  address, statuses from draft to archived and its own permissions, their
  fields named and typed. The thirty limit the collections, not what can be
  said: a document uses far more than thirty `@type`s, through value objects,
  such as a postal address or opening hours held inside a record, a record's
  kind and a media file's format. What seems to need a thirty-first type is
  nearly always a kind, an additional property, a list or a term. A thing none
  of them carries, one needing its own address, statuses and permissions, is
  added as a type by a release, the same in every install.

- **The linking.** Records point at each other under schema.org's own names,
  and an edge carries words of its own, *CTO from 2021 to 2024*. A list
  replaces every custom collection, and a set of terms every taxonomy table.

- **The admin.** A collection per type from the first day, seen as a list, a
  table, cards, a grid or a timeline, since the type says what is a date and
  what an image. Every view edits the fields it shows in place, the table all
  of them, and acts on the records ticked in it together, a status, one value
  or the trash across them, each logged per record. The trash's rows are
  ticked the same way and restored or purged together, one dialog counting the
  references over the lot, and a lot holding one record a required edge names
  stays as it was, naming that record. Beside them a record screen, a media
  library, a search across every collection, and next to a list or a record
  the address it is read at, with the document as the public reads it, or as a
  key reading what the person logged in reads.

- **The address.** Everything the house publishes is read at `/feed`, in four
  formats, by anything.

- **The grant.** The same address takes writes from a key, a secret a program
  sends with each request, and the key's grant says what it may do, down to one
  field or one record of one collection, until a date.

- **Running it.** Files on ordinary webspace and one folder of data. No daemon,
  no database password, no build step.

- **The proof.** The profile is reconciled against schema.org's released
  vocabulary, and the promises made here are checked against a running
  install, before every release.

**No frontend and no plugins, by intent.** Websites, shops and apps are built
by somebody against the address and run somewhere else. A theme or a starter
may come as a product of its own, talking to Skemik over HTTP and never running
inside it.

## Requirements

Ask the host for these before you copy anything.

- **PHP 8.5 or newer** with `pdo_sqlite`, `intl`, `dom` and `mbstring`, the
  first built against SQLite 3.38 or newer, which `/install` checks.

- **A web server that sends every request to `index.php`**: Apache with
  `mod_rewrite` and an `AllowOverride` of at least `FileInfo` and `AuthConfig`
  reads the shipped `.htaccess`, and another server, nginx among them, is given
  the same rules, as *Install* sets them out.

- **A host that reaches its own address**: `/install` fetches `config.php` from
  outside to prove it is not served as text, and asks a `PATCH` of itself,
  which the host's front must let through; over HTTPS both need `openssl`.

- **The data folder on a disk of the one machine that serves the install**,
  never on a file system several web servers mount.

- **Five optional extensions**, each costing one thing where it is missing:
  without `fileinfo` no upload is accepted, without `gd` and its WebP support
  media get no thumbnails, without `openssl` no login code goes through an SMTP
  server and no update is downloaded, and without `sodium` or `phar` no update
  is verified or unpacked, so it is copied by hand.

- **Two settings that cost time** and refuse nothing, each named by `/install`
  where it stands: without the opcode cache PHP compiles the tree anew for
  every answer, and with `zend.assertions` left at `1` the self-tests run on
  every page and slow each answer.

- **One that gives something away** and costs no time: run as CGI or FastCGI,
  without `fastcgi_finish_request`, PHP cannot end an answer before the work
  after it, so a login waits for its mail, and how long it takes tells a
  stranger whether the address holds an account. `/install` names it too.

## Install

Copy everything the release's folder holds into the folder your domain points
at: `index.php`, `.htaccess`, `core/`, `view/`, `language/` and `migrations/`,
and the four papers beside them, since the house checks its files against
`SHA256SUMS` and a paper left out reads as missing. Then open `/install` on it
by hand: nothing links there, and until it has run no other address answers.
Run it the minute the files are copied: it answers only while the house has no
account, and until then the house belongs to whoever runs it first, who needs
nothing but the path of an empty data folder.

A release is one zip named after its tag, `skemik-1.0.0.zip`, holding that
folder under the same name, the code and four papers, this README, the
`LICENSE`, `SECURITY.md` and `SHA256SUMS`, and beside it its signature,
minisign's, which the updater checks against the key in the source before it
unpacks anything.
Both are downloaded from the release's page at
[github.com/plue-gmbh/skemik/releases](https://github.com/plue-gmbh/skemik/releases),
where every release stands under its tag, and the updater fetches the same two
from `releases.skemik.org`. Check it by hand with the same key before you open
the folder:

```sh
minisign -Vm skemik-1.0.0.zip -P RWTcPiN2NJok7rtTpL5Ipn/LHlDpGfDQGT2ZHkbGp1Lry05p4J71nMzW
```

`SHA256SUMS` lists every file the release ships but `.htaccess`, whose lines
outside the Skemik block are the hoster's, so once the files are copied this
checks each of them against the release, in the domain root:

```sh
sha256sum -c SHA256SUMS
```

What the copied files are, the one `/install` writes, and the papers beside
them, which nothing runs and an update replaces:

```
index.php     the front controller
.htaccess     Apache's rules, as below
config.php    written by /install, never shipped and never touched by an
              update: the data folder, the install's address, the mail
              relay, whether a snapshot is taken daily, whether updates
              install and how early, the security contact
core/         the application, one folder per layer
view/         the admin's pages, its Vue islands and its stylesheet, and vendor/
              with Vue and the Inter fonts pinned beside their licences
language/     the admin's words, one file per language, English the source,
              beside each the names of a select's options, and beside each
              translation the names of the fields
migrations/   numbered scripts that move an existing database to the declared
              shape, forward only
README.md     this file, as the release it came with
LICENSE       the licence, stamped with the release's version and Change Date
SECURITY.md   where to report a vulnerability
SHA256SUMS    the hash of every file shipped but .htaccess
```

The installer checks the server first and stores nothing until every check
passes. One check is a `PATCH` of its own address: every save is one, and a
front that refuses the method would otherwise be learnt at the house's first
save. PHP writes in two places, the data folder and the folder holding
`index.php`: `config.php` at the first run, the whole tree when a release
installs itself. Where it cannot, the check's line says so, with the user PHP
runs as where the host lets that be asked. Then it asks for the data folder,
unless `config.php` already names one, the owner's name and login address, the
house's timezone and the admin's language. That is also the first language the
house publishes in, in the region the installing browser names for it, `de-AT`,
else the one it is most at home in, `de-DE` or `en-US`; the admin's own words
are the language's, in a region's spelling where a file for it ships, Swiss
German's *Strasse*, and its dates and numbers are written as the browser's
region writes them, *€ 1.234,50* in `de-AT`.

- **Make the data folder beforehand**, empty, best above the domain root. The
  installer never creates one, never takes over one that holds something, and
  accepts one inside the domain root only once it has fetched it from outside
  and been refused. `config.php` is the one file `/install` writes into the
  domain root,
  readable only by the user PHP runs as, since a relay's password is typed into
  it, and fetched the same way to prove it cannot be read as text.

- **The address `/install` ran under is the install's own**, written into
  `config.php` as `'address' => 'https://…'` once both proofs were fetched under
  it. A login mail names it, the hourly probe of the data folder fetches it and
  an update asks the new code under it, whatever name a later request carries: a
  server that answers any name would otherwise mail a colleague, anybody with an
  account, a stranger's address to log in at. A house moving its domain changes
  it there. The feed's documents keep the name each request came under.

- **The first login code** is mailed and always also written to `login/` in the
  data folder, so a host whose mail does not work still lets you in. Where the
  host's `mail()` delivers nothing, name a relay in `config.php`: `'relay' =>
  ['host' => …, 'port' => 587, 'user' => …, 'password' => …, 'sender' => …]`.
  It must offer STARTTLS; implicit TLS on 465 is not spoken.

- **Apache reads the shipped `.htaccess`** and needs `mod_rewrite` and an
  `AllowOverride` of at least `FileInfo` and `AuthConfig`. Where `/install`
  answers the web server's own error page, the rules are not read: the override
  is off for the folder, or the file was not copied. Another server needs the
  same rules: every request goes to `index.php` except `view/`'s `.css`, `.js`,
  `.woff2`, `.ico`, `.png` and `.webmanifest` files, and no other `.php` file
  is served; one requested anyway does nothing, since only `index.php` has side
  effects.

- **Behind a proxy that speaks HTTPS for the host**, a CDN or a hoster's TLS
  front, the install sees plain HTTP from one address, since any client can
  send the headers a proxy adds: every `@id` reads `http://` and every visitor
  shares one rate limit. A setting in `config.php` naming the proxy comes when a
  house behind one asks. Served over HTTPS itself, every answer asks the
  browser to stay on it for a year, so a plain `http://` beside it is not
  opened again once the admin was.

To try the first run on your own machine, start PHP's built-in server in the
folder holding `index.php`:

```sh
PHP_CLI_SERVER_WORKERS=2 php -S localhost:8080 index.php
```

and open `http://localhost:8080/install`. The second worker is needed because
the installer fetches its own address while still answering the form. With
`zend.assertions=1`, which a development `php.ini` sets, the self-tests halt a
page loudly, naming what they guard, so a halt on your machine is a defect to
report and not a setup to fix.

## Running it

**One install serves one house**: two houses are two installs, never one data
folder. In the data folder `config.php` names, `holdings/` holds the house's
data, its holdings: the database, and `media/` with the uploads. Beside it
stands what is derived or must not survive a restore: `backups/`, `staging/`,
`log/`, `login/`, `sessions/` and `cache/`. A copy of `holdings/` is a backup
and a move to another host; the rest is rebuilt or, for a login code and a
session, rightly gone.

**One snapshot a day is taken automatically**, on the first request of the day
in the house's timezone, with no daemon and no cron to set up: the database
settled into one file by `VACUUM INTO`, never a copy of the live WAL, and the
media tree beside it, hardlinked where the disk allows. Seven daily and four
weekly are kept, about thirty days, in `backups/` beside the holdings. Where a
day's copy will not fit, the window shrinks before the day is lost, the weekly
copies first; the backups screen says so, and that or a daily that failed is a
line of the Needs a look card on the home screen. A snapshot sits on the
holdings' disk, so it undoes a mistake and a bad update, not the loss of the
server, which is the host's backup or a snapshot downloaded. A host that backs
up properly switches the daily ones off with `'daily_backups' => false` in
`config.php`, which belongs to the machine.

**The owner's backups screen** opens from a row on the home screen's Settings
card, which says when the last snapshot was taken. It takes a snapshot now,
lists each with its path and its download, one zip that unpacked into `backups/`
is that snapshot again, and restores one whole: what was written since is kept
as a snapshot named *before the restore*, and everybody is logged out. Who may
get in is the exception: an account or a key the snapshot holds comes back only
where the house still holds it, as the house has it. So a key revoked or an
account removed since stays out, and one made since is gone as a record made
since is, but for the owner restoring, who keeps theirs. A database copied back
by hand is none of that: its accounts and keys come back with it, a key revoked
and an account removed since among them, the `data.sqlite-wal` of the one it
replaces goes with that one, and it is moved to the running release, as
*Updating* describes, only once `version.txt` is deleted from the data folder.

**What `/install` proved is asked again once an hour**, and at once after an
update or on another PHP: that the data folder and its `sessions/` take a write
and, where the folder sits inside the domain root, that it is still not served.
One that fails stops every address with a page naming it, until a request finds
it fixed. Short of 100 MB free where the data lies, every admin screen says so
and nothing stops.

**The holdings are checked automatically once a day**: every record against
what its type asks, five hundred records every ten minutes until all are read,
each slice after an answer has left, so nobody waits for it. What is found, an
edge into nothing or a required field a release added to records that stood, is
a line of Needs a look, and the Holdings check screen behind it names each
record.

**The release's files are compared with its `SHA256SUMS` once a day**, and at
once after an update: a file changed, one missing, and one added where only the
release puts files, `core/`, `view/`, `language/`, `migrations/` and a `.php`
beside `index.php`, each stand on a row of the home screen's Settings card and
as a line of Needs a look, and the row says so while the files match. While one
differs they are compared every hour, so a tree put right, a hand edit undone
or a copy by hand finished, turns green by itself within the hour. A copy with
no `SHA256SUMS` beside it is not compared. Whoever can change the code can
change the list too, so this finds a release half copied, a hand edit or a
careless intruder, never a careful one.

**A failure is logged under the incident id its page or answer shows**, six
characters to read out over the phone, in `log/app.log`, rotated at 8 MB with
the five files before it kept. The owner's error log, opened from a row on the
home screen's Settings card, lists the last 200 newest first and finds an
entry by its id, so answering *there's an error* needs no shell.

**No address answers with a failure**: every one the install answers to a `GET`,
the admin's screens and the feed's documents alike, gives a stranger, an editor
and the owner what it owes them, the page, a login or a refusal, and never a
failure.

## Updating

**A release is installed automatically**, with nobody pressing: once an hour,
after an answer has left and from any request, the install reads the manifest
at `releases.skemik.org`, which names the newest release and nothing older, and
installs what it finds at once. So a house where nobody opens the admin is
current, and the address answers *being updated* for the seconds of the swap.

- **The check sends nothing that identifies the install**, no version, no
  domain, no id. The host of `releases.skemik.org` keeps an access log we
  cannot switch off, with the first 16 bits of an IPv4 address and 32 of an
  IPv6 and the rest zero, so a line there names a network and never an
  install, and we cannot count them.

- **The download is verified before it is unpacked**, minisign's signature over
  the archive through `sodium` against the key in the source, with no way to
  install anyway.

- **A snapshot of the holdings is taken first** and the tree as it ran kept
  beside it, and both go back together where the new release cannot take over
  the database. The lines a hoster had you add to `.htaccess` outside `# BEGIN
  Skemik` and `# END Skemik` are kept. This README, the licence, the security
  policy and `SHA256SUMS` beside the code are replaced by the release's own, so
  what an install says of itself is the release it runs.

- **An install declines with `'updates' => false`** in `config.php`. A
  development copy says so too, since `0.3.0-dev` sorts below `0.3.0` and the
  release would replace it. It still checks once an hour and installs
  nothing, so its update screen names the release that came out.

- **A release may be held back until a day** the manifest names, so it reaches
  a few houses before all of them: an install whose `config.php` says
  `'updates' => 'early'` takes it the hour it goes up.

- **Where it cannot run**, the tree not PHP's to write on a host where the
  files belong to the FTP account, an extension missing, the data folder short
  of room, or a release refused, the header's version is marked on every page
  for the owner and Needs a look says the copy by hand is theirs. The update
  screen behind the version says when the install last checked, what it
  installed and whether that was a security release, or what it could not and
  why.

**By hand, updating is copying the next release over the last**, `config.php`
left alone and the data folder untouched; take a snapshot on the backups screen
first, since that is the way back. An update stopped by the host in the middle
of its swap is mended the same way, with the release it was installing. Either
way, the first request on the new version moves the holdings to it before
anything reads them: where a migration script is pending the database is
copied, the scripts above its number run, every record's sort keys and search
words are written again by the new code, and the data folder is stamped with
the version and its last script's number, `version.txt`. A request meanwhile is
answered *being updated*, and the holdings check behind Needs a look starts
over, since the fields a release asks for are its own. Where a script fails, or
the database afterwards lacks the shape the release declares, the database goes
back to the copy and every address answers a page saying so, until the release
that ran before is copied back, or `updating.txt` in the data folder is removed
once the cause is mended. The reason stands in that file, not on the page every
stranger reads, whose words can name a path. A release is not undone by copying
the older files back: the database stays at its number and the older code
refuses it, so the way back is the snapshot.

## Addresses

A website or an app needs every address below but two: the profile, which
tells a program what each type holds, and a collection's configuration, which a
script setting a house up writes. Reading needs no key; a key reads what its
rules name beyond that and is needed for every write, sent as
`Authorization: Bearer …`, from a server or from a page on any domain, whose
browser's preflight is answered.

| Method | Address | Answers |
|---|---|---|
| `GET` | `/feed` | the locales the house holds |
| `GET` | `/feed/{locale}` | the catalogue: the collections this reader reads, the names of their fields in that language and the words for every value a field takes from a fixed list, such as `EventScheduled` |
| `GET` | `/feed/{locale}/profile` | every field of the thirty types, whatever this house carries, with the words of every fixed list, a row's keys' among them, and what a write must send, may repeat and cannot set |
| `GET` | `/feed/{locale}/{collection}` | a page of its records |
| `GET` | `/feed/{locale}/{collection}/{slug}` | one record |
| `POST` | `/feed/{locale}/{collection}` | a new record, a draft, `201` with its address in `Location` |
| `PATCH` | `/feed/{locale}/{collection}/{slug}` | the fields sent, under the `If-Match` its reading gave |
| `PATCH` | `/feed/{locale}/{collection}` | the collection's configuration, the fields it carries and publishes, from a key that may configure it |

A collection or a record answers JSON-LD, or a page to a browser, and
`.jsonld`, `.html`, `.csv` or `.md` after the collection or the slug picks the
format; the locales, the collections and the profile are JSON-LD only. Every
reading carries an `ETag`, and `If-None-Match` with it answers `304` while
nothing changed. An asset's file at its `contentUrl`, and the preview at
`/thumb` behind it, carry the file's name as their tag, since that address
names bytes that never change, and answer `304` only to whoever may read the
file: a stranger revalidating a draft is refused, never told it is there. A
refusal is JSON whatever was asked: `error` says what went wrong, a `422` on a
write adds `violations`, a `field` and a `reason` for each property as the
document spells it, and a `500` gives an `incident` to quote to the house. A
list takes these parameters and refuses any other, as *Reading the feed* says
in full:

| Parameter | Asks for |
|---|---|
| `?{field}=` | records whose field matches: a text from its start, a link whole, a range `a..b`, a month `2026-10`, any of `a,b`, an edge by its address `organizations/acme` |
| `?q=` | records holding the words, in the fields this reader reads or in their slug |
| `?sort=` | the order by a field, `-` before it for the other way |
| `?page=` | a page of 50 records, from `1` |
| `?per_page=` | up to 200 records a page |
| `?render=html` | every text as HTML, on a record too |

## Collections

The collections are the same thirty in every install, one per type. Each type
is the schema.org class it is named after, and every field has the name
schema.org gives it. A house chooses which of a type's fields its collection
carries, the ones its admin edits, and which of those it publishes, the ones a
stranger reads: the catalogue at `/feed/{locale}` says both, and the profile at
`/feed/{locale}/profile` every field each type has. A creative work has **a
record per language**, each with its own slug and status. Every other
type is one record on every locale, with **words per language** where it has
words to translate and **no translation** where a second language would be a
second one of it, a review or an order. A medium or a dataset is **either**, a
record per language where the file differs. Four are **never** public and
answer a key alone.

| Type | Collection | Languages | Public |
|---|---|---|---|
| [Person](https://schema.org/Person) | `people` | words per language | can be |
| [Organization](https://schema.org/Organization) | `organizations` | words per language | can be |
| [LocalBusiness](https://schema.org/LocalBusiness) | `local-businesses` | words per language | can be |
| [Place](https://schema.org/Place) | `places` | words per language | can be |
| [Article](https://schema.org/Article) | `articles` | a record per language | can be |
| [WebPage](https://schema.org/WebPage) | `web-pages` | a record per language | can be |
| [WebSite](https://schema.org/WebSite) | `web-sites` | a record per language | can be |
| [HowTo](https://schema.org/HowTo) | `how-tos` | a record per language | can be |
| [Course](https://schema.org/Course) | `courses` | words per language | can be |
| [Dataset](https://schema.org/Dataset) | `datasets` | either | can be |
| [Book](https://schema.org/Book) | `books` | a record per language | can be |
| [MediaObject](https://schema.org/MediaObject) | `media` | either | can be |
| [Review](https://schema.org/Review) | `reviews` | no translation | can be |
| [Comment](https://schema.org/Comment) | `comments` | no translation | can be |
| [Message](https://schema.org/Message) | `messages` | no translation | never |
| [Product](https://schema.org/Product) | `products` | words per language | can be |
| [Service](https://schema.org/Service) | `services` | words per language | can be |
| [MemberProgram](https://schema.org/MemberProgram) | `membership-programs` | words per language | can be |
| [Brand](https://schema.org/Brand) | `brands` | words per language | can be |
| [Offer](https://schema.org/Offer) | `offers` | words per language | can be |
| [Order](https://schema.org/Order) | `orders` | no translation | never |
| [Invoice](https://schema.org/Invoice) | `invoices` | no translation | never |
| [Event](https://schema.org/Event) | `events` | words per language | can be |
| [Reservation](https://schema.org/Reservation) | `reservations` | no translation | never |
| [ItemList](https://schema.org/ItemList) | `item-lists` | a record per language | can be |
| [DefinedTerm](https://schema.org/DefinedTerm) | `defined-terms` | words per language | can be |
| [JobPosting](https://schema.org/JobPosting) | `job-postings` | a record per language | can be |
| [SoftwareApplication](https://schema.org/SoftwareApplication) | `software` | words per language | can be |
| [Question](https://schema.org/Question) | `questions` | a record per language | can be |
| [Action](https://schema.org/Action) | `actions` | no translation | can be |

## Reading the feed

Open `https://example.com/feed/en-GB/events?sort=startDate` in a browser, with
no key: what the house has published of its events, in English and soonest
first. The address is the house, its `/feed`, a locale the house holds, the
collection and the question. A stranger reads the published records, each with
the fields the house publishes, and an address with nothing to read, a typo or a
collection that is never public, is the same `404`. A record is a draft, in
review, published or archived. A published record may carry a window: while its
start lies ahead the admin shows it as scheduled, it is read from that minute
with no job having run, and no longer once the window's end has passed. Any
cache between may keep what a stranger reads for a minute, so a record taken
offline can still be served for sixty seconds. `/robots.txt` sends every crawler
away, from the feed's pages too: a search engine should find the house's
website.

From `1.0` on, every release's tag in the public repository carries its notes:
one line per addition at the address and per change a frontend has to act on.

### One record

As `/feed/de-DE/events/test-exercise` answers a stranger:

```json
{
  "@context": "https://schema.org",
  "@id": "https://example.com/feed/de-DE/events/test-exercise",
  "@type": "Event",
  "name": "Test Exercise",
  "startDate": "2026-10-02T16:00:00Z",
  "eventStatus": "https://schema.org/EventScheduled",
  "location": {
    "@id": "https://example.com/feed/de-DE/places/north-hall",
    "@type": "Place",
    "name": "North Hall"
  },
  "maximumAttendeeCapacity": 12,
  "remainingAttendeeCapacity": 11,
  "offers": [
    {
      "@type": "AggregateOffer",
      "lowPrice": 15,
      "highPrice": 15,
      "priceCurrency": "EUR",
      "offerCount": 1
    }
  ]
}
```

A record's targets are written into it one level deep, with the fields the
reader may read, and theirs as addresses; a list nested in it still arrives with
its members, but a list among those brings its own as addresses, to be read at
its address. A listing's member is the record's own document, to the same depth,
so a page drawn from a listing reads no member a second time. `eventStatus`
holds a value from schema.org's fixed list, spelt as the member's address,
`https://schema.org/EventScheduled`, whose words in each language the catalogue
holds under the code, `EventScheduled`, which a filter names and a write may
send in place of the address. An order, an invoice, a reservation, an event and
an action each say how they stand in such a list of their own, `orderStatus`,
`paymentStatus`, `reservationStatus`, `eventStatus` and `actionStatus`.
`remainingAttendeeCapacity` and `offers` are computed as the
document is written and never stored. A time leaves in UTC, as `startDate` does
here, and a date as the day it is. No answer names the house's timezone, so a
frontend that shows a time is configured with it once.

Two lines of consumer contract hold in all four formats: read a list member as
`member.item ?? member`, and unwrap any value whose `@type` is `Role` or
`ProgramMembership`, again where one holds the other. Both wrappers say what the
referring record means about the target, and their words override the target's
without replacing them: where an edge carries no `name` or `description` of its
own, the target's stand, so read the edge first and the entity second. An edge
to a membership programme is always a `ProgramMembership` around it, since a
membership and not its programme is what `memberOf` holds, and a role's
`roleName`, `startDate` and `endDate` on a list's member or on a membership
stand in a `Role` around the target, the one class that declares them.

### Texts, terms and links

Text is Markdown and only Markdown, so a heading is a heading and not a guess
from blank lines. The subset is headings, `- ` and `1. ` lists, `> ` quotes,
paragraphs, inline code, bold, italic and links: no fenced code, no tables, a
line ending in two spaces breaks the line and any other line break inside a
paragraph is a space. A heading is the level written, `##` an `<h2>`, never
shifted: the record's name is the page's `<h1>`, so a body starts at `##`. A
consumer renders it, twenty lines for headings, paragraphs and links, or asks
`?render=html` on any read and gets every text rendered at every depth by the
renderer the `.html` uses, a duration too in the route's language, `P1M` as *1
month*; any other value of `render` is refused naming it. A plain text, a
`description`, leaves rendered as paragraphs with its line breaks, so a snippet
for a meta tag is read from the document as stored. An image is a field and
never part of the text: the `.html` shows a record's first image under its
heading, and a `![…](…)` in a text is printed as written.

A text's link to a record of the house leaves as that record's `@id`, in every
format and at every depth, rendered as `<a href="…">` and nothing else on the
tag, and as its words alone where the reader may not read the record. It is
held by the record and not by its address, so it follows a changed slug, and a
link a key posts to an address of the house is held the same way.

Past a site's home page, `url` is the address of a thing that lives on somebody
else's domain and stays empty for one held here: where a page is published is
the frontend's route, as *How to build a website* says. `workTranslation` says
which record is the translation of which, and purging an original leaves its
translations one group, naming each other. An edge to a work names its
translation in the reader's language where there is one to read, and where not
the work itself, or another translation where that may not be read either: a
product pointing at its German screenshot shows the English one on an English
route, and the German one while the English is a draft, whichever of the two it
points at. What is no work is one record under an `@id` per locale:
`/feed/de-DE/people/mara` and `/feed/en-GB/people/mara` are one person.

A list points at what its cards display. Where every card shows a site's
screenshot and link, the list's members are the `WebSite`s and not their
owners: a member that has to be fetched before its card can be drawn is one
edge away from the list's subject.

An order line names what was ordered as a record or as a word: read
`orderedItem.name` either way, and follow its `@id` where it has one. The same
holds for an answer's `author` and a certificate's `issuedBy`. A word leaves as
a thing of that name, since a one-off item and an outside examiner have no
record here, and a record the reader may not read leaves the key out.

A term leaves as its code, `"keywords": ["gluten-free"]`, and its word in each
language is its record in `defined-terms`, where the house publishes those:
the catalogue holds words for schema.org's own fixed values only. A word typed
into a term field in the admin becomes a term of that field's set, made with it,
and the same word typed again is that term.

A record's kind is its `additionalType`, beside an `@type` that stays one of
the thirty: a product group is a `Product` whose `additionalType` is
`ProductGroup`, holding `variesBy` and its variants under `hasVariant` in their
order. A page that marks it up for a search engine lifts the kind into `@type`
itself.

A record states its price range and never its offers: `offers` holds one
`AggregateOffer` per currency, its `lowPrice`, `highPrice` and `offerCount`
computed from the offers anyone may read, none past its `validThrough`, so a
card says *from €129* without opening a single date, and a course states the
range of its runs. An offer's own `price` is one number beside `priceCurrency`;
where it recurs, its `priceSpecification` repeats both and adds the billing
period and whether tax is included, and `?sort=price` orders by it.
The offers themselves are asked for by what they offer,
`/feed/{locale}/offers?itemOffered=events/test-exercise`, where the house
publishes `itemOffered`.

That holds wherever a whole has parts: a record never lists what points at it.
An event's parts are `events?superEvent=…`, an article's comments
`comments?about=…`, a product's reviews `reviews?itemReviewed=…`, a site's
pages `web-pages?isPartOf=…`, each where the house publishes that field. What a
whole does state is what is computed over its parts: the price range, and a
rating over the reviews that name it, on a product, a service, a brand, an
event, a place, an organisation and a work other than a review, a comment, a
message or a question.

### Asking a list

`numberOfItems` counts every record a list's question matches and not the page,
a page past the end is empty under the same count, and the `.html` and `.md`
listings link their next page.

A filter on a number, a date or a time asks a range with `..`, either end
open: `?startDate=2026-10-10..` for what comes,
`?maximumAttendeeCapacity=10..12`, and `?startDate=2026-10` for a month. A
month, a day or a wall time is the house's, in its timezone:
`?startDate=2026-11-07` is that day in the house's timezone, and the event above
is `?startDate=2026-10-02T18:00` in Berlin. A comma asks for any of its values,
`?keywords=vegan,organic`, fifty at most, and is the one way to: a parameter
given twice is refused, as is one the address does not know, so a campaign's
`?utm_source=` stays on the page's own address.

A text matches from its start, `?name=mar` finding *Maria*, in the words the
route shows: the English route finds a person by her English name and never by
her German one. A link matches whole, as the record states it and without case,
since a link names one thing and its start names many:
`?url=https://www.example.com` finds the site stating that address and not the
one stating `https://www.example.com/en`. An edge is named as a document names
it, `?worksFor=organizations/acme`, a bare slug being refused. A link a
record holds under the house's own address is kept as its path and served
whole, so a house that moves its domain keeps every link, and the address finds
it by either spelling.

`?q=` finds the records holding every word typed, each as the start of a word
in a field the reader reads: a text in any of its languages, a link, so a
customer is found by an email address, the code of a public term a record is
tagged with, so `?q=bakery` finds what carries `Bakery`, and its slug, which
every address shows, so `?q=imprint` finds `web-pages/imprint` whatever the
page is named. In a short field of words, a name or a title, a word typed is
found anywhere inside a word too, since a name is often one compound word:
`?q=kurs` finds *Grundkurs* and `?q=list` *itemlist.jpg*, while a long text is
matched from the start of its words. Case and accents do not count, `ca` finding
*Café*, and *Müller* is found as `muller` and as `mueller`. A field holding a
number or a date is a filter's, and what holds no letter or digit, a stray
quote, finds nothing rather than failing. The most matching words come first
unless `?sort=` says otherwise.
A search takes twenty words at most: each word, like each value of a filter, is
weighed against the records, so a question asking more is refused with `400`
naming its parameter rather than costing more than a page of 200 does.

A key reads what a stranger reads, plus the fields its rule names, and one bound
to a record reads that record and no other of its collection, at any depth,
since the fields it names are that record's. It reads what is not published only
where the owner said so on that *read* rule, which the key's sentence in the
admin then states: a customer is a draft person, so an invoice comes with its
customer to the bookkeeping's key and without to every other. An expired or
revoked key is a `401` on a read too, never the stranger's reading, so a website
holding one stops on its date rather than quietly showing less. A colleague
logged into the admin reads `/feed` as a stranger does: the address never reads
a session, and a draft is seen in the admin.

### The profile

`/feed/{locale}/profile` is what every collection can hold, the same to every
reader and in every install: the thirty, the four never public among them, each
with every field of its type, where the catalogue says what its reader reads of
what this house carries. A type is the `Class` it is `about`, and a field is
the catalogue's entry, named and worded alike, typed a `Property` too: its
`rangeIncludes` is its kind, `Text`, `DateTime`, `URL`, `Integer` for a count,
the enumeration a fixed list's values are members of, or what an edge points
at, a term's being `DefinedTerm`, each within the range schema.org gives the
property or named in the release's code as a step outside it. A fixed list's
words stand under its `valueReference`. A value object is one entry with its
keys under `valueReference`, as a key that configures the collection reads it,
and a field of rows lists a row's keys there, a key on a fixed list with its
words. `inLanguage` on a field's entry is the route's language where its words
differ by route, and absent where its value is one on every route. Each
collection's `potentialAction` is the `CreateAction` a `POST` to it is, whose
`result` annotates each property as schema.org annotates an action, nested where
a document nests it: `valueRequired` where a write must send it,
`multipleValues` where it holds a list and `readonlyValue` where it is computed
and never stored, a flag that is false left out, as schema.org defaults it. The
events, cut to five of their fields as `/feed/en-GB/profile` answers:

```json
{
  "@type": "DataFeed",
  "url": "https://example.com/feed/en-GB/events",
  "name": "Event",
  "about": {"@type": "Class", "@id": "https://schema.org/Event", "name": "Event"},
  "variableMeasured": [
    {
      "@type": ["PropertyValue", "Property"],
      "propertyID": "name",
      "name": "Name",
      "rangeIncludes": ["https://schema.org/Text"],
      "inLanguage": "en-GB"
    },
    {
      "@type": ["PropertyValue", "Property"],
      "propertyID": "startDate",
      "name": "Starts",
      "rangeIncludes": ["https://schema.org/DateTime"]
    },
    {
      "@type": ["PropertyValue", "Property"],
      "propertyID": "eventAttendanceMode",
      "name": "Attendance",
      "valueReference": [
        {"@type": "DefinedTerm", "@id": "https://schema.org/OfflineEventAttendanceMode",
          "termCode": "OfflineEventAttendanceMode", "name": "In person"},
        {"@type": "DefinedTerm", "@id": "https://schema.org/OnlineEventAttendanceMode",
          "termCode": "OnlineEventAttendanceMode", "name": "Online"},
        {"@type": "DefinedTerm", "@id": "https://schema.org/MixedEventAttendanceMode",
          "termCode": "MixedEventAttendanceMode", "name": "Hybrid"}
      ],
      "rangeIncludes": ["https://schema.org/EventAttendanceModeEnumeration"]
    },
    {
      "@type": ["PropertyValue", "Property"],
      "propertyID": "maximumAttendeeCapacity",
      "name": "Capacity",
      "rangeIncludes": ["https://schema.org/Integer"]
    },
    {
      "@type": ["PropertyValue", "Property"],
      "propertyID": "performer",
      "name": "Performer",
      "rangeIncludes": ["https://schema.org/Person", "https://schema.org/Organization"]
    }
  ],
  "potentialAction": {
    "@type": "CreateAction",
    "target": "https://example.com/feed/en-GB/events",
    "result": {
      "@type": "Event",
      "name-input": {"@type": "PropertyValueSpecification"},
      "startDate-input": {"@type": "PropertyValueSpecification", "valueRequired": true},
      "performer-input": {"@type": "PropertyValueSpecification", "multipleValues": true}
    }
  }
}
```

### The dump

A dump, taken in the admin by any account of what it reads, reads the same
way, with three differences it states about itself.
`jsonld/{locale}/index.jsonld` is that locale's catalogue as its taker reads
it, so the names of the fields and the words of the fixed values leave with the
house, and each collection the taker may configure is its entry as a key that
configures it reads it, every published field flagged `isAccessibleForFree`.
Which fields are published is the one thing the records cannot say, so the
entries written back by a key that configures there configure the next house
alike.
`manifest.jsonld` at its root names JSON-LD as the authoritative format, since
CSV drops edge qualifiers and HTML is a rendering, and carries what the
documents cannot — slug, locale, status, type and version per record, and who
took the dump under which scope. And every link inside the box is the file
beside it (`../organizations/acme.html`), so the folder opens in a browser with
no server and nothing installed. The JSON-LD files are byte for byte what the
address serves, a listing's page under the `?per_page=200` it names, and what
answers on every locale is a file in each locale's tree.

## Writing through the feed

A key holding a grant writes to the address it reads, one record a write. A
`POST` takes a document shaped like the ones the feed serves, and a `PATCH` the
fields to change: one left out stays, one sent empty is removed, and a list is
replaced whole, so `"keywords": ["vegan"]` leaves the product one term, as
`"keywords": "vegan"` does: a field that holds several is held and served as a
list, a list of one where there is one. A fixed list's value is sent as the
member's address or as its code, `https://schema.org/EventCancelled` or
`EventCancelled`, and stored as the code either way. A dump's JSON-LD file
posted back gives an equal record, one locale's tree at a time: a person is in
every tree, and her other language is a `PATCH` on its route. An asset is posted
beside its file, as `multipart/form-data` with the document in a `document` part
and the bytes in a `file` part: the file says its own address, format, size and
pixels, so the copy's `contentUrl` names the new file. A person's `name` is made
from `givenName` and `familyName` where those are sent and no name is, and
follows a new `familyName` until a name is typed, which then stands.

- **Nothing to learn but the feed's own shapes.** A nested record, a bare
  `{"@id": …}` and a term's code in the field's set all name a target, and
  `Role`, `ProgramMembership` and `ListItem` carry the edge's own words in as
  they carry them out. A nested record names its target and is never written.
  What is named must already stand and be what the key reads, as a filter's
  target is. So a re-import posts records in the order they point, and closes a
  cycle, such as a site's lists and their pages, with a `PATCH` afterwards; a
  key naming a draft customer needs to read people past the published, as one
  modifying a customer does. An address at another host than the one written to
  is refused as one that does not stand, so a dump moved to another domain posts
  its addresses under the new one. A property the type does not have is refused
  by name, as a field the collection does not carry is: nothing is stored that
  no document shows.

- **Words sent on another locale's route are that locale's**, a person's name
  and the role on her edge alike, beside the ones they were written with. An
  article in English names the German one it translates as `translationOfWork`,
  where the group has no English one yet, and an `inLanguage` other than the
  route's is refused. On a type with one record for every language, a message
  or an event, it
  is the field of that name, as a German contact form writes `de`. A currency,
  a country and a language are their ISO code, `EUR`, `DE`, `de`, and need
  nothing set up: the house holds no term for what the world closes.

- **A new record lands as a draft**, with a slug made from its name (`till`,
  then `till-2`). Status, slug and version never travel in a document, and no
  key publishes or trashes: what a script wrote, the house sees before the
  world does, and a stray record leaves as a draft nobody saw. A run that
  leaves eighty drafts across ten collections is not eighty visits: each
  collection filtered to *draft*, in whichever view, is ticked as a page and
  set to *published*, or trashed, in one bar, and a key allowed to *read* with
  *also what is not published* reads its drafts back meanwhile, an asset's file
  at its `contentUrl` among them.

- **A `PATCH` echoes the `ETag` its record was read under**, as `If-Match`, and
  every write answers with the new one. Without it the answer is `428`, and a
  stale one or one from another record is `412`, so two channels cannot both
  sell the last item. Nothing reserves stock while a checkout runs or writes it
  back when a payment fails: that is the selling channel's. A key that modifies
  what is not published, a customer, is also given *read* with *also what is
  not published*, since the version it echoes is read first.

- **What somebody did is a record too.** A training app posts a finished
  workout to `actions` as an `Action` whose `additionalType` is
  `ExerciseAction`, with the member as `agent` and the workout as `object`,
  reads a member's back with `?agent=`, and corrects a wrong entry with a
  `PATCH`.

- **An order arriving twice is one order.** A `POST` repeating an order number
  under the same `seller` answers that order as held and writes nothing, `200`
  and not `201`, so a retry cannot undo the house's correction; changing it is
  a `PATCH`. One the house has put in the trash answers `422` and stays there.
  The `seller` is an organisation or a branch, since a `LocalBusiness` is one.
  An invoice number under its `provider` and a term's code in its set are held
  once too, and a second is refused with `422` naming the field.

- **A refusal names what was missing**: `401` for a missing, revoked or expired
  key alike, `403` naming the field no grant covers, asked before the record is
  looked up, so a key with no rule to set in a collection is told the same of
  every address there and a key bound to one record is told absence of any
  other, `422` naming each property that cannot be read or does not fit its
  type, `429` for more than 600 writes a minute per key or IP address. Every
  write is logged under the key's name.

- **A key that configures a collection writes its fields the way it reads
  them.** Its catalogue entry at `/feed/{locale}` shows that key every field the
  collection carries, in order, a value object as one entry with its keys under
  `valueReference`, and `isAccessibleForFree` on each that is published; a
  `PATCH` on the collection's own address takes that `variableMeasured` back.
  What is named is carried and what is left out is taken off, its values kept
  for when it is added again. A value object is published key by key, since a
  house may publish the town and keep the street internal. A required field left
  out, a name the type does not have, a field published on a type that is never
  public or a value object flagged whole or sent without its keys is refused
  with `422` naming it, and a key not allowed to *configure* there with `403`.
  So an agency's script configures a fresh install in minutes, and what one
  house's entry shows is written to the next.

- **The set-up key is issued on the home screen's Grants card**, a rule per
  collection for configuring and, where ticked, for creating, modifying and
  reading, since a script that sets a house up brings its records in too; a
  single collection's rule on its badge. Publishing a field weighs what setting
  a public price does: a changed configuration is logged under the key's name,
  the fields published named first, as a field published, made internal, added
  or taken off in the admin is logged under the colleague's, and the key's
  sentence in the admin says it may. What stays by hand: the house's languages
  and settings, which no collection carries, and publishing and trashing.

## Reading it from PHP

Copy the PHP below into a page on ordinary webspace and it reads the feed with
nothing else: no library, no build step, and one folder `cache/` beside the
script that PHP may write. The blocks are general to any house and free to
copy, as *Licence and trademark* says, and every release runs them against a
trial install, so none breaks them unnoticed.

**The documents a page needs, in one call.** A copy is kept ten to fifteen
minutes, a span of its own so the copies a page took together do not all run
out together, and is stamped before it is fetched again, so two pages asked in
the same second refresh it once. Then it is revalidated by its `ETag`; the last
copy stands while the feed fails and goes when the address answers `404`. A key
reads what a stranger cannot, an invoice
by the bookkeeping's, and its copies are kept apart from the stranger's.
`reads()` takes every address a page needs and fetches the ones not kept
together, so the page waits for the slowest answer and not for each in turn:
against a house on another host the round trip is what costs, a `304` as much
as a `200`. `read()` is one address.

```php
function fetching(string $address, string $file, array $headers): CurlHandle
{
  if (is_file($file . '.etag')) {
    $headers[] = 'If-None-Match: ' . file_get_contents($file . '.etag');
  }
  $curl = curl_init($address);
  $etag = function ($curl, string $line) use ($file): int {
    if (stripos($line, 'ETag:') === 0) {
      file_put_contents($file . '.etag', trim(substr($line, 5)));
    }
    return strlen($line);
  };
  curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 5, CURLOPT_HEADERFUNCTION => $etag,
    CURLOPT_USERAGENT => $_SERVER['HTTP_HOST'] ?? 'php']);
  return $curl;
}

function kept(string $file, int $status, ?string $body): void
{
  if ($status === 200 && $body !== null) {
    file_put_contents($file, $body);
  } elseif ($status === 404) {
    array_map('unlink', glob($file . '*') ?: []);
  } elseif (is_file($file)) {
    touch($file);
  }
}

function reads(array $addresses, string $key = ''): array
{
  $headers = ['Accept: application/ld+json', ...($key === '' ? [] : ['Authorization: Bearer ' . $key])];
  is_dir(__DIR__ . '/cache') || mkdir(__DIR__ . '/cache');
  $files = [];
  foreach ($addresses as $address) {
    $files[$address] = __DIR__ . '/cache/' . md5($key . $address);
  }
  $multi = curl_multi_init();
  $fetching = [];
  foreach ($files as $address => $file) {
    if (!is_file($file) || filemtime($file) <= time() - 600 - crc32($file) % 300) {
      is_file($file) && touch($file);
      $fetching[$file] = fetching($address, $file, $headers);
      curl_multi_add_handle($multi, $fetching[$file]);
    }
  }
  do {
    curl_multi_exec($multi, $running);
    $running && curl_multi_select($multi);
  } while ($running);
  foreach ($fetching as $file => $curl) {
    kept($file, curl_getinfo($curl, CURLINFO_RESPONSE_CODE), curl_multi_getcontent($curl));
  }
  return array_map(fn (string $file): ?array
    => is_file($file) ? json_decode((string) file_get_contents($file), true) : null, $files);
}

function read(string $address, string $key = ''): ?array
{
  return reads([$address], $key)[$address];
}
```

**A list's members, and what an edge points at.** The two lines of consumer
contract from *Reading the feed* as code: a member is its `item`, and a `Role`
or a `ProgramMembership` is opened onto its target, one inside another, with
the edge's own words first.

```php
function members(array $list): array
{
  return array_map(fn (array $member): array => target($member['item'] ?? $member, 'item'),
    $list['itemListElement'] ?? []);
}

function target(array $value, string $property): array
{
  if (!in_array($value['@type'] ?? '', ['Role', 'ProgramMembership'], true)) {
    return $value;
  }
  $key = isset($value[$property]) ? $property : 'program';
  return [...target($value[$key], 'program'), ...array_diff_key($value, ['@type' => 1, $key => 1])];
}
```

**The catalogue's words.** A type's field names in the route's language, as
`/feed/{locale}` gives them, so a page prints *Starts* without typing it.

```php
function labels(array $catalog, string $type): array
{
  foreach ($catalog['dataset'] ?? [] as $collection) {
    if (($collection['about'] ?? '') === 'https://schema.org/' . $type) {
      return array_column($collection['variableMeasured'], 'name', 'propertyID');
    }
  }
  return [];
}
```

**A page's head.** Its address is the one it states, which only a home page
does, or its slug after its site's; its title stands alone on the home page and
before the site's name elsewhere. Its other languages are the page's
`translationOfWork` and `workTranslation`, addresses read together for their
language and their site. Where there are any, the page lists every version,
itself among them, since a search engine trusts an alternate only where each
version names every other, and the English one again as `x-default`, the
version shown to a reader of none of these languages.

```php
function address(array $site, array $page): string
{
  return $page['url'] ?? rtrim($site['url'], '/') . '/' . basename($page['@id']);
}

function head(array $site, array $page): string
{
  $title = isset($page['url']) ? $page['name'] : $page['name'] . ' – ' . $site['name'];
  $lines = ['<title>' . htmlspecialchars($title) . '</title>',
    '<meta name="description" content="'
      . htmlspecialchars($page['description'] ?? $site['description'] ?? '') . '">',
    '<link rel="canonical" href="' . htmlspecialchars(address($site, $page)) . '">'];
  $others = array_column([...$page['translationOfWork'] ?? [], ...$page['workTranslation'] ?? []], '@id');
  $versions = array_filter([$page, ...reads($others)]);
  $tagged = count($versions) > 1 ? array_column($versions, null, 'inLanguage') : [];
  $english = preg_grep('/^en/', array_keys($tagged));
  $tagged += $english === [] ? [] : ['x-default' => $tagged[reset($english)]];
  foreach ($tagged as $tag => $version) {
    $lines[] = '<link rel="alternate" hreflang="' . htmlspecialchars($tag) . '" href="'
      . htmlspecialchars(address($version['isPartOf'] ?? $site, $version)) . '">';
  }
  return implode("\n", $lines);
}
```

**Who acts for an organisation**, as an imprint names them: the people whose
`worksFor` edge to it says a `roleName`, grouped under it, and nobody whose edge
says none. A filter names an edge as a document names it, so `named()` cuts an
address to its collection and slug.

```php
function named(string $address): string
{
  return implode('/', array_slice(explode('/', $address), -2));
}

function staff(array $organization): array
{
  $people = read(dirname($organization['@id'], 2) . '/people?worksFor=' . named($organization['@id']));
  $staff = [];
  foreach (members($people ?? []) as $person) {
    foreach ($person['worksFor'] ?? [] as $edge) {
      $to = target($edge, 'worksFor');
      if (isset($to['roleName']) && $to['@id'] === $organization['@id']) {
        $staff[$to['roleName']][] = $person;
      }
    }
  }
  return $staff;
}
```

**A text's links, onto the site.** A link to a record leaves as its `@id`, so
`local()` turns every one in a rendered document before a visitor follows it:
to the page it names, or to the site's page whose `mainEntity` is the record it
names, and to its words alone where there is none. Every link is asked in one
call. A page of this site becomes its path, so a preview keeps its visitor on
the host previewed, and a page of another site its address. A page is then
`local(read($feed . '/web-pages/about?render=html'), $site)`.

```php
function local(array $document, array $site): array
{
  $pattern = '#<a href="(' . preg_quote(dirname($site['@id'], 3), '#') . '/[^"]+)">(.*?)</a>#s';
  $found = [];
  array_walk_recursive($document, function ($value) use ($pattern, &$found): void {
    preg_match_all($pattern, is_string($value) ? $value : '', $links);
    $found = [...$found, ...$links[1]];
  });
  $to = destinations(array_unique($found), $site);
  array_walk_recursive($document, function (&$value) use ($pattern, $to): void {
    $value = !is_string($value) ? $value : preg_replace_callback($pattern, fn (array $link): string
      => $to[$link[1]] === null ? $link[2] : '<a href="' . htmlspecialchars($to[$link[1]]) . '">' . $link[2] . '</a>',
      $value);
  });
  return $document;
}

function destinations(array $addresses, array $site): array
{
  $asked = [];
  foreach ($addresses as $address) {
    $asked[$address] = str_contains($address, '/web-pages/') ? $address : dirname($site['@id'], 2)
      . '/web-pages?isPartOf=' . named($site['@id']) . '&mainEntity=' . named($address);
  }
  $answers = reads($asked);
  $to = [];
  foreach ($asked as $address => $asking) {
    $page = $asking === $address ? $answers[$asking] : members($answers[$asking] ?? [])[0] ?? null;
    $to[$address] = match (true) {
      $page === null => null,
      ($page['isPartOf']['@id'] ?? null) === $site['@id'] => parse_url(address($site, $page), PHP_URL_PATH) ?: '/',
      default => address($page['isPartOf'], $page),
    };
  }
  return $to;
}
```

**A blog is these lines**, the articles newest first with their texts rendered,
and a bookkeeping view is `read($feed . '/invoices', $key)` under the same
loop.

```php
$feed = 'https://example.com/feed/de-DE';
$words = labels(read($feed), 'Article');
foreach (members(read($feed . '/articles?sort=-datePublished&render=html')) as $article) {
  echo '<article><h2>', htmlspecialchars($article['headline']), '</h2>', $article['text'] ?? '';
  echo '<p>', $words['datePublished'], ': ', htmlspecialchars($article['datePublished'] ?? '');
  echo '</p>';
  echo '</article>';
}
```

## How to build a website

One way to build one, and the way skemik.com is built, so that changing it is
changing records and not code: each thing it shows has one home in the feed,
found by a rule a person can state. The feed enforces none of it, and an
imprint may as well be a page whose `text` is typed whole.

- **The site** is a `WebSite`, the one whose `url` is the address the frontend
  serves, on each route the address it serves that language under, so
  `web-sites?url=https://www.example.com/en` on the English route where the
  English site states that. A preview names the site it shows in its own
  configuration, never in the holdings, since which host previews what is the
  deployment's and not a fact about the site.

- **The home page** is the site's `WebPage` stating the site's `url` as its
  own, the one page that states one: `web-pages?isPartOf=web-sites/…&url=…`.
  Every other page's path is its slug, `/imprint` being `web-pages/imprint`, so
  two sites of one house cannot both have an `/imprint`.

- **A page's sections** are its `hasPart`, lists in their order, each member
  drawn by its type. A list of terms is chips, alphabetical in the page's
  language, a term's `broader` kept in the data and not shown; a screenshot
  right after a claim is drawn inside the claim's card.

- **The menu and the footer** are the site's own `hasPart`: the first list is
  the menu, each further one a column of the footer. A section in them links to
  its place on the home page and a page to its path. The order means something,
  and a reordering shows at once.

- **Buttons**: the site's `potentialAction` stand on every page, a page's own
  on that page. A button is a label and a link, so `potentialAction` is written
  inline on any record as rows of `name` and `target`, and is not a record of
  the `Action` collection, which holds what somebody did.

- **The look** is the `Brand` the site is `about`. Its colours and typefaces
  are rows of its `additionalProperty`, a `name` and a `value` each, and four
  names are our convention, `color-primary`, `color-ink`, `font-body` and
  `font-display`, so a frontend built for one house finds the same tokens at
  the next. None is required and none is closed. A font's value is the
  typeface's name, and its files are the woff2 `MediaObject`s that are `about`
  the brand, `media?encodingFormat=font/woff2&about=brands/…`.

- **A page about a record**, an imprint, shows its `mainEntity`'s fields under
  the catalogue's words in the page's language, never retyped, and for an
  organisation who may act for it, under the `roleName` their `worksFor` edge
  to it says, and nobody whose edge says none:
  `people?worksFor=organizations/…`. Its `text` holds only what is typed
  nowhere, the liability and copyright wording. A register entry is a row of
  `identifier`, a `name` and a `value` each, as the brand's tokens are, so the
  page prints *Handelsregister* above *HRB 784950* and never the field's word,
  and `?identifier=` and `?q=` find the value; a bare string is refused. An
  invoice's `identifier` stays its number, one string.

- **A link in a text** goes to the page it names, or to the site's page whose
  `mainEntity` is the record it names, `web-pages?isPartOf=…&mainEntity=…`, and
  is its words alone where no page is about it. A visitor never lands in the
  feed.

- **Pictures and fonts** are served from the frontend's own copies, never from
  the feed's address in an `<img>`: a file at its `contentUrl` comes through the
  house's PHP, a worker per picture per visitor, while a copy costs the house
  one read per file in fifteen minutes. The copy is made once the page has
  left, never on a visitor's path, and a file the feed no longer serves, `404`,
  loses its copy, so a picture taken offline leaves the site within the quarter
  hour. skemik.com keeps its copies under its own `media/`.

- **Words** such as *Licence* are the catalogue's names of the fields. The site
  has none of its own.

Two rules keep it so. **Placement lives in lists and only there**: an article a
section holds names no page, since two homes for one fact drift apart, and
`isPartOf` says which site a page belongs to and nothing else. **`about` is
what a record is about**, true wherever it stands, and a site reads it only for
its own look.

## Decisions

Look a choice up here before proposing another: each says what it bought and
what it spends.

### The model

- **Thirty types and one address, tried on paper before any server code was
  written**, both being cheaper to refute there than in a product. *Do thirty
  types carry real cases?* Five small German organisations, one per kind of
  house, were mapped onto the profile item by item, and no snag asked for a
  thirty-first type. More than half the snags were ours: properties schema.org
  publishes and the profile had left out, found by reading the vocabulary and
  not by walking a sixth house, which is why the reconciliation is a check that
  runs. Two stand, recorded and not solved: a title two people hold jointly,
  and a membership that makes you a member of a third body. We drew the types
  and also sorted the items, which only an independent evaluation can close.
  *Does the address carry a page?* Three houses' real pages were rebuilt with
  `fetch` and nothing else, while changes were still edits and not migrations.
  A card's *from €129*, two records away and a request per date, became a price
  range computed when the document is written, what must not leave the house
  went through one filter at every depth, and no `expand` parameter was wanted.

### The stack

- **The toolchain is the maintenance cost, not the language.** Anything that
  must be built, resolved or locked before the code runs is a liability that
  outlives whatever it bought.

- **PHP, from 8.5 on.** Running it yourself means copying files to cheap
  webspace, where Node or Go would make a process to supervise. The app is
  written for PHP 8.5 and runs on each one after it, 8.6 and 9 as they ship,
  and no release asks for more, since an install updates itself: one that did
  would refuse every address of a house whose hoster had not moved. What this
  spends is a second version kept straight, the oldest beside the newest, and
  whatever a later PHP adds, which the code does without. And reach at the
  cheap end: a house on shared webspace can only ask its hoster for the
  version, and an install on an older one is refused on the first request,
  naming what it found and what it needs, down to 8.2: below that the page
  naming them cannot be parsed either, and the host's own 500 is the whole
  answer.

- **Pages from the server, Vue as islands.** A page that is not reactive cannot
  break. Vue normally arrives with npm, a bundler and a lockfile, and that
  toolchain is what makes a codebase expensive to return to after a year. So it
  is one committed file loaded with a `<script>` tag, used for the editor's
  parts and never as an application shell. The cost is doing without
  single-file components,
  and `unsafe-eval` in the admin's policy, since Vue compiles its templates in
  the browser; no record's text is ever compiled as one. Upgrading is replacing
  one file, and declining to is a valid position.

- **The admin and the feed say their framing and referrer policy twice**, in
  the policy header and in the page, where a browser reads it first: a host's
  front may add or replace headers of its own.

- **Plain CSS with custom properties.** A CSS framework would bring back the
  build step avoided everywhere else.

- **SQLite, and a folder of files beside it.** A folder of JSON files cannot
  answer what changed last across all types or what points at a record without
  loading everything, and the way out is an index, then invalidation, then
  locking: a database written by accident, badly. Two backends behind one
  interface is worse, every query written and tested twice for a setup question
  nobody asked. SQLite keeps the folder's appeal, no server and no credentials,
  and answers real queries. What it spends is the second machine: its
  write-ahead log is memory its processes share, which a file system mounted by
  several web servers does not carry. Uploads stay files: in the database they
  would cost byte ranges, `Content-Length` and a copy that moves gigabytes for
  a few thousand records, and a JPEG on disk is still a JPEG in twenty years.
  So the unit of state is one directory, backing up is copying it and restoring
  is copying it back. An uploaded file is never changed in place, which is what
  lets a copy be taken while the site runs.

### Files

- **An upload is an allowlist and a rename.** The stored name is generated and
  the original one is content, so `invoice.pdf.php` is a harmless string in a
  column. The type is sniffed, the browser's claim discarded, and the list of
  formats is part of the profile and not a setting. An SVG passes or is refused
  whole and is never repaired, because a sanitiser owns its parser's output
  forever, and it is served under a policy that lets it run and fetch nothing,
  since the check will one day be behind the format. Replace file on an asset's
  screen stores the new one under a new name and repoints the record, its other
  fields and its edges kept, and the old bytes leave with the last record
  holding them. That is how a translated asset gets its own, since a
  translation starts on the original's file. A name ending in the file's
  extension, `photo.jpg`, counts as the upload's and follows the new file, even
  where it was typed by hand; any other name stays, and a download is saved
  under it with the extension it lacks.

- **One preview size, made on first view.** With one size and no parameter the
  work a stranger can provoke is the library, once. Photographs only: rendering
  a PDF means Ghostscript, whose own hardening advice is not to, on a server
  whose configuration is not ours, so a PDF gets a glyph.

- **Files are the export, all four formats.** The claim a dump makes is *you
  can leave*, and it is strongest when the box holds the graph for a machine, a
  table for a spreadsheet, prose for a reader or an assistant, and pages that
  open by double-clicking. A dump is not a backup: it restores nothing, and
  posted back it gives equal content without its history. Each dump is a line
  of the owner's log: who took it, how much and under which types.

### People and keys

- **Three roles, and the one refinement is the type list.** Owner, editor and
  viewer are fixed, as the types are: an owner does everything, an editor
  writes, publishes and adds or takes out the fields of what it writes, and a
  viewer reads. Only an owner issues keys, manages accounts, purges the trash,
  reads the log, updates and backs up, and every role takes a dump of what it
  reads. An account may be narrowed to the types it writes and the types it
  reads internally, two sets of thirty checkboxes and no capability matrix: the
  list of things to tick is fixed by the release. A read scope cannot reach
  below the public, since for a type outside it the account sees what a
  stranger sees, through the same filter the feed uses and never a second one,
  counts and findings included. Reading everything is the recommendation: a
  half-visible graph is a broken graph, and the usual need is that the person
  who keeps the calendar does not edit the membership file, not that they
  cannot see it. Writing includes publishing: a separate release right covers
  only a first publication unless a live record may not be edited either, and
  its honest form is a draft beside the published record, which is a second
  product. Rights per field or per record are for machines, not sliced between
  colleagues, and two departments sharing one type are two houses.

- **An account is held by a person and is not one.** A colleague is an ordinary
  `Person` record, and the account beside it holds what this install knows of
  its relationship to them: the login address, the role, the scopes. So who
  wrote an article and who may write it are one record of one human. The login
  address is the account's and the person's `email` stays content, so
  correcting somebody's contact details cannot redirect their login. A person
  holding an account cannot be deleted, and the refusal names the account.

- **Nobody has a password.** A login is a code typed back into the browser that
  asked for it: single use, ten minutes, burned by five wrong tries. A login
  lasts two idle days and fourteen in all, ended on the server, and the account
  menu ends it in every other browser at once, for a laptop left behind. An
  account saved under another address, or disabled, loses its logins and the
  codes on their way, so neither outlives the mailbox it was proven for. The
  reset is the login, so there is nothing to reuse from another site's breach
  and no forgotten-owner dead end in a house with one owner and nobody to reset
  them, and the last owner cannot be demoted, disabled or deleted. The
  Accounts screen says when each last logged in, so an account nobody uses is
  seen.

- **A login answers the same to everybody.** The answer is one sentence whether
  or not the address holds an account, and takes as long, since the code is
  issued and sent once the answer has left, except where PHP cannot end an
  answer before its work, as *Requirements* says. Ten codes an hour per login
  address and client together, five tries a quarter hour per browser, and
  twenty codes an hour per login address whatever the client, past which the
  code is still written to `login/` and no mail leaves: one client spends half
  of a mailbox's twenty, and a colleague's mail is withheld only while several
  ask for their login address.

- **A code is written down as well as mailed**, because `mail()` answers true
  for a message the host drops, and whoever reads `login/` reads the holdings
  beside it anyway: an install cannot lock out its own operator. SMTP is built
  in, some hundred and fifty lines and no library, because good hosting is
  increasingly hosting with `mail()` switched off. The relay's password is in
  `config.php` and not in the holdings, so a copy handed to a client carries no
  credential, and a restore onto another host loses the relay, as it should.

- **Refused, because both will be proposed again.** Encrypting the holdings
  with a key the hoster cannot use defends the door of a house whose walls are
  glass: the server filters, sorts and indexes the holdings in plaintext, so
  whoever has the machine needs no login. A second factor hardens one case, the
  mailbox being taken, and sells a lockout to an owner who is one person.

- **Machines get keys, never accounts.** A key's grant is a set of rules, each
  naming a collection, optionally one field and one record, and one of
  *create*, *modify*, *read* and *configure*, the last on the collection whole.
  A customer reading what is theirs is such a key, not a fourth role. Every key
  expires, two years after its issue at the latest, and *never* cannot be said,
  not even as 2099: the integration that breaks on a known date is the one
  somebody renews on purpose. A key's grant is narrowed and never widened: to
  renew a key or give it more, a new one is issued under the same name, holding
  the old one's rules and what is added, said before it is issued, while the
  old one runs to its date or, where the owner ticks so, every older key of the
  name stops at once. Taking back a key's last rule revokes it. In one
  collection a key reads one way, the whole collection or some of its records,
  each with the same fields, since its filter, sort and search there are the
  collection's: reading it a second way takes a second key under its own name.
  The name is required because the log signs every write with it, and the key's
  screen says when it was last used, to the hour, so one nothing uses any more
  is seen and revoked. A key is issued where it applies, on the collection, the
  field or the record, since *let this one thing be written from outside* is a
  thought somebody has while looking at it.

### Records

- **Two words of the feed stand off their domain.** `isAccessibleForFree` on a
  field's entry and the profile's `inLanguage`: schema.org declares both on
  `CreativeWork` and `Event`, and neither on a `PropertyValue`, which has no
  word for whether a field is public or whether its words differ by language.
  They stand there because a property of our own would be the second
  vocabulary they are there to avoid, and only a key that configures the
  collection reads the flag, or the dump of a colleague who may.

- **A relation is typed at one end.** A part names its whole, an offer its
  item, a review what it reviews, and the whole's list of them is no field: the
  inverse is the same fact a second time, and nothing would keep the two
  agreeing. The whole's screen shows what points at it. What this spends is one
  request: a page asks for an article's comments where it could have read them
  nested. The exception is a list, whose members are its own, in its order.
  Purging the target of a required edge is refused, naming the records that
  need it, and every other record losing an edge to a purge counts a version
  and a line in its log, as it would had a save taken the edge out.

- **Deleting is deleting.** A record purged from its collection's trash leaves
  the holdings with its edges and its search words, and its lines in the log
  stay, naming it by its type alone. Two things in the log can still name a
  person, and the owner takes each out: the name a colleague signed their lines
  with, when their record is purged and the box is ticked, and a former address
  on its line, since a slug can be a name. An order, an invoice and a
  reservation warn before they are purged, since they usually fall under a
  retention duty. Anything else about a person is found under *Points here* on
  their record and dealt with by hand. A snapshot still holds all of it until
  it ages out, after about thirty days.

- **Writes are conditional, and that is not a new verb.** A record's version
  goes out as its `ETag` and comes back as `If-Match`: no `decrement`, no
  reservation of stock, no fourth capability. The same carrier stops two
  colleagues with one record open from discarding each other's work.

### Words

- **The words come in files per language, English the source.** A word
  missing from a translation falls back to English, so another language is
  three files, its words, its field names and what a select's options are
  called, and no code. English rather than German, although the first houses
  are German: every later language would otherwise be translated out of one
  most translators do not have. German, French, Spanish, Italian and Polish
  ship beside it, and Swiss German as a region's file, `de-CH.php` beside
  `de.php`, holding only the words its spelling differs in and read first, word
  by word, in the admin and in the profile's field names, so a browser asking
  for `de-CH`, and the profile of a `de-CH` route, read *Strasse* and German
  everywhere else; a region ships as its chrome file, its field names beside it.
  The language is the reader's and not the house's, since a bookkeeper and an
  editor need not share one: the browser's decides, and the one chosen at
  `/install` stands where the browser names none a file ships for. It is no
  content language: a house publishing German and English may be edited in
  French, and none of the admin's own words reaches a document. The one crossing
  is the first run, since a house set up in English should not write its first
  record as German. The region is the reader's too, and writes the admin's dates
  and numbers whether or not it spells differently, *€ 1.234,50* in `de-AT` and
  *16 Jan 2026, 14:05* in `en-GB`: a spelling is a file somebody keeps, while
  every region's formats come with PHP's `intl`.

- **A field's name is ours, in every language.** The fields are named before
  you start, and that holds for their words too: a file per language says what
  the thirty types' fields are called, the admin shows them in the editor's
  language and the feed's catalogue in the language of the content. A house
  cannot rename one: a renamed field would say *Monthly fee* on the screen and
  `price` at the address, one word for every content language, and the next
  agency would read two vocabularies. The word a page shows is the page's.

## Security

Report a vulnerability in Skemik to security@skemik.org, in German or English,
and not in a public issue. plue GmbH is the manufacturer of every release and
answers there, whoever installed it and under whichever licence.

Every install answers `/.well-known/security.txt` automatically, as RFC 9116
words it, for the researcher or the scanner who finds a house and not us. Its
first `Contact` is the one `config.php` names,
`'security_contact' => 'it@example.com'`, an address or a link, the house or the
agency that runs the install. Ours follows as the manufacturer's of the
software, never as the site's owner, and stands alone where `config.php` names
none. Its `Expires` is a year after the day of the release the install runs, so
one that has not updated for a year says of itself that it is stale. A
`.well-known/security.txt` the operator writes into the domain root is answered
in its place, as written.

A fix arrives as the next release and nowhere else: there is one line, the
newest, and nothing is carried back to an older one. An install that follows
updates has the fix within the hour, its update screen saying that it was a
security release, and one that declines them takes it by copying the newest
release. A vulnerability reported in a release before its Change Date, three
years after its day as its `LICENSE` names, is fixed in the newest release.

## Contributing

Open an issue to report or to discuss; pull requests are not merged. Skemik is
closed to outside contributions, said plainly. Every line has one owner, and
that is what lets each release name the day it becomes free software. One
merged outside contribution, and neither that date nor any later change of
licence is possible without its author.

## Licence and trademark

Source available, not Open Source: the Business Source License is not an OSI
licence. A release is under it until its Change Date and under AGPLv3 from that
day on.

- **Running it for clients is free, offering it to many is not.** Anybody may
  install, host, operate and maintain it in production for any number of
  clients, on their machines or on one's own, and charge for it. Until a
  release's Change Date the licence withholds one thing: operating it for
  others where that operation is offered or advertised to many at once, so no
  Skemik hosting by public offer, newsletter or price list, and none as a
  one-click installation or a pre-installed image of a hosting product. A
  licence for that is asked of plue GmbH, for a fee.

- **The code blocks in this README are free to copy.** A read with a cache
  pasted into a client's site is that site's, so every block here is also under
  the Zero-Clause BSD licence, `0BSD`: use, change and pass it on without
  restriction and without naming us. Everything else at the tag is under the
  licence with the code.

- **The name is not part of the licence.** Skemik™ and its mark are plue
  GmbH's. Say truthfully what something works with — for example *a theme for
  Skemik*. A fork takes a name of its own, and nothing calls itself Skemik or
  reads as coming from us.

- **Every release stamps its own version and an absolute Change Date** into the
  `LICENSE` it ships: the day of the release commit plus three years, written
  as a date, never a formula a licensee has to evaluate.

- **The licence unit is the released artifact, never a version family.**
  `1.4.7` carries its own date and does not inherit `1.4.0`'s.

- **It covers the repository at the release's tag and not only its code**, so
  this README converts to AGPLv3 with the app.

---

Copyright © 2026 [plue GmbH](https://plue.tech) · Skemik™ is a trademark of plue GmbH.
