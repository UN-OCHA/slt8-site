Notes
=====

List of notes related to the installation and other issues discovered during
development.

**Composer memory issue**

Run `COMPOSER_MEMORY_LIMIT=-1 composer install`

**Fist installation with drush or drupal console**

Run `drush site:install minimal --site-name="OCHA Donor Support Group" --site-mail="admin@slt8.test"`

This will add the database settings and hash_salt to the `html/sites/default/settings.php` and should probably be removed and added to `/srv/www/shared/settings/settings.local.php` instead.

**Import configuration after installation**

1. Change the site uid: `drush -y config-set "system.site" uuid "UUID_FROM_CONFIG_SYNC_SYSTEM_SITE_YML"`
2. Run `drush -y config:import`

**Reload migration configurations**

Run `drush slt-mrc`

**View overriden configuration**

For example, to view HID settings defined in a dev settings.php:

Run `drush cget social_auth_hid.settings --include-overridden`

**Missing User 0**

After a core update, it may happen that the user 0 (anonymous) is removed which
breaks for example the migration.

To recreate it:

1. Generate a uuid: `drush ev 'echo \Drupal::service("uuid")->generate() . PHP_EOL;'`
2. Create the user (replace UUID with the uuid from above):
    - `drush sql-query "INSERT INTO users (uid, uuid, langcode) VALUES (0, 'UUID', 'en');"`
    - `drush sql-query "INSERT INTO users_field_data (uid, langcode, preferred_langcode, preferred_admin_langcode, name, pass, mail, timezone, status, created, changed, access, login, init, default_langcode) VALUES ('0', 'en', 'en', NULL, '', NULL, NULL, 'UTC', '1', '1493916010', NULL, '0', '0', NULL, '1');"`
