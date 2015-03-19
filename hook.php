<?php
/*
 * @version $Id: hook.php 157 2013-07-31 06:56:26Z yllen $
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

function plugin_uninstall_postinit() {
   global $UNINSTALL_TYPES;

   foreach ($UNINSTALL_TYPES as $type) {
      CommonGLPI::registerStandardTab($type, 'PluginUninstallUninstall');
   }
}

// ** Massive actions **

function plugin_uninstall_MassiveActions($type) {
   global $UNINSTALL_TYPES;

   if (in_array($type, $UNINSTALL_TYPES)) {
      return array ("plugin_uninstall" => __("Uninstall", 'uninstall'));
   }
   return array ();
}


function plugin_uninstall_MassiveActionsDisplay($options=array()) {
   global $UNINSTALL_TYPES;

   if (in_array($options['itemtype'], $UNINSTALL_TYPES)) {
      $uninst = new PluginUninstallUninstall();
      $uninst->dropdownUninstallModels("model_id", $_SESSION["glpiID"],
                                       $_SESSION["glpiactive_entity"]);
      echo "&nbsp;<input type='submit' name='massiveaction' class='submit' value=\"" .
                   _sx('button','Post'). "\" >";
   }
   return "";

}


function plugin_uninstall_MassiveActionsProcess($data) {
   global $CFG_GLPI;

   $res = array('ok'      => 0,
                'ko'      => 0,
                'noright' => 0);

   switch ($data["action"]) {
      case "plugin_uninstall" :
         foreach ($data["item"] as $key => $val) {
            if ($val == 1) {
               $_SESSION['glpi_uninstalllist'][$data['itemtype']][$key] = $key;
            }
         }
         // TODO review this to avoid action launch by GET
         $res['ok']++;
         $REDIRECT = $CFG_GLPI["root_doc"] . '/plugins/uninstall/front/action.php?device_type=' .
                     $data["itemtype"] . "&model_id=" . $data["model_id"];
         break;
   }

   Html::redirect($REDIRECT);
   return $res;
}

// ** Search **

function plugin_uninstall_addDefaultWhere($itemtype) {

   switch ($itemtype) {
      case 'PluginUninstallModel' :
         if (!PluginUninstallModel::canReplace()) {
            return "`glpi_plugin_uninstall_models`.`types_id` = '1'";
         }
         break;
   }
}

// ** Install / Uninstall plugin **

function plugin_uninstall_install() {

   $plugin_infos = plugin_version_uninstall();
   $migration    = new Migration($plugin_infos['version']);

   //Plugin classes are not loaded when plugin is not activated : force class loading
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/uninstall.class.php");
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/profile.class.php");
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/preference.class.php");
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/model.class.php");

   PluginUninstallProfile::install($migration);
   PluginUninstallModel::install($migration);
   PluginUninstallPreference::install($migration);
   return true;
}


function plugin_uninstall_uninstall() {

   require_once (GLPI_ROOT . "/plugins/uninstall/inc/uninstall.class.php");
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/profile.class.php");
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/preference.class.php");
   require_once (GLPI_ROOT . "/plugins/uninstall/inc/model.class.php");

   PluginUninstallProfile::uninstall();
   PluginUninstallModel::uninstall();
   PluginUninstallPreference::uninstall();
   return true;
}
