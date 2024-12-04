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

// ** Massive actions **

function plugin_uninstall_MassiveActions($type)
{
    /** @var array $UNINSTALL_TYPES */
    global $UNINSTALL_TYPES;

   // Like GLPI 0.84, this plugin don't support massive actions in Global item page.
    if (isset($_REQUEST['container']) && $_REQUEST['container'] == 'massformAllAssets') {
        return [];
    }

    if (in_array($type, $UNINSTALL_TYPES)) {
        return ["PluginUninstallUninstall:uninstall" => __("Uninstall", 'uninstall')];
    }
    return [];
}

// ** Search **

function plugin_uninstall_addDefaultWhere($itemtype)
{

    switch ($itemtype) {
        case 'PluginUninstallModel':
            if (!PluginUninstallModel::canReplace()) {
                return "`glpi_plugin_uninstall_models`.`types_id` = '1'";
            }
            break;
        case 'PluginUninstallModelcontainer':
            if (isset($_GET['id'])) {
                return "`glpi_plugin_uninstall_modelcontainers`.`plugin_uninstall_models_id` = '" . $_GET['id'] . "'";
            }
            break;
        case 'PluginUninstallModelcontainerfield':
            if (isset($_GET['id'])) {
                return "`glpi_plugin_uninstall_modelcontainerfields`.`plugin_uninstall_modelcontainers_id` = '" . $_GET['id'] . "'";
            }
            break;
    }
}

// ** Install / Uninstall plugin **

function plugin_uninstall_install()
{
    $dir = Plugin::getPhpDir('uninstall');

    $plugin_infos = plugin_version_uninstall();
    $migration    = new Migration($plugin_infos['version']);

   //Plugin classes are not loaded when plugin is not activated : force class loading
    require_once($dir . "/inc/uninstall.class.php");
    require_once($dir . "/inc/profile.class.php");
    require_once($dir . "/inc/preference.class.php");
    require_once($dir . "/inc/model.class.php");
    require_once($dir . "/inc/replace.class.php");
    require_once($dir . "/inc/config.class.php");
    require_once($dir . "/inc/modelcontainer.class.php");
    require_once($dir . "/inc/modelcontainerfield.class.php");

    PluginUninstallProfile::install($migration);
    PluginUninstallModel::install($migration);
    PluginUninstallPreference::install($migration);
    PluginUninstallConfig::install($migration);
    PluginUninstallModelcontainer::install($migration);
    PluginUninstallModelcontainerfield::install($migration);

    $migration->executeMigration();

    return true;
}


function plugin_uninstall_uninstall()
{
    $dir = Plugin::getPhpDir('uninstall');

    require_once($dir . "/inc/uninstall.class.php");
    require_once($dir . "/inc/profile.class.php");
    require_once($dir . "/inc/preference.class.php");
    require_once($dir . "/inc/model.class.php");
    require_once($dir . "/inc/replace.class.php");
    require_once($dir . "/inc/config.class.php");

    PluginUninstallProfile::uninstall();
    PluginUninstallModel::uninstall();
    PluginUninstallPreference::uninstall();
    PluginUninstallConfig::uninstall();
    PluginUninstallModelcontainer::uninstall();
    PluginUninstallModelcontainerfield::uninstall();
    return true;
}

function plugin_uninstall_hook_add_container($item)
{
    global $UNINSTALL_TYPES;
    if (!($item instanceof PluginFieldsContainer)) {
        return;
    }
    $types = json_decode($item->fields['itemtypes']);
    // only create matching elements for containers concerning item types used by the plugin
    if (!empty(array_intersect($types, $UNINSTALL_TYPES))) {
        $containerId = $item->getID();
        $uninstallContainer = new PluginUninstallModelcontainer();
        $model = new PluginUninstallModel();
        $models = $model->find();
        foreach ($models as $mod) {
            $uninstallContainer->add([
                'plugin_uninstall_models_id' => $mod['id'],
                'plugin_fields_containers_id' => $containerId
            ]);
        }
    }
}

function plugin_uninstall_hook_add_field($item)
{
    if (!($item instanceof PluginFieldsField)) {
        return;
    }
    $fieldId = $item->getID();
    $uninstallContainer = new PluginUninstallModelcontainer();
    $uninstallContainers = $uninstallContainer->find(
        ['plugin_fields_containers_id' => $item->fields['plugin_fields_containers_id']]
    );
    $uninstallField = new PluginUninstallModelcontainerfield();
    foreach ($uninstallContainers as $container) {
        $uninstallField->add([
            'plugin_uninstall_modelcontainers_id' => $container['id'],
            'plugin_fields_fields_id' => $fieldId
        ]);
    }
}

function plugin_uninstall_hook_purge_container($item)
{
    if (!($item instanceof PluginFieldsContainer)) {
        return;
    }
    global $DB;
    $containerId = $item->getID();
    // all uninstall containers associated with the purged item
    $pluginUninstallContainers = $DB->request([
        'FROM' => PluginUninstallModelcontainer::getTable(),
        'SELECT' => 'id',
        'WHERE' => ['plugin_fields_containers_id' => $containerId]
    ]);
    $ids = [];
    foreach ($pluginUninstallContainers as $cont) {
        $ids[] = $cont['id'];
    }
    // delete all uninstall fields associated with one of the previously fetched uninstall container
    if (count($ids)) {
        $DB->delete(
            PluginUninstallModelcontainerfield::getTable(),
            ['plugin_uninstall_modelcontainers_id' => $ids]
        );
    }
    // delete all uninstall containers associated with the purged item
    $DB->delete(
        PluginUninstallModelcontainer::getTable(),
        ['plugin_fields_containers_id' => $containerId]
    );
}

function plugin_uninstall_hook_purge_field($item)
{
    if (!($item instanceof PluginFieldsField)) {
        return;
    }
    global $DB;
    $fieldId = $item->getID();
    $DB->delete(
        PluginUninstallModelcontainerfield::getTable(),
        ['plugin_fields_fields_id' => $fieldId]
    );
}
