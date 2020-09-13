Saving Lives Together - Access module
=====================================

This module defines various permissions.

**Content Types**

This module defines permissions to **view** content types per bundle.

It denies **view** access to node pages for users without the corresponding
`Node Bundle: view published content` permission.

To work, the `view published content` must be checked for anonymous and
authenticated users (otherwise they are already denied access).

This modules only deals with **published** content. Access to unpublished
content is managed by the `view unpubished content` permission independently
of this module.

**Roles**

This module also provides a permission to assign user roles, decoupling it
from the `administer permissions` permission.

**Private files**

This module check the access to private files by determining if the file is
referenced by a published public page and the user has the permission to view
published public page.

Setup
-----

1. Enable the module.
2. Check `view published content` for anonymous and authenticated users.
3. Set granular `Node Bundle: view published content` permission for each node
   types.
