<?php
/*
 * @version $Id: dropdownReplaceFindDevice.php 149 2013-07-10 09:54:40Z tsmr $
 LICENSE

 This file is part of the uninstall plugin.

 Uninstall plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Uninstall plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with uninstall. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   uninstall
 @author    the uninstall plugin team
 @copyright Copyright (c) 2010-2013 Uninstall plugin team
 @license   GPLv2+
            http://www.gnu.org/licenses/gpl.txt
 @link      https://forge.indepnet.net/projects/uninstall
 @link      http://www.glpi-project.org/
 @since     2009
 ---------------------------------------------------------------------- */

include ('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

// Security
if (!TableExists($_POST['table'])) {
   exit();
}

$itemtypeisplugin = isPluginItemType($_POST['itemtype']);
$item             = new $_POST['itemtype']();

if ($item->isEntityAssign()) {
   // allow opening ticket on recursive object (printer, software, ...)
   $where = getEntitiesRestrictRequest("WHERE", $_POST['table'], '',
                                         $_SESSION['glpiactiveentities'], $item->maybeRecursive());

} else {
   $where = "WHERE 1";
}

if ($item->maybeDeleted()) {
   $where .= " AND `is_deleted` = '0' ";
}
if ($item->maybeTemplate()) {
   $where .= " AND `is_template` = '0' ";
}

if ((strlen($_POST['searchText']) > 0)
    && ($_POST['searchText'] != $CFG_GLPI["ajax_wildcard"])) {
   $search = Search::makeTextSearch($_POST['searchText']);

   $where .= " AND (`name` ".$search."
                    OR `id` = '".$_POST['searchText']."'
                    OR `serial` ".$search."
                    OR `otherserial` ".$search.")";
}

//If software or plugins : filter to display only the objects that are allowed to be visible in Helpdesk
if (in_array($_POST['itemtype'], $CFG_GLPI["helpdesk_visible_types"])) {
   $where .= " AND `is_helpdesk_visible` = '1' ";
}

if (isset($_POST['current_item']) && ($_POST['current_item'] > 0)) {
   $where .= " AND `id` != " . $_POST['current_item'];
}

$NBMAX = $CFG_GLPI["dropdown_max"];
$LIMIT = "LIMIT 0,$NBMAX";

if ($_POST['searchText'] == $CFG_GLPI["ajax_wildcard"]) {
   $LIMIT = "";
}

$query = "SELECT *
          FROM `".$_POST['table']."`
          $where
          ORDER BY `name`
          $LIMIT";
$result = $DB->query($query);

echo "<select name='newItems[\"".$_POST['newItems_id']."\"]' size='1'>";

if (($_POST['searchText'] != $CFG_GLPI["ajax_wildcard"])
    && ($DB->numrows($result) == $NBMAX)) {
   echo "<option value='0'>--".__('Limited view')."--</option>";
}

echo "<option value='0'>".Dropdown::EMPTY_VALUE."</option>";

if ($DB->numrows($result)) {
   while ($data = $DB->fetch_array($result)) {
      $output = $data['name'];

      if (($_POST['table'] != "glpi_softwares")
          && !$itemtypeisplugin) {
         if (!empty($data['contact'])) {
            $output .= " - ".$data['contact'];
         }
         if (!empty($data['serial'])) {
            $output .= " - ".$data['serial'];
         }
         if (!empty($data['otherserial'])) {
            $output .= " - ".$data['otherserial'];
         }
      }

      if (empty($output) || $_SESSION['glpiis_ids_visible']) {
         $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
      }
      echo "<option value='".$data['id']."' title=\"".Html::cleanInputText($output)."\">".
            Toolbox::substr($output, 0, $_SESSION["glpidropdown_chars_limit"])."</option>";
   }
}

echo "</select>";
