<?php

/** Plugin declaration
* extends MantisPlugin
* Example plugin that implements Jquery files
*/


class BugHeatPlugin extends MantisPlugin
{

    private $affectedUsers = null;
    private $aCalculateHeatArray = [];

    public function register()
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
    public function hooks()
    {
        return array(
            "EVENT_VIEW_BUG_DETAILS" => 'displayAffectedControls',
            "EVENT_DISPLAY_BUG_ID"=> 'updateBugHeat',
            "EVENT_LAYOUT_RESOURCES" => 'getBugHeatResources',
            "EVENT_REST_API_ROUTES" => 'setRoutes'
        );
    }

    public function getBugHeatResources()
    {
        //include style and script files
        $resources = '<link rel="stylesheet" type="text/css" href="' . plugin_file('BugHeat.css') . '" />'
                    .'<script type="text/javascript" src="' . plugin_file('BugHeat.js') . '"></script>';
        return  $resources;
    }

    public function displayAffectedControls($sEventName, $iBugID)
    {
        $oCustomFields=custom_field_get_all_linked_fields($iBugID);
        if (!isset($oCustomFields["Bug heat"])) {
            return;
        }
        
        $this->updateBugHeat($sEventName, $iBugID);
        $aAffectedUsers=$this->getAffectedUsers($iBugID);
        if (empty($aAffectedUsers)) {
            $aAffectedUsers=[];
        }

        echo '<div class="row BugHeat--description-row">'
                .'<div class="col-md-11 BugHeat--description-column">';
        if (auth_is_user_authenticated() && current_user_get_access_level()>=REPORTER) {
            $iUserID=auth_get_current_user_id();
            $oBug = bug_get($iBugID, true);

            if ($iUserID==$oBug->reporter_id) {
                // If the user is the reporter of this bug
                printf('This bug affects you and %s other person(s).', count($aAffectedUsers));
            } else {
                    // If user already marked that he is affected
                    echo "<a href='#' id='BugHeat--action-link-affection'><span id='BugHeat--affectMessage' data-affected=\""
                    .(in_array($iUserID, $aAffectedUsers)
                        ? "1\">".sprintf('This bug affects you and %s other person(s).', count($aAffectedUsers))
                        : "0\">".sprintf('This bug affects %s person(s). Does this bug affect you?', count($aAffectedUsers)+1)
                    )
                    ."</span></a>";
                
            }
        } else {
            printf('This bug affects %s person(s).', count($aAffectedUsers)+1);
        }
        
        $oCustomFields=custom_field_get_all_linked_fields($iBugID);
        $heatcount = 0;

        if (isset($oCustomFields["Bug heat"])) {
            $iFieldID = custom_field_get_id_from_name('Bug heat');
            $heatcount = custom_field_get_value($iFieldID, $iBugID);
        }

        echo '</div>'
                .'<div class="col-md-1 BugHeat--badge-column">'
                    .'<span id="BugHeat--badge" data-container="body" data-toggle="popover" data-html="true" data-placement="left" data-title="BugHeat Calculation" data-content="'.$this->getBugHeatDescription($iBugID).'" class="badge badge-'.
                    (
                        $heatcount > 100
                        ? 'danger'
                        : (
                            $heatcount > 20
                            ? 'warning'
                                : ($heatcount > 1 ? 'info' : 'default')
                            )
                    ).'">'
                        .'<i class="glyphicon glyphicon-fire " aria-hidden="true" ></i>&nbsp;';
        
        echo "<span id='BugHeat--badge-count'>".$heatcount."</span>";
        echo        '</span>'
                .'</div>'
            .'</div>';
    }

