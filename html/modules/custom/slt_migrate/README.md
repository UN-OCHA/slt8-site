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

Files
-----

The file migration doesn't copy the files, this will be done independently by
copying the file directories.

As opposed to the drupal 7 site, all the files are private and their access
is dependent of the page they are displayed on.

Public and private pages
------------------------

Public pages didn't exist in the drupal 7 site. There was a single basic page
content type and the `content_access` module was used to control access.

However, this basically resulted in having public pages and private pages (most
pages). So, in drupal 8, we simply model that by having 2 different types of
pages: public and private and using the basic built-in roles and permissions
to control acess.

Paragraphs
----------

The drupal 8 pages have only field which can reference different types of
paragraphs. The content for the drupal 7 pages is converted to paragraphs.

Notes
-----

If you revert the migration, make sure to run `drush cron` or visit
the /admin/config/system/delete-orphans page to delete the orphan paragraphs as
entity_reference_revisions doesn't do that directly when the host entity is
removed but instead put the child entities to delete in a queue.
