Common Design Table Component
=============================

This component provides styling of the common design tables and handle
responsivity by displaying tables with the `cd-table--responsive` class in
a single column format on small viewports.

This component has a javascript component that adds a `data-content` attribute
to each table cell with the column name so that combined with css rules, the
column label is appended in front of each cell value in the single column
display on small viewports.

This works with table added dynamically (ex: via ajax) by observing the addition
of such tables in the DOM.

The javascript is not mandatory and adding the `data-content` attributes in the
template directly works as well.

To avoid including the javascript, override the library in the `theme.info.yml`:

```yaml
libraries-override:
  common_design/cd-table:
    js:
      components/cd-table/cd-table.js: false
```
