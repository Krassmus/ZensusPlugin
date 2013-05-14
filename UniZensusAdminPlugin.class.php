<?php
/**
* UniZensusAdminPlugin.class.php
*
*
*
*
* @author		Andr� Noack <noack@data-quest.de>, Suchi & Berg GmbH <info@data-quest.de>
* @version		$Id: UniZensusAdminPlugin.class.php,v 1.4 2011/12/09 10:18:11 anoack Exp $
*/
// +---------------------------------------------------------------------------+
// This file is part of Stud.IP
// UniZensusAdminPlugin.class.php
//
// Copyright (C) 2007 Andr� Noack <noack@data-quest.de>
// Suchi & Berg GmbH <info@data-quest.de>
// +---------------------------------------------------------------------------+
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or any later version.
// +---------------------------------------------------------------------------+
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
// +---------------------------------------------------------------------------+
require_once "lib/classes/StudipForm.class.php";
require_once "UniZensusPlugin.class.php";
require_once 'zensus_xml_func.php';   // XML-Funktionen

class UniZensusAdminPlugin extends AbstractStudIPSystemPlugin {

    private $user_is_eval_admin;
    private $zensuspluginid;

    public function __construct() {

        parent::__construct();

        if ($this->hasPermission()) {
            $navigation = new AutoNavigation($this->getDisplayname(), PluginEngine::getLink($this, array(), 'show'));
            if (basename($_SERVER['PHP_SELF']) == 'plugins.php') {
                Navigation::addItem('/UniZensusAdmin', $navigation);
                Navigation::addItem('/UniZensusAdmin/show', clone $navigation);
            } else {
                Navigation::addItem('/start/UniZensusAdmin', clone $navigation);
            }
            $info = PluginManager::getInstance()->getPluginInfo('unizensusplugin');
            $this->zensuspluginid = $info['id'];
        }
    }

    private function getDisplayname() {
        return _("Lehrevaluation-Administration");
    }

    private function hasPermission() {
        $user = $this->getUser();
        $permission = $user->getPermission();
        if (!$permission->hasTeacherPermission()) {
            return false;
        }
        if ($this->user_is_eval_admin === null) {
            # Pr�fen, ob der User die Rolle 'eval_admin' hat:
            $eval_admin = false;
            foreach ( $user->getAssignedRoles() as $role )
            {
                if ( $role->rolename === 'eval_admin' )
                {
                    $eval_admin = true;
                }
            }
            $this->user_is_eval_admin = $eval_admin;
        }
        return $permission->hasRootPermission() || $this->user_is_eval_admin;
    }

