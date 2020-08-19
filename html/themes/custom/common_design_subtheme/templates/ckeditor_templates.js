// Override the default template set
CKEDITOR.addTemplates('default', {
  // The name of sub folder which hold the shortcut preview images of the
  // templates.  Determine base path of drupal installation if any
  // (ckeditor could possibly be loaded w/o drupalSettings).
  imagesPath: ((drupalSettings && drupalSettings.path) ? drupalSettings.path.baseUrl : '/') + 'themes/custom/common_design_subtheme/img/ckeditor/',

  // The templates definitions.
  templates: [
    {
      title: 'Grid - 4 columns',
      image: '4_25_25_25_25.png',
      description: '4 columns that wrap on smaller screens',
      html: '<div class="cd-grid cd-grid-4-col">' +
            '<div>Sample content column 1</div>' +
            '<div>Sample content column 2</div>' +
            '<div>Sample content column 3</div>' +
            '<div>Sample content column 4</div>' +
            '</div>'
    }
  ]
});
