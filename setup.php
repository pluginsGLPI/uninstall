<?php
/*
 * @version $Id: setup.php 154 2013-07-11 09:26:04Z yllen $
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

function plugin_init_uninstall() {
   global $PLUGIN_HOOKS, $CFG_GLPI,$UNINSTALL_TYPES,$UNINSTALL_DIRECT_CONNECTIONS_TYPE;

   $PLUGIN_HOOKS['csrf_compliant']['uninstall'] = true;

   Plugin::registerClass('PluginUninstallPreference', array('addtabon' => array('Preference')));

   Plugin::registerClass('PluginUninstallProfile', array('addtabon' => array('Profile')));

   $PLUGIN_HOOKS['change_profile']['uninstall'] = array('PluginUninstallProfile', 'changeProfile');

   $plugin = new Plugin();
   if ($plugin->isActivated('uninstall')) {
      $UNINSTALL_TYPES                    = array ('Computer', 'Monitor', 'NetworkEquipment',
                                                   'Peripheral', 'Phone', 'Printer');
      $UNINSTALL_DIRECT_CONNECTIONS_TYPE  = array('Monitor', 'Peripheral', 'Phone', 'Printer');


      if (Session::getLoginUserID()) {
         if (plugin_uninstall_haveRight("use", "r")) {
            $PLUGIN_HOOKS['use_massive_action']['uninstall']   = 1;

            //Menus
            $PLUGIN_HOOKS['menu_entry']['uninstall']           = 'front/model.php';
            $PLUGIN_HOOKS['submenu_entry']['uninstall']['search']
                                                               = 'front/model.php';

            //Item actions
            $PLUGIN_HOOKS['item_update']['uninstall']
               = array('PluginUninstallModel' => array('PluginUninstallPreference',
                                                       'afterUpdateModel')
               );
            $PLUGIN_HOOKS['item_delete']['uninstall']
               = array('PluginUninstallModel' => array('PluginUninstallPreference',
                                                       'beforeItemPurge'));

            $PLUGIN_HOOKS['pre_item_purge']['uninstall']
               = array('User' => array('PluginUninstallPreference', 'beforeItemPurge'));
         }

         if (Session::haveRight("config", "w")
             || Session::haveRight("profile", "r")) {
            $PLUGIN_HOOKS['submenu_entry']['uninstall']['add'] = 'front/model.form.php';
         }
      }
      $PLUGIN_HOOKS['post_init']['uninstall'] = 'plugin_uninstall_postinit';
   }
}

function plugin_version_uninstall() {
   return array('name'           => __("Item's uninstallation", 'uninstall'),
                'author'         => 'Walid Nouh, François Legastelois, Remi Collet',
                'license'        => 'GPLv2+',
                'homepage'       => 'https://forge.indepnet.net/projects/uninstall',
                'minGlpiVersion' => '0.85',
                'version'        => '2.2');
}

function plugin_uninstall_check_prerequisites() {
   if (version_compare(GLPI_VERSION,'0.85','lt')
       || version_compare(GLPI_VERSION,'0.86','ge')) {
      _e('This plugin requires GLPI >= 0.85', 'uninstall');
      return false;
   }
   return true;
}

function plugin_uninstall_check_config($verbose=false) {
   return true;
}

function plugin_uninstall_haveRight($module, $right) {
   $matches = array (""  => array ("","r","w"),
                     "r" => array ("r","w"),
                     "w" => array ("w"),
                     "1" => array ("1"),
                     "0" => array ("0","1"));

   if (isset ($_SESSION["glpi_plugin_uninstall_profile"][$module])
      && in_array($_SESSION["glpi_plugin_uninstall_profile"][$module], $matches[$right])) {
      return true;
   }
   return false;
}
