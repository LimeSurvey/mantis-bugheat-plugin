<?php

/** Plugin declaration
* extends MantisPlugin
* Example plugin that implements Jquery files
*/


class BugHeatPlugin extends MantisPlugin
{ 

    function register()
    {
        $this->name = 'BugHeat';
        $this->description = 'Provides a bug heat index which gives you an idea how important a bug is to your users';
        $this->page = "config_page";

        $this->version = '0.0.2';
        $this->requires = array(
            "MantisCore" => "2.0.0",
        );

        $this->author = 'Carsten Schmitz';
        $this->contact = 'carsten.schmitz@limesurvey.org';
        $this->url = 'https://www.limesurvey.org';
    }


    # Hooked functions runs when the event is triggered
    function hooks()
    {
        return array(
            "EVENT_VIEW_BUG_DETAILS" => 'displayAffectedControls',
            "EVENT_DISPLAY_BUG_ID"=> 'updateBugHeat'
        );
    }

    function displayAffectedControls($sEventName,$iBugID)
    {
        $oCustomFields=custom_field_get_all_linked_fields($iBugID);
        if (!isset($oCustomFields["Bug heat"])) { return; }
        
        $this->updateBugHeat($sEventName,$iBugID);
        $aAffectedUsers=$this->getAffectedUsers($iBugID);
        if (empty($aAffectedUsers)) $aAffectedUsers=[];
        echo '<div class="row" style="padding:2px 5px;"><div class="col-md-11"> ';
        if (auth_is_user_authenticated() && current_user_get_access_level()>=REPORTER) {
            $iUserID=auth_get_current_user_id();
            $oBug = bug_get( $iBugID,true );

            if ($iUserID==$oBug->reporter_id){
                // If the user is the reporter of this bug
                printf('This bug affects you and %s other person(s).',count($aAffectedUsers));
            } else {
                if (in_array($iUserID,$aAffectedUsers)) {
                    // If user already marked that he is affected
                    echo "<a href=''>".sprintf('This bug affects you and %s other person(s).',count($aAffectedUsers))."</a>";
                } else {
                    echo "<a href=''>".sprintf('This bug affects %s person(s). Does this bug affect you?',count($aAffectedUsers)+1)."</a>";
                }
            }
        } else {
            printf('This bug affects %s person(s).',count($aAffectedUsers)+1);
        }
        echo '</div><div class="col-md-1"> <span class="badge badge-danger"><span class="glyphicon glyphicon-fire " aria-hidden="true"></span> ';
        
        $oCustomFields=custom_field_get_all_linked_fields($iBugID);
        if (!isset($oCustomFields["Bug heat"])) { return; }
        $iFieldID=custom_field_get_id_from_name('Bug heat');
        echo custom_field_get_value($iFieldID,$iBugID);
        echo '</span></div></div>';
    }    

    function getAffectedUsers($iBugID)
    {
        $dbtable = plugin_table("affected_users");
        $dbquery = "SELECT userid FROM {$dbtable} WHERE bugid=$iBugID and affected=1";
        $dboutput = db_query($dbquery);
        return db_fetch_array($dboutput);
    }
    
    function updateBugHeat($sEventName, $iBugID)
    {
        $oCustomFields=custom_field_get_all_linked_fields($iBugID);
        if (!isset($oCustomFields["Bug heat"])) { return; }
        $iFieldID=custom_field_get_id_from_name('Bug heat');
        custom_field_set_value($iFieldID,$iBugID,$this->calculateBugHeat($iBugID),false);
        return $iBugID;
    }
    
    /*** Default plugin configuration.     */
    function config() {
        /*
        Default Launchpad values
        Attribute           Calculation

        Private             Adds 150 points
        Security issue      Adds 250 points
        Duplicates          Adds 6 points per duplicate bug
        Affected users      Adds 4 points per affected user
        Subscribers         Adds 2 points per subscriber  (incl. subscribers to duplicates)
        */
        return array(
            'monitor_heat'        => 2 ,
            'private_heat'        => 150,
            'security_issue_heat' => 250,
            'affected_user_heat'  => 4,
            'duplicate_heat'      => 6,
            'subscriber_heat'     => 2
        );
    } 
    
    function calculateBugHeat($iBugID)
    {
        $t_bug = bug_get( $iBugID, true );
        $oRelationShips=relationship_get_all_dest($iBugID);
        $oMonitorCount=count(bug_get_monitors($iBugID));
        $oBugNotes = bugnote_get_all_bugnotes($iBugID);
        $iHeat=plugin_config_get( 'affected_user_heat' ); // There is always one affected user (the reporter)
        $aReporterIDs=[];
        $iHeat+= $oMonitorCount*plugin_config_get( 'monitor_heat' );
        foreach ($oRelationShips as $oRelationShip) {
            if ($oRelationShip->type==0){
                $iHeat+=plugin_config_get( 'duplicate_heat' ); 
                $oRelationShipBug=bug_get($oRelationShip->src_bug_id);
                $oRelationShipBugNotes = bugnote_get_all_bugnotes($oRelationShip->src_bug_id);
                foreach ($oRelationShipBugNotes as $oBugNote){
                    $aReporterIDs[]=$oBugNote->reporter_id;
                } 
                $iHeat+=count(bug_get_monitors($oRelationShip->src_bug_id))*plugin_config_get( 'subscriber_heat' ); ;
            }
        } 
        if ($t_bug->view_state==VS_PRIVATE) {
            $iHeat+=plugin_config_get( 'private_heat' );
        }
        if ($t_bug->category_id==31) {
            $iHeat+=plugin_config_get( 'security_issue_heat' );;
        }
        foreach ($oBugNotes as $oBugNote){
            $aReporterIDs[]=$oBugNote->reporter_id;
        } 
        $iHeat+=count(array_unique($aReporterIDs))*plugin_config_get( 'subscriber_heat' );
        return $iHeat;
    }

    function schema() {
        return array(
            array(
                "CreateTableSQL",
                array(
                    plugin_table( "affected_users" ),
                    "
                    bugid    I    NOTNULL UNSIGNED PRIMARY,
                    userid    I    NOTNULL UNSIGNED PRIMARY,
                    affected  I    NULL UNSIGNED
                    ",
                    array('mysql' => 'ENGINE=MyISAM DEFAULT CHARSET=utf8', 'pgsql' => 'WITHOUT OIDS')
                ),
            ),
            array( 'InsertData', array( db_get_table( 'custom_field' ), "
                (name, type, possible_values, default_value, valid_regexp, access_level_r, access_level_rw, length_min, length_max, require_report, require_update, display_report, display_update, require_resolved, display_resolved, display_closed, require_closed, filter_by) 
                VALUES 
            ('Bug heat', 1, '', '0', '', 10, 90, 0, 0, 0, 0, 1, 1, 0, 1, 1, 0, 1)" ) )

        );
    }



}