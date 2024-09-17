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

class PluginUninstallModelcontainerfield extends CommonDBChild
{
    public $dohistory = true;

    protected $displaylist = true;

    public static $rightname = "uninstall:profile";
    // do nothing
    const ACTION_NONE = 0;
    // delete value, uninstall only
    const ACTION_RAZ = 1;
    // set value to new_value, uninstall only
    const ACTION_NEW_VALUE = 2;
    // copy value, replace only
    const ACTION_COPY = 3;

    public static $itemtype = 'PluginUninstallModelcontainer';
    public static $items_id = 'plugin_uninstall_modelcontainers_id';

    public static function getTypeName($nb = 0)
    {
        return __("Field");
    }

    public function getName($options = [])
    {
        $field = new PluginFieldsField();
        $field->getFromDB($this->fields['plugin_fields_fields_id']);
        return $field->fields['label'];
    }

    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $uninstallContainer = new PluginUninstallModelcontainer();
        $uninstallContainer->getFromDB($this->fields['plugin_uninstall_modelcontainers_id']);

        $fieldsContainer = new PluginFieldsContainer();
        $fieldsContainer->getFromDB($uninstallContainer->fields['plugin_fields_containers_id']);

        $model = new PluginUninstallModel();
        $model->getFromDB($uninstallContainer->fields['plugin_uninstall_models_id']);

