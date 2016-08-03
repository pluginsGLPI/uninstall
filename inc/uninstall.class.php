<?php
/*
 * @version $Id: uninstall.class.php 171 2015-01-28 09:50:39Z orthagh $
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

class PluginUninstallUninstall {

   const PLUGIN_UNINSTALL_TRANSFER_NAME = "plugin_uninstall";

   static $rightname = "uninstall:profile";

   static function getTypeName($nb=0) {
      return __("Item's uninstallation", 'uninstall');
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
    **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {
      global $UNINSTALL_TYPES;

      foreach ($ma->getItems() as $itemtype => $data) {
         if (!in_array($itemtype, $UNINSTALL_TYPES)) {
            return "";
         }
      }

      switch ($ma->getAction()) {
         case 'uninstall':
            $uninst = new PluginUninstallUninstall();
            $uninst->dropdownUninstallModels("model_id", $_SESSION["glpiID"],
                  $_SESSION["glpiactive_entity"]);
            echo "&nbsp;".
                  Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
                  return true;
      }
      return "";
   }


   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case "uninstall":

            $itemtype = $ma->getItemtype(false);

            foreach ($ids as $id) {
               if ($item->getFromDB($id)) {
                  //Session::addMessageAfterRedirect(sprintf(__('Form duplicated: %s', 'formcreator'), $item->getName()));
                  $_SESSION['glpi_uninstalllist'][$itemtype][$id] = $id;
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
               }
            }
            Html::redirect($CFG_GLPI["root_doc"] . '/plugins/uninstall/front/action.php?device_type=' .
                  $itemtype . "&model_id=" . $_POST["model_id"]);
            return;
            break;
      }
      return;
   }

   static function uninstall($type, $model_id, $tab_ids, $location) {
      global $UNINSTALL_DIRECT_CONNECTIONS_TYPE;
      //Get the model

      $model = new PluginUninstallModel();
      $model->getConfig($model_id);

      //Then destroy all the connexions
      $transfer = new Transfer();
      $transfer->getFromDB($model->fields["transfers_id"]);

      echo "<div class='center'>";
      echo "<table class='tab_cadre_fixe'><tr><th>".__('Uninstall', 'uninstall')."</th></tr>";
      echo "<tr class='tab_bg_2'><td>";
      $count = 0;
      $tot   = count($tab_ids[$type]);
      Html::createProgressBar(__('Please wait, uninstallation is running...', 'uninstall'));

      foreach ($tab_ids[$type] as $id => $value) {

         $count++;
         $item = new $type();
         $item->getFromDB($id);

         //First clean object and change location and status if needed
         $entity               = $item->fields["entities_id"];
         $input                = array();
         $input["id"]          = $id;
         $input["entities_id"] = $entity;
         $fields               = array();

         //Hook to perform actions before item is being uninstalled
         $item->fields['_uninstall_event'] = $model->getID();
         $item->fields['_action']          = 'uninstall';
         Plugin::doHook("plugin_uninstall_before", $item);

         //--------------------//
         //Direct connections //
         //------------------//
         if (in_array($type,$UNINSTALL_DIRECT_CONNECTIONS_TYPE)) {
            $conn = new Computer_Item();
            $conn->deleteByCriteria(array('computers_id' => $id), true);
         }

         //--------------------//
         //-- Common fields --//
         //------------------//

         //RAZ contact and contactnumber
         if ($item->isField('contact') && ($model->fields["raz_contact"] == 1)) {
            $fields["contact"] = '';

            if ($item->isField('contact_num')) {
               $fields["contact_num"] = '';
            }
         }

         //RAZ user
         if (($model->fields["raz_user"] == 1) && $item->isField('users_id')) {
            $fields["users_id"] = 0;
         }

         //RAZ status
         if (($model->fields["states_id"] > 0) && $item->isField('states_id')) {
            $fields["states_id"] = $model->fields["states_id"];
         }

         //RAZ machine's name
         if ($item->isField('name') && ($model->fields["raz_name"] == 1)) {
               $fields["name"] = '';
         }

         if ($item->isField('locations_id')) {
            if ($location == '') {
               $location = 0;
            }
            switch ($location) {
               case -1 :
                  break;

               default :
                  $fields["locations_id"] = $location;
                  break;
            }
         }

         if ($item->isField('groups_id')) {
            $nbgroup = countElementsInTableForEntity("glpi_groups", $entity,
                                                     "`id`='".$item->fields['groups_id']."'");
            if (($model->fields["groups_id"] > -1)
                && ($nbgroup == 1)) {
               // If a new group is defined and if the group is accessible in the object's entity
               $fields["groups_id"] = $model->fields["groups_id"];
            }
         }

         //------------------------------//
         //-- Computer specific fields --//
         //------------------------------//

         if ($type == 'Computer') {
            //RAZ all OS related informations
            if ($model->fields["raz_os"] == 1) {
               $fields["operatingsystems_id"]            = 0;
               $fields["operatingsystemversions_id"]     = 0;
               $fields["operatingsystemservicepacks_id"] = 0;
               $fields["os_licenseid"]                   = '';
               $fields["os_license_number"]              = '';
               $fields["autoupdatesystems_id"]           = 0;
            }

            $plug = new Plugin();
            if ($plug->isActivated('ocsinventoryng')) {
               if ($item->fields["is_dynamic"]
                   && ($model->fields["remove_from_ocs"] || $model->fields["delete_ocs_link"])) {
                  $input["is_dynamic"] = 0;
               }
            }

            if ($item->isField('domains_id') && $model->fields["raz_domain"]) {
               $fields["domains_id"] = 0;
            }

            //RAZ network
            if ($item->isField('networks_id') && ($model->fields["raz_network"] == 1)) {
               $fields["networks_id"] = 0;
            }
         }

         //RAZ IPs from all the network cards
         if ($model->fields["raz_ip"] == 1) {
            self::razPortInfos($type, $id);

            // For NetworkEquiment
            if ($item->isField('ip')) {
               $fields['ip'] = '';
            }
            if ($item->isField('mac')) {
               $fields['mac'] = '';
            }
         }

         foreach ($fields as $name => $value) {
            if (!($item->getField($name) != NOT_AVAILABLE)
                || ($item->getField($name) != $value)) {
               $input[$name] = $value;
            }
         }

         $item->dohistory = true;
         $item->update($input);

         if ($model->fields["raz_budget"] == 1) {
            $infocom_id = self::getInfocomPresentForDevice($type, $id);
            if ($infocom_id > 0) {
               $infocom            = new InfoCom();
               $tmp["id"]          = $infocom_id;
               $tmp["budgets_id"]  = 0;
               $infocom->dohistory = false;
               $infocom->update($tmp);
            }
         }

         //Delete machine from glpi_ocs_link
         if ($type == 'Computer') {
            //Delete computer's volumes
            self::purgeComputerVolumes($id);

            if ($model->fields["raz_history"] == 1) {
               //Delete history related to software
               self::deleteHistory($id, false);
            } else if ($model->fields["raz_soft_history"] == 1) {
               //Delete history related to software
               self::deleteHistory($id, true);
            }


            $plug = new Plugin();
            if ($plug->isActivated('ocsinventoryng')) {
               //Delete computer from OCS
               if ($model->fields["remove_from_ocs"] == 1) {
                  self::deleteComputerInOCSByGlpiID($id);
               }
               //Delete link in glpi_ocs_link
               if ($model->fields["delete_ocs_link"] || $model->fields["remove_from_ocs"]) {
                  self::deleteOcsLink($id);
               }
            }
            //Should never happend that transfer_id = 0, but just in case
            if ($model->fields["transfers_id"] > 0) {
               $transfer->moveItems(array($type => array($id => $id)),
                                    $entity, $transfer->fields);
            }
         }

         if ($model->fields['raz_fusioninventory'] == 1) {
            self::deleteFusionInventoryLink($type, $id);
         }

         //Plugin hook after uninstall
         Plugin::doHook("plugin_uninstall_after", $item);

         Html::changeProgressBarPosition($count,$tot+1);
      }

      //Add line in machine's history to say that machine was uninstalled
      self::addUninstallLog($type, $id);

      Html::changeProgressBarPosition($count,$tot,__('Uninstallation successful', 'uninstall'));

      echo "</td></tr>";
      echo "</table></div>";
   }


   /**
    * Function to uninstall an object
    *
    * @param $computers_id the computer's ID in GLPI
    *
    * @return nothing
   **/
   static function deleteOcsLink($computers_id) {

      $link = new PluginOcsinventoryngOcslink();
      $link->dohistory = false;
      $link->deleteByCriteria(array('computers_id' => $computers_id));

      $reg = new PluginOcsinventoryngRegistryKey();
      $reg->deleteByCriteria(array('computers_id' => $computers_id));

   }


   static function deleteRegistryKeys($computers_id) {
     $key = new RegistryKey();
     $key->deleteByCriteria(array('computers_id' => $computers_id));
   }

   /**
    * Remove a computer in the OCS database
    *
    * @param computer_id the computer's ID in GLPI
    *
    * @return nothing
   **/
   static function deleteComputerInOCSByGlpiID($computer_id) {
      global $DB;

      $crit     = "`computers_id` = '" . $computer_id . "'";
      $iterator = $DB->request('glpi_plugin_ocsinventoryng_ocslinks', $crit);

      if ($iterator->numrows() == 1) {
         $data = $iterator->next();
         self::deleteComputerInOCS($data["ocsid"], $data["plugin_ocsinventoryng_ocsservers_id"]);
         self::addUninstallLog('Computer', $computer_id, 'removeFromOCS', $data["ocsid"]);
      }
   }


   static function deleteComputerInOCS($ocs_id, $ocs_server_id) {

      $DBocs = PluginOcsinventoryngOcsServer::getDBocs($ocs_server_id)->getDB();

      //First try to remove all the network ports
      $query = "DELETE
                FROM `netmap`
                WHERE `MAC` IN (SELECT `MACADDR`
                                FROM `networks`
                                WHERE `networks`.`HARDWARE_ID` = '".$ocs_id."')";
      $DBocs->query($query);

      $tables = array ("accesslog", "accountinfo", "bios", "controllers", "devices", "drives",
            "download_history", "download_servers", "groups_cache", "inputs",
            "memories", "modems", "monitors", "networks", "ports", "printers",
            "registry", "slots", "softwares", "sounds", "storages", "videos");

      foreach ($tables as $table) {
         if (self::OcsTableExists($ocs_server_id, $table)) {
            $query = "DELETE
                      FROM `" . $table . "`
                      WHERE `hardware_id` = '".$ocs_id."'";
            $DBocs->query($query);
         }
      }

      $query = "DELETE
                FROM `hardware`
                WHERE `ID` = '".$ocs_id."'";
      $DBocs->query($query);

   }


   static function OcsTableExists($ocs_server_id, $tablename) {
      $DBocs = PluginOcsinventoryngOcsServer::getDBocs($ocs_server_id)->getDB();

      // Get a list of tables contained within the database.
      $result = $DBocs->list_tables("%".$tablename."%");
      if ($rcount = $DBocs->numrows($result)) {
         while ($data = $DBocs->fetch_row($result)) {
            if ($data[0] === $tablename) {
               return true;
            }
         }
      }
      $DBocs->free_result($result);
      return false;
   }

   /**
    * Function to uninstall an object
    *
    * @param $computers_id the computer's ID in GLPI
    *
    * @return nothing
   **/
   static function deleteFusionInventoryLink($itemtype, $items_id) {
      if (function_exists('plugin_pre_item_purge_fusioninventory')) {
         $item = new $itemtype();
         $item->getFromDB($items_id);
         $agent = new PluginFusioninventoryAgent();
         $agents = $agent->getAgentsFromComputers(array($items_id));

         // clean item associated to agents
         plugin_pre_item_purge_fusioninventory($item);

         if ($itemtype == 'Computer') {
            // remove agent(s)
            foreach($agents as $current_agent) {
               $agent->deleteByCriteria(array('id' => $current_agent['id']), true);
            }

            // remove licences
            $pfComputerLicenseInfo = new PluginFusioninventoryComputerLicenseInfo();
            $pfComputerLicenseInfo->deleteByCriteria(array('computers_id' => $items_id));

            // remove batteries
            $pfInventoryComputerBatteries = new PluginFusioninventoryInventoryComputerBatteries();
            $pfInventoryComputerBatteries->deleteByCriteria(array('computers_id' => $items_id));


            // Delete links between two computerstorages
            $pfInventoryComputerStorageStorageStorage = new PluginFusioninventoryInventoryComputerStorage_Storage();
            $tab_ids = $pfInventoryComputerStorageStorageStorage->getOpposites($items_id);
            if (!empty($tab_ids)) { //because getOpposites can return 0
               foreach ($tab_ids as $inventorycomputerstorages_id) {
                  $storage->deleteByCriteria(array(
                     'plugin_fusioninventory_inventorycomputerstorages_id_1' => $inventorycomputerstorages_id));
                  $storage->deleteByCriteria(array(
                     'plugin_fusioninventory_inventorycomputerstorages_id_2' => $inventorycomputerstorages_id));
               }
            }

            // ** Delete computerstorages **
            $pfInventoryComputerStorage = new PluginFusioninventoryInventoryComputerStorage();
            $pfInventoryComputerStorage->deleteByCriteria(array('computers_id' => $items_id));
         }
      }
   }


   static function purgeComputerVolumes($computers_id) {

      $computerdisk            = new ComputerDisk();
      $computerdisk->dohistory = false;
      $computerdisk->deleteByCriteria(array('computers_id' => $computers_id));
   }


   /**
    * Remove all the computer software's history
    *
    * @param computer_id      the computer's ID in GLPI
    * @param $only_history    (true by default)
    *
    * @return nothing
   **/
   static function deleteHistory($computer_id, $only_history=true) {
      global $DB;

      $where = "`itemtype` = 'Computer'
                AND `items_id` = '" . $computer_id . "'";

      if ($only_history) {
         $where .= " AND `linked_action` IN ('".Log::HISTORY_INSTALL_SOFTWARE."',
                                             '".Log::HISTORY_UNINSTALL_SOFTWARE."')";

      }
      $log            = new Log();
      $log->dohistory = false;

      foreach ($DB->request("glpi_logs", $where) as $row) {
         $log->delete($row);
      }
   }


   /**
    * @param type
    * @param $computer_id
    * @param $action          (default 'uninstall'
    * @param $ocs_id          (default '')
   **/
   static function addUninstallLog($type, $computer_id, $action='uninstall', $ocs_id='') {

      $changes[0] = 0;
      $changes[1] = "";

      switch ($action) {
         case 'uninstall' :
            $changes[2] = __('Item is now uninstalled', 'uninstall');
            break;

         case 'replaced_by':
            $changes[2] = __('Item replaced by a new one', 'uninstall');
            break;

         case 'replace':
            $changes[2] = __('Item replacing an old one', 'uninstall');
            break;

         case 'removeFromOCS' :
            $changes[2] = addslashes(sprintf(__('%1$s %2$s'),
                                             __('Removed from OCSNG with ID', 'uninstall'),
                                             $ocs_id));
            break;
      }
      Log::history($computer_id, $type, $changes, __CLASS__, Log::HISTORY_PLUGIN);
   }


   /**
    * Get an history entry message
    *
    * @param $data Array from glpi_logs table
    *
    * @since GLPI version 0.84
    *
    * @return string
   **/
   static function getHistoryEntry($data) {

      switch($data['linked_action'] - Log::HISTORY_PLUGIN) {
         case 0:
            return $data['new_value'];
      }
      return '';
   }


   /**
    * @param $create (true by default)
    * @return int
    */
   static function getUninstallTransferModelID($create = true) {
      global $DB;

      $iterator = $DB->request('glpi_transfers',
                               "`name`='" . self::PLUGIN_UNINSTALL_TRANSFER_NAME . "'");

      if (!$iterator->numrows()) {
         if ($create) {
            $transfer = new Transfer();
            $input["name"]              = self::PLUGIN_UNINSTALL_TRANSFER_NAME;
            $input["keep_networklink"]  = 2;
            $input["keep_history"]      = 1;
            $input["keep_devices"]      = 1;
            $input["keep_infocoms"]     = 1;
            $input["keep_enterprises"]  = 1;
            $input["keep_contacts"]     = 1;
            $input["keep_contracts"]    = 1;
            $input["keep_documents"]    = 1;
            $id = $transfer->add($input);
         } else {
            $id = 0;
         }
      } else {
         $data = $iterator->next();
         $id = $data['id'];
      }
      return $id;
   }


   /**
    * @param $type
    * @param $ID
   **/
   static function getInfocomPresentForDevice($type, $ID) {
      global $DB;

      $query = "SELECT `id`
              FROM `glpi_infocoms`
              WHERE `itemtype` = '".$type."'
                    AND `items_id` = '" . $ID . "'";
      $result = $DB->query($query);

      if ($DB->numrows($result) > 0) {
         return $DB->result($result, 0, "id");
      }
      return 0;
   }


   /**
    * @param $ID
    * @param $item
    * @param $user_id
   **/
   static function showFormUninstallation($ID, $item, $user_id) {
      global $CFG_GLPI;

      $type = $item->getType();
      // TODO review this to pass arg in form, not in URL.
      echo "<form action='".$CFG_GLPI["root_doc"]."/plugins/uninstall/front/action.php?device_type=$type'
             method='post'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'>";
      echo "<tr><th colspan='3'>" . self::getTypeName() . "</th></tr>";

      echo "<tr class='tab_bg_1'><td>" . PluginUninstallModel::getTypeName() . "</td><td>";
      $item = new $type();
      $item->getFromDB($ID);
      $rand = self::dropdownUninstallModels("model_id", $_SESSION["glpiID"],
                                            $item->fields["entities_id"]);
      echo "</td></tr>";

      $params = array('templates_id' => '__VALUE__',
                       'entity'       => $item->fields["entities_id"],
                       'users_id'     => $_SESSION["glpiID"]);

      Ajax::updateItemOnSelectEvent("dropdown_model_id$rand", "show_objects",
                                    $CFG_GLPI["root_doc"] . "/plugins/uninstall/ajax/locations.php",
                                    $params);

      echo "<tr class='tab_bg_1'><td>" . __("Item's location after uninstall", "uninstall") ."</td>";
      echo "<td><span id='show_objects'>\n".Dropdown::EMPTY_VALUE."</span></td>\n";
      echo "</tr>";

      echo "<tr class='tab_bg_1 center'><td colspan='3'>";
      echo "<input type='submit' name='uninstall' value=\"" ._sx('button', 'Post') . "\"
             class='submit'>";
      echo "<input type='hidden' name='id' value='" . $ID . "'>";
      echo "</td></tr>";
      echo "</table>";
      Html::closeForm();
   }


   /**
    * @param $type
    * @param $items_id
   **/
   static function razPortInfos($type, $items_id) {
      global $DB;

      $nn   = new NetworkName();
      $conn = new NetworkPort_NetworkPort();
      $vlan = new NetworkPort_Vlan();
      $crit = array('items_id' => $items_id,
                    'itemtype' => $type);

      foreach ($DB->request('glpi_networkports', $crit) as $data) {

         $nn->unaffectAddressesOfItem($data['id'], 'NetworkPort');

         if ($conn->getFromDBForNetworkPort($data['id'])) {
            $conn->dohistory = false;
            $conn->delete(array('id' => $conn->fields['id']));
         }

         //Delete vlan to port connection
         $crit = array('networkports_id' => $data['id']);
         $vlan->deleteByCriteria($crit);
      }
   }


   /**
    * @param $name
    * @param $user
    * @param $entity
   **/
   static function dropdownUninstallModels($name, $user, $entity) {
      global $DB;

      $used = array();

      if (!PluginUninstallModel::canReplace()) {
          foreach ($DB->request('glpi_plugin_uninstall_models', "`types_id` = '2'") as $data) {
             $used[] = $data['id'];
          }
      }

      return PluginUninstallModel::dropdown(array('name'   => $name,
                                                    'value'  => 0,
                                                    'entity' => $entity,
                                                    'used'   => $used));
   }


   /**
    * @param $name
    * @param $entity                (default 0)
    * @param $entity_sons  array
    * @param $value                 (default -1)
    */
   static function dropdownFieldAction($name, $entity=0, $entity_sons=array(), $value=-1) {
      global $CFG_GLPI;

      if ($value == -1) {
         $action = 'old';
      } else {
         $action = 'set';
      }

      $tabactions = array ('old' => __('Keep in the current group', 'uninstall'), // Keep the current value
                           'set' => __('Affect to a new group', 'uninstall')); // Affect a new value

      preg_match('/(.*)_id/', $name, $results);
      $ajax_page = $results[1];
      $rand      = Dropdown::showFromArray("_" . $name . "_action", $tabactions,
                                           array('value' => $action));

      $params    = array('id'          => '__VALUE__',
                         $name         => $value,
                         'entities_id' => $entity,
                         'entity_sons' => $entity_sons);

      Ajax::updateItemOnSelectEvent("dropdown__" . $name . "_action".$rand, "show_".$ajax_page,
                                    $CFG_GLPI["root_doc"]."/plugins/uninstall/ajax/$ajax_page.php",
                                    $params);
      return $action;
   }


   /**
    * @param $entity
    * @param $add_entity   (false by default)
   **/
   static function getAllTemplatesByEntity($entity, $add_entity=false) {
      global $DB, $CFG_GLPI;

      $templates = array();
      $query = "SELECT `entities_id`, `id`, `name`
                FROM `glpi_plugin_uninstall_models`".
                getEntitiesRestrictRequest(" WHERE", "glpi_plugin_uninstall_models", "entities_id",
                                           $entity, true)."
                ORDER BY `name`";
      $result = $DB->query($query);

      while ($datas = $DB->fetch_array($result)) {
         $templates[$datas["id"]] = ($add_entity
                                       ? Dropdown::getDropdownName("glpi_entities",
                                                                   $datas["entities_id"]) . " > "
                                       : "")
                                    .$datas["name"];
      }
      return $templates;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $UNINSTALL_TYPES;

      if (PluginUninstallProfile::canView()
         && in_array($item->getType(), $UNINSTALL_TYPES)) {
         if (!$withtemplate) {
            return __('Uninstall', 'uninstall');
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $UNINSTALL_TYPES;

      if (in_array($item->getType(), $UNINSTALL_TYPES)) {
         self::showFormUninstallation($item->fields['id'], $item, Session::getLoginUserID());
      }
      return true;
   }

}
?>