    public function getAffectedUsers($iBugID, $force=false)
    {
        if($this->affectedUsers == null || $force) {
            $dbtable = plugin_table("affected_users", 'BugHeat');
            $dbquery = "SELECT userid FROM {$dbtable} WHERE bugid=$iBugID and affected=1";
            $dboutput = db_query($dbquery);
            $aResultArray = [];
            while (($aResult = db_fetch_array($dboutput))) {
                $aResultArray[] = $aResult['userid'];
            };
            $this->affectedUsers = (is_array($aResultArray) ? $aResultArray : []);
        }
        return $this->affectedUsers;
    }

    
    public function updateBugHeat($sEventName, $iBugID)
    {
        $oCustomFields=custom_field_get_all_linked_fields($iBugID);
        if (!isset($oCustomFields["Bug heat"])) {
            return;
        }
        $iFieldID=custom_field_get_id_from_name('Bug heat');
        custom_field_set_value($iFieldID, $iBugID, $this->calculateBugHeat($iBugID), false);
        return $iBugID;
    } 

    

    public function setRoutes($p_event_name, $p_event_args)
    {
        $t_app = $p_event_args['app'];
        $t_app->group('/bugheat', function () use ($t_app) {
            $t_app->get('/getaffected', function ($req, $res, $args) {
                if (auth_is_user_authenticated()) {
                    return $res->withStatus(200)->withJson(BugHeatPlugin::staticGetAffectedUser());
                }

                return $res->withStatus(403);
            });
            $t_app->post('/addaffect', function ($req, $res, $args) {
                if (auth_is_user_authenticated() && current_user_get_access_level()>=REPORTER) {
                    return $res->withStatus(200)->withJson(BugHeatPlugin::staticAddAffectedUser($req->getParsedBody()));
                }

                return $res->withStatus(403);
            });
            $t_app->post('/removeaffect', function ($req, $res, $args) {
                if (auth_is_user_authenticated() && current_user_get_access_level()>=REPORTER) {
                    return $res->withStatus(200)->withJson(BugHeatPlugin::staticRemoveAffectedUser($req->getParsedBody()));
                }

                return $res->withStatus(403);
            });
        });
    }
    public static function staticGetAffectedUser() {
        $iBugID = (int) $_GET['bugid'];
        $dbtableplugin = plugin_table("affected_users", 'BugHeat');
        $dbquery = "SELECT u.username FROM {$dbtableplugin} LEFT JOIN {user} u ON u.id = userid WHERE bugid=".$iBugID." and affected=1";
        $dboutput = db_query($dbquery);
        $aResultArray = [];
        while (($aResult = db_fetch_array($dboutput))) {
            $aResultArray[] = $aResult['username'];
        };

        return (is_array($aResultArray) ? $aResultArray : []);
    }

    public static function staticAddAffectedUser() {
        $iBugID = (int) $_POST['bugid'];
        $oBugHeat = new BugHeatPlugin('BugHeat');
        $oBugHeat->init();
        return $oBugHeat->addAffectedUser($iBugID);
    }
    public function addAffectedUser($iBugId)
    {
        $iUserID=auth_get_current_user_id();
        $dbtable = plugin_table("affected_users",'BugHeat');
        $dbquery = "INSERT INTO {$dbtable} VALUES(".db_param().",".db_param().", 1)";
        db_query($dbquery, [$iBugId, $iUserID]);
        
        return [
            "success" => true,
            "newHeat" => $this->calculateBugHeat($iBugId),
            "newString" => $this->getBugHeatString($iBugId),
        ];
    }

    public static function staticRemoveAffectedUser() {
        $iBugID = (int) $_POST['bugid'];
        $oBugHeat = new BugHeatPlugin('BugHeat');
        $oBugHeat->init();
        return $oBugHeat->removeAffectedUser($iBugID);
    }

    public function removeAffectedUser($iBugID)
    {
        $dbtable = plugin_table("affected_users",'BugHeat');
        $dbquery = "DELETE FROM {$dbtable} WHERE bugid=" . db_param() . " AND userid=" . db_param();
        $iUserID=auth_get_current_user_id();
        db_query($dbquery, [$iBugID, $iUserID]);

        return [
            "success" => true,
            "newHeat" => $this->calculateBugHeat($iBugID),
            "newString" => $this->getBugHeatString($iBugID),
        ];
    }

