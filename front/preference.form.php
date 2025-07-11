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
 * @copyright Copyright (C) 2015-2023 by Teclib'.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/uninstall
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight(PluginUninstallPreference::$rightname, UPDATE);

// Save user preferences
if (isset($_POST['update_user_preferences_uninstall'])) {
    $pref = new PluginUninstallPreference();
    foreach ($_POST["id"] as $prefid => $values) {
        $pref = new PluginUninstallPreference();
        // load preferences and check if current user is the related user
        if ($pref->getFromDB($_POST["id"]) && $pref->fields['users_id'] == Session::getLoginUserID()) {
            $pref->update($values);
        } else {
            Html::displayRightError("You don't have permission to perform this action");
        }
    }
    Html::back();
}
