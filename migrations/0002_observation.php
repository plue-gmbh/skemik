<?php

declare(strict_types=1);

/* Observation, the thirty-first type, answers on every locale, so the partial
   index naming those types names it too. A database holds no observation yet,
   so no two can share a slug. Frozen as `Storage\Schema` made it, and never
   edited again. */
return function (\PDO $db): void {
  $db->exec('DROP INDEX entity_slug_across_locales');
  $db->exec(<<<'SQL'
    CREATE UNIQUE INDEX entity_slug_across_locales on entity (type, slug)
      where type in ('https://schema.org/Action', 'https://schema.org/Brand',
        'https://schema.org/Comment', 'https://schema.org/Course', 'https://schema.org/Dataset',
        'https://schema.org/DefinedTerm', 'https://schema.org/Event',
        'https://schema.org/Invoice', 'https://schema.org/LocalBusiness',
        'https://schema.org/MediaObject', 'https://schema.org/MemberProgram',
        'https://schema.org/Message', 'https://schema.org/Observation', 'https://schema.org/Offer',
        'https://schema.org/Order', 'https://schema.org/Organization', 'https://schema.org/Person',
        'https://schema.org/Place', 'https://schema.org/Product',
        'https://schema.org/Reservation', 'https://schema.org/Review',
        'https://schema.org/Service', 'https://schema.org/SoftwareApplication')
    SQL);
};
