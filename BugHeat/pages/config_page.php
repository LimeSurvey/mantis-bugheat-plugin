<?php

layout_page_header('BugHeat Configuration page');
layout_page_begin();

?>

<br/>

<form class="form-horizontal" action="<?php echo plugin_page( 'config_update' ) ?>" method="post">
<?php echo form_security_field( 'plugin_BugHeat_config_update' ) ?>

  <div class="form-group">
    <label for="monitor_heat" class="col-sm-3 control-label">Heat multiplier per monitoring user</label>
    <div class="col-sm-1">
      <input type="number" min="0" max="1000" class="form-control" name="monitor_heat" id="monitor_heat" value="<?php echo string_attribute(  plugin_config_get( 'monitor_heat' ) ) ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="private_heat" class="col-sm-3 control-label">Heat per private issue</label>
    <div class="col-sm-1">
      <input type="number" min="0" max="1000" class="form-control" name="private_heat" id="private_heat" value="<?php echo string_attribute(  plugin_config_get( 'private_heat' ) ) ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="duplicate_heat" class="col-sm-3 control-label">Heat multiplier per duplicate</label>
    <div class="col-sm-1">
      <input type="number" min="0" max="1000" class="form-control" name="private_heat" id="duplicate_heat" value="<?php echo string_attribute(  plugin_config_get( 'duplicate_heat' ) ) ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="security_issue_heat" class="col-sm-3 control-label">Heat per security category</label>
    <div class="col-sm-1">
      <input type="number" min="0" max="1000" class="form-control" name="security_issue_heat" id="security_issue_heat" value="<?php echo string_attribute(  plugin_config_get( 'security_issue_heat' ) ) ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="affected_user_heat" class="col-sm-3 control-label">Heat multiplier per affected user</label>
    <div class="col-sm-1">
      <input type="number" min="0" max="1000" class="form-control" name="affected_user_heat" id="affected_user_heat" value="<?php echo string_attribute(  plugin_config_get( 'affected_user_heat' ) ) ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="monitor_heat" class="col-sm-3 control-label">Heat multiplier per participating user</label>
    <div class="col-sm-1">
      <input type="number" min="0" max="1000" class="form-control" name="subscriber_heat" id="monitor_heat" value="<?php echo string_attribute(  plugin_config_get( 'subscriber_heat' ) ) ?>">
    </div>
  </div>
  <div class="form-group">
    <div class="col-sm-offset-3 col-sm-10">
      <button type="submit" class="btn btn-default">Save settings</button>
    </div>
  </div>
</form>

<?php

layout_page_end();