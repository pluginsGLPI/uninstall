<?php

/**
 * -------------------------------------------------------------------------
 * Uninstall plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Uninstall.
 *
 * Uninstall is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Uninstall is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Uninstall. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2015-2022 by Teclib'.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/uninstall
 * -------------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

Html::header(__('Transfer'), $_SERVER['PHP_SELF'], "admin", "transfer");

Session::checkSeveralRightsOr(['uninstall:profile' => READ,
                               'uninstall:profile' => PluginUninstallProfile::RIGHT_REPLACE]);

if (!isset($_REQUEST["device_type"])
    || !isset($_REQUEST["model_id"])
    || ($_REQUEST["model_id"] == 0)) {
   Html::back();
}

if (isset($_REQUEST["locations_id"])) {
   $location = $_REQUEST["locations_id"];
} else {
   $location = PluginUninstallPreference::getLocationByUserByEntity($_SESSION["glpiID"],
                                                                    $_REQUEST["model_id"],
                                                                    $_SESSION["glpiactive_entity"]);
}

if (isset($_REQUEST["replace"])) {

   PluginUninstallReplace::replace($_REQUEST["device_type"], $_REQUEST["model_id"],
                                   $_REQUEST['newItems'], $location);

   unset($_SESSION['glpi_uninstalllist']);
   Session::addMessageAfterRedirect(__('Replacement successful', 'uninstall'));

   Html::footer();

   $device_type = $_REQUEST["device_type"];
   Html::redirect($device_type::getSearchURL());
}

$model = new PluginUninstallModel();
$model->getConfig($_REQUEST["model_id"]);

//Case of a uninstallation initiated from the object form
if (isset($_REQUEST["uninstall"])) {

   //Uninstall only if a model is selected
   if ($model->fields['types_id'] == PluginUninstallModel::TYPE_MODEL_UNINSTALL) {
      //Massive uninstallation

      PluginUninstallUninstall::uninstall($_REQUEST["device_type"], $_REQUEST["model_id"],
                                          [$_REQUEST["device_type"]
                                                => [$_REQUEST["id"] => $_REQUEST["id"]]],
                                          $location);
      Html::back();
   } else {
      PluginUninstallReplace::showReplacementForm($_REQUEST["device_type"], $_REQUEST["model_id"],
                                       [$_REQUEST["device_type"]
                                             => [$_REQUEST["id"] => $_REQUEST["id"]]],
                                       $location);
      Html::footer();
   }

} else {

   if ($model->fields['types_id'] == PluginUninstallModel::TYPE_MODEL_UNINSTALL) {
      //Massive uninstallation
      if (isset($_SESSION['glpi_uninstalllist'])) {
         PluginUninstallUninstall::uninstall($_REQUEST["device_type"], $_REQUEST["model_id"],
                                             $_SESSION['glpi_uninstalllist'], $location);
      }

      unset($_SESSION['glpi_uninstalllist']);
      Session::addMessageAfterRedirect(__('Uninstallation successful', 'uninstall'));

      Html::footer();

      $device_type = $_REQUEST["device_type"];
      Html::redirect($device_type::getSearchURL());

   } else {
      if (isset($_SESSION['glpi_uninstalllist'])) {
         PluginUninstallReplace::showReplacementForm($_REQUEST["device_type"], $_REQUEST["model_id"],
                                          $_SESSION['glpi_uninstalllist'], $location);
      }
      Html::footer();
   }
}
