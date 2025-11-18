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

class PluginUninstallConfig extends Config
{
    public const CFG_CTXT = 'plugin:uninstall';

    public static function getTypeName($nb = 0)
    {
        return __("Item's Lifecycle", 'uninstall');
    }

    /**
     * Return the current config of the plugin store in the glpi config table
     *
     * @return array config with keys => values
     */
    public static function getConfig()
    {
        return Config::getConfigurationValues(self::CFG_CTXT);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() === "Config") {
            return self::createTabEntry(self::getTypeName(), 0, $item::getType(), PluginUninstallReplace::getIcon());
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Config) {
            return self::showForConfig($item, $withtemplate);
        }

        return true;
    }

    public static function showForConfig(Config $config, $withtemplate = 0)
    {
        if (!self::canView()) {
            return false;
        }

        $cfg     = self::getConfig();
        $canedit = Session::haveRight(self::$rightname, UPDATE);
        echo "<div class='uninstall_config'>";
        if ($canedit) {
            echo "<form name='form' action='" . Toolbox::getItemTypeFormURL("Config") . "' method='post'>";
        }

        echo "<h2 class='header'>" . __s("Shortcuts", 'uninstall') . "</h2>";

        echo "<ul class='shortcuts'>";
        echo "<li><a href='" . PluginUninstallModel::getSearchURL() . "' class='vsubmit'>"
        . PluginUninstallModel::getTypeName(Session::getPluralNumber()) . "</a><li>";
        echo "<li><a href='preference.php?forcetab=PluginUninstallPreference$1' class='vsubmit'>"
        . __s("Location preferences", 'uninstall') . "</a><li>";
        echo "</ul>";

        echo "<h2 class='header'>" . __s("Configuration") . "</h2>";

        $rand = mt_rand();
        echo "<div class='field'>";
        echo sprintf("<label for='dropdown_replace_status_dropdown%d'>", $rand)
           . __s("Replace status dropdown by plugin actions", 'uninstall')
           . "</label>";
        Dropdown::showYesNo("replace_status_dropdown", $cfg['replace_status_dropdown'], -1, [
            'rand' => $rand,
        ]);
        echo "</div>";

        if ($canedit) {
            echo Html::hidden('config_class', ['value' => self::class]);
            echo Html::hidden('config_context', ['value' => self::CFG_CTXT]);
            echo Html::submit(_sx('button', 'Save'), [
                'name' => 'update',
                'class' => 'vsubmit',
            ]);
        }

        Html::closeForm();
        echo "</div>"; //.uninstall_config
        return null;
    }


    /**
     * Database table installation for the item type
     *
     * @return boolean True on success
     */
    public static function install(Migration $migration)
    {
        $current_config = self::getConfig();

        // fill config table with default values if missing
        foreach (['replace_status_dropdown' => 0] as $key => $value) {
            if (!isset($current_config[$key])) {
                Config::setConfigurationValues(self::CFG_CTXT, [$key => $value]);
            }
        }

        return true;
    }

    /**
     * Database table uninstallation for the item type
     *
     * @return boolean True on success
     */
    public static function uninstall()
    {
        $config = new Config();
        $config->deleteByCriteria(['context' => self::CFG_CTXT]);

        return true;
    }

    /**
     * Callback for Config `pre_item_add` hook.
     */
    public static function preConfigSet(Config $config): void
    {
        if (
            ($config->input['context'] ?? null) === 'inventory'
            && ($config->input['name'] ?? null) === '_stale_agents_uninstall'
        ) {
            $value = $config->input['value'] ?? 0;
            // Stop config `add` operation on `inventory` context.
            // Even if config already exists, it is submitted on `inventory` context and will therefore be
            // considered as new. We have to call `Config::setConfigurationValues()` using the good context to be able to
            // trigger `add` or `update` whether config already exists or not.
            $config->input = false;
            Config::setConfigurationValues('plugin:uninstall', ['stale_agents_uninstall' => $value]);
        }
    }

    /**
     * Show the configuration option for stale agents uninstallation
     *
     * @return string|false The HTML code to display the option or false if the option is not available
     */
    public static function renderStaleAgentConfigField()
    {
        $stale_agents_uninstall = Config::getConfigurationValue('plugin:uninstall', 'stale_agents_uninstall');
        if (!PluginUninstallModel::canView()) {
            return false;
        }

        return PluginUninstallModel::dropdown([
            'name' => '_stale_agents_uninstall',
            'value' => $stale_agents_uninstall ?? 0,
            'entity' => $_SESSION['glpiactive_entity'],
            'condition' => [
                'types_id' => PluginUninstallModel::TYPE_MODEL_UNINSTALL,
            ],
            'display' => false,
        ]);
    }
}