    /*** Default plugin configuration.     */
    public function config()
    {
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
    
    public function getBugHeatString($iBugID)
    {
        $aAffectedUsers=$this->getAffectedUsers($iBugID, true);
        $iUserID=auth_get_current_user_id();
        $oBug = bug_get($iBugID, true);
        if ($iUserID==$oBug->reporter_id) {
            // If the user is the reporter of this bug
            return sprintf('This bug affects you and %s other person(s).', count($aAffectedUsers));
        } else {
            return ( in_array($iUserID, $aAffectedUsers)
                ? sprintf('This bug affects you and %s other person(s).', count($aAffectedUsers))
                : sprintf('This bug affects %s person(s). Does this bug affect you?', count($aAffectedUsers)+1)
            );
        }
    }

    private function getBugHeatCalculation($iBugID){
        if (empty($this->aCalculateHeatArray)) {
            $t_bug = bug_get($iBugID, true);
            $oRelationShips=relationship_get_all_dest($iBugID);
            $oMonitorCount=
            $oBugNotes = bugnote_get_all_bugnotes($iBugID);
            $aAffectedUsers = $this->getAffectedUsers($iBugID, force);
            
            plugin_push_current('BugHeat');
            $iAffectedMultiplier = plugin_config_get('affected_user_heat');
            $iDuplicationMultiplier = plugin_config_get('duplicate_heat');
            $iFollowingMultiplier = plugin_config_get('monitor_heat');
            $iSubscriberMultiplier = plugin_config_get('subscriber_heat');
            $iPrivateMultiplier = plugin_config_get('private_heat');
            $iSecurityMultiplier = plugin_config_get('security_issue_heat');
            
            $aReporterIDs = array_map(function ($oBugNote) {
                return $oBugNote->reporter_id;
            }, $oBugNotes);

            $iCountAffectedUsers = count($aAffectedUsers);
            
            $iCountFollowing = count(bug_get_monitors($iBugID));
            $iCountDuplicates = 0;

            array_walk($oRelationShips, function ($oRelation) use (&$iCountDuplicates, &$aReporterIDs, &$iCountFollowing) {
                if ($oRelationShip->type==0) {
                    $iCountDuplicates++;
                    $oRelationShipBug=bug_get($oRelationShip->src_bug_id);
                    $oRelationShipBugNotes = bugnote_get_all_bugnotes($oRelationShip->src_bug_id);
                    foreach ($oRelationShipBugNotes as $oBugNote) {
                        $aReporterIDs[]=$oBugNote->reporter_id;
                    }
                    $iCountFollowing += count(bug_get_monitors($oRelationShip->src_bug_id));
                }
            });

            if ($t_bug->view_state==VS_PRIVATE) {
                $iCountPrivate  = 1;
            }
            if ($t_bug->category_id==31) {
                $iCountSecurity = 1;
            }
            $iCountSubscriber = count(array_unique($aReporterIDs));

            $this->aCalculateHeatArray = [
                'iCountAffectedUsers' => $iCountAffectedUsers,
                'iCountFollowing' => $iCountFollowing,
                'iCountDuplicates' => $iCountDuplicates,
                'iCountPrivate' => $iCountPrivate,
                'iCountSecurity' => $iCountSecurity,
                'iCountSubscriber' => $iCountSubscriber,
                'iAffectedMultiplier' => $iAffectedMultiplier,
                'iFollowingMultiplier' => $iFollowingMultiplier,
                'iDuplicationMultiplier' => $iDuplicationMultiplier,
                'iPrivateMultiplier' => $iPrivateMultiplier,
                'iSecurityMultiplier' => $iSecurityMultiplier,
                'iSubscriberMultiplier' => $iSubscriberMultiplier,
            ];
        }

        return $this->aCalculateHeatArray;
    }

    public function calculateBugHeat($iBugID)
    {
        $calcArray = $this->getBugHeatcalculation($iBugID);
        $iHeat = 0;
        $iHeat += $calcArray['iCountAffectedUsers'] * $calcArray['iAffectedMultiplier'];
        $iHeat += $calcArray['iCountSubscriber'] * $calcArray['iSubscriberMultiplier'];
        $iHeat += $calcArray['iCountDuplicates'] * $calcArray['iDuplicationMultiplier'];
        $iHeat += $calcArray['iCountFollowing'] * $calcArray['iFollowingMultiplier'];
        $iHeat += $calcArray['iCountPrivate'] * $calcArray['iPrivateMultiplier'];
        $iHeat += $calcArray['iCountSecurity'] * $calcArray['iSecurityMultiplier'];
        return $iHeat;
    }

    public function getBugHeatDescription($iBugID)
    {
        $calcArray = $this->getBugHeatcalculation($iBugID);
        return "<ul class=list-group>"
            .($calcArray['iCountAffectedUsers'] > 0 
                ? "<li class=list-group-item>"
                        .sprintf(
                            "Calculating <b>%s affected user(s)</b> with a weight of <b>%s</b> to a total of <b>%s heat</b>", 
                            $calcArray['iCountAffectedUsers'], 
                            $calcArray['iAffectedMultiplier'], 
                            $calcArray['iCountAffectedUsers'] * $calcArray['iAffectedMultiplier']
                        )
                    ."</li>"
                : "" )
            .($calcArray['iCountSubscriber'] > 0 
                ? "<li class=list-group-item>"
                    .sprintf(
                        "Calculating <b>%s subscriber(s)</b> with a weight of <b>%s</b> to a total of <b>%s heat</b>", 
                        $calcArray['iCountSubscriber'], 
                        $calcArray['iSubscriberMultiplier'], 
                        $calcArray['iCountSubscriber'] * $calcArray['iSubscriberMultiplier']
                    )
                    ."</li>"
                : "") 
            .($calcArray['iCountDuplicates'] > 0 
                    ? "<li class=list-group-item>"
                        .sprintf(
                            "Calculating <b>%s duplicate(s)</b> with a weight of <b>%s</b> to a total of <b>%s heat</b>", 
                            $calcArray['iCountDuplicates'], 
                            $calcArray['iDuplicationMultiplier'], 
                            $calcArray['iCountDuplicates'] * $calcArray['iDuplicationMultiplier']
                        )
                        ."</li>"
                : "") 
            .($calcArray['iCountFollowing'] > 0 
                ? "<li class=list-group-item>"
                        .sprintf(
                            "Calculating <b>%s follower(s)</b> with a weight of <b>%s</b> to a total of <b>%s heat</b>", 
                            $calcArray['iCountFollowing'], 
                            $calcArray['iFollowingMultiplier'], 
                            $calcArray['iCountFollowing'] * $calcArray['iFollowingMultiplier']
                        )
                    ."</li>"
                : "") 
            .($calcArray['iCountSecurity'] == 1 
                ? "<li class=list-group-item>"
                    .sprintf(
                        "Security relevant issue, adding <b>%s heat</b>",
                        $calcArray['iSecurityMultiplier']
                    )
                    ."</li>" 
                : ""
            )
            .($calcArray['iCountPrivate'] == 1 
                ? "<li class=list-group-item>"
                    .sprintf(
                        "Private issue, adding <b>%s heat</b>",
                        $calcArray['iPrivateMultiplier']
                    )
                    ."</li>" 
                : ""
            )
        ."</ul>";

    }

    public function schema()
    {
        return array(
            array(
                "CreateTableSQL",
                array(
                    plugin_table("affected_users", 'BugHeat'),
                    "
                    bugid    I    NOTNULL UNSIGNED PRIMARY,
                    userid    I    NOTNULL UNSIGNED PRIMARY,
                    affected  I    NULL UNSIGNED
                    ",
                    array('mysql' => 'ENGINE=MyISAM DEFAULT CHARSET=utf8', 'pgsql' => 'WITHOUT OIDS')
                ),
            ),
            array( 'InsertData', array( db_get_table('custom_field'), "
                (name, type, possible_values, default_value, valid_regexp, access_level_r, access_level_rw, length_min, length_max, require_report, require_update, display_report, display_update, require_resolved, display_resolved, display_closed, require_closed, filter_by) 
                VALUES 
            ('Bug heat', 1, '', '0', '', 10, 90, 0, 0, 0, 0, 1, 1, 0, 1, 1, 0, 1)" ) )

        );
    }
}
