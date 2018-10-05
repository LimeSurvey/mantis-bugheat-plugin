<?php
form_security_validate( 'plugin_BugHeat_config_update' );

plugin_config_set( 'monitor_heat', gpc_get_int( 'monitor_heat' )); 
plugin_config_set( 'private_heat', gpc_get_int( 'private_heat' )); 
plugin_config_set( 'security_issue_heat', gpc_get_int( 'security_issue_heat' )); 
plugin_config_set( 'affected_user_heat', gpc_get_int( 'affected_user_heat' ) );
plugin_config_set( 'subscriber_heat', gpc_get_int( 'subscriber_heat' ) );

form_security_purge( 'plugin_BugHeat_config_update' );
print_successful_redirect( plugin_page( 'config_page', true ) );