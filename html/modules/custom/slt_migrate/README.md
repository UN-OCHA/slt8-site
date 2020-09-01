Saving Lives Together - Migrate module
======================================

This module handles migration from Drupal 7 to Drupal 8.

Migrated content
----------------

In migration order:

1. URL aliases
2. Users
3. Files
4. Document file entities (media)
5. Image file entities (media)
6. Menu
7. Private page nodes
8. Public page nodes
9. Menu links
10. Contact nodes
11. Taxonomy vocabularies
12. Taxonomy terms

**Files**

The file migration doesn't copy the files, this will be done independently by
copying the file directories.

Public and private pages
------------------------

Public pages didn't exist in the drupal 7 site. There was a single basic page
content type and the `content_access` module was used to control access.

However, this basically resulted in having public pages and private pages (most
pages). So, in drupal 8, we simply model that by having 2 different type of
pages: public and private and using the basic built-in roles and permissions
to control acess.

Layouts
-------

There is currently no way to export individual layouts created via the Drupal 8
layout builder as configuration.

That's not a big issue but we need them for the production migration, so this
module provides a drush command to export those individual layouts created
**during development** so that they can be imported during the migration.

Development steps:

1. Migrate content
2. Create the layouts in Drupal 8
3. Export them with `drush slt-migrate:export-layouts`
4. Commit the changes

Now everytime the migration is run somewhere, the exported layouts will be
imported when migrating the corresponding nodes.
