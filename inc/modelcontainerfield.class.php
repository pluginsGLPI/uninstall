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

    public function getName($options = []) {
        $field = new PluginFieldsField();
        $field->getFromDB($this->fields['plugin_fields_fields_id']);
        return $field->fields['label'];
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => '1',
            'table' => self::getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'massiveaction' => false,
            'datatype' => 'itemlink'
        ];

        $tab[] = [
            'id' => '2',
            'table' => PluginFieldsField::getTable(),
            'field' => 'label',
            'name' => __('Label'),
            'datatype' => 'text',
            'linkfield' => 'plugin_fields_fields_id',
        ];

        // temp solution to the fact that the plugin Fields does not provide the specific value display for type
        $tab[] = [
            'id'            => 3,
            'table'         => self::getTable(),
            'field'         => 'plugin_fields_fields_id',
            'name'          => __("Type"),
            'datatype'      => 'specific',
            'massiveaction' => false,
            'nosearch'      => true,
        ];

        $tab[] = [
            'id'            => 4,
            'table'         => self::getTable(),
            'field'         => 'action',
            'name'          => __('Action'),
            'datatype'      => 'specific',
            'massiveaction' => true,
            'nosearch'      => true,
        ];

        return $tab;
    }

    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $pluginFieldsField = new PluginFieldsField();
        $pluginUninstallContainer = new PluginUninstallModelcontainer();
        $pluginUninstallContainer->getFromDB($this->fields['plugin_uninstall_modelcontainers_id']);
        $pluginUninstallModel = new PluginUninstallModel();
        $pluginUninstallModel->getFromDB($pluginUninstallContainer->fields['plugin_uninstall_models_id']);
        if ($pluginFieldsField->getFromDB($this->fields['plugin_fields_fields_id'])) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('Field informations', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
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

            echo "<tr class='tab_bg_1 center'>";
            $actionTitle = '';
            if ($pluginUninstallContainer->fields['model_type'] == $pluginUninstallModel::TYPE_MODEL_UNINSTALL) {
                $actionTitle .= 'Uninstallation';
            } else {
                $actionTitle .= 'Replacement';
            }
            echo "<th colspan='4'>" . __('Action for ', 'uninstall') . __($actionTitle, 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Action') . " :</td>";
            echo "<td>";
            $rand = mt_rand();
            $options = [
                self::ACTION_NONE => __('Do nothing'),
            ];
            if ($pluginUninstallContainer->fields['model_type'] == $pluginUninstallModel::TYPE_MODEL_UNINSTALL) {
                $options[self::ACTION_RAZ] = __('Blank');
                if ($pluginFieldsField->fields['type'] !== 'glpi_item') {
                    $options[self::ACTION_NEW_VALUE] = __('Set value', 'uninstall');
                }
            } else {
                $options[self::ACTION_COPY] = __('Copy');
            }

            Dropdown::showFromArray(
                "action",
                $options,
                [
                    'value' => (isset($this->fields["action"])
                        ? $this->fields["action"] : self::ACTION_NONE),
                    'width' => '100%',
                    'rand' => $rand
                ]
            );
            echo "</td>";
            echo "<td><span id='label-set-value' style='display: none'>".__('New value', 'uninstall')." : </span></td>";
            echo "<td id='container-set-value'>";
            if ($pluginFieldsField->fields['type'] === 'glpi_item') {
                echo __('Action set value is not available for this field type', 'uninstall');
            }
            echo "</td>";
            echo "</tr>";
            $url = Plugin::getWebDir('uninstall') . "/ajax/fieldValueInput.php";
            echo "
            <script>
                $(document).ready(function() {
                    const select = $('#dropdown_action$rand');
                    const label = $('#label-set-value');
                    const inputContainer = $('#container-set-value');
                    select.change(e => {
                        if (e.target.selectedIndex === ".self::ACTION_NEW_VALUE.") {
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

            $this->showFormButtons($options);
        }

        return true;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'plugin_fields_fields_id':
                $pluginField = new PluginFieldsField();
                $pluginField->getFromDB($values[$field]);
                $types = PluginFieldsField::getTypes(true);
                return $types[$pluginField->fields['type']];
            case 'action':
                switch ($values[$field]) {
                    case self::ACTION_NONE:
                        return __('Do nothing');
                    case self::ACTION_RAZ:
                        return __('Blank');
                    case self::ACTION_COPY:
                        return __('Copy');
                    case self::ACTION_NEW_VALUE:
                        return __('Set value', 'uninstall');
                }
        }

        return '';
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
                    `action` int NOT NULL DEFAULT ". self::ACTION_NONE ." ,
                    `new_value` varchar(255),
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());

            $queryPreferences = "INSERT INTO `glpi_displaypreferences` (`itemtype`, `num`, `rank`, `users_id`)
                VALUES 
                    ('".self::class."', '2', '1', '0'),
                    ('".self::class."', '3', '2', '0'),
                    ('".self::class."', '4', '3', '0')
                    ;";

            $DB->queryOrDie($queryPreferences, $DB->error());
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
