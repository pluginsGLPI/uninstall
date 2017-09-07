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

define ('PLUGIN_UNINSTALL_VERSION', '2.3.0');

/**
 * Function Init
 */
function plugin_init_uninstall() {
   global $PLUGIN_HOOKS, $CFG_GLPI, $UNINSTALL_TYPES,
          $UNINSTALL_DIRECT_CONNECTIONS_TYPE;

   $PLUGIN_HOOKS['csrf_compliant']['uninstall'] = true;

   Plugin::registerClass('PluginUninstallPreference', ['addtabon' => ['Preference']]);
   Plugin::registerClass('PluginUninstallProfile', ['addtabon' => ['Profile']]);

   $plugin = new Plugin();
   if ($plugin->isActivated('uninstall')) {
      $UNINSTALL_TYPES                    = ['Computer', 'Monitor',
                                             'NetworkEquipment',
                                             'Peripheral', 'Phone', 'Printer'];
      $UNINSTALL_DIRECT_CONNECTIONS_TYPE  = ['Monitor', 'Peripheral', 'Phone',
                                             'Printer'];

      if (Session::getLoginUserID()) {
         if (Session::haveRight(PluginUninstallProfile::$rightname, READ)) {
            $PLUGIN_HOOKS['use_massive_action']['uninstall'] = true;

            if (Session::haveRight('uninstall:profile', READ)) {
               // Add link in GLPI plugins list :
               $PLUGIN_HOOKS["menu_toadd"]['uninstall'] = ['admin' => 'PluginUninstallModel'];

               // add to 'Admin' menu :
               $PLUGIN_HOOKS['config_page']['uninstall'] = "front/model.php";
            }

            //Item actions
            $PLUGIN_HOOKS['item_update']['uninstall']
               = ['PluginUninstallModel'
                  => ['PluginUninstallPreference', 'afterUpdateModel']];
            $PLUGIN_HOOKS['item_delete']['uninstall']
               = ['PluginUninstallModel'
                  => ['PluginUninstallPreference', 'beforeItemPurge']];

            $PLUGIN_HOOKS['pre_item_purge']['uninstall']
               = ['User' => ['PluginUninstallPreference', 'beforeItemPurge']];
         }

      }
      $PLUGIN_HOOKS['post_init']['uninstall'] = 'plugin_uninstall_postinit';
   }
}

function plugin_version_uninstall() {
   return ['name'           => __("Item's uninstallation", 'uninstall'),
           'author'         => 'Walid Nouh, François Legastelois, Remi Collet',
           'license'        => '<a href="../plugins/uninstall/LICENSE" target="_blank">GPLv2+</a>',
           'homepage'       => 'https://github.com/pluginsGLPI/uninstall',
           'minGlpiVersion' => '9.2',
           'version'        => PLUGIN_UNINSTALL_VERSION,
           'license'        => 'GPLv2+',
           'requirements'   => [
              'glpi' => [
                 'min' => '9.2',
                 'max' => '9.3',
                 'dev' => true
              ]
           ]
         ];
}

function plugin_uninstall_check_prerequisites() {
   $version = rtrim(GLPI_VERSION, '-dev');
   if (version_compare($version, '9.2', 'lt')) {
      echo "This plugin requires GLPI 9.2";
      return false;
   }
   return true;
}

function plugin_uninstall_check_config($verbose=false) {
   return true;
}
