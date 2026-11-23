<?php

declare(strict_types=1);

/* What a grown install's screens ask of every row: the newest records of a
   type, its trash, a key's and a record's lines of the log, the window edges
   passed, and each select field's tokens, which the query bar offers, as a
   generated column with its index. Frozen as `Storage\Schema` made it, and never
   edited again. */
return function (\PDO $db): void {
  $statements = [
    'CREATE INDEX entity_newest on entity (type, created_at, id)',
    'CREATE INDEX entity_trashed on entity (type) where deleted_at is not null',
    'CREATE INDEX entity_publish_from on entity (publish_from) where publish_from is not null',
    'CREATE INDEX entity_publish_to on entity (publish_to) where publish_to is not null',
    'CREATE INDEX activity_target on activity (target_id, start_time)',
    'CREATE INDEX activity_actor on activity (actor_id, start_time)',
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_address_country any generated always as (case when type in
      ('https://schema.org/Person', 'https://schema.org/Organization', 'https://schema.org/LocalBusiness',
      'https://schema.org/Place') then json_extract(props, '$.address_country') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_currency any generated always as (case when type in
      ('https://schema.org/HowTo', 'https://schema.org/Invoice') then json_extract(props, '$.currency') end)
      virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_book_format any generated always as (case when type in
      ('https://schema.org/Book') then json_extract(props, '$.book_format') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_in_language any generated always as (case when type in
      ('https://schema.org/Comment', 'https://schema.org/Message', 'https://schema.org/Event') then
      json_extract(props, '$.in_language') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_item_condition any generated always as (case when type in
      ('https://schema.org/Product', 'https://schema.org/Offer') then json_extract(props, '$.item_condition')
      end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_country_of_origin any generated always as (case when type in
      ('https://schema.org/Product') then json_extract(props, '$.country_of_origin') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_has_merchant_return_policy_category any generated always as (case
      when type in ('https://schema.org/Product', 'https://schema.org/Offer') then json_extract(props,
      '$.has_merchant_return_policy_category') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_has_merchant_return_policy_method any generated always as (case when
      type in ('https://schema.org/Product', 'https://schema.org/Offer') then json_extract(props,
      '$.has_merchant_return_policy_method') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_has_merchant_return_policy_fees any generated always as (case when
      type in ('https://schema.org/Product', 'https://schema.org/Offer') then json_extract(props,
      '$.has_merchant_return_policy_fees') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_provider_mobility any generated always as (case when type in
      ('https://schema.org/Service') then json_extract(props, '$.provider_mobility') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_price_currency any generated always as (case when type in
      ('https://schema.org/Offer', 'https://schema.org/Reservation') then json_extract(props,
      '$.price_currency') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_availability any generated always as (case when type in
      ('https://schema.org/Offer') then json_extract(props, '$.availability') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_order_status any generated always as (case when type in
      ('https://schema.org/Order') then json_extract(props, '$.order_status') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_discount_currency any generated always as (case when type in
      ('https://schema.org/Order') then json_extract(props, '$.discount_currency') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_billing_address_country any generated always as (case when type in
      ('https://schema.org/Order') then json_extract(props, '$.billing_address_country') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_payment_status any generated always as (case when type in
      ('https://schema.org/Invoice') then json_extract(props, '$.payment_status') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_event_status any generated always as (case when type in
      ('https://schema.org/Event') then json_extract(props, '$.event_status') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_event_attendance_mode any generated always as (case when type in
      ('https://schema.org/Event') then json_extract(props, '$.event_attendance_mode') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_reservation_status any generated always as (case when type in
      ('https://schema.org/Reservation') then json_extract(props, '$.reservation_status') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_variable_measured any generated always as (case when type in
      ('https://schema.org/Observation') then json_extract(props, '$.variable_measured') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_job_location_type any generated always as (case when type in
      ('https://schema.org/JobPosting') then json_extract(props, '$.job_location_type') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_salary_currency any generated always as (case when type in
      ('https://schema.org/JobPosting') then json_extract(props, '$.salary_currency') end) virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_application_category any generated always as (case when type in
      ('https://schema.org/SoftwareApplication') then json_extract(props, '$.application_category') end)
      virtual
    SQL,
    <<<'SQL'
    ALTER TABLE entity ADD COLUMN prop_action_status any generated always as (case when type in
      ('https://schema.org/Action') then json_extract(props, '$.action_status') end) virtual
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_address_country on entity (type, prop_address_country, deleted_at) where
      prop_address_country is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_currency on entity (type, prop_currency, deleted_at) where prop_currency is not
      null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_book_format on entity (type, prop_book_format, deleted_at) where
      prop_book_format is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_in_language on entity (type, prop_in_language, deleted_at) where
      prop_in_language is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_item_condition on entity (type, prop_item_condition, deleted_at) where
      prop_item_condition is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_country_of_origin on entity (type, prop_country_of_origin, deleted_at) where
      prop_country_of_origin is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_has_merchant_return_policy_category on entity (type,
      prop_has_merchant_return_policy_category, deleted_at) where prop_has_merchant_return_policy_category is
      not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_has_merchant_return_policy_method on entity (type,
      prop_has_merchant_return_policy_method, deleted_at) where prop_has_merchant_return_policy_method is not
      null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_has_merchant_return_policy_fees on entity (type,
      prop_has_merchant_return_policy_fees, deleted_at) where prop_has_merchant_return_policy_fees is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_provider_mobility on entity (type, prop_provider_mobility, deleted_at) where
      prop_provider_mobility is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_price_currency on entity (type, prop_price_currency, deleted_at) where
      prop_price_currency is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_availability on entity (type, prop_availability, deleted_at) where
      prop_availability is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_order_status on entity (type, prop_order_status, deleted_at) where
      prop_order_status is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_discount_currency on entity (type, prop_discount_currency, deleted_at) where
      prop_discount_currency is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_billing_address_country on entity (type, prop_billing_address_country,
      deleted_at) where prop_billing_address_country is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_payment_status on entity (type, prop_payment_status, deleted_at) where
      prop_payment_status is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_event_status on entity (type, prop_event_status, deleted_at) where
      prop_event_status is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_event_attendance_mode on entity (type, prop_event_attendance_mode, deleted_at)
      where prop_event_attendance_mode is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_reservation_status on entity (type, prop_reservation_status, deleted_at) where
      prop_reservation_status is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_variable_measured on entity (type, prop_variable_measured, deleted_at) where
      prop_variable_measured is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_job_location_type on entity (type, prop_job_location_type, deleted_at) where
      prop_job_location_type is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_salary_currency on entity (type, prop_salary_currency, deleted_at) where
      prop_salary_currency is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_application_category on entity (type, prop_application_category, deleted_at)
      where prop_application_category is not null
    SQL,
    <<<'SQL'
    CREATE INDEX entity_prop_action_status on entity (type, prop_action_status, deleted_at) where
      prop_action_status is not null
    SQL,
  ];
  foreach ($statements as $statement) {
    $db->exec($statement);
  }
};
