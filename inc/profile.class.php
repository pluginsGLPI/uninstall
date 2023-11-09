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

class PluginUninstallProfile extends Profile
{
    const RIGHT_REPLACE = 128;

   /**
    *
    * Get rights matrix for plugin
    *
    * @return array:array:string rights matrix
    */
    public function getGeneralRights()
    {
        $rights = [
            [
                'itemtype'  => 'PluginUninstallProfile',
                'label'     => PluginUninstallUninstall::getTypeName(),
                'field'     => "uninstall:profile",
                'rights'    => [READ                => __('Read'),
                    UPDATE              => __('Write'),
                    self::RIGHT_REPLACE => PluginUninstallReplace::getTypeName()
                ]
            ],
        ];
        return $rights;
    }

    public function showForm($ID, $options = [])
    {
        $profile = new Profile();

        if ($ID) {
            $this->getFromDB($ID);
            $profile->getFromDB($ID);
        } else {
            $this->getEmpty();
        }

        if ($canedit = self::canUpdate()) {
            $options['colspan'] = 1;
            $options['target']  = $profile->getFormURL();
            $this->fields["id"] = $ID;
            $this->showFormHeader($options);
        }

        $rights = $this->getGeneralRights();
        $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
            'default_class' => 'tab_bg_2'
        ]);
        if ($canedit) {
            $options['candel'] = false;
            $this->showFormButtons($options);
        }

        return true;
    }

    public static function createFirstAccess($ID)
    {
        self::addDefaultProfileInfos(
            $ID,
            ['uninstall:profile' => UPDATE | READ | self::RIGHT_REPLACE,
                'plugin_uninstall_replace'         => 1
            ],
            true
        );
    }

   /**
    * Init profiles
    *
    **/
    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case '':
                return 0;
            case 'r':
                return READ;
            case 'w':
                return UPDATE + READ;
            case '0':
            case '1':
                return $old_right;

            default:
                return 0;
        }
    }

   /**
    * @since 0.85
    * Migration rights from old system to the new one for one profile
    * @param $profiles_id the profile ID
    */
    public static function migrateOneProfile($profiles_id)
    {
        /** @var DBmysql $DB */
        global $DB;
       //Cannot launch migration if there's nothing to migrate...
        if (!$DB->tableExists('glpi_plugin_uninstall_profiles')) {
            return true;
        }

        foreach (
            $DB->request(
                'glpi_plugin_uninstall_profiles',
                "`id`='$profiles_id'"
            ) as $profile_data
        ) {
            $translatedRight = self::translateARight($profile_data["use"]);
            $translatedRight = $translatedRight | (self::translateARight($profile_data["replace"]) ? self::RIGHT_REPLACE : 0);
            ProfileRight::updateProfileRights($profiles_id, [PluginUninstallProfile::$rightname => $translatedRight]);
        }
    }

   /**
    * Initialize profiles, and migrate it necessary
    */
    public static function migrateAllProfiles()
    {
        /** @var DBmysql $DB */
        global $DB;

       //Add new rights in glpi_profilerights table
        foreach ([PluginUninstallProfile::$rightname] as $field) {
            if (!countElementsInTable("glpi_profilerights", ['name' => $field])) {
                ProfileRight::addProfileRights([$field]);
            }
        }

       //Migration old rights in new ones
        $profiles_it = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profiles'
        ]);
        foreach ($profiles_it as $prof) {
            self::migrateOneProfile($prof['id']);
        }
        $rights_it = $DB->request([
            'FROM' => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                'name' => ['LIKE', '%plugin_uninstall%']
            ]
        ]);
        foreach ($rights_it as $prof) {
            $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if ($item->getType() == 'Profile') {
            if ($item->getField('interface') == 'central') {
                return PluginUninstallUninstall::getTypeName();
            }
        }
        return '';
    }

    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (
                countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id, 'name' => $right]
                ) && $drop_existing
            ) {
                $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
            }
            if (
                !countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id, 'name' => $right]
                )
            ) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == 'Profile') {
            $ID = $item->getID();
            $prof = new self();

            self::addDefaultProfileInfos(
                $ID,
                [PluginUninstallProfile::$rightname     => 0]
            );
            $prof->showForm($ID);
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

       // From 0.2 to 1.0.0
        $table = 'glpi_plugin_uninstallcomputer_profiles';
        if ($DB->tableExists($table)) {
            $migration->changeField($table, 'use', 'use', "char", ['value' => '0']);
            $migration->migrationOneTable($table);

            $DB->updateOrDie($table, [
                'use' => 'r'
            ], ['use' => '1'], "change value use (1 to r) for $table");

            $migration->renameTable($table, 'glpi_plugin_uninstall_profiles');
        }

        $table = 'glpi_plugin_uninstall_profiles';
       // Plugin already installed
        if ($DB->tableExists($table)) {
           // From 1.0.0 to 1.3.0
            if ($DB->fieldExists($table, 'ID')) {
                $migration->changeField($table, 'ID', 'id', 'autoincrement');
                $migration->changeField($table, 'use', 'use', "varchar(1) DEFAULT ''");
            }

           // From 1.3.0 to 2.0.0
            if (!$DB->fieldExists($table, 'replace')) {
                $migration->addField($table, 'replace', "bool");
                $migration->migrationOneTable($table);
               // UPDATE replace access for current user
                $DB->update('glpi_plugin_uninstall_profiles', [
                    'replace' => 1
                ], ['id' => $_SESSION['glpiactiveprofile']['id']]);
            }

            self::migrateAllProfiles();

            $migration->dropTable($table);
        } else {
           // plugin never installed
            $query = "CREATE TABLE `" . $table . "` (
                    `id` int {$default_key_sign} NOT NULL DEFAULT '0',
                    `profile` varchar(255) NOT NULL DEFAULT '0',
                    `use` varchar(1) DEFAULT '',
                    `replace` tinyint NOT NULL default '0',
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->queryOrDie($query, $DB->error());
            self::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
        }
        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `" . getTableForItemType(__CLASS__) . "`");
    }
}
