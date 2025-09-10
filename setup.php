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

use Glpi\Plugin\Hooks;

define('PLUGIN_UNINSTALL_VERSION', '2.10.0-beta2');
define("PLUGIN_UNINSTALL_MIN_GLPI", "11.0.0");
define("PLUGIN_UNINSTALL_MAX_GLPI", "11.0.99");

/**
 * Function Init
 */
function plugin_init_uninstall()
{
    /**
     * @var array $PLUGIN_HOOKS
     * @var array $UNINSTALL_TYPES
     * @var array $UNINSTALL_DIRECT_CONNECTIONS_TYPE
     */
    global $PLUGIN_HOOKS, $UNINSTALL_TYPES, $UNINSTALL_DIRECT_CONNECTIONS_TYPE;

    Plugin::registerClass(PluginUninstallPreference::class, ['addtabon' => [Preference::class]]);
    Plugin::registerClass(PluginUninstallProfile::class, ['addtabon' => [Profile::class]]);

    $plugin = new Plugin();
    if ($plugin->isActivated('uninstall')) {
        $UNINSTALL_TYPES                    = [Computer::class, Monitor::class, NetworkEquipment::class, Peripheral::class, Phone::class, Printer::class];
        $UNINSTALL_DIRECT_CONNECTIONS_TYPE  = [Monitor::class, Peripheral::class, Phone::class, Printer::class];

        if (Session::getLoginUserID()) {
            // config page
            Plugin::registerClass(PluginUninstallConfig::class, ['addtabon' => Config::class]);
            $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['uninstall'] = 'front/config.form.php';
            $uninstallconfig = PluginUninstallConfig::getConfig();

            $PLUGIN_HOOKS[Hooks::ADD_CSS]['uninstall'] = ['css/uninstall.css'];

            if ($uninstallconfig['replace_status_dropdown']) {
                // replace item state by uninstall list
                $PLUGIN_HOOKS['post_item_form']['uninstall'] = [PluginUninstallState::class, 'replaceState'];
            } else {
                // add tabs to items
                foreach ($UNINSTALL_TYPES as $type) {
                    Plugin::registerClass(PluginUninstallUninstall::class, [
                        'addtabon' => $type,
                    ]);
                }
            }

            // As config update is submitted using the `context` inventory, it will always be considered as "new" and will
            // be processed by an `add` operation.
            $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['uninstall'] = ['Config::class' => [PluginUninstallConfig::class, 'preConfigSet']];

            $PLUGIN_HOOKS[Hooks::STALE_AGENT_CONFIG]['uninstall'] = [
                [
                    'label' => 'Apply uninstall profile',
                    'render_callback' => static function ($config) {
                        return PluginUninstallConfig::renderStaleAgentConfigField();
                    },
                    'action_callback' => static function (Agent $agent, array $config, ?CommonDBTM $item): bool {
                        if ($item === null) {
                            return false;
                        }
                        \PluginUninstallUninstall::doStaleAgentUninstall($item);
                        return true;
                    },
                ],
            ];

            if (Session::haveRight('uninstall:profile', READ)) {
                $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['uninstall'] = true;

                if (Session::haveRight('uninstall:profile', UPDATE)) {
                    // Add link in GLPI plugins list :
                    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['uninstall'] = ['admin' => PluginUninstallModel::class];
                }

                //Item actions
                $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['uninstall'] = [PluginUninstallModel::class => [PluginUninstallPreference::class, 'afterUpdateModel']];
                $PLUGIN_HOOKS[Hooks::ITEM_DELETE]['uninstall'] = [PluginUninstallModel::class => [PluginUninstallPreference::class, 'beforeItemPurge']];
                $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['uninstall'] = ['User' => [PluginUninstallPreference::class, 'beforeItemPurge']];
            }
        }
        $PLUGIN_HOOKS[Hooks::POST_INIT]['uninstall'] = 'plugin_uninstall_postinit';
    }
}

function plugin_version_uninstall()
{
    return [
        'name'           => __("Item's Lifecycle (uninstall)", 'uninstall'),
        'author'         => 'Walid Nouh, François Legastelois, Remi Collet',
        'homepage'       => 'https://github.com/pluginsGLPI/uninstall',
        'version'        => PLUGIN_UNINSTALL_VERSION,
        'license'        => 'GPLv2+',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_UNINSTALL_MIN_GLPI,
                'max' => PLUGIN_UNINSTALL_MAX_GLPI,
                'dev' => true, //Required to allow 9.2-dev
            ],
        ],
    ];
}
