Linked Responsive Image Media Formatter
=======================================

This module provides a formatter for image media entity reference fields.

The formatter will display the media image using a responsive image style and
can wrap in a link.

The link can either:

- A link to the parent entity of the media entity reference field
- A link to the media entity
- A link to the media image file
- A custom link that can use **tokens**

The formatter also allow to specify what `alt` attribute to use for the image:

- `Alt` text of the media image
- `Custom` text that can use **tokens**

Finally the formatter offer the possibility to indicate that the image should be
used as background for the link, in which case the `alt` text will be used as
the link text instead of the being added to the image which will be considered
as a decorative image with an empty `alt`.

Example
-------

A node with a `link` field and an media entity reference field using this
formatter that use tokens to reference the link field URL in order to create
an image linking to somewhere.

Todo
----

- Add settings to be able to specify additional attributes for the  link like
  `rel`, `target` and `download`.
