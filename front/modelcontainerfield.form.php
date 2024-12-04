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

Session::checkRightsOr('uninstall:profile', [READ, PluginUninstallProfile::RIGHT_REPLACE]);

$field = new PluginUninstallModelcontainerfield();

if (isset($_POST["update"])) {
    // TODO handling of mandatory when action = SET_VALUE
    $field->check($_POST['id'], UPDATE);
    $field->update($_POST);
    Html::back();
} else {
    Html::header(
        PluginUninstallModelcontainerfield::getTypeName(),
        $_SERVER['PHP_SELF'],
        "admin",
        "PluginUninstallModel",
        "model"
    );

    if (isset($_GET["id"])) {
        PluginUninstallModelcontainerfield::displayFullPageForItem($_GET['id']);
    } else {
    }


    Html::footer();
}
