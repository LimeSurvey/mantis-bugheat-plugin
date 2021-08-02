# Bug Heat plugin for Mantis Bugtracker

This plugin allows you to display a bug heat for each of your bug tracker  issues similar to the one that can be found on launchpad ( https://bugs.launchpad.net ) 

Just install like any other Mantis plugin.
To activate it create an additional field called 'Bug heat' in the settings and activate it for the projects you want the bug heat to display for.
The value is automatically calculated as soon as someone looks at any issue.

Also make sure to set
```php
$g_show_monitor_list_threshold = VIEWER;
```
in your config.php to avoid the heat constantly being updated due to different viewing permissions.