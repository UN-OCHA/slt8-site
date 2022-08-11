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

- [CD header](sass/cd/cd-header/_cd-header.css)

  Added `position: relative;` to `.cd-header` to fix position of the main menu
  dropdown. This could/should be added to the `common_design` theme.

- [CD layout](sass/cd/cd-layout/_cd-layout.css)

  Changed the `flex-basis` and `flex-grow` of the `.cd-layout-content` to
  ensure content spans the entire width of the main content area.

- [Forms](sass/components/_forms.css)

  Styling for the drupal inline forms.

- [Page title](sass/components/_page_title.css)

  Styling for the drupal page title.

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

# OCHA Common Design sub theme for Drupal 8

A sub theme, extending [common_design](https://github.com/UN-OCHA/common_design) base theme.

This can be used as a starting point for implementations. Add components, override and extend base theme as needed. Refer to [Drupal 8 Theming documentation](https://www.drupal.org/docs/8/theming) for more.

Copy this directory to `/themes/custom/` and optionally rename the folder and associated theme files from
`common_design_subtheme` to your theme name. Then rename the `common_design_subtheme.info.yml.example` to `common_design_subtheme.info.yml`.

### Path of the libraries
If the subtheme name changes, the path of the global style sheet in `common_design_subtheme.info.yml` needs to reflect the new sub theme name.
```
libraries:
- common_design_subtheme/global-styling
```

### Customise the logo
- Set the logo `logo: 'img/logos/logo.svg'` in the `common_design_subtheme.info.yml` file, and in the `sass/cd-header/_cd-logo.scss` partial override file.
- Adjust the grid column width in `sass/cd-header/_cd-header.scss` partial override file to accommodate the logo.

### Customise the favicon and homescreen icons
Replace the favicon in the theme's root, and the homescreen icons in `img/` with branded versions

### Customise colours
- Change colour-related variable names and values in `sass/cd/_cd_variables.scss` and replace in all references to in partial overrides in `common_design_subtheme/sass/cd/`

### Other customisations
Override sass partials and extend twig templates from the base theme as needed, copying them into the sub theme and linking them using `@import` for sass and `extend` or `embed` for twig templates.

Add new components by defining new libraries in `common_design_subtheme.libraries.yml` and attaching them to relevant templates. Or use existing components from `common_design.libraries.yml` base theme by attaching the libraries to twig template overrides in the sub theme.

Override theme preprocess functions by copying from `common_design.theme` and editing as needed. For example, if new icons are added, a new icon sprite will need to be generated and the `common_design_preprocess_html` hook used to attach the icon sprite to the page will need a new path to reflect the sub theme's icon sprite location.

Refer to [common_design README](https://github.com/UN-OCHA/common_design/#common-design-base-theme-for-drupal-8) for general details about base theme and instructions for compilation. There should be no need to compile the base theme, only the sub theme.

Refer to [common_design README E2E testing](https://github.com/UN-OCHA/common_design/#e2e-testing) for information about running tests.
