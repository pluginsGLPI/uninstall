<?php
/*
 * @version $Id: model.class.php 171 2015-01-28 09:50:39Z orthagh $
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

class PluginUninstallModel extends CommonDBTM {

   static $rightname         = "uninstall:profile";
   public $dohistory         = true;
   public $first_level_menu  = "plugins";
   public $second_level_menu = "uninstall";

   const TYPE_MODEL_UNINSTALL   = 1;
   const TYPE_MODEL_REPLACEMENT = 2;

   static function getTypeName($nb=0) {
      return __("Uninstallation template", 'uninstall');
   }

   static function canDelete() {
      return self::canUpdate();
   }

   static function canPurge() {
      return self::canUpdate();
   }

   static function canCreate() {
      return self::canUpdate();
   }

   static function canReplace() {
      return Session::haveRight(self::$rightname, PluginUninstallProfile::RIGHT_REPLACE) ? true : false;
   }

   static function getMenuContent() {
      global $CFG_GLPI;
      $menu          = array();

      // get Menu name :
      $tab = plugin_version_uninstall();
      $menu['title'] = $tab["name"];

      $menu['page']  = '/plugins/uninstall/front/model.php';

      if (Session::haveRight(PluginUninstallProfile::$rightname, READ)) {

         $menu['options']['model']['title'] = self::getTypeName(1);
         $menu['options']['model']['page'] = Toolbox::getItemTypeSearchUrl('PluginUninstallModel', false);
         $menu['options']['model']['links']['search'] = Toolbox::getItemTypeSearchUrl('PluginUninstallModel', false);

         if (Session::haveRight(PluginUninstallProfile::$rightname, UPDATE)) {
            $menu['options']['model']['links']['add'] = Toolbox::getItemTypeFormUrl('PluginUninstallModel', false);
         }

      }

      return $menu;
   }

   function prepareInputForAdd($input) {

      if (isset($input['_groups_id_action'])
            && ($input['_groups_id_action'] == 'old')) {
          $input['groups_id'] = -1;
      }
      return $input;
   }


   function prepareInputForUpdate($input) {
      return $this->prepareInputForAdd($input);
   }

   /**
    * Dropdown of model type
    *
    * @param $name   select name (default 'types_id')
    * @param $value  default value (default '')
   **/
   static function dropdownType($name='types_id', $value='') {

      $values[1] = __('Uninstallation', 'uninstall');
      if (self::canReplace()) {
         $values[2] = __('Replacement', 'uninstall');
      }
      Dropdown::showFromArray($name, $values, array('value' => $value));
   }


   static function getReplacementMethods() {

      $plug = new Plugin();
      if ($plug->isActivated('PDF')) {
         $archive_method = " - ".__('PDF Archiving', 'uninstall');
      } else {
         $archive_method = " - ".__('CSV Archiving', 'uninstall');
      }

      return array(PluginUninstallReplace::METHOD_PURGE => __('Purge', 'uninstall') . $archive_method,
                   PluginUninstallReplace::METHOD_DELETE_AND_COMMENT => __('Delete + Comment', 'uninstall'));
   }


   /**
    * Dropdown of method remplacement
    *
    * @param $name   select name
    * @param $value  default value (default '')
   **/
   static function dropdownMethodReplacement($name, $value='') {

      Dropdown::showFromArray($name, self::getReplacementMethods(),
                              array('value' => $value));
   }


   /**
    * @param $value  (default 0)
   **/
   static function getMethodReplacement($value=0) {

      $values = self::getReplacementMethods();
      if (isset($values[$value])) {
         return $values[$value];
      }
      return "";
   }

   /**
    * Définition du nom de l'onglet
    **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      switch ($item->getType()) {
         case 'Preference' :
            return PluginUninstallUninstall::getTypeName(1);

         case __CLASS__ :
            $tab = array();
            $tab[1] = self::getTypeName(1);
            $tab[2] = __('Replacing data', 'uninstall');
            return $tab;
      }
      return '';
   }

   /**
    * Définition du contenu de l'onglet
    **/
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      switch ($item->getType()) {
         case __CLASS__ :
            switch ($tabnum) {
               case 1 :
                  $item->showForm($item->getID());
                  break;
               case 2 :
                  $item->showFormAction($item);
                  break;
            }
      }
      return true;
   }

   function defineTabs($options=array()) {
      $ong = array();
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('Log', $ong, $options);
      return $ong;
   }

   function showForm($ID, $options=array()) {
      global $DB, $CFG_GLPI;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      $entities = (isset($_SESSION['glpiparententities']) ? $_SESSION['glpiparententities'] : 0);
      $entity_sons = empty($entity_sons) ? 0 : 1;

      echo "<tr class='tab_bg_1'><td>" . __('Name') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this,'name');
      echo "</td>";
      echo "<td>" . __('Type of template', 'uninstall')."</td>";
      echo "<td>";
      $value = (isset ($this->fields["types_id"]) ? $this->fields["types_id"] : 0);
      self::dropdownType('types_id', $value);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
         echo "<td>" . __("Transfer's model to use", "uninstall") ."</td>";
         echo "<td>";
         if ($ID == -1) {
            $value = PluginUninstallUninstall::getUninstallTransferModelid();
         } else {
            $value = $this->fields["transfers_id"];
         }
         Transfer::dropdown(array('value'               => $value,
                                  'display_emptychoice' => false));
      } else {
         echo "<td></td>";
         echo "<td></td>";
         echo "<input type='hidden' name='transfers_id' value='0'";
      }
      echo "</td>";
      echo "<td rowspan='4'>" . __('Comments') . "</td>";
      echo "<td rowspan='4'>";
      echo "<textarea cols='60' rows='4' name='comment'>" . $this->fields["comment"] . "</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>" . __('New status of the computer', 'uninstall') ."</td>";
      echo "<td>";
      State::dropdown(array('value'       => $this->fields['states_id'],
                            'emptylabel'  => __('None')));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      if ($ID == -1) {
         $this->fields['groups_id'] = -1;
      }

      if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
         echo "<td>" . __('Action on group', 'uninstall') . "</td><td>";
         $uninst = new PluginUninstallUninstall();
         $action = $uninst->dropdownFieldAction("groups_id", $this->fields['entities_id'],
                                                $entity_sons, $this->fields["groups_id"]);
         echo "</td>";
      } else {
         echo "<td colspan='2'></td>";
      }
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
         echo "<td>" . __('New group', 'uninstall') . "</td><td>";
         echo "<span id='show_groups' name='show_groups'>";
         if ($this->fields['groups_id'] != -1) {
            Group::dropdown(array('value'       => $this->fields["groups_id"],
                                  'entity'      => $this->fields["entities_id"],
                                  'entity_sons' => $entities,
                                  'emptylabel'  => __('None')));
         } else {
            echo Dropdown::EMPTY_VALUE;
         }
         echo "</span></td>";
      } else {
         echo "<td colspan='2'></td>";
      }
      echo "</tr>";

      if (!Session::isMultiEntitiesMode()
          && Session::haveRight("transfer", READ)) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='2'>";
         echo "<a href='" . $CFG_GLPI["root_doc"] .
                "/front/transfer.form.php'\">" . __('Add template', 'uninstall') . "</td>";
         echo "<td colspan='2'>";
         echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/transfer.php'\">" .
                __('Manage templates', 'uninstall') . "</a></td>";
         echo "</tr>";
      }

      $this->showFormButtons($options);

      return true;
   }

   function showPartFormUninstall() {
      echo "<tr class='tab_bg_1 center'>";
      echo "<th colspan='4'>" . __('Erase datas', 'uninstall') . "</th></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" . __('Delete software history (computers)', 'uninstall') . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_soft_history",
                          (isset($this->fields["raz_soft_history"])
                           ? $this->fields["raz_soft_history"] : 1));
      echo "</td><td>".__('Delete the whole history', 'uninstall')."</td><td>";
      Dropdown::showYesNo("raz_history",
                          (isset ($this->fields["raz_history"])
                           ? $this->fields["raz_history"] : 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>".sprintf(__('%1$s %2$s'), __('Blank'), __('Name')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_name",
                          (isset($this->fields["raz_name"])
                           ? $this->fields["raz_name"] : 1));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'),  __('Blank'), __('Contact')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_contact",
                          (isset($this->fields["raz_contact"])
                           ? $this->fields["raz_contact"] : 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Blank'), __('User')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_user",
                          (isset($this->fields["raz_user"])
                           ? $this->fields["raz_user"] : 1));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Blank'), __('Operating system')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_os",
                          (isset($this->fields["raz_os"]) ? $this->fields["raz_os"] : 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Blank'), __('Network')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_network",
                          (isset($this->fields["raz_network"])
                           ? $this->fields["raz_network"] : 1));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Blank'), __('Domain')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_domain",
                          (isset($this->fields["raz_domain"])
                           ? $this->fields["raz_domain"] : 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>".sprintf(__('%1$s %2$s'),__('Blank'),
                          __('IP')." & " . __('Subnet mask') . " & " . __('Gateway')." & ".
                           __('Subnet')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_ip",
                          (isset($this->fields["raz_ip"]) ? $this->fields["raz_ip"] : 1));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Blank'), __('Budget')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("raz_budget",
                          (isset($this->fields["raz_budget"])
                           ? $this->fields["raz_budget"] : 0));
      echo "</td></tr>";
   }

   function showPartFormRemplacement() {
      echo "<tr class='tab_bg_1 center'>";
      echo "<th colspan='4'>".sprintf(__('%1$s - %2$s'),
                                      __('Informations replacement', 'uninstall'),
                                      __('General informations', 'uninstall'))."</th></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'), __('Name')) . "</td><td>";
      Dropdown::showYesNo("replace_name",
                          (isset($this->fields["replace_name"])
                           ? $this->fields["replace_name"]: 1),
                           -1, array('width' => '100%'));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'), __('Serial number')) . "</td><td>";
      Dropdown::showYesNo("replace_serial",
                          (isset($this->fields["replace_serial"])
                           ? $this->fields["replace_serial"]: 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'),__('Inventory number')) . "</td><td>";
      Dropdown::showYesNo("replace_otherserial",
                          (isset($this->fields["replace_otherserial"])
                           ? $this->fields["replace_otherserial"]: 1),
                           -1, array('width' => '100%'));
      echo "</td>";
      echo "<td>".__('Overwrite informations (from old item to the new)', 'uninstall')."</td>";
      echo "<td>";
      Dropdown::showYesNo("overwrite",
                          (isset($this->fields["overwrite"]) ? $this->fields["overwrite"]: 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" . __('Archiving method of the old material', 'uninstall') . "</td>";
      echo "<td colspan='2'>";
      $value = (isset($this->fields["replace_method"]) ? $this->fields["replace_method"] : 0);
      self::dropdownMethodReplacement('replace_method', $value);
      echo "</td>";
      echo "<td>";
      $plug = new Plugin();
      if ($plug->isActivated('PDF')
          && $plug->fields['version'] >= '0.7.1') {
         echo "<span class='green b tracking_small'>".
                __('Plugin PDF is installed and activated', 'uninstall')."</span>";
      } else {
         echo "<span class='red b tracking_small'>".
                __("Plugin PDF is not installed, you won't be able to use PDF format for archiving",
                   "uninstall")."</span>";
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<th colspan='4'>".sprintf(__('%1$s - %2$s'),
                                      __('Informations replacement', 'uninstall'),
                                      __('Connections with other materials', 'uninstall'));
      echo "</th></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>".sprintf(__('%1$s %2$s'), __('Copy'), _n('Document', 'Documents', 2))."</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_documents",
                          (isset($this->fields["replace_documents"])
                           ? $this->fields["replace_documents"] : 1),
                           -1, array('width' => '100%'));
      echo "</td>";
      echo "<td>".sprintf(__('%1$s %2$s'), __('Copy'), _n('Contract', 'Contracts', 2))."</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_contracts",
                          (isset($this->fields["replace_contracts"])
                           ? $this->fields["replace_contracts"] : 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>".sprintf(__('%1$s %2$s'), __('Copy'),
                          __('Financial and administratives information')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_infocoms",
                          (isset($this->fields["replace_infocoms"])
                           ? $this->fields["replace_infocoms"] : 1),
                           -1, array('width' => '100%'));
      echo "</td>";
      echo "<td>".sprintf(__('%1$s %2$s'), __('Copy'), _n('Reservation', 'Reservations', 2));
      echo "</td>";
      echo "<td>";
      if (isset($this->fields["replace_reservations"])) {
         $reservation = $this->fields["replace_reservations"];
      } else {
         $reservation = 1;
      }
      Dropdown::showYesNo("replace_reservations", $reservation);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'), __('User')) . "</td>";
      echo "<td>";
      if (isset($this->fields["replace_users"])) {
         $user = $this->fields["replace_users"];
      } else {
         $user = 1;
      }
      Dropdown::showYesNo("replace_users", $user, -1, array('width' => '100%'));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'), __('Group')) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_groups",
                          (isset($this->fields["replace_groups"])
                           ? $this->fields["replace_groups"] : 1));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'), _n('Ticket', 'Tickets', 2)). "</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_tickets",
                          (isset ($this->fields["replace_tickets"])
                           ? $this->fields["replace_tickets"] : 1),
                           -1, array('width' => '100%'));
      echo "</td>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'),
                           sprintf(__('%1$s %2$s'), _n('Connection', 'Connections', 2),
                                   _n('Network', 'Networks', 2))) . "</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_netports",
                          (isset ($this->fields["replace_netports"])
                           ? $this->fields["replace_netports"] : 1));
      echo "</td></tr>";

      echo "<tr class='tab_bg_1 center'>";
      echo "<td>" .sprintf(__('%1$s %2$s'), __('Copy'), __('Direct connections', 'uninstall'));
      echo "</td>";
      echo "<td>";
      Dropdown::showYesNo("replace_direct_connections",
                          (isset($this->fields["replace_direct_connections"])
                           ? $this->fields["replace_direct_connections"] : 1),
                           -1, array('width' => '100%'));
      echo "</td>";
      echo "<td colspan='2'></td>";
      echo "</tr>";
   }

   /**
    * @param $item
   **/
   function showFormAction($item) {
      global $DB;

      $spotted = false;
      $id      = $item->getID();

      if ($id > 0) {
         if ($this->can($id, READ)) {
            $spotted = true;
         }
      } else {
         //$use_cache = false;
         if ($this->can(-1, UPDATE)) {
            $spotted = true;
            $this->getEmpty();
         }
      }

      if (! $spotted) {
         echo "<span class='center b'>" . __('No item found') . "</span>";
         return false;
      }

      $canedit = $this->can($id, UPDATE);
      echo "<form action='".$item->getFormURL()."' method='post'>";
      echo "<table class='tab_cadre_fixe' cellpadding='5'>";

      if ($this->fields["types_id"] == self::TYPE_MODEL_UNINSTALL) {
         // if Uninstall is selected
         self::showPartFormUninstall();
      } else {
         // if Replacement is selected
         self::showPartFormRemplacement();
      }

      $plug = new Plugin();
      if ($plug->isActivated('ocsinventoryng')) {
         echo "<tr class='tab_bg_1 center'>";
         echo "<th colspan='4'>" . _n('OCSNG link', 'OCSNG links', 2, 'ocsinventoryng').
              "</th></tr>";
         echo "<th colspan='4'>".__('These options only apply to computers coming from OCSNG',
                                    'uninstall') . "</th></tr>";

         echo "<tr class='tab_bg_1 center'>";
         echo "<td>" . __('Delete computer in OCSNG', 'ocsinventoryng') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("remove_from_ocs",
                             (isset($this->fields["remove_from_ocs"])
                              ? $this->fields["remove_from_ocs"] : 0), -1, array('width' => '100%'));
         echo "</td>";
         echo "<td>" . __('Delete link with computer in OCSNG', 'uninstall') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("delete_ocs_link",
                             (isset($this->fields["delete_ocs_link"])
                              ? $this->fields["delete_ocs_link"] : 0));
         echo "</td></tr>";
      }

      if ($plug->isActivated('fusioninventory')) {
         echo "<tr class='tab_bg_1 center'>";
         echo "<th colspan='4'>" . __('FusionInventory').
              "</th></tr>";

         echo "<tr class='tab_bg_1 center'>";
         echo "<td>" . __('Delete computer in FusionInventory', 'uninstall') . "</td>";
         echo "<td>";
         Dropdown::showYesNo("raz_fusioninventory",
                             (isset($this->fields["raz_fusioninventory"])
                              ? $this->fields["raz_fusioninventory"] : 0), -1, array('width' => '100%'));
         echo "</td>";
         echo "<td colspan='2'></td>";
         echo "</tr>";
      }

      if ($canedit) {
         echo "<tr class='tab_bg_1 center'>";
         echo "<td colspan='4' class='center'>";
         echo "<input type='hidden' name='id' value='" . $this->fields["id"] . "'>";
         echo "<input type='submit' name='update' value=\"" . _sx('button', 'Save') . "\" class='submit'>";
         echo "</td></tr>";
      }

      echo "</table>";

      echo "<input type='hidden' name='entities_id' value='".$this->fields["entities_id"]."'>";
      Html::closeForm();

      return true;
   }


   /**
    * @param $model_id
   **/
   function getConfig($model_id) {
      if (! $this->getFromDB($model_id)) {
         $this->fields = array();
      }
   }


   function cleanDBonPurge() {
      PluginUninstallPreference::deleteUserPreferenceForModel($this->fields['id']);
   }


   function getSearchOptions() {

      $tab                       = array();

      $tab['common']             = self::getTypeName();

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();

      $tab[3]['table']           = $this->getTable();
      $tab[3]['field']           = 'raz_name';
      $tab[3]['name']            = sprintf(__('%1$s %2$s'), __('Blank'), __('Name'));
      $tab[3]['datatype']        = 'bool';

      $tab[4]['table']           = $this->getTable();
      $tab[4]['field']           = 'raz_soft_history';
      $tab[4]['name']            = __('Delete software history (computers)', 'uninstall');
      $tab[4]['datatype']        = 'bool';

      $tab[5]['table']           = $this->getTable();
      $tab[5]['field']           = 'raz_contact';
      $tab[5]['name']            = sprintf(__('%1$s %2$s'), __('Blank'), __('Contact'));
      $tab[5]['datatype']        = 'bool';

      $tab[6]['table']           = $this->getTable();
      $tab[6]['field']           = 'raz_user';
      $tab[6]['name']             = sprintf(__('%1$s %2$s'), __('Blank'), __('User'));
      $tab[6]['datatype']        = 'bool';

      $tab[7]['table']           = 'glpi_states';
      $tab[7]['field']           = 'name';
      $tab[7]['name']            = __('Status');
      $tab[7]['datatype']        = 'dropdown';

      $tab[8]['table']           = $this->getTable();
      $tab[8]['field']           = 'raz_os';
      $tab[8]['name']            = sprintf(__('%1$s %2$s'), __('Blank'), __('Operating system'));
      $tab[8]['datatype']        = 'bool';

      $tab[9]['table']           = $this->getTable();
      $tab[9]['field']           = 'raz_network';
      $tab[9]['name']            = sprintf(__('%1$s %2$s'), __('Blank'), __('Network'));
      $tab[9]['datatype']        ='bool';

      $tab[10]['table']          = $this->getTable();
      $tab[10]['field']          = 'raz_domain';
      $tab[10]['name']           = sprintf(__('%1$s %2$s'), __('Blank'), __('Domain'));
      $tab[10]['datatype']       = 'bool';

      $tab[11]['table']          = $this->getTable();
      $tab[11]['field']          = 'raz_ip';
      $tab[11]['name']           = sprintf(__('%1$s %2$s'), __('Blank'),
                                           __('IP')." & ".__('Subnet mask')." & ".__('Gateway').
                                           " & ".__('Subnet'));
      $tab[11]['datatype']       = 'bool';

      $tab[12]['table']          = $this->getTable();
      $tab[12]['field']          = 'raz_budget';
      $tab[12]['name']           = sprintf(__('%1$s %2$s'), __('Blank'), __('Budget'));
      $tab[12]['datatype']       = 'bool';

      $tab[13]['table']          = $this->getTable();
      $tab[13]['field']          = 'is_recursive';
      $tab[13]['name']           = __('Child entities');
      $tab[13]['datatype']       = 'bool';

      $tab[15]['table']          = 'glpi_transfers';
      $tab[15]['field']          = 'name';
      $tab[15]['name']           = __("Transfer's model to use", "uninstall");
      $tab[15]['datatype']       = 'itemlink';
      $tab[15]['itemlink_type']  = 'Transfer';

      $tab[17]['table']          = $this->getTable();
      $tab[17]['field']          = 'comment';
      $tab[17]['name']           = __('Comments');
      $tab[17]['datatype']       = 'text';

      $plug = new Plugin();
      if ($plug->isActivated('ocsinventoryng')) {

         $tab[18]['table']       = $this->getTable();
         $tab[18]['field']       = 'remove_from_ocs';
         $tab[18]['name']        = __('Delete computer in OCSNG', 'ocsinventoryng');
         $tab[18]['datatype']    = 'bool';

         $tab[19]['table']       = $this->getTable();
         $tab[19]['field']       = 'delete_ocs_link';
         $tab[19]['name']        = __('Delete link with computer in OCSNG', 'uninstall');
         $tab[19]['datatype']    ='bool';
      }

      $tab[20]['table']          = $this->getTable();
      $tab[20]['field']          = 'types_id';
      $tab[20]['name']           = __('Type of template', 'uninstall');
      $tab[20]['linkfield']      = '';
      $tab[20]['datatype']       = 'specific';
      $tab[20]['searchtype']     = 'equals';

      $tab[21]['table']          = $this->getTable();
      $tab[21]['field']          = 'groups_id';
      $tab[21]['linkfield']      = '';
      $tab[21]['name']           = __('Action on group', 'uninstall');
      $tab[21]['datatype']       = 'specific';
      $tab[21]['searchtype']     = 'equals';

      $tab[22]['table']          = $this->getTable();
      $tab[22]['field']          = 'replace_method';
      $tab[22]['linkfield']      = '';
      $tab[22]['name']           = __('Archiving method of the old material', 'uninstall');
      $tab[22]['datatype']       = 'specific';
      $tab[22]['searchtype']     = 'equals';

      $tab[23]['table']          = $this->getTable();
      $tab[23]['field']          = 'raz_history';
      $tab[23]['name']           = __('Delete the whole history', 'uninstall');
      $tab[23]['datatype']       = 'bool';

      $tab[24]['table']          = $this->getTable();
      $tab[24]['field']          = 'replace_users';
      $tab[24]['name']           = sprintf(__('%1$s %2$s'), __('Copy'), __('User'));
      $tab[24]['datatype']       = 'bool';

      $tab[25]['table']          = $this->getTable();
      $tab[25]['field']          = 'replace_name';
      $tab[25]['name']           = sprintf(__('%1$s %2$s'), __('Copy'), __('Name'));
      $tab[25]['datatype']       = 'bool';

      $tab[26]['table']          = $this->getTable();
      $tab[26]['field']          = 'replace_serial';
      $tab[26]['name']           = sprintf(__('%1$s %2$s'), __('Copy'), __('Serial number'));
      $tab[26]['datatype']       = 'bool';

      $tab[27]['table']          = $this->getTable();
      $tab[27]['field']          = 'replace_otherserial';
      $tab[27]['name']           = sprintf(__('%1$s %2$s'), __('Copy'), __('Inventory number'));
      $tab[27]['datatype']       = 'bool';

      $tab[28]['table']          = $this->getTable();
      $tab[28]['field']          = 'replace_documents';
      $tab[28]['name']           = sprintf(__('%1$s %2$s'), __('Copy'),
                                           _n('Document', 'Documents', 2));
      $tab[28]['datatype']       = 'bool';

      $tab[29]['table']          = $this->getTable();
      $tab[29]['field']          = 'replace_contracts';
      $tab[29]['name']           = sprintf(__('%1$s %2$s'), __('Copy'),
                                           _n('Contract', 'Contracts', 2));
      $tab[29]['datatype']       = 'bool';

      $tab[30]['table']          = $this->getTable();
      $tab[30]['field']          = 'replace_infocoms';
      $tab[30]['name']           = sprintf(__('%1$s %2$s'), __('Copy'),
                                           __('Financial and administratives information'));
      $tab[30]['datatype']       = 'bool';

      $tab[31]['table']          = $this->getTable();
      $tab[31]['field']          = 'replace_reservations';
      $tab[31]['name']           = sprintf(__('%1$s %2$s'), __('Copy'),
                                           _n('Reservation', 'Reservations', 2));
      $tab[31]['datatype']       = 'bool';

      $tab[32]['table']          = $this->getTable();
      $tab[32]['field']          = 'replace_groups';
      $tab[32]['name']           = sprintf(__('%1$s %2$s'), __('Copy'), __('Group'));
      $tab[32]['datatype']       = 'bool';

      $tab[80]['table']          = 'glpi_entities';
      $tab[80]['field']          = 'completename';
      $tab[80]['name']           = __('Entity');
      $tab[80]['datatype']       = 'dropdown';

      /*
      $tab[33]['table']      = $this->getTable();
      $tab[33]['field']      = 'raz_ocs_registrkeys';
      $tab[33]['linkfield']  = 'raz_ocs_registrykeys';
      $tab[33]['name']       = 'unknown'; //unknown name on 0.85
      $tab[33]['datatype']   = 'bool';
      $tab[33]['searchtype'] = 'equals';
      */

      return $tab;
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $values
    * @param $options   array
   **/
   static function getSpecificValueToDisplay($field, $values, array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {

         case 'replace_method' :
            if ($values['replace_method'] != 0) {
               return self::getMethodReplacement($values['replace_method']);
            }
            return Dropdown::EMPTY_VALUE;
            break;

         case 'types_id' :
            if ($values['types_id'] == self::TYPE_MODEL_UNINSTALL) {
               return __('Uninstallation', 'uninstall');
            }
            return __('Replacement', 'uninstall');
            break;

         case 'groups_id' :
            if ($values['groups_id'] < 0) {
               return __('Keep in the current group', 'uninstall');
            } else if (!$values['groups_id']) {
               return __('None');
            }
            return Dropdown::getDropdownName('glpi_groups', $values['groups_id']);

            break;

      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $name               (default '')
    * @param $values             (defaut '')
    * @param $options   array
   **/
   static function getSpecificValueToSelect($field, $name='', $values='', array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      $options['display'] = false;
      switch ($field) {
         case 'replace_method':

            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getReplacementMethods(), $options);

         case 'types_id' :

            $types[self::TYPE_MODEL_UNINSTALL] = __('Uninstallation', 'uninstall');
            if (self::canReplace()) {
               $types[self::TYPE_MODEL_REPLACEMENT] = __('Replacement', 'uninstall');
            }
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, $types, $options);

         case 'groups_id' :
            $options['name']        = $name;
            $options['value']       = $values[$field];
            $options['emptylabel']  = __('None');
            return Group::dropdown($options);

      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * Get the standard massive actions which are forbidden
    *
    * @since version 0.84
    *
    * @return an array of massive actions
    **/
   public function getForbiddenStandardMassiveAction() {

      $forbidden = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }


   static function install($migration) {
      global $DB;

      // From 0.2 to 1.0.0
      if (TableExists('glpi_plugin_uninstallcomputer_config')) {
         $table = 'glpi_plugin_uninstall_models';
         $migration->renameTable('glpi_plugin_uninstallcomputer_config',  $table);
         $migration->addField($table, 'FK_entities', 'integer');
         $migration->addField($table, 'recursive', 'int(1) NOT NULL DEFAULT 1');
         $migration->addField($table, 'name', 'string');
         $migration->addField($table, `comments`, 'text', array('value' => 'NOT NULL'));

         $migration->migrationOneTable($table);
         $ID = PluginUninstallUninstall::getUninstallTransferModelID();
         $query = "INSERT INTO `glpi_plugin_uninstall_models`
                          (`FK_entities`,`recursive`,`name`,`transfer_id`, `state`, `raz_name`,
                           `raz_contact`, `raz_ip`, `raz_os`, `raz_domain`, `raz_network`,
                           `raz_soft_history`, `raz_budget`)
                   VALUES (0, 1, 'Uninstall',".$ID.", 0, 1, 1, 1, 1, 1, 1, 0, 0)";
         $DB->queryOrDie($query, "add uninstall model in ".$table);
      }

      // Plugin already installed
      $table = 'glpi_plugin_uninstall_models';
      if (TableExists($table)) {
         // From 1.0.0 to 1.1.0
         if (!FieldExists($table, 'group')) {
            $migration->addField($table, 'group', 'integer');
            $migration->addField($table, 'remove_from_ocs', 'int(1) NOT NULL DEFAULT 0');
         }

         // From 1.1.0 to 1.2.1
         if (!FieldExists($table, 'delete_ocs_link')) {
            $migration->addField($table, 'delete_ocs_link', 'int(1) NOT NULL DEFAULT 0');
         }

         // from 1.2.1 to 1.3.0
         if (FieldExists($table, 'ID')) {
            $migration->changeField($table, 'ID', 'id', 'autoincrement');
            $migration->changeField($table, 'FK_entities', 'entities_id', 'integer');
            $migration->changeField($table, 'recursive', 'is_recursive', "bool",
                                    array('value' => 1));
            $migration->changeField($table, 'transfer_id', 'transfers_id', "integer");
            $migration->changeField($table, 'state', 'states_id', "integer");
            $migration->changeField($table, 'group', 'groups_id', "integer");
         }

         // from 1.3.0 to 2.0.0
         if (!FieldExists($table, 'types_id')) {

            $migration->addField($table, 'types_id', 'integer');
            $migration->migrationOneTable($table);
            $query = "UPDATE `".$table."`
                      SET `types_id` = '1'
                      WHERE `types_id` = '0'";
            $DB->queryOrDie($query, "update types_id of ".$table);

            $migration->addField($table, 'replace_name', "bool");
            $migration->addField($table, 'replace_serial', "bool");
            $migration->addField($table, 'replace_otherserial', "bool");
            $migration->addField($table, 'replace_documents', "bool");
            $migration->addField($table, 'replace_contracts', "bool");
            $migration->addField($table, 'replace_infocoms', "bool");
            $migration->addField($table, 'replace_reservations', "bool");
            $migration->addField($table, 'replace_users', "bool");
            $migration->addField($table, 'replace_groups', "bool");
            $migration->addField($table, 'replace_tickets', "bool");
            $migration->addField($table, 'replace_netports', "bool");
            $migration->addField($table, 'replace_direct_connections', "bool");
            $migration->addField($table, 'overwrite', "bool");
            $migration->addField($table, 'replace_method', "integer", array('value' => 2));

            $migration->migrationOneTable($table);
            self::createTransferModel('Replace');
         }

         // from 2.0.0 to 2.0.1
         if (!FieldExists($table, 'raz_history')) {
            $migration->addField($table, 'raz_history', 'integer', array('after' => 'raz_network'));
         }

         if (!FieldExists($table, 'raz_ocs_registrykeys')) {
            $migration->addField($table, 'raz_ocs_registrkeys', "integer");
         }

         if (!FieldExists($table, 'raz_fusioninventory')) {
            $migration->addField($table, 'raz_fusioninventory', "integer");
         }
         $migration->migrationOneTable($table);

      // plugin never installed
      } else {
         $query = "CREATE TABLE IF NOT EXISTS `".getTableForItemType(__CLASS__)."` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `entities_id` int(11) DEFAULT '0',
                    `is_recursive` tinyint(1) NOT NULL DEFAULT '1',
                    `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                    `transfers_id` int(11) NOT NULL,
                    `states_id` int(11) NOT NULL,
                    `raz_name` int(1) NOT NULL DEFAULT '1',
                    `raz_contact` int(1) NOT NULL DEFAULT '1',
                    `raz_ip` int(1) NOT NULL DEFAULT '1',
                    `raz_os` int(1) NOT NULL DEFAULT '1',
                    `raz_domain` int(1) NOT NULL DEFAULT '1',
                    `raz_network` int(1) NOT NULL DEFAULT '1',
                    `raz_history` int(1) NOT NULL DEFAULT '1',
                    `raz_soft_history` int(1) NOT NULL DEFAULT '1',
                    `raz_budget` int(1) NOT NULL DEFAULT '1',
                    `raz_user` int(1) NOT NULL DEFAULT '1',
                    `raz_ocs_registrykeys` int(1) NOT NULL DEFAULT '1',
                    `comment` text COLLATE utf8_unicode_ci NOT NULL,
                    `groups_id` int(11) NOT NULL DEFAULT '0',
                    `remove_from_ocs` int(1) NOT NULL DEFAULT '0',
                    `delete_ocs_link` int(1) NOT NULL DEFAULT '0',
                    `types_id` int(11) NOT NULL default '0',
                    `replace_name` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_serial` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_otherserial` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_documents` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_contracts` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_infocoms` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_reservations` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_users` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_groups` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_tickets` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_netports` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_direct_connections` tinyint(1) NOT NULL DEFAULT '0',
                    `overwrite` tinyint(1) NOT NULL DEFAULT '0',
                    `replace_method` int(11) NOT NULL DEFAULT '2',
                    `raz_fusioninventory` int(1) NOT NULL DEFAULT '1',
                    PRIMARY KEY (`id`)
                  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

         $DB->queryOrDie($query, $DB->error());

         self::createTransferModel('Uninstall');
         self::createTransferModel('Replace');
      }
      return true;
   }


   static function uninstall() {
      global $DB;

      $DB->query("DROP TABLE IF EXISTS `".getTableForItemType(__CLASS__)."`");

      //If a transfer model exists for this plugin -> delete it
      $transfer_id     = PluginUninstallUninstall::getUninstallTransferModelID(false);
      if ($transfer_id) {
         $tr = new Transfer();
         $tr->delete(array('id' => $transfer_id), true);
      }

      //Delete history
      $log = new Log();
      $log->dohistory = false;
      $log->deleteByCriteria(array('itemtype' => __CLASS__));
   }


   /**
    * @param $name   (default 'Uninstall')
   **/
   static function createTransferModel($name='Uninstall') {

      $transfers_id = PluginUninstallUninstall::getUninstallTransferModelID();

      if (!countElementsInTable('glpi_plugin_uninstall_models', "`name`='$name'")) {
         $model = new self();
         $tmp['entities_id']                = 0;
         $tmp['is_recursive']               = 1;
         $tmp['name']                       = $name;
         $tmp['transfers_id']               = $transfers_id;
         $tmp['states_id']                  = 0;
         $tmp['raz_name']                   = 1;
         $tmp['raz_contact']                = 1;
         $tmp['raz_ip']                     = 1;
         $tmp['raz_os']                     = 1;
         $tmp['raz_domain']                 = 1;
         $tmp['raz_network']                = 1;
         $tmp['raz_soft_history']           = 1;
         $tmp['raz_budget']                 = 1;
         $tmp['raz_user']                   = 1;
         $tmp['raz_ocs_registrykeys']       = 1;
         $tmp['raz_fusioninventory']        = 1;
         $tmp['comment']                    = '';
         $tmp['groups_id']                  = 0;
         $tmp['remove_from_ocs']            = 0;
         $tmp['delete_ocs_link']            = 0;
         if ($name == 'Uninstall') {
            $tmp['types_id']                = self::TYPE_MODEL_UNINSTALL;
         } else {
            $tmp['types_id']                = self::TYPE_MODEL_REPLACEMENT;
         }
         $tmp['replace_name']               = 1;
         $tmp['replace_serial']             = 1;
         $tmp['replace_otherserial']        = 1;
         $tmp['replace_documents']          = 1;
         $tmp['replace_contracts']          = 1;
         $tmp['replace_infocoms']           = 1;
         $tmp['replace_reservations']       = 1;
         $tmp['replace_users']              = 1;
         $tmp['replace_groups']             = 1;
         $tmp['replace_tickets']            = 1;
         $tmp['replace_netports']           = 1;
         $tmp['replace_direct_connections'] = 1;
         $tmp['overwrite']                  = 0;
         $tmp['replace_method']             = PluginUninstallReplace::METHOD_DELETE_AND_COMMENT;
         $model->add($tmp);
      }
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
    **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {
      global $UNINSTALL_TYPES;

      switch ($ma->getAction()) {
         case 'transfert':
            Entity::dropdown();
            echo "&nbsp;".
                  Html::submit(_x('button','Post'), array('name' => 'massiveaction'));
            return true;
      }
      return "";
   }

   function getSpecificMassiveActions($checkitem=NULL) {

      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($isadmin) {
         if (Session::haveRight('transfer', READ)
             && Session::isMultiEntitiesMode()) {
            $actions['PluginUninstallModel:transfert'] = __('Transfer');
         }
      }

      return $actions;
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case "transfert":
            $input = $ma->getInput();
            $entities_id = $input['entities_id'];

            foreach ($ids as $id) {
               if ($item->getFromDB($id)) {
                  $item->update(array(
                        "id" => $id,
                        "entities_id" => $entities_id,
                        "update" => __('Update'),
                  ));
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
               }
            }
            return;
               break;
      }
      return;
   }

}
?>
