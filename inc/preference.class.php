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

class PluginUninstallPreference extends CommonDBTM
{
    public static $rightname = "uninstall:replace";

    public function prepareInputForAdd($input)
    {
        return $this->handleLocationPref($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->handleLocationPref($input);
    }

    public function handleLocationPref(array $input): array
    {
        if (array_key_exists('locations_id', $input)) {
            if ($input['locations_id'] == -1) {
                $input['locations_action'] = 'old';
                $input['locations_id']     = 0;
            } else {
                $input['locations_action'] = 'set';
            }
        }
        return $input;
    }

    public function showFormUserPreferences()
    {
        $entity     = $_SESSION['glpiactive_entity'];
        $userID     = Session::getLoginUserID();
        $templates  = PluginUninstallUninstall::getAllTemplatesByEntity(
            $_SESSION["glpiactive_entity"],
            true,
        );
        $data       = plugin_version_uninstall();

        echo "<form action='" . $this->getFormURL() . "' method='post'>";
        echo "<div class='center'>";

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . $data['name'] . "</th></tr>";

        if (!empty($templates)) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th>" . PluginUninstallModel::getTypeName() . "</th>";
            echo "<th>" . __("Item's location after uninstall", "uninstall") . "</th>";
            echo "</tr>";

            foreach ($templates as $ID => $name) {
                $pref_ID = self::checkIfPreferenceExistsByEntity($userID, $ID, $entity);

                if (!$pref_ID) {
                    $pref_ID = self::addDefaultPreference($userID, $ID, $entity);
                }

                $this->getFromDB($pref_ID);

                echo "<tr class='tab_bg_1'><td>" . $name . "</td>";
                echo "<td>";
                $value = (isset($this->fields["locations_id"]) ? $this->fields["locations_id"] : 0);

                // Handle "old" special value, dropdown expect "-1" in this case
                if ($this->fields['locations_action'] == "old" && $value == 0) {
                    $value = -1;
                }

                Location::dropdown(['name'      => "id[$pref_ID][locations_id]",
                    'value'     => ($value == '' ? 0 : $value),
                    'comments'  => 1,
                    'entity'    => $entity,
                    'toadd'     => [-1 => __('Keep previous location', 'uninstall'),
                        0  => __('Empty location', 'uninstall'),
                    ],
                ]);

                echo "<input type='hidden' name='id[" . $pref_ID . "][id]' value='" . $pref_ID . "'>";
                echo "</td></tr>";
            }

            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
            echo "<input type='submit' name='update_user_preferences_uninstall' value='" .
                _sx('button', 'Post') . "' class='submit'>";
            echo "</td></tr>";
        }

        echo "</table>";
        echo "</div>";
        Html::closeForm();
    }


    /**
     * @param $item
    **/
    public static function afterUpdateModel($item)
    {

        if ($item->fields["is_recursive"] == 0) {
            self::deleteUserPreferenceForModel($item->fields["id"], $item->fields["entities_id"]);
        }
    }


    /**
     * @param $item
    **/
    public static function beforeItemPurge($item)
    {

        switch ($item->getType()) {
            case 'User':
                self::deleteUserPreferences($item->fields["id"]);
                break;

            case 'PluginUninstallModel':
                self::deleteUserPreferenceForModel($item->fields["id"]);
                break;
        }
    }


    /**
     * @param $models_id
     * @param $except_entity   (default -1)
    **/
    public static function deleteUserPreferenceForModel($models_id, $except_entity = -1)
    {
        /** @var DBmysql $DB */
        global $DB;

        $criteria = ['templates_id' => $models_id];
        if ($except_entity != -1) {
            $criteria[] = [
                'NOT' => ['entities_id' => $except_entity],
            ];
        }
        $DB->delete(getTableForItemType(__CLASS__), $criteria);
    }


    /**
     * @param $users_id
    **/
    public static function deleteUserPreferences($users_id)
    {
        $preference = new self();
        $preference->deleteByCriteria(['users_id' => $users_id]);
    }


