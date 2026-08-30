<?php

use Glpi\Application\View\TemplateRenderer;

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
 * @copyright Copyright (C) 2015-2026 by Teclib'.
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


            $actionFields = ['action_replace', 'action_uninstall'];
            foreach ($actionFields as $field) {
                $undertitle[$field] = $uninstallContainer::getActions()[$uninstallContainer->fields[$field]];
            }

            TemplateRenderer::getInstance()->display('@uninstall/modelcontainerfield_showForm.html.twig',[
                'modelValues'=> $model->fields,
                'fieldContainer' => $fieldsContainer->fields,
                'itemValues'=> $this->fields,
                'pluginFieldsField' => $pluginFieldsField->fields,
                'uninstallContainer' => $uninstallContainer->fields,
                'rand' => mt_rand(),
                'link' => [
                    'model' => $model->getFormUrlWithID($model->getID()),
                    'container' => $model->getFormUrlWithID($model->getID()),
                    'ajax' => $CFG_GLPI['root_doc'] . "/plugins/uninstall/ajax/fieldValueInput.php",
                ],
                'idItem' => $ID,
                'pluginFieldsFieldType' => PluginFieldsField::getTypes(true)[$pluginFieldsField->fields['type']],
                'undertitle' => $undertitle,
            ]);

            $this->showFormButtons($options);
        }

        return true;
    }

    /**
     * Check whether a PluginFieldsField's value can be considered not empty
     * @param array $field row of glpi_plugin_fields_fields
     * @param array $values row of table used by plugin fields to save item data
     * @return bool
     */
    public static function fieldHasValue(array $field, array $values): bool
    {
        if (str_starts_with($field['type'], 'dropdown')) {
            if ($field['type'] == 'dropdown') {
                $property = 'plugin_fields_' . $field['name'] . 'dropdowns_id';
            } else { // for dropdown-$itemtype type
                $property = $field['name'];
            }

            if ($field['multiple']) { // not null and not []
                return $values[$property] && $values[$property] != '[]';
            }

            return $values[$property]; // not 0
        }

        if (in_array($field['type'], ['text', 'textarea', 'richtext', 'number'])) { // numbers also stored as varchar
            return trim($values[$field['name']]) || trim($values[$field['name']]) === '0'; // not null or empty string
        }

        if ($field['type'] == 'glpi_item') {
            return $values['items_id_' . $field['name']];
        }

        return $values[$field['name']];
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

            $DB->doQuery($query);
        }
        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->doQuery("DROP TABLE IF EXISTS `" . getTableForItemType(__CLASS__) . "`");

        $DB->doQuery("DELETE FROM `glpi_displaypreferences` WHERE `itemtype` = '" . self::class . "';");

        //Delete history
        $log = new Log();
        $log->dohistory = false;
        $log->deleteByCriteria(['itemtype' => __CLASS__]);
    }

    public static function getIcon()
    {
        return "ti ti-input-search";
    }
}