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

if (!isset($_GET["withtemplate"])) {
    $_GET["withtemplate"] = "";
}

if (isset($_GET["id"])) {
    $id = $_GET["id"];
} else if (isset($_POST["id"])) {
    $id = $_POST["id"];
} else {
    $id = -1;
}

$model = new PluginUninstallModel();

if (isset($_POST["add"])) {
    $model->check(-1, UPDATE, $_POST);
    if ($id = $model->add($_POST)) {
        $relationsCreated = false;
        if (isset($_POST['action_plugin_fields_uninstall'])) {
            if ($_POST['action_plugin_fields_uninstall'] == PluginUninstallModel::PLUGIN_FIELDS_ACTION_ADVANCED) {
                $model->createPluginFieldsRelations($id);
                $relationsCreated = true;
            }
        }
        // possible that the relations were created in previous if for replace then uninstall model types
        if (isset($_POST['action_plugin_fields_replace']) && !$relationsCreated) {
            if ($_POST['action_plugin_fields_replace'] == PluginUninstallModel::PLUGIN_FIELDS_ACTION_ADVANCED) {
                $model->createPluginFieldsRelations($id);
            }
        }
    }
    Html::back();
} else if (isset($_POST["update"])) {
    $model->check($_POST['id'], UPDATE);
    if ($model->update($_POST)) {
        $relationsCreated = false;
        if (isset($_POST['action_plugin_fields_uninstall'])) {
            if ($_POST['action_plugin_fields_uninstall'] == PluginUninstallModel::PLUGIN_FIELDS_ACTION_ADVANCED) {
                $model->createPluginFieldsRelations($id);
                $relationsCreated = true;
            }
        }
        if (isset($_POST['action_plugin_fields_replace']) && !$relationsCreated) {
            if ($_POST['action_plugin_fields_replace'] == PluginUninstallModel::PLUGIN_FIELDS_ACTION_ADVANCED) {
                $model->createPluginFieldsRelations($id);
            }
        }
    }
    Html::back();
} else if (isset($_POST['purge'])) {
    $model->check($_POST['id'], DELETE);
    $model->delete($_POST);
    $model->redirectToList();
} else if (isset($_GET["load_fields"])) {
    $model->check($id, UPDATE);
    $model->createPluginFieldsRelations($id);
    Html::back();
} else {
    Html::header(
        PluginUninstallModel::getTypeName(),
        $_SERVER['PHP_SELF'],
        "admin",
        "PluginUninstallModel",
        "model"
    );

    if ($model->getFromDB($id)) {
        if ($model->fields['types_id'] == PluginUninstallModel::TYPE_MODEL_REPLACEMENT) {
            if (
                !Session::haveRight(
                    'uninstall:profile',
                    PluginUninstallProfile::RIGHT_REPLACE
                )
            ) {
                Html::displayRightError();
            }
        }
    }

    $model->display(['id'           => $id,
        'withtemplate' => $_GET["withtemplate"]
    ]);

    Html::footer();
}
