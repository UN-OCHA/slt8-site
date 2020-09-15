Saving Lives Together - Contacts module
=======================================

This module handles various aspects of the contact management.

Contact import
--------------

This modules provides a form to import contacts from a spreadsheet (Excel,
OpenOffice, CSV).

Contact list filter
-------------------

This module alters the exposed "country" filter on the contacts page to ensure
no results are shown when no country is selected.

Contact list order
------------------

This module provides a sort plugin for the `security title` in order to sort the
contacts according to the following title hierarchy:

1. Chief Security Adviser
2. Deputy Security Adviser
3. Security Adviser
4. Field Security Coordination Officer

It's lenient enough to support variants like "Deputy Security Adviser ( DSA)",
"Field Security Coordination Officer (FSCO)", etc.
