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

define('PLUGIN_UNINSTALL_VERSION', '2.9.3');

// Minimal GLPI version, inclusive
define("PLUGIN_UNINSTALL_MIN_GLPI", "10.0.7");
// Maximum GLPI version, exclusive
define("PLUGIN_UNINSTALL_MAX_GLPI", "10.0.99");

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

    $PLUGIN_HOOKS['csrf_compliant']['uninstall'] = true;

    Plugin::registerClass('PluginUninstallPreference', ['addtabon' => ['Preference']]);
    Plugin::registerClass('PluginUninstallProfile', ['addtabon' => ['Profile']]);

    $plugin = new Plugin();
    if ($plugin->isActivated('uninstall')) {
        $UNINSTALL_TYPES                    = ['Computer', 'Monitor',
            'NetworkEquipment',
            'Peripheral', 'Phone', 'Printer'
        ];
        $UNINSTALL_DIRECT_CONNECTIONS_TYPE  = ['Monitor', 'Peripheral', 'Phone',
            'Printer'
        ];

        if (Session::getLoginUserID()) {
           // config page
            Plugin::registerClass('PluginUninstallConfig', [
                'addtabon' => 'Config'
            ]);
            $PLUGIN_HOOKS['config_page']['uninstall'] = 'front/config.form.php';
            $uninstallconfig = PluginUninstallConfig::getConfig();

            $PLUGIN_HOOKS['add_css']['uninstall'] = [
                'css/uninstall.css',
            ];

            if ($uninstallconfig['replace_status_dropdown']) {
               // replace item state by uninstall list
                $PLUGIN_HOOKS['post_item_form']['uninstall'] = [
                    'PluginUninstallState', 'replaceState'
                ];
            } else {
               // add tabs to items
                foreach ($UNINSTALL_TYPES as $type) {
                    Plugin::registerClass('PluginUninstallUninstall', [
                        'addtabon' => $type
                    ]);
                }
            }

           // As config update is submitted using the `context` inventory, it will always be considered as "new" and will
           // be processed by an `add` operation.
            $PLUGIN_HOOKS['pre_item_add']['uninstall'] = [
                'Config' => ['PluginUninstallConfig', 'preConfigSet']
            ];

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
                    }
                ]
            ];

            if (Session::haveRight('uninstall:profile', READ)) {
                $PLUGIN_HOOKS['use_massive_action']['uninstall'] = true;

                if (Session::haveRight('uninstall:profile', UPDATE)) {
                   // Add link in GLPI plugins list :
                    $PLUGIN_HOOKS["menu_toadd"]['uninstall'] = ['admin' => 'PluginUninstallModel'];
                }

               //Item actions
                $PLUGIN_HOOKS['item_update']['uninstall']
                = ['PluginUninstallModel'
                  => ['PluginUninstallPreference', 'afterUpdateModel']
                ];
                $PLUGIN_HOOKS['item_delete']['uninstall']
                = ['PluginUninstallModel'
                  => ['PluginUninstallPreference', 'beforeItemPurge']
                ];

                $PLUGIN_HOOKS['pre_item_purge']['uninstall']
                = ['User' => ['PluginUninstallPreference', 'beforeItemPurge']];
            }
        }
        $PLUGIN_HOOKS['post_init']['uninstall'] = 'plugin_uninstall_postinit';

        if ($plugin->isActivated('fields')) {
            $PLUGIN_HOOKS[Hooks::ITEM_ADD]['uninstall'] = [
                PluginFieldsContainer::class => 'plugin_uninstall_hook_add_container',
                PluginFieldsField::class => 'plugin_uninstall_hook_add_field',
            ];
            $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['uninstall'] = [
                PluginFieldsContainer::class => 'plugin_uninstall_hook_purge_container',
                PluginFieldsField::class => 'plugin_uninstall_hook_purge_field',
            ];
        }
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
            ]
        ]
    ];
}
