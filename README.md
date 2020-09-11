[![Develop - build Status](https://travis-ci.com/UN-OCHA/slt8-site.svg?token=q5DydpJDYUBJoayLktvd&branch=develop)](https://travis-ci.com/UN-OCHA/slt8-site)
[![Main - build Status](https://travis-ci.com/UN-OCHA/slt8-site.svg?token=q5DydpJDYUBJoayLktvd&branch=main)](https://travis-ci.com/UN-OCHA/slt8-site)
![Build docker image](https://github.com/UN-OCHA/slt8-site/workflows/Build%20docker%20image/badge.svg)

Saving Lives Together (SLT) site - Drupal 8 version
===================================================

Migration
---------

The migration is handled by the [slt_migrate](html/modules/custom/slt_migrate)
module.

Connection settings to the Drupal 7 database should be defined in a settings.php
file with the name `slt7`.

Run `drush mim --group=slt`.

Theme
-----

The site uses the DSS common design and mainly views for the different blocks
arranged on the pages via the Drupal's built-in Layout Builder module.

Theme customizations are in the
[Common design subtheme](html/themes/custom/common_design_subtheme).

Notes
-----

Some notes related to the initial installation and development are available in
the [notes.md](notes.md) file.

Todo
----

- [ ] Allow anonymous access to media that are least referenced by one publicly
      accessible entity. See https://www.drupal.org/project/media_private_access
      and https://www.drupal.org/project/entity_usage.
- [ ] Allow embedding image media via CKEditor and allow selection of view mode
      to handle image size and responsiveness.
- [ ] Convert images in HTML blobs to `<drupal-media>` during migration.
- [ ] Evaluate preserving the Hero image size and crop it instead on smaller
      screen (`object-fit: cover` etc.).
- [ ] Add styles for twocol and treecol layouts.
- [ ] Review access to content, media and admin pages.
- [ ] Fix `z-index` for autocomplete widgets in paragraphs.
- [ ] Create sort plugin for the contacts view to handle the custom order by
      security title
- [ ] Check `optimize_image_binaries` module and if `pngquant` is available

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
