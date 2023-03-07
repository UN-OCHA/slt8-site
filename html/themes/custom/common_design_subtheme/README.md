OCHA Common Design sub theme for the Drupal 8 Saving Lives Together (SLT) site
==============================================================================

See below for [generic information](#ocha-common-design-sub-theme-for-drupal-8)
about the OCHA Common Design sub theme.

Requirements
------------

The customizations for the SLT site require the installation of the
[components](https://www.drupal.org/project/components) drupal module.

Issues
------

The heading hierarchy is "incorrect" because the user menu at the top has a `<h2>`
title while the `<h1>` appears later in the page.

For SLT, currently the `<h1>` is the node or page title (not the site name in
the header), which works fine because the homepage is a node with its own title
corresponding to the site name. This is more "problematic" for other sites like
https://reliefweb.int where the homepage is a series of sections and having the
site logo/name as `<h1>` makes more sense. There is an open discussion about that
[here](https://humanitarian.atlassian.net/browse/CD-208), though the problem
with the heading in the user menu is still an issue in terms of hierarchy.

Reference: https://www.w3.org/WAI/tutorials/page-structure/headings/

Notes
-----

**Site name**

Ensure `site_name` is selected in `/admin/structure/block/manage/sitebranding`
so that it's available in the `system-branding` block.

**CD table component**

The [`cd-table`](components/cd-table) component is a temporary averride of the
common design theme's cd-table component while changes/improvements are being
[discussed](https://humanitarian.atlassian.net/browse/CD-219) and should be
removed if the modifications are added upstream.

**Page title block**

As described in https://www.drupal.org/project/drupal/issues/2887071, using
the visibility options on the page title for example to hide it on some node
pages will cause the page title on views pages and maybe other places to be
hidden as well... So instead we remove the page title block in a
hook_preproprecess_page() if it was already rendered by a page title paragraph.

Customizations
--------------

The list below contains additions to the default common design subtheme:

**Base styling**

- [CD header](components/cd/cd-header/cd-header.css)

  Added `position: relative;` to `.cd-header` to fix position of the main menu
  dropdown. This could/should be added to the `common_design` theme.

  Changed the max-width for the menu items to accommodate the long titles.

- [CD logo](components/cd/cd-header/cd-logo.css)

  Adjusted max-width of the logo and use compact version on mobile.

**Components**

- [components/cd-table](components/cd-table):

  Styling for the common design tables (see note above).

- [components/slt-contact-table](components/slt-contact-table):

  Styling for the table with the list of contacts (`/contacts`).

- [components/slt-hero](components/slt-hero):

  Styling for the hero image (paragraph) displayed on public and private pages.

- [components/slt-image-link](components/slt-image-link):

  Styling for the links with a background image and linked images.

**Layouts**

- [layouts/twocol_section](layouts/twocol_section):

  Overrides the layout builder two columns section to add margins and use the
  common_design breakpoints.

- [layouts/threecol_section](layouts/threecol_section):

  Overrides the layout builder three columns section to add margins and use the
  common_design breakpoints.

- [layouts/fourcol_section](layouts/fourcol_section):

  Overrides the layout builder four columns section to add margins and use the
  common_design breakpoints.

**Templates**

- [Site logo block (system branding)](templates/block/block--system-branding-block.html.twig)

  This block is for the site logo with the link to the homepage. The overrides
  removes the wrapping `h1`.
  In the case of SLT, the homepage is a node with the site title so we
  don't need to have a `h1` there. Other non-node pages use the `page-title`
  block which uses a `h1` tag as well. So that should be fairly consistent.

- [SLT contact table](templates/views/views-view-table--contacts.html.twig):

  Override of the views table template to use the `slt-contact-table`
  component for the list of contacts.

- [Paragraph - Hero image](templates/paragraphs/paragraph--image--hero-image.html.twig):

  Override of the paragraph template to use the `slt-hero` component for the
  Hero images. This is applied when an **image** paragraph has its **view mode**
  set to `Hero image`.

- [Paragraph - Link with background image (small)](templates/paragraphs/paragraph--image-link--link-with-background-image-small.html.twig):

  Override of the paragraph template to use the `slt-image-link` component.
  This is applied when an **image-link** paragraph has its **view mode**
  set to `Link with background image - Small`.

- [Paragraph - Linked image (medium)](templates/paragraphs/paragraph--image-link--linked-image-medium.html.twig):

  Override of the paragraph template to use the `slt-image-link` component.
  This is applied when an **image-link** paragraph has its **view mode**
  set to `Linked image - medium`.

- [Image field in media](templates/field/field--media--field-media-image--image.html.twig):

  Override of the image media image field template to reduce the number of
  wrapping tags.

- [Media](templates/content/media.html.twig):

  Override of the media template to change the wrapping element to `div` instead
  of `article` which doesn't make so much semantic sense in the paragraphs
  context in which they are displayed in SLT.

**Preprocessors**

- The [common_design_subtheme.theme](common_design_subtheme.theme) file contains
  a fiew preprocess hooks to work with the new components and page styling.

  It also contains a preprocessor to parse formatted texts and attach the
  `cd-table` component and add the relevant classes when they contain a table.

  This ensures display consistency for the tables and avoid adding the
  `cd-table` classes in the content stored in the database which is preferable
  in case it is decided to use a different component or theme.

  There are also other preprocess and helper functions to ensure the local tasks
  (edit etc.) are displayed on node pages that use a page title paragraph to
  display their title.

**Overrides**

- Header: [Logos](img/logos)
- Header: [OCHA services](templates/cd/cd-header/cd-ocha.html.twig)
- Footer: [Social menu](templates/cd/cd-footer/cd-social-menu.html.twig)

---

# OCHA Common Design sub-theme for Drupal 9+

A starterkit to use the [OCHA Common Design](https://github.com/UN-OCHA/common_design) base-theme in a way that allows for "upstream" changes such as security updates, new features, and so forth. The sub-theme is ready to help you implement the following types of customizations:

- Customise your site colors
- Add your own icons to the SVG sprite
- Override/extend base-theme templates
- Adding/overriding/extending frontend components

Refer to [Drupal 9+ Theming documentation][theming-docs] for more information.

  [theming-docs]: https://www.drupal.org/docs/theming-drupal


## Getting started

1. Copy the `common_design_subtheme` directory from the base-theme into `/themes/custom/` of the Drupal site.
2. Rename the `common_design_subtheme.info.yml.example` to `common_design_subtheme.info.yml`
3. In the Drupal Admin, go to Appearance, find **OCHA Common Design sub theme** and select **Enable and set default**.
4. Customize the `name`/`description` fields of the sub-theme's info file if you wish.
5. Rebuild the cache.
6. Edit the sub-theme's `css/brand.css` to pick your site's palette. No other modifications are necessary.


### Customise the logo

- Set the logo `logo: 'img/logos/your-logo.svg'` in the `common_design_subtheme.info.yml` file.
- Adjust `--brand-logo-width` inside `css/brand.css`


### Customise the favicon and homescreen icons

Replace the favicon in the theme's root, and the homescreen icons in `img/` with branded versions


### Customise colours

Change the CSS Vars in `css/brand.css` and save the file. Sass is no longer available in CD v8.


### Customise icons

- Copy SVG icons from the [Humanitarian icon set][cd-icons] into the sub-theme `img/icons` directory and follow the instructions in the [common_design README][cd-icons-readme] to generate a sprite with those new icons.
- Edit the sub-theme's `templates/cd/cd-icons/cd-icons.html.twig` to include the generated sprite file.

  [cd-icons]: https://brand.unocha.org/d/xEPytAUjC3sH/icons
  [cd-icons-readme]: https://github.com/UN-OCHA/common_design/blob/main/README.md#icons


### Creating Drupal libraries

Add new components by [defining Drupal libraries][library-define] in `common_design_subtheme.libraries.yml` and attaching them to relevant templates. Or use existing components from `common_design.libraries.yml` base-theme by overriding Twig templates in the sub-theme and [attaching the libraries][library-attach] like so:

```c
{# Use a CD base-theme component #}
{{ attach_library('common_design/cd-teaser') }}

{# Attach a custom sub-theme library #}
{{ attach_library('common_design_subtheme/my-article-teaser') }}
```

  [library-define]: https://www.drupal.org/docs/theming-drupal/adding-stylesheets-css-and-javascript-js-to-a-drupal-theme#define
  [library-attach]: https://www.drupal.org/docs/theming-drupal/adding-stylesheets-css-and-javascript-js-to-a-drupal-theme#attach-library-specific-twig


### Overriding Drupal libraries

Occasionally you might want to [totally replace a given library][library-override] that is output by core or CD base-theme. In that case, use `libraries-override` to replace the library of your choice with the customized version. No additional work should be necessary to attach libraries inside Twig templates.

  [library-override]: https://www.drupal.org/docs/theming-drupal/adding-stylesheets-css-and-javascript-js-to-a-drupal-theme#override-extend


### Extending Drupal libraries

Core and CD base-theme libraries [can be extended with small customizations][library-extend] by using the `libraries-extend` directive in `common_design_subtheme.info.yml`. By extending a library, your customizations automatically apply when any core or base-theme library would normally be output. Use this feature to override colors inside components, for example.

  [library-extend]: https://www.drupal.org/docs/theming-drupal/adding-stylesheets-css-and-javascript-js-to-a-drupal-theme#s-libraries-extend


### Extending the theme

Override theme preprocess functions by copying from `common_design.theme` and editing as needed. hen copying, the **function name will always need to be modified** from `common_design_` to `common_design_subtheme_`.

Refer to [common_design README][cd-readme] for
general details about base-theme and instructions for compilation. There should be no need to use Sass in the sub-theme anymore.

  [cd-readme]: https://github.com/UN-OCHA/common_design/blob/main/README.md#ocha-common-design-for-drupal-9


## Tests

Refer to [common_design README E2E testing][cd-testing] for information about running tests.

  [cd-testing]: https://github.com/UN-OCHA/common_design/blob/main/README.md#e2e-testing