        $pluginFieldsField = new PluginFieldsField();
        if ($pluginFieldsField->getFromDB($this->fields['plugin_fields_fields_id'])) {
            // context
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('Parents', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Model') . " : </td>";
            echo "<td>";
            echo "<a href='" . $model->getFormUrlWithID($model->getID()) . "'>" . $model->fields['name'] . "</a>";
            echo "</td>";
            echo "<td>" . __('Bloc') . " : </td>";
            echo "<td>";
            echo "<a href='" . $uninstallContainer->getFormUrlWithID($uninstallContainer->getID()) . "'>" . $fieldsContainer->fields['name'] . "</a>";
            echo "</td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1 center'>";
            // field infos
            echo "<th colspan='4' class='fs-2'>" . __('Field informations', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center fw-bold'>";
            echo "<td>" . __("Label") . " : </td>";
            echo "<td>";
            echo $pluginFieldsField->fields['label'];
            echo "</td>";
            echo "<td>" . __("Type") . " : </td>";
            echo "<td>";
            echo PluginFieldsField::getTypes(true)[$pluginFieldsField->fields['type']];
            echo "</td>";
            echo "</tr>";
            // DEFAULT VALUE
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Active') . " :</td>";
            echo "<td>";
            echo $pluginFieldsField->fields["is_active"]  ? __('Yes') : __('No');
            echo "</td>";
            echo "<td>" . __("Mandatory field") . " : </td>";
            echo "<td>";
            echo $pluginFieldsField->fields["mandatory"]  ? __('Yes') : __('No');
            echo "</td>";
            echo "</tr>";

            $actionFields = ['action_replace', 'action_uninstall'];
            foreach ($actionFields as $field) {
                // model can use the property
                if (
                    ($field === 'action_uninstall' && $model->fields['types_id'] != $model::TYPE_MODEL_REPLACEMENT)
                    || ($field === 'action_replace' && $model->fields['types_id'] != $model::TYPE_MODEL_UNINSTALL)
                ) {
                    switch ($field) {
                        case 'action_uninstall':
                            $typeTitle = __('Uninstallation', 'uninstall');
                            $modelProperty = 'action_plugin_fields_uninstall';
                            break;
                        case 'action_replace':
                            $typeTitle = __('Replacement', 'uninstall');
                            $modelProperty = 'action_plugin_fields_replace';
                            break;
                    }

                    echo "<th colspan='4' class='center fs-3'>" . __('Action for ', 'uninstall') . $typeTitle .
                        "</th></tr>";
                    echo "<tr class='tab_bg_1 center'>";
                    // model and container let the action be decided at this level, display dropdown
                    if (
                        $model->fields[$modelProperty] == $model::PLUGIN_FIELDS_ACTION_ADVANCED
                        && $uninstallContainer->fields[$field] == $uninstallContainer::ACTION_CUSTOM
                    ) {
                        echo "<td>" . __('Action') . " :</td>";
                        $colspan = $field == 'action_uninstall' ? 1 : 3;
                        echo "<td colspan='$colspan'>";
                        $rand = mt_rand();
                        $options = [
                            self::ACTION_NONE => __('Do nothing'),
                        ];
                        if ($field == 'action_uninstall') {
                            $options[self::ACTION_RAZ] = __('Blank');
                            if ($pluginFieldsField->fields['type'] !== 'glpi_item') {
                                $options[self::ACTION_NEW_VALUE] = __('Set value', 'uninstall');
                            }
                        } else {
                            $options[self::ACTION_COPY] = __('Copy');
                        }

                        Dropdown::showFromArray(
                            $field,
                            $options,
                            [
                                'value' => (isset($this->fields[$field])
                                    ? $this->fields[$field] : self::ACTION_NONE),
                                'width' => '100%',
                                'rand' => $rand
                            ]
                        );
                        echo "</td>";
                        // for uninstall, show the part that allow for a new value to be set
                        if ($field == 'action_uninstall') {
                            echo "<td><span id='label-set-value$rand' style='display: none'>" . __('New value', 'uninstall') . " : </span></td>";
                            echo "<td id='container-set-value$rand'>";
                            if ($pluginFieldsField->fields['type'] === 'glpi_item') {
                                echo __('Action set value is not available for this field type', 'uninstall');
                            }
                            echo "</td>";
                            echo "</tr>";
                            $url = Plugin::getWebDir('uninstall') . "/ajax/fieldValueInput.php";
                            echo "
                            <script>
                                $(document).ready(function() {
                                    const select = $('#dropdown_$field$rand');
                                    const label = $('#label-set-value$rand');
                                    const inputContainer = $('#container-set-value$rand');
                                    select.change(e => {
                                        if (e.target.selectedIndex === " . self::ACTION_NEW_VALUE . ") {
                                            label[0].style.display = '';
                                        } else {
                                            label[0].style.display = 'none'
                                        }
                                        inputContainer.load('$url', {
                                            'id' : $ID,
                                            'action' : e.target.selectedIndex
                                        });
                                    })
                                    select.trigger('change');
                                });
                            </script>
                            ";
                        }
                    } else {
                        if ($model->fields[$modelProperty] == $model::PLUGIN_FIELDS_ACTION_ADVANCED) {
                            echo "<td colspan='4'><strong>" . $uninstallContainer::getActions()[$uninstallContainer->fields[$field]] . "</strong> (" . __('set by bloc', 'uninstall') . ")</td>";
                        } else {
                            switch ($model->fields[$modelProperty]) {
                                case $model::PLUGIN_FIELDS_ACTION_NONE:
                                    $action = __('Do nothing');
                                    break;
                                case $model::PLUGIN_FIELDS_ACTION_RAZ:
                                    $action = __('Blank');
                                    break;
                                case $model::PLUGIN_FIELDS_ACTION_COPY:
                                    $action = __('Copy');
                                    break;
                            }

                            echo "<td colspan='4'><strong>" . $action . "</strong> (" . __('set by model', 'uninstall') . ")</td>";
                        }
                    }
                    echo "</tr>";
                }
            }

            $this->showFormButtons($options);
        }

        return true;
    }

    public static function install($migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        // first version with this feature
        if (!$DB->tableExists(getTableForItemType(__CLASS__))) {
            $query = "CREATE TABLE IF NOT EXISTS `" . getTableForItemType(__CLASS__) . "` (
                    `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                    `plugin_uninstall_modelcontainers_id` int {$default_key_sign} DEFAULT '0',
                    `plugin_fields_fields_id` tinyint NOT NULL DEFAULT '0',
                    `action_uninstall` int NOT NULL DEFAULT '0',
                    `action_replace` int NOT NULL DEFAULT '0',
                    `new_value` varchar(255),
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());
        }
        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `" . getTableForItemType(__CLASS__) . "`");

        $DB->query("DELETE FROM `glpi_displaypreferences` WHERE `itemtype` = '" . self::class . "';");

        //Delete history
        $log = new Log();
        $log->dohistory = false;
        $log->deleteByCriteria(['itemtype' => __CLASS__]);
    }
}
