[![Develop - build Status](https://travis-ci.com/UN-OCHA/slt8-site.svg?token=q5DydpJDYUBJoayLktvd&branch=develop)](https://travis-ci.com/UN-OCHA/slt8-site)
[![Main - build Status](https://travis-ci.com/UN-OCHA/slt8-site.svg?token=q5DydpJDYUBJoayLktvd&branch=main)](https://travis-ci.com/UN-OCHA/slt8-site)
![Build docker image](https://github.com/UN-OCHA/slt8-site/workflows/Build%20docker%20image/badge.svg)

Saving Lives Together (SLT) site - Drupal 8 version
===================================================

This is the drupal 8 codebase for the [Saving Lives Together](https://savinglivestogether.unocha.org) site.

Migration
---------

The migration is handled by the [slt_migrate](html/modules/custom/slt_migrate)
module.

Connection settings to the Drupal 7 database should be defined in a settings.php
file with the name `slt7`.

Run `drush mim --group=slt`.

Content
-------

The site has 3 types of content: `contacts`, `public pages` and `private pages`.
The site also contains `images` and `documents`, managed as `media` entities.
All the files are private but images on public pages are accessible to all.

**Pages and Paragraphs**

The public and private pages contain a unique field that can accept different
types of paragraphs like hero image, text, links and even a page title
paragraph. Those paragraphs can be arranged via [*layout paragraphs*](https://www.drupal.org/project/layout_paragraphs) to define
multi columns sections or image grids for example.

Themes
------

The site uses the DSS common design and views and paragraphs for the content.

Theme customizations are in the
[Common design subtheme](html/themes/custom/common_design_subtheme).

The site also has an administration sub-theme extending the `seven` theme and
providing a few tweaks to the admin interface like full width node forms:
[Common design admin subtheme](html/themes/custom/common_design_admin_subtheme).

Modules
-------

The main contrib modules for this site are the [paragraphs](https://www.drupal.org/project/paragraphs) related ones (see
[composer file](composer.json).

In addition, the site has several custom modules:

- [**SLT Access**](html/modules/custom/slt_access)

  The slt_access module provides granular view permissions for node and media
  entities as well as handling the access to images on public pages, and a
  permission to assign roles.

- [**SLT Contacts**](html/modules/custom/slt_contacts)

  The slt_contacts module provides a form (`/admin/content/contacts/import`) to
  import contacts from a spreadsheet. It also handles the filtering by country
  and the ordering by security role of the contact list (`/contacts`).

- [**SLT General**](html/modules/custom/slt_general)

  The slt_general module provides tests of the site as well as general
  customizations like "add content" action links for the different content
  admin pages (`/admin/content`)

- [**SLT Layouts**](html/modules/custom/slt_layouts)

  The slt_layouts module provides addition layouts to use with modules relying
  on the Layout API to arrange display (ex: layout builder module or
  [layout_paragraphs](https://www.drupal.org/project/layout_paragraphs) in the
  case of SLT). This module provides notably a layout plugin to handle
  configurable grids with any number of areas and 2 image grid layouts based on
  it.

- [**SLT Migrate**](html/modules/custom/slt_migrate)

  The slt_migrate module provides plugins and migration configuration to migrate
  content from the SLT drupal 7 site. It notably converts SLT drupal 7 page
  node content to paragraphs (see [SltNode source plugin](html/modules/custom/slt_migrate/src/Plugin/migrate/source/SltNode.php)).

The site has 3 more custom modules that could/should be separated from the SLT
codebase to be independent modules that other sites could use:

- [**Telephone Type**](html/modules/custom/telephone_type)

  The telephone_type module is similar to the `telephone` field core module but
  adds an extra `type` sub-field to indicate the type of phone number (ex:
  business, work cell phone etc.).

- [**Paragraphs - Page title**](html/modules/custom/paragraphs_page_title)

  The paragraphs_page_title module provides a paragraph type and associated
  theme, template and preprocess function to display the page title in a
  similar fashion to the `page_title` block.

  In the case of SLT, this is used, in combination to an image paragraph type,
  to display a hero image followed by the page title, allowing the content of
  the SLT node private and public pages to be fully managed and structured via
  paragraphs.

  Note: when using this module the `page_title` block visibility for the
  content type using page title paragraphs should be changed so that page titles
  don't appear multiple times.

- [**Linked Responsive Image Media Formatter**](html/modules/custom/linked_responsive_image_media_formatter)

  The linked_responsive_image_media_formatter module, in addition to competing
  for the longest module name, provides a formatter for image media types. This
  formatter can be used to display the image using responsive image styles and
  with extended linking options: link to content, link to media, link to image
  and custom link that can use `tokens`. It also offers the opion to set a
  custom `alt` text using `tokens` as well and an option to display the image
  as background for the link, using the `alt` text as text for the link.

  In the case of SLT, there is a `image link` paragraph type with a media
  reference field (image media) and a link field. The formatter for the image
  media is configured to have the image linking to the URL from the link field
  using a token, and to use the link field text as alt text via a token as well.

  This paragraph type is used in 2 different places, combined with a layout
  paragraph, to display an image grid of partner logos linking to the partner
  sites, and a custom menu with links to SLT pages, with a background image for
  each link.


Notes
-----

Some notes related to the initial installation and development are available in
the [notes.md](notes.md) file.

Todo
----

- [ ] Consolidate Hero image size (`object-fit: cover` etc.).
- [ ] Review responsiveness of image grids.
- [ ] Check `optimize_image_binaries` module and if `pngquant` is available


Local development
-------------

For local development, add this line to settings.local.php:
`$config['config_split.config_split.config_dev']['status'] = TRUE;`
After importing a fresh database, run `drush cim` to enable devel, database log
stage_file_proxy and views_ui.

Local testing
-------------

**With Docksal**

Note: Replace `test.slt8-site.docksal` below with the appriate hostname for
your local site.

```bash
mkdir -p ./html/sites/test
cp ./.travis/local/* ./html/sites/test/

fin db create test
fin drush --uri=test.slt8-site.docksal si minimal -y
fin drush --uri=test.slt8-site.docksal cset system.site uuid $(grep uuid ./config/system.site.yml | awk '{print $2}') -y
fin drush --uri=test.slt8-site.docksal cim -y
fin drush --uri=test.slt8-site.docksal cr

fin drush --uri=test.slt8-site.docksal en yaml_content -y
fin drush --uri=test.slt8-site.docksal yaml-content-import /var/www/.travis/
```

Run tests using docksal

```bash
fin exec DTT_BASE_URL=http://test.slt8-site.docksal/ ./vendor/bin/phpunit --debug --colors --testsuite=existing-site,existing-site-javascript --printer '\Drupal\Tests\Listeners\HtmlOutputPrinter'
```
