Eclipse Update URL
==================

Show details about an Eclipse Update URL.

Syntax
------

```
[[eclipseUpdate>%LOCATION_OF_UPDATE_XML%|%OPTIONS%]]
```

 * __%LOCATION_OF_UPDATE_XML%__ - can be a local or remote upate xml file
 * __%OPTIONS%__ - either just a number (amount of lines to show) or the following keywords and values separated by pipes (``|``) - ``%KEYWORD%=%VALUE%``
 
Option | Description
-------|------------
__direct__|Generate a direct link - all other options need this
__name__|The name of the link to be displayed
__category__|A category from the XML file
__id__|An ID from the XML file
