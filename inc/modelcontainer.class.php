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

class PluginUninstallModelcontainer extends CommonDBChild
{
    public $dohistory = true;

    public static $rightname = "uninstall:profile";

    // do nothing
    const ACTION_NONE = 0;
    // delete values, uninstall only
    const ACTION_RAZ = 1;
    // copy values, replace only
    const ACTION_COPY = 2;
    // choose action for each field individually
    const ACTION_CUSTOM = 3;

    public static $itemtype = 'PluginUninstallModel';
    public static $items_id = 'plugin_uninstall_models_id';
    protected $displaylist = true;


    public static function getTypeName($nb = 0)
    {
        return __("Block", "fields");
    }

    /**
     * Get the list of actions available for an instance, or all available actions
     * @param $type int|null
     * @return array value => label
     */
    public static function getActions($type = null)
    {
        if ($type) {
            $values = [
                self::ACTION_NONE => __('Do nothing'),
            ];
            if ($type == PluginUninstallModel::TYPE_MODEL_UNINSTALL) {
                $values[self::ACTION_RAZ] = __('Blank');
            } else {
                $values[self::ACTION_COPY] = __('Copy');
            }
            $values[self::ACTION_CUSTOM] = __('Per field action', 'uninstall');
            return $values;
        }
        return [
            self::ACTION_NONE => __('Do nothing'),
            self::ACTION_RAZ => __('Blank'),
            self::ACTION_COPY => __('Copy'),
            self::ACTION_CUSTOM => __('Per field action', 'uninstall')
        ];
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case __CLASS__:
                switch ($tabnum) {
                    case 1:
                        $item->showForm($item->getID());
                        break;
                    case 2:
                        $item->showFields($item);
                        break;
                }
        }
        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case __CLASS__:
                $tab = [];
                $tab[1] = self::getTypeName(1);
                $model = new PluginUninstallModel();
                $model->getFromDB($item->fields['plugin_uninstall_models_id']);
                if (($model->fields['types_id'] != $model::TYPE_MODEL_UNINSTALL && $item->fields['action_replace'] == self::ACTION_CUSTOM) ||
                    ($model->fields['types_id'] != $model::TYPE_MODEL_REPLACEMENT && $item->fields['action_uninstall'] == self::ACTION_CUSTOM)) {
                    $tab[2] = __('Fields');
                }
                return $tab;
        }
        return '';
    }

    public function getName($options = [])
    {
        $container = new PluginFieldsContainer();
        $container->getFromDB($this->fields['plugin_fields_containers_id']);
        return $container->getFriendlyName();
    }

    /**
     * Copy from PluginFieldsContainer
     * @param $field_id_or_search_options
     * @param $name
     * @param $values
     * @param $options
     * @return mixed
     */
    public function getValueToSelect($field_id_or_search_options, $name = '', $values = '', $options = [])
    {
        switch ($field_id_or_search_options['table'] . '.' . $field_id_or_search_options['field']) {
            case $this->getTable() . '.itemtypes':
                $options['display'] = false;
                return Dropdown::showFromArray($name, self::getItemtypes(false), $options);
        }

        return parent::getValueToSelect($field_id_or_search_options, $name, $values, $options);
    }

    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $model = new PluginUninstallModel();
        $model->getFromDB($this->fields['plugin_uninstall_models_id']);

        $pluginFieldsContainer = new PluginFieldsContainer();
        if ($pluginFieldsContainer->getFromDB($this->fields['plugin_fields_containers_id'])) {
            // associated model name
            echo "<h3>" . __('Model') . ' : ' . $model->fields['name'] . "</h3>";
            echo "<tr class='tab_bg_1 center'>";
            // associated plugin fields block informations
            echo "<th colspan='4'>" . __('Block informations', 'uninstall') .
                "</th></tr>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . $pluginFieldsContainer->getTypeName() . " : </td>";
            echo "<td>";
            echo "<a href='" . $pluginFieldsContainer->getFormURLWithID($pluginFieldsContainer->getID()) . "'>" . $pluginFieldsContainer->fields['label'] . "</a>";
            echo "</td>";
            echo "<td>" . __("Associated item type") . " : </td>";
            echo "<td>";
            $types = json_decode($pluginFieldsContainer->fields['itemtypes']);
            $obj = '';
            $count = count($types);
            $i = 1;
            foreach ($types as $type) {
                // prevent usage of plugin class if not loaded
                if (!class_exists($type)) {
                    continue;
                }

                $name_type = getItemForItemtype($type);
                $obj .= $name_type->getTypeName(2);
                if ($count > $i) {
                    $obj .= ", ";
                }
                $i++;
            }
            echo $obj;
            echo "</td>";
            echo "</tr>";

            // actual form data
            $titleColspan = 4;
            $actionColspan = 3;
            if ($model->fields['types_id'] == $model::TYPE_MODEL_REPLACEMENT_UNINSTALL) {
                $titleColspan = 2;
                $actionColspan = 1;
            }

            echo "<tr class='tab_bg_1 center'>";
            if ($model->fields['types_id'] != $model::TYPE_MODEL_UNINSTALL) {
                echo "<th colspan='$titleColspan'>" . __('Action for ', 'uninstall') . __('Replacement', 'uninstall') . "</th>";
            }
            if ($model->fields['types_id'] != $model::TYPE_MODEL_REPLACEMENT) {
                echo "<th colspan='$titleColspan'>" . __('Action for ', 'uninstall') . __('Uninstallation', 'uninstall') . "</th>";
            }
            echo "</tr>";
            echo "<tr class='tab_bg_1 center'>";
            if ($model->fields['types_id'] != $model::TYPE_MODEL_UNINSTALL) {
                echo "<td>" . __('Action') . "</td>";
                echo "<td colspan='$actionColspan'>";
                if ($model->fields['action_plugin_fields_replace'] == $model::PLUGIN_FIELDS_ACTION_ADVANCED) {
                    $rand = mt_rand();
                    Dropdown::showFromArray(
                        "action_replace",
                        self::getActions($model::TYPE_MODEL_REPLACEMENT),
                        [
                            'value' => (isset($this->fields["action_replace"])
                                ? $this->fields["action_replace"] : $this::ACTION_NONE),
                            'width' => '100%',
                            'rand' => $rand
                        ]
                    );
                } else {
                    switch ($model->fields['action_plugin_fields_replace']) {
                        case $model::PLUGIN_FIELDS_ACTION_NONE :
                            $action = __('Do nothing');
                            break;
                        case $model::PLUGIN_FIELDS_ACTION_COPY :
                            $action = __('Copy');
                    }

                    echo "<strong>" . $action . "</strong> " . "(" . __('inherit from model', 'uninstall') . ")";
                }
                echo "</td>";
            }

            if ($model->fields['types_id'] != $model::TYPE_MODEL_REPLACEMENT) {
                echo "<td>" . __('Action') . "</td>";
                echo "<td colspan='$actionColspan'>";
                if ($model->fields['action_plugin_fields_uninstall'] == $model::PLUGIN_FIELDS_ACTION_ADVANCED) {
                    $rand = mt_rand();
                    Dropdown::showFromArray(
                        "action_uninstall",
                        self::getActions($model::TYPE_MODEL_UNINSTALL),
                        [
                            'value' => (isset($this->fields["action_uninstall"])
                                ? $this->fields["action_uninstall"] : $this::ACTION_NONE),
                            'width' => '100%',
                            'rand' => $rand
                        ]
                    );
                } else {
                    switch ($model->fields['action_plugin_fields_uninstall']) {
                        case $model::PLUGIN_FIELDS_ACTION_NONE :
                            $action = __('Do nothing');
                            break;
                        case $model::PLUGIN_FIELDS_ACTION_RAZ :
                            $action = __('Blank');
                    }

                    echo "<strong>" . $action . "</strong> " . "(" . __('inherit from model', 'uninstall') . ")";
                }
                echo "</td>";
            }
            echo "</tr>";

            $this->showFormButtons($options);
        }

        return true;
    }

    /**
     * @param $modelId int PluginUninstallModel id
     * @param $type int const TYPE_MODEL from PluginUninstallModel
     * @return void
     */
    public static function showListsForType($modelId, $type)
    {
        $typeTitle = $type === PluginUninstallModel::TYPE_MODEL_UNINSTALL ? __('Uninstallation', 'uninstall') : __(
            'Replacement',
            'uninstall'
        );
        echo "<h3>" . $typeTitle . '</h3>';
        $self = new self();
        $uninstallContainers = $self->find([
            'plugin_uninstall_models_id' => $modelId
        ]);
        $fieldContainer = new PluginFieldsContainer();
        if (count($uninstallContainers)) {
            echo "<table class='tab_cadre_fixe'>";
            echo "<thead><tr>";
            echo "<th>" . __('Block', 'fields') . "</th>";
            echo "<th>" . __("Action") . "</th>";
            echo "<th>" . __("Associated item type") . "</th>";
            echo "</tr></thead>";
            echo "<tbody>";
            $actionProperty = $type === PluginUninstallModel::TYPE_MODEL_UNINSTALL ? 'action_uninstall' : 'action_replace';
            foreach($uninstallContainers as $uninstallContainer) {
                if ($fieldContainer->getFromDB($uninstallContainer['plugin_fields_containers_id'])) {
                    echo "<tr>";
                    $link = PluginUninstallModelContainer::getFormURLWithID($uninstallContainer['id']);
                    echo "<td>";
                    echo "<a href='$link'>" . $fieldContainer->fields['label'] . "</a>";
                    echo "</td>";
                    echo "<td><strong>" .  self::getActions()[$uninstallContainer[$actionProperty]] . "</strong></td>";
                    $types = json_decode($fieldContainer->fields['itemtypes']);
                    $obj = '';
                    $count = count($types);
                    $i = 1;
                    foreach ($types as $type) {
                        if (!class_exists($type)) {
                            continue;
                        }
                        $name_type = getItemForItemtype($type);
                        $obj .= $name_type->getTypeName(2);
                        if ($count > $i) {
                            $obj .= ", ";
                        }
                        $i++;
                    }
                    echo "<td>" . $obj . "</td>";
                    echo "</tr>";
                }
            }
            echo "</tbody>";
            echo "</table>";
        } else {
            if (count(self::getContainerForItemtypes())) {
                $link = PluginUninstallModel::getFormURLWithID($modelId) . "&load_fields=1";
                echo "<a href='$link'>" . __('Load plugin data', 'uninstall') . "</a>";
            }
        }
    }

    /**
     * Get all containers from the plugin fields which are associated with an itemtype used by uninstall
     * @param $ids array list of plugin fields container ids to exclude from the request
     * @return array
     */
    public static function getContainerForItemtypes($ids = []) {
        global $DB, $UNINSTALL_TYPES;
        $query = "SELECT * FROM " . PluginFieldsContainer::getTable() . " WHERE ";
        if (count($ids)) {
            $query .= 'id NOT IN ('.implode(',', $ids).') AND (';
        }
        foreach($UNINSTALL_TYPES as $index => $type) {
            $query .= 'itemtypes LIKE \'%"'.$type.'"%\' ';
            if ($index != count($UNINSTALL_TYPES) - 1) {
                $query .= 'OR ';
            }
        }
        if (count($ids)) {
            $query .= ')';
        }
        $return = [];
        if ($result = $DB->doQuery($query)) {
            if ($DB->numrows($result) > 0) {
                while ($data = $DB->fetchAssoc($result)) {
                    $return[] = $data;
                }
            }
        }
        return $return;
    }

    /**
     * Display list
     * @param $item
     * @return void
     */
    public function showFields($item)
    {
        $model = new PluginUninstallModel();
        $model->getFromDB($item->fields['plugin_uninstall_models_id']);

        echo "<h3>" . __('Plugin additionnal fields field', 'uninstall') . "</h3>";

        echo "<p>" . __('Model') . " : " . "<a href='" . $model->getFormUrlWithID(
                $model->getID()
            ) . "'>" . $model->fields['name'] . "</a>" . "</p>";

        $actionFields = ['action_replace', 'action_uninstall'];
        foreach ($actionFields as $field) {
            if (($field === 'action_uninstall' && $model->fields['types_id'] != $model::TYPE_MODEL_REPLACEMENT)
                || ($field === 'action_replace' && $model->fields['types_id'] != $model::TYPE_MODEL_UNINSTALL)) {
                switch ($field) {
                    case 'action_uninstall':
                        $typeTitle = __('Uninstallation', 'uninstall');
                        $modelProperty = 'action_plugin_fields_uninstall';
                        break;
                    case 'action_replace' :
                        $typeTitle = __('Replacement', 'uninstall');
                        $modelProperty = 'action_plugin_fields_replace';
                        break;
                }
                $actionTitle = __('Action for ', 'uninstall') . $typeTitle . ' : ';
                if ($model->fields[$modelProperty] != $model::PLUGIN_FIELDS_ACTION_ADVANCED) {
                    switch ($model->fields[$modelProperty]) {
                        case $model::PLUGIN_FIELDS_ACTION_NONE :
                            $action = __('Do nothing');
                            break;
                        case $model::PLUGIN_FIELDS_ACTION_RAZ :
                            $action = __('Blank');
                            break;
                        case $model::PLUGIN_FIELDS_ACTION_COPY :
                            $action = __('Copy');
                            break;
                    }

                    $actionTitle .= $action . " (" . __('set by model', 'uninstall') . ")</td>";
                } else {
                    $actionTitle .= self::getActions()[$item->fields[$field]];
                    if ($item->fields[$field] != self::ACTION_CUSTOM) {
                        $actionTitle .= " (" . __('set by bloc', 'uninstall') . ')';
                    }
                }
                echo "<h3 class='mt-4'>" . $actionTitle . "</h3>";
                if ($item->fields[$field] == self::ACTION_CUSTOM) {
                    $uninstallField = new PluginUninstallModelcontainerfield();
                    $fields = $uninstallField->find([
                        'plugin_uninstall_modelcontainers_id' => $item->getID()
                    ]);
                    if (count($fields)) {
                        echo "<table class='tab_cadre_fixe'>";
                        echo "<thead><tr>";
                        echo "<th>" . __('Field') . "</th>";
                        echo "<th>" . __("Type") . "</th>";
                        echo "<th>" . __("Action") . "</th>";
                        echo "<th></th>";
                        echo "</tr></thead>";
                        echo "<tbody>";

                        $fieldsField = new PluginFieldsField();
                        $types = $fieldsField->getTypes(true);
                        foreach ($fields as $fieldData) {
                            if ($fieldsField->getFromDB($fieldData['plugin_fields_fields_id'])
                                && $uninstallField->getFromDB($fieldData['id'])) {
                                echo "<tr>";
                                $link = PluginUninstallModelcontainerfield::getFormURLWithID($fieldData['id']);
                                echo "<td>";
                                echo "<a href='$link'>" . $fieldsField->fields['label'] . "</a>";
                                echo "</td>";
                                echo "<td>" . $types[$fieldsField->fields['type']] . "</td>";
                                echo "<td><div class='d-flex'>";
                                $options = [
                                    $uninstallField::ACTION_NONE => __('Do nothing'),
                                ];
                                if ($field == 'action_uninstall') {
                                    $options[$uninstallField::ACTION_RAZ] = __('Blank');
                                    if ($fieldsField->fields['type'] !== 'glpi_item') {
                                        $options[$uninstallField::ACTION_NEW_VALUE] = __('Set value', 'uninstall');
                                    }
                                } else {
                                    $options[$uninstallField::ACTION_COPY] = __('Copy');
                                }
                                $rand = mt_rand();
                                echo "<div>";
                                Dropdown::showFromArray(
                                    $field,
                                    $options,
                                    [
                                        'value' => (isset($uninstallField->fields[$field])
                                            ? $uninstallField->fields[$field] : $uninstallField::ACTION_NONE),
                                        'width' => '100%',
                                        'rand' => $rand
                                    ]
                                );
                                echo "</div>";
                                $id = $uninstallField->getID();
                                if ($field == 'action_uninstall') {
                                    echo "<div class='d-flex align-items-center'><label id='label-set-value$field$rand' class='mx-2' style='display: none'>" . __('New value', 'uninstall') . "</label>";
                                    echo "<div id='container-set-value$field$rand'>";
                                    if ($fieldsField->fields['type'] === 'glpi_item') {
                                        echo __('Action set value is not available for this field type', 'uninstall');
                                    }
                                    echo "</div>";
                                    echo "</div>";
                                    $url = Plugin::getWebDir('uninstall') . "/ajax/fieldValueInput.php";
                                    echo "
                                    <script>
                                        $(document).ready(function() {
                                            const select = $('#dropdown_$field$rand');
                                            const label = $('#label-set-value$field$rand');
                                            const inputContainer = $('#container-set-value$field$rand');
                                            select.change(e => {
                                                if (e.target.selectedIndex === " . $uninstallField::ACTION_NEW_VALUE . ") {
                                                    label[0].style.display = '';
                                                } else {
                                                    label[0].style.display = 'none'
                                                }
                                                inputContainer.load('$url', {
                                                    'id' : $id,
                                                    'action' : e.target.selectedIndex,
                                                    'rand' : '$rand'
                                                });
                                            })
                                            select.trigger('change');
                                        });
                                    </script>
                                    ";
                                }
                                echo "</div></td>";
                                echo "<td>";
                                echo "<div id='button$field$rand'>";
                                echo "<button class='btn btn-primary me-2' id='update$field$rand'>";
                                echo '<i class="far fa-save"></i>';
                                echo "</button></div>";
                                $saveUrl = Plugin::getWebDir('uninstall') . "/ajax/saveField.php";
                                echo "
                                    <script>
                                        $(document).ready(function() {
                                            const button = $('#update$field$rand');
                                            const container = $('#button$field$rand');
                                            button.click(e => {
                                                container.addClass('d-none'); 
                                                container.html('<i class=\"fas fa-2 fa-spinner fa-pulse m-1\"></i>');
                                                const action = $('#dropdown_$field$rand').val();
                                                let new_value = null;
                                                if ($('".'[id$'."=\"new_value$rand\"]')) {
                                                    new_value = $('".'[id$'."=\"new_value$rand\"]').val();   
                                                }
                                                $.ajax({
                                                  method : 'POST',
                                                  url: '$saveUrl',
                                                  data: {
                                                      'id' : $id,
                                                      '$field' : action,
                                                      'new_value' : new_value
                                                  },
                                                  success: function(e) {
                                                      window.location.reload();
                                                  },
                                                  error: function(e) {
                                                      window.location.reload();
                                                  }
                                                });
                                            })
                                           
                                        });
                                    </script>
                                    ";
                                echo "</td>";
                                echo "</tr>";
                            }
                        }
                        echo "</tbody>";
                        echo "</table>";
                    }
                }
            }
        }
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
                    `plugin_uninstall_models_id` int {$default_key_sign} DEFAULT '0',
                    `plugin_fields_containers_id` tinyint NOT NULL DEFAULT '0',
                    `action_uninstall` int NOT NULL DEFAULT '0',
                    `action_replace` int NOT NULL DEFAULT '0',
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