    public function actionShow() {
        if (!$this->hasPermission()) {
            throw new AccessDeniedException("Nur Root und ausgew�hlte Admins d�rfen dieses Plugin sehen.");
        }

        $cols = array();
        $cols[] = array(1,'','');
        $cols[] = array(30,_("Veranstaltung"),'Name');
        $cols[] = array(15,_("Dozenten"),'dozenten');
        $cols[] = array(5,_("Zensus Status"),'zensus_status');
        $cols[] = array(5,_("Plugin eingeschaltet"),'plugin_activated');
        $cols[] = array(10,_("Startzeit manuell"),'begin_evaluation');
        $cols[] = array(10,_("Endzeit manuell"),'end_evaluation');
        $cols[] = array(10,_("Startzeit automatisch"),'time_frame_begin');
        $cols[] = array(10,_("Endzeit automatisch"),'time_frame_end');

        $form_fields['starttime']  = array('type' => 'date',  'separator' => '&nbsp;', 'default' => 'YYYY-MM-DD', 'date_popup' => true);
        $form_fields['endtime']  = array('type' => 'date',  'separator' => '&nbsp;', 'default' => 'YYYY-MM-DD', 'date_popup' => true);
        $form_fields['plugin_status']  = array('type' => 'radio',  'separator' => '&nbsp;', 'default_value' => 1, 'options' => array(array('name'=>_("Ein"),'value'=>'1'),array('name'=>_("Aus"),'value'=>'0')));
        $form_buttons['set_plugin_status'] = array('type' => 'uebernehmen', 'info' => _("Plugin ein/ausschalten"));
        $form_buttons['set_starttime'] = array('type' => 'uebernehmen', 'info' => _("Startzeit �bernehmen"));
        $form_buttons['set_endtime'] = array('type' => 'uebernehmen', 'info' => _("Endzeit �bernehmen"));
        $form_buttons['switch'] = array('type' => 'auswahlumkehr', 'info' => _("Auswahl umkehren"));
        $form = new StudipForm($form_fields, $form_buttons, 'studipform', false);

        if($form->isClicked('set_starttime') || $form->isClicked('set_endtime')){
            if(is_array($_REQUEST['sem_choosen'])){
                if ($form->isClicked('set_starttime')){
                    $datafield_value = $form->getFormFieldValue('starttime');
                    $datafield_id = md5('UNIZENSUSPLUGIN_BEGIN_EVALUATION');
                } else {
                    $datafield_value = $form->getFormFieldValue('endtime');
                    $datafield_id = md5('UNIZENSUSPLUGIN_END_EVALUATION');
                }
                $db = new DB_Seminar();
                foreach(array_keys($_REQUEST['sem_choosen']) as $seminar_id){
                    $db->queryf("REPLACE INTO datafields_entries (range_id, datafield_id, content, chdate) VALUES ('%s','%s','%s',UNIX_TIMESTAMP())",
                        $seminar_id, $datafield_id , $datafield_value);
                }
                $form->doFormReset();
            }
        }
        if($form->isClicked('set_plugin_status')){
            if(is_array($_REQUEST['sem_choosen'])){
                $set_to_status = $form->getFormFieldValue('plugin_status') ? 'on' : 'off';
                $db = new DB_Seminar();
                foreach(array_keys($_REQUEST['sem_choosen']) as $seminar_id){
                    $db->queryf("REPLACE INTO plugins_activated (pluginid,poiid,state) VALUES ('%s','%s','%s')",
                    $this->zensuspluginid, 'sem' . $seminar_id, $set_to_status);
                }
                $form->doFormReset();
            }
        }

        if(isset($_REQUEST['choose_institut_x'])){
            if(isset($_REQUEST['select_sem'])){
                $_SESSION['_default_sem'] = $_REQUEST['select_sem'];
            }
            $_SESSION['zensus_admin']['check_eval'] = isset($_REQUEST['check_eval']);
            $_SESSION['zensus_admin']['plugin_activated'] = isset($_REQUEST['plugin_activated']);
            $_SESSION['zensus_admin']['filter_name'] = trim(Request::get('filter_name'));
        }

        if(!$_SESSION['_default_sem'] || $_SESSION['_default_sem'] == 'all'){
            $semester = SemesterData::GetInstance();
            $one_semester = $semester->getCurrentSemesterData();
            $_SESSION['_default_sem'] = $one_semester['semester_id'];
        }
        if ($_SESSION['_default_sem']){
            $semester = SemesterData::GetInstance();
            $one_semester = $semester->getSemesterData($_SESSION['_default_sem']);
            if($one_semester["beginn"]){
                $sem_condition = "AND seminare.start_time <=".$one_semester["beginn"]." AND (".$one_semester["beginn"]." <= (seminare.start_time + seminare.duration_time) OR seminare.duration_time = -1) ";
            }
        }
        if ($_SESSION['zensus_admin']['filter_name']) {
            $sem_condition .= " AND seminare.Name LIKE '".mysql_escape_string($_SESSION['zensus_admin']['filter_name'])."%' ";
        }
        if(isset($_REQUEST['sortby'])){
            foreach($cols as $col){
                if($_REQUEST['sortby'] == $col[2]){
                    if($_SESSION['zensus_admin']['sortby']['field'] == $_REQUEST['sortby']){
                        $_SESSION['zensus_admin']['sortby']['direction'] = (int)!$_SESSION['zensus_admin']['sortby']['direction'];
                    } else {
                        $_SESSION['zensus_admin']['sortby']['field'] = $_REQUEST['sortby'];
                        $_SESSION['zensus_admin']['sortby']['direction'] = 0;
                    }
                    break;
                }
            }
        }

        $_my_inst = $this->getInstitute($sem_condition);
        if (is_array($_my_inst)){
            $_my_inst_arr = array_keys($_my_inst);
            if(!$_SESSION['zensus_admin']['institut_id']){
                $_SESSION['zensus_admin']['institut_id'] = $_my_inst_arr[1];
            }
            if($_REQUEST['institut_id']){
                $_SESSION['zensus_admin']['institut_id'] = ($_my_inst[$_REQUEST['institut_id']]) ? $_REQUEST['institut_id'] : $_my_inst_arr[1];
            }
            ?>
            <div id="layout_container" style="padding-top:1px;">
            <form action="<?=PluginEngine::getLink($this)?>" method="post">
            <?= (class_exists('CSRFProtection') ? CSRFProtection::tokenTag() : '') ?>
            <div style="font-weight:bold;font-size:10pt;margin:10px;">
            <?=_("Bitte w&auml;hlen Sie eine Einrichtung aus:")?>
            </div>
            <div style="margin-left:10px;">
            <select name="institut_id" style="vertical-align:middle;">
            <?
            reset($_my_inst);
            while (list($key,$value) = each($_my_inst)){
                printf ("<option %s value=\"%s\" style=\"%s\">%s (%s)</option>\n",
                ($key == $_SESSION['zensus_admin']['institut_id']) ? "selected" : "" , $key,($value["is_fak"] ? "font-weight:bold;" : ""),
                htmlReady($value["name"]), $value["num_sem"]);

                if ($value["is_fak"] == 'all'){
                    $num_inst = $value["num_inst"];
                    for ($i = 0; $i < $num_inst; ++$i){
                        list($key,$value) = each($_my_inst);
                        printf("<option %s value=\"%s\">&nbsp;&nbsp;&nbsp;&nbsp;%s (%s)</option>\n",
                        ($key == $_SESSION['zensus_admin']['institut_id']) ? "selected" : "", $key,
                        htmlReady($value["name"]), $value["num_sem"]);
                    }
                }
            }
            list($institut_id,) = explode('_', $_SESSION['zensus_admin']['institut_id']);
            if($institut_id == 'all') $institut_id = 'root';
            ?>
            </select>&nbsp;
            <?=SemesterData::GetSemesterSelector(array('name'=>'select_sem', 'style'=>'vertical-align:middle;'), $_SESSION['_default_sem'], 'semester_id', false)?>
            <?=makeButton("auswaehlen","input",_("Einrichtung ausw�hlen"), "choose_institut")?>
            <br>
            <span style="font-size:80%;">
            ausgew�hlte ID: <span style="background-color:yellow;"><?=$institut_id?></span>
            </span>
            </div>
            <div style="font-size:10pt;margin:10px;">
            <b><?=_("Angezeigte Veranstaltungen einschr�nken:")?></b>
            <span style="margin-left:10px;font-size:10pt;">
            <input type="text" name="filter_name" value="<?=htmlReady($_SESSION['zensus_admin']['filter_name'])?>" style="vertical-align:middle;">&nbsp;<?=_("Name der Veranstaltung")?>
            </span>
            <span style="margin-left:10px;font-size:10pt;">
            <input type="checkbox" name="check_eval" <?=$_SESSION['zensus_admin']['check_eval'] ? 'checked' : ''?> value="1" style="vertical-align:middle;">&nbsp;<?=_("Evaluation in Zensus aktiviert")?>
            </span>
            <span style="margin-left:10px;font-size:10pt;">
            <input type="checkbox" name="plugin_activated" <?=$_SESSION['zensus_admin']['plugin_activated'] ? 'checked' : ''?> value="1" style="vertical-align:middle;">&nbsp;<?=_("Plugin eingeschaltet")?>
            </span>
            </div>
            </form>
            <hr>
            <?
            $data = $this->getSeminareData($sem_condition);
            $cssSw = new CssClassSwitcher();

        if (count($data)) {
                if($form->isClicked('switch')){
                    foreach($data as $seminar_id => $semdata) {
                        if(!isset($_REQUEST['sem_choosen'][$seminar_id])) $data[$seminar_id]['choosen'] = true;
                    }
                } else if(is_array($_REQUEST['sem_choosen'])){
                    foreach($data as $seminar_id => $semdata) {
                        if(isset($_REQUEST['sem_choosen'][$seminar_id])) $data[$seminar_id]['choosen'] = true;
                    }
                }
                echo chr(10).$form->getFormStart(PluginEngine::getLink($this));
                echo chr(10).'<div style="margin:10px;font-size:10pt;font-weight:bold">';
                echo _("Start- und Endzeiten f�r ausgew�hlte Veranstaltungen setzen:");
                echo chr(10). '</div>';
                echo chr(10).'<div style="margin:10px;font-size:10pt;">';
                echo  '<span>' . _("Startzeit:") . '</span>';
                echo chr(10) .'<span style="padding-left:10px;">' . $form->getFormField('starttime');
                echo '</span><span style="padding-left:10px;">'. $form->getFormButton('set_starttime', array('style' => 'vertical-align:middle'));
                echo chr(10). '</span></div>';
                echo chr(10).'<div style="margin:10px;font-size:10pt;">';
                echo '<span>' ._("Endzeit:") . '</span>';
                echo chr(10) .'<span style="padding-left:10px;">' . $form->getFormField('endtime');
                echo '</span><span style="padding-left:10px;">'. $form->getFormButton('set_endtime', array('style' => 'vertical-align:middle'));
                echo chr(10). '</span></div>';
                echo chr(10).'<div style="margin:10px;font-size:10pt;font-weight:bold">';
                echo _("Evaluationsplugin f�r ausgew�hlte Veranstaltungen ein/ausschalten:") .'</div>';
                echo chr(10).'<div style="margin:10px;font-size:10pt;">';
                echo chr(10) . $form->getFormField('plugin_status');
                echo '&nbsp;&nbsp;&nbsp;'. $form->getFormButton('set_plugin_status', array('style' => 'vertical-align:middle'));
                echo chr(10). '</div>';
                echo chr(10).'<div style="margin:10px;font-size:10pt;">';
                echo $form->getFormButton('switch');
                echo chr(10). '</div>';
                print ("<table width=\"99%\" align=\"center\" border=0 cellspacing=2 cellpadding=2>");
                print ("<tr style=\"font-size:80%\">");
                foreach($cols as $col){
                    echo "<th width=\"{$col[0]}%\">";
                    if($col[1]){
                        echo '<a class="tree" href="';
                        echo PluginEngine::getLink($this,array('sortby' => $col[2]));
                        echo '">'.$col[1].'&nbsp;';
                        if($col[2] == $_SESSION['zensus_admin']['sortby']['field']){
                            printf('<img src="%s/images/%s" border="0" align="top">', $this->getPluginUrl(),$_SESSION['zensus_admin']['sortby']['direction'] ? 'dreieck_up.png' : 'dreieck_down.png');
                        }
                        echo '</a>';
                    }
                    echo "</th>";
                }
                echo "</tr>";
            } elseif ($_SESSION['zensus_admin']['institut_id']) {
                print ("<table width=\"99%\" border=0 cellspacing=2 cellpadding=2>");
                parse_msg ("info�"._("Im gew&auml;hlten Bereich existieren keine Veranstaltungen")."�", "�", "steel1",2, FALSE);
            }
            foreach($data as $seminar_id => $semdata) {
                if($semdata['activated_by_sem'] == 'on' || ($semdata['activated_by_sem'] != 'off' && $semdata['activated_by_default'] == 'on')){
                    $plugin = PluginManager::getInstance()->getPluginById($this->zensuspluginid);
                    $plugin->setId($seminar_id);
                    $plugin->getCourseStatus();
                    $plugin->semester_id = $_SESSION['_default_sem'] ? $_SESSION['_default_sem'] : null;
                    if($_SESSION['zensus_admin']['check_eval'] && !in_array($plugin->course_status['status'], array('prepare','run','analyze','finished'))){
                        unset($data[$seminar_id]);
                        continue;
                    }
                    $data[$seminar_id]['link'] = "<a href=\"".PluginEngine::GetLink($plugin,array('cid' => $seminar_id)) . "\">"
                                            . htmlReady($plugin->course_status['status'])."</a>";
                    $data[$seminar_id]['zensus_status'] = $plugin->course_status['status'];
                    $data[$seminar_id]['time_frame_begin'] = $plugin->course_status['time_frame']['begin'];
                    $data[$seminar_id]['time_frame_end'] = $plugin->course_status['time_frame']['end'];
                    $data[$seminar_id]['plugin_activated'] = true;
                } else {
                    $plugin = null;
                    if($_SESSION['zensus_admin']['check_eval'] || $_SESSION['zensus_admin']['plugin_activated']){
                        unset($data[$seminar_id]);
                        continue;
                    }
                    $data[$seminar_id]['plugin_activated'] = false;
                }
                $data[$seminar_id]['dozenten'] = join(', ',(array)$semdata['dozenten']);
                $sorter[$seminar_id] = $data[$seminar_id][$_SESSION['zensus_admin']['sortby']['field']];
            }
			if($_SESSION['zensus_admin']['sortby']['field'] && count($data) && count($data) == count($sorter)){
                array_multisort($sorter, ($_SESSION['zensus_admin']['sortby']['direction'] ? SORT_ASC : SORT_DESC), $data);
            }
            $semlink = $GLOBALS['perm']->have_studip_perm('admin', $_SESSION['zensus_admin']['institut_id']) ? 'seminar_main.php?auswahl=' : 'details.php?sem_id=';
            foreach($data as $seminar_id => $semdata) {
                $cssSw->switchClass();
                echo "<tr>\n";
                echo '<td class="'.$cssSw->getClass().'" align="center"><input type="checkbox" name="sem_choosen['.$seminar_id.']" value="1" '.($semdata['choosen'] ? 'checked':'').'></td>';
                printf ("<td class=\"%s\">
                <a title=\"%s\" href=\"%s\">
                <font size=\"-1\">%s%s%s</font>
                </a></td>
                <td class=\"%s\" align=\"center\">
                <font size=\"-1\">%s</font></td>
                <td class=\"%s\" align=\"center\"><font size=\"-1\">%s</font></td>
                <td class=\"%s\" align=\"center\"><font size=\"-1\">%s</font></td>
                <td class=\"%s\" align=\"center\"><font size=\"-1\">%s</font></td>
                <td class=\"%s\" align=\"center\"><font size=\"-1\">%s</font></td>
                <td class=\"%s\" align=\"center\"><font size=\"-1\">%s</font></td>
                <td class=\"%s\" align=\"center\"><font size=\"-1\">%s</font></td>
                ",
                $cssSw->getClass(),
                htmlready($semdata['Name']),
                UrlHelper::getLink($semlink.$seminar_id),
                htmlready(substr($semdata['Name'], 0, 60)),
                (strlen($semdata['Name'])>60) ? "..." : "",
                !$semdata['visible'] ? ' ' . _("(versteckt)") : '',
                $cssSw->getClass(),
                htmlReady($semdata['dozenten']),
                $cssSw->getClass(),
                $semdata['link'],
                $cssSw->getClass(),
                ($semdata['plugin_activated'] ? 'ja' : 'nein') ,
                $cssSw->getClass(),
                $semdata['begin_evaluation'] ? date("d.m.Y", $semdata['begin_evaluation']) : '-',
                $cssSw->getClass(),
                $semdata['end_evaluation'] ? date("d.m.Y", $semdata['end_evaluation']) : '-',
                $cssSw->getClass(),
                ($semdata['time_frame_begin'] ? date("d.m.Y", $semdata['time_frame_begin']) : '-'),
                $cssSw->getClass(),
                ($semdata['time_frame_end'] ? date("d.m.Y", $semdata['time_frame_end']) : '-')
                );
                echo "</tr>";
            }
            echo "</table>";
            echo $form->getFormEnd();
            echo '</div>';
        } else {
            $_msg[] = array("info", sprintf(_("Sie wurden noch keinen Einrichtungen zugeordnet. Bitte wenden Sie sich an einen der zust&auml;ndigen %sAdministratoren%s."), "<a href=\"impressum.php?view=ansprechpartner\">", "</a>"));
        }

    }