    /**
     * @param $user_id
     * @param $template
     * @param $entity
    **/
    public static function checkIfPreferenceExistsByEntity($user_id, $template, $entity)
    {
        /** @var DBmysql $DB */
        global $DB;

        $it = $DB->request([
            'SELECT' => ['id'],
            'FROM' => getTableForItemType(__CLASS__),
            'WHERE' => [
                'users_id' => $user_id,
                'entities_id' => $entity,
                'templates_id' => $template,
            ],
        ]);
        return count($it) ? $it->current()['id'] : 0;
    }


    /**
     * @param $user_id
     * @param $template
     * @param $entity
    **/
    public static function addDefaultPreference($user_id, $template, $entity)
    {

        $pref                  = new self();
        $input["users_id"]     = $user_id;
        $input["entities_id"]  = $entity;
        $input["locations_id"] = -1;
        $input["templates_id"] = $template;
        return $pref->add($input);
    }


    /**
     * @param $user_id
     * @param $template
     * @param $entity
    **/
    public static function getLocationByUserByEntity($user_id, $template, $entity)
    {
        /** @var DBmysql $DB */
        global $DB;

        $result = $DB->request(
            [
                'SELECT' => ['locations_action', 'locations_id'],
                'FROM'   => self::getTable(),
                'WHERE'  => [
                    'users_id'     => $user_id,
                    'entities_id'  => $entity,
                    'templates_id' => $template,
                ],
            ],
        );
        if ($userpref = $result->current()) {
            return $userpref['locations_action'] === 'old'
            ? -1
            : $userpref['locations_id'];
        }

        return '';
    }


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if ($item->getType() == 'Preference' && Session::haveRight('uninstall:profile', READ)) {
            return PluginUninstallUninstall::getTypeName();
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'Preference' && Session::haveRight('uninstall:profile', READ)) {
            $pref = new self();
            $pref->showFormUserPreferences();
        }
        return true;
    }


    public static function install(Migration $migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        // From 0.2 to 1.0.0
        $table = 'glpi_plugin_uninstallcomputer_preference';
        if ($DB->tableExists($table)) {
            $migration->changeField($table, 'user_id', 'FK_users', "integer");
            $migration->addField($table, 'FK_template', 'integer');
            $migration->renameTable($table, getTableForItemType(__CLASS__));
        }

        $table = getTableForItemType(__CLASS__);
        // plugin already installed
        if ($DB->tableExists($table)) {
            // from 1.0.0 to 1.3.0
            if ($DB->fieldExists($table, 'ID')) {
                $migration->changeField($table, 'ID', 'id', 'autoincrement');
                $migration->changeField($table, 'FK_users', 'users_id', "int {$default_key_sign} NOT NULL");
                $migration->changeField($table, 'FK_entities', 'entities_id', "int {$default_key_sign} DEFAULT 0");
                $migration->changeField($table, 'FK_template', 'templates_id', "int {$default_key_sign} DEFAULT 0");
                $migration->changeField($table, 'location', 'locations_id', "int {$default_key_sign} DEFAULT 0");
            }

            // 2.7.2
            if (!$DB->fieldExists($table, 'locations_action')) {
                $migration->addField($table, 'locations_action', "varchar(10) NOT NULL DEFAULT 'set'");
                $migration->addPostQuery(
                    $DB->buildUpdate(
                        $table,
                        ['locations_action' => 'set'],
                        ['NOT' => ['locations_id' => '-1']],
                    ),
                );
                $migration->addPostQuery(
                    $DB->buildUpdate(
                        $table,
                        ['locations_action' => 'old', 'locations_id' => '0'],
                        ['locations_id' => '-1'],
                    ),
                );
            }
        } else {
            // plugin nevers installed
            $query = "CREATE TABLE `" . $table . "` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `users_id` int {$default_key_sign} NOT NULL,
                     `entities_id` int {$default_key_sign} DEFAULT '0',
                     `templates_id` int {$default_key_sign} DEFAULT '0',
                     `locations_action` varchar(10) NOT NULL DEFAULT 'set',
                     `locations_id` int {$default_key_sign} DEFAULT '0',
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
    }
}
