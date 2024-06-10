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
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

switch ($_POST['action']) {
    case PluginUninstallModelcontainerfield::ACTION_NONE:
    case PluginUninstallModelcontainerfield::ACTION_RAZ:
        echo "";
        break;

    case PluginUninstallModelcontainerfield::ACTION_NEW_VALUE:
        if (isset($_POST['id']) && $_POST['id']) {
            $pluginUninstallField = new PluginUninstallModelcontainerfield();
            $pluginUninstallField->getFromDB($_POST['id']);

            $pluginFieldsField = new PluginFieldsField();
            $pluginFieldsField->getFromDB($pluginUninstallField->fields['plugin_fields_fields_id']);

            $type = $pluginFieldsField->fields['type'];

            if ($type === 'glpi_item') {
                // TODO handling this case
                // Display "allowed values" field
                echo __('Allowed values', 'fields') . ' :';

                $allowed_itemtypes = !empty($pluginFieldsField->fields['allowed_values'])
                    ? json_decode($pluginFieldsField->fields['allowed_values'])
                    : [];
                echo implode(
                    ', ',
                    array_map(
                        function ($itemtype) {
                            return is_a($itemtype, CommonDBTM::class, true)
                                ? $itemtype::getTypeName(Session::getPluralNumber())
                                : $itemtype;
                        },
                        $allowed_itemtypes
                    )
                );

            } else {
                $dropdown_matches = [];
                $is_dropdown = $type == 'dropdown' || preg_match(
                        '/^dropdown-(?<class>.+)$/',
                        $type,
                        $dropdown_matches
                    ) === 1;

                if (in_array($type, ['date', 'datetime'])) {
                    echo '<i class="pointer fa fa-info" title="' . __s(
                            "You can use 'now' for date and datetime field"
                        ) . '"></i>';
                }

                if ($is_dropdown) {
                    $multiple = (bool)$pluginFieldsField->fields['multiple'];
                    Toolbox::logInfo($multiple);

                    echo '<div style="line-height:var(--tblr-body-line-height);">';

                    $itemtype = $type == 'dropdown'
                        ? PluginFieldsDropdown::getClassname($pluginFieldsField->fields['name'])
                        : $dropdown_matches['class'];
                    $default_value = $multiple ? json_decode(
                        $pluginUninstallField->fields['new_value'] ?? $pluginFieldsField->fields['default_value']
                    ) : $pluginUninstallField->fields['new_value'] ?? $pluginFieldsField->fields['default_value'];
                    Dropdown::show(
                        $itemtype,
                        [
                            'name' => 'new_value' . ($multiple ? '[]' : ''),
                            'value' => $default_value,
                            'entity_restrict' => -1,
                            'multiple' => $multiple,
                        ]
                    );

                    echo '</div>';
                } else {
                    echo Html::input(
                        'new_value',
                        [
                            'value' => $pluginUninstallField->fields['new_value'] ?? $pluginFieldsField->fields['default_value'],
                        ]
                    );
                }
            }
        }
        break;
}