    private function getInstitute($seminare_condition){
        global $perm, $user,$_default_sem;
        $db = new DB_Seminar();
        $db2 = new DB_Seminar();
        if($this->hasPermission()){
            $db->query("SELECT COUNT(*) FROM seminare WHERE 1 $seminare_condition");
            $db->next_record();
            $_my_inst['all'] = array("name" => _("alle") , "num_sem" => $db->f(0));
            $db->query("SELECT a.Institut_id,a.Name, 1 AS is_fak, count(seminar_id) AS num_sem FROM Institute a
            LEFT JOIN seminare ON(seminare.Institut_id=a.Institut_id $seminare_condition  ) WHERE a.Institut_id=fakultaets_id GROUP BY a.Institut_id ORDER BY is_fak,Name,num_sem DESC");
        }
        while($db->next_record()){
            $_my_inst[$db->f("Institut_id")] = array("name" => $db->f("Name"), "is_fak" => $db->f("is_fak"), "num_sem" => $db->f("num_sem"));
            if ($db->f("is_fak")){
                $_my_inst[$db->f("Institut_id").'_all'] = array("name" => '[Alle unter '.$db->f("Name").']', "is_fak" => 'all', "num_sem" => $db->f("num_sem"));
                $db2->query("SELECT a.Institut_id, a.Name,count(seminar_id) AS num_sem FROM Institute a
                LEFT JOIN seminare ON(seminare.Institut_id=a.Institut_id $seminare_condition  ) WHERE fakultaets_id='" . $db->f("Institut_id") . "' AND a.Institut_id!='" .$db->f("Institut_id") . "'
                GROUP BY a.Institut_id ORDER BY a.Name,num_sem DESC");
                $num_inst = 0;
                $num_sem_alle = $db->f("num_sem");
                while ($db2->next_record()){
                    if(!$_my_inst[$db2->f("Institut_id")]){
                        ++$num_inst;
                        $num_sem_alle += $db2->f("num_sem");
                    }
                    $_my_inst[$db2->f("Institut_id")] = array("name" => $db2->f("Name"), "is_fak" => 0 , "num_sem" => $db2->f("num_sem"));
                }
                $_my_inst[$db->f("Institut_id")]["num_inst"] = $num_inst;
                $_my_inst[$db->f("Institut_id").'_all']["num_inst"] = $num_inst;
                $_my_inst[$db->f("Institut_id").'_all']["num_sem"] = $num_sem_alle;
            }
        }
        return $_my_inst;
    }

    private function getSeminareData($seminare_condition){
        global $perm;
        $db = new DB_Seminar();
        $db2 = new DB_Seminar();
        $datafield1 = md5('UNIZENSUSPLUGIN_BEGIN_EVALUATION');
        $datafield2 = md5('UNIZENSUSPLUGIN_END_EVALUATION');
        $pluginid = $this->zensuspluginid;

        $ret = array();
        list($institut_id, $all) = explode('_', $_SESSION['zensus_admin']['institut_id']);
        if ($institut_id == "all"  && $this->hasPermission())
        $query = "SELECT Name,Seminar_id as seminar_id, VeranstaltungsNummer, visible FROM seminare WHERE 1 $seminare_condition ORDER BY Name";
        elseif ($all == 'all')
        $query = "SELECT seminare.Name,seminare.Seminar_id as seminar_id, seminare.VeranstaltungsNummer, seminare.visible FROM seminare LEFT JOIN seminar_inst USING (Institut_id)
        INNER JOIN Institute ON seminar_inst.institut_id = Institute.Institut_id WHERE Institute.fakultaets_id  = '{$institut_id}' $seminare_condition
        GROUP BY seminare.Seminar_id ORDER BY Name";
        else
        $query = "SELECT seminare.Name,seminare.Seminar_id as seminar_id, seminare.VeranstaltungsNummer, seminare.visible FROM seminare LEFT JOIN seminar_inst USING (Institut_id)
        WHERE seminar_inst.institut_id = '{$institut_id}' $seminare_condition
        GROUP BY seminare.Seminar_id ORDER BY Name";
        $db->query($query);
        while($db->next_record()){
            $seminar_id = $db->f("seminar_id");
            $ret[$seminar_id] = $db->Record;
            $query2 = "SELECT seminar_user.user_id,username,Nachname FROM seminar_user LEFT JOIN auth_user_md5 USING (user_id) WHERE seminar_id='$seminar_id' AND status='dozent' ORDER BY position,Nachname";
            $db2->query($query2);
            $c = 0;
            while($db2->next_record()){
                $ret[$seminar_id]['dozenten'][$db2->f('username')] = $db2->f('Nachname');
                if(++$c > 2) {
                    $ret[$seminar_id]['dozenten'][] = '...';
                    break;
                }
            }
            $query2 = "SELECT datafield_id,content FROM datafields_entries WHERE range_id='$seminar_id' AND datafield_id IN('$datafield1','$datafield2')";
            $db2->query($query2);
            while($db2->next_record()){
                if($db2->f('datafield_id') == $datafield1) $ret[$seminar_id]['begin_evaluation'] = UniZensusPlugin::SQLDateToTimestamp($db2->f('content'));
                if($db2->f('datafield_id') == $datafield2) $ret[$seminar_id]['end_evaluation'] = UniZensusPlugin::SQLDateToTimestamp($db2->f('content'));
            }
            $query2 = "SELECT state, 'sem' AS activated_by
            FROM plugins_activated pat
            WHERE pat.pluginid = '$pluginid'
            AND pat.poiid = 'sem$seminar_id'
            UNION SELECT 'on', 'default'
            FROM seminar_inst s
            JOIN Institute i ON i.Institut_id = s.institut_id
            JOIN plugins_default_activations pa ON i.fakultaets_id = pa.institutid
            OR i.Institut_id = pa.institutid
            JOIN plugins p ON pa.pluginid = p.pluginid
            WHERE s.seminar_id = '$seminar_id'
            AND p.pluginid = '$pluginid'";
            $db2->query($query2);
            while($db2->next_record()){
                $ret[$seminar_id]['activated_by_' . $db2->f('activated_by')] = $db2->f('state');
            }
        }
        return $ret;
    }

    function getExportData($key, $seminar_id)
    {
        static $data = array();
        if (!$data[$seminar_id]) {
            $data = array();
            $data[$seminar_id] = UniZensusPlugin::getAdditionalExportData($seminar_id);
        }
        if ($key == 'teilnehmer_anzahl_aktuell') {
            if ($data[$seminar_id]['eval_participants']) {
                return zensus_xmltag($key, $data[$seminar_id]['eval_participants']);
            } else {
                return zensus_xmltag($key,DbManager::get()->query("SELECT COUNT(*) FROM seminar_user WHERE seminar_id='".$seminar_id."' AND status='autor'")->fetchColumn());
            }
        }
        if ($key == 'resultpublic') {
            return zensus_xmltag($key, (int)$data[$seminar_id]['eval_public']);
        }
        if ($key == 'resultstore') {
            return zensus_xmltag($key, (int)$data[$seminar_id]['eval_stored']);
        }
    }

    function actionExport()
    {
        global $ex_sem, $ex_only_homeinst,$ex_sem_class, $ex_only_visible;

        global $xml_groupnames_fak,$xml_names_fak,$xml_groupnames_inst,$xml_names_inst
               ,$xml_groupnames_lecture,$xml_names_lecture,$xml_groupnames_person
               ,$xml_names_person,$xml_groupnames_studiengaenge,$xml_names_studiengaenge;

        require_once ('lib/export/export_xml_vars.inc.php');   // XML-Variablen

        $xml_names_lecture['teilnehmer_anzahl_aktuell'] = array($this, 'getExportData');
        $xml_names_lecture['resultpublic'] = array($this, 'getExportData');
        $xml_names_lecture['resultstore'] = array($this, 'getExportData');

        $ex_tstamp = Request::get('ex_tstamp');
        list($y,$M,$d,$h,$m) = explode('-', $ex_tstamp);
        $tstamp = mktime($h,$m,0,$M,$d,(int)$y);
        $hash = md5(get_config('UNIZENSUSPLUGIN_SHARED_SECRET1') . $ex_tstamp . get_config('UNIZENSUSPLUGIN_SHARED_SECRET2'));
        if ((Request::option('ex_hash') != $hash || $tstamp < (time() - 600))) {
            $export_error = 'authorization failed';
        } else {
            if (Request::option('ex_sem') == 'next') {
                $ex_sem = Semester::findNext()->semester_id;
            } else {
                $ex_sem = Semester::findCurrent()->semester_id;
            }
            if (!$ex_sem) {
                $export_error = 'no valid semester found';
            }
        }
        $range_id = Request::option('range_id', 'root');
        $ex_only_visible = Request::int('ex_only_visible', 1);
        $ex_only_homeinst = Request::int('ex_only_homeinst', 1);
        $ex_sem_class = Request::intArray('ex_sem_class');
        if (!count($ex_sem_class)) $ex_sem_class[] = 1;
        ini_set('memory_limit', '256M');
        while(ob_get_level()) ob_end_clean();
        header("Content-type: text/xml; charset=utf-8");
        if ($export_error) {
            echo '<?xml version="1.0"?>' . chr(10);
            echo zensus_xmltag('studip_export_error_msg', strip_tags($export_error));
            exit();
        }
        zensus_export_range($range_id, $ex_sem, 'direct');
    }

    function display_action($action) {
        if (strtolower($action) == 'actionshow') {
            if (!isset($GLOBALS['CURRENT_PAGE'])) {
                $GLOBALS['CURRENT_PAGE'] = $this->getDisplayTitle();
            }
            include 'lib/include/html_head.inc.php';
            include 'lib/include/header.php';
            $this->$action();
            include 'lib/include/html_end.inc.php';
        } else {
            $this->$action();
        }
    }

}

