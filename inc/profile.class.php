<?php
/*
 * @version $Id: profile.class.php 154 2013-07-11 09:26:04Z yllen $
 LICENSE

 This file is part of the uninstall plugin.

 Uninstall plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Uninstall plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with uninstall. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   uninstall
 @author    the uninstall plugin team
 @copyright Copyright (c) 2010-2013 Uninstall plugin team
 @license   GPLv2+
            http://www.gnu.org/licenses/gpl.txt
 @link      https://forge.indepnet.net/projects/uninstall
 @link      http://www.glpi-project.org/
 @since     2009
 ---------------------------------------------------------------------- */

class PluginUninstallProfile extends CommonDBTM {


   static function getTypeName($nb=0) {
      return __('Rights management', 'uninstall');
   }


   static function canCreate() {
      return Session::haveRight('profile', 'w');
   }


   static function canView() {
      return Session::haveRight('profile', 'r');
   }


   static function cleanProfiles(Profile $prof) {

      $profile = new self();
      $profile->deleteByCriteria(array('id' => $prof->getField("id")));
   }


   function showForm($ID,$options=array()){
      global $DB;

      $target = $this->getFormURL();
      if (isset($options['target'])) {
        $target = $options['target'];
      }

      $profile = new Profile();

      if (!Session::haveRight("profile", "r")) {
         return false;
      }

      if ($ID){
         $this->getFromDB($ID);
         $profile->getFromDB($ID);
      } else {
         $this->getEmpty();
      }
      $options['colspan'] = 1;
      $this->showFormHeader($options);

      echo "<tr><th colspan='2' class='center b'>".sprintf(__('%1$s - %2$s'), self::getTypeName(),
         $profile->fields["name"])."</th></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".PluginUninstallUninstall::getTypeName()."</td><td>";
      Profile::dropdownNoneReadWrite("use", $this->fields["use"], 1, 1, 1);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".PluginUninstallReplace::getTypeName()."</td><td>";
      Dropdown::showYesNo("replace",$this->fields["replace"]);
      echo "</td></tr>\n";

      echo "<input type='hidden' name='id' value=".$this->fields["id"].">";

      $options['candel'] = false;
      $this->showFormButtons($options);
   }


   function createUserAccess($profile) {

      return $this->add(array('id'      => $profile->getField('id'),
                              'profile' => addslashes($profile->getField('name'))));
   }


   static function createFirstAccess($ID) {

      $firstProf = new self();
      if (!$firstProf->GetfromDB($ID)) {
         $profile = new Profile();
         $profile->getFromDB($ID);
         $name = addslashes($profile->fields["name"]);

         $firstProf->add(array('id'       => $ID,
                               'profile'  => $name,
                               'use'      => 'w',
                               'replace'  => 1));
      }
   }


   static function changeProfile() {

      $prof = new self();
      if ($prof->getFromDB($_SESSION['glpiactiveprofile']['id'])) {
         $_SESSION["glpi_plugin_uninstall_profile"] = $prof->fields;
      } else {
         unset ($_SESSION["glpi_plugin_uninstall_profile"]);
      }
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType() == 'Profile') {
         if ($item->getField('interface') == 'central') {
            return PluginUninstallUninstall::getTypeName();
         }
         return '';
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == 'Profile') {
         $prof = new self();
         $ID = $item->getField('id');
         if (!$prof->GetfromDB($ID)) {
            $prof->createUserAccess($item);
         }
         $prof->showForm($ID);
      }
      return true;
   }


   static function install($migration) {
      global $DB;

      // From 0.2 to 1.0.0
      $table = 'glpi_plugin_uninstallcomputer_profiles';
      if (TableExists($table)) {
         $migration->changeField($table, 'use', 'use', "char", array('value' => '0'));
         $migration->migrationOneTable($table);

         $query = "UPDATE `".$table."`
                   SET `use` = 'r'
                   WHERE `use` = '1'";
         $DB->queryOrDie($query, "change value use (1 to r) for ".$table);

         $migration->renameTable($table, 'glpi_plugin_uninstall_profiles');
      }


      $table = 'glpi_plugin_uninstall_profiles';
      // Plugin already installed
      if (TableExists($table)) {
         // From 1.0.0 to 1.3.0
         if (FieldExists($table, 'ID')) {
            $migration->changeField($table, 'ID', 'id', 'autoincrement');
            $migration->changeField($table, 'use', 'use', "varchar(1) DEFAULT ''");
         }

         // From 1.3.0 to 2.0.0
         if (!FieldExists($table, 'replace')) {
            $migration->addField($table, 'replace', "bool");
            $migration->migrationOneTable($table);
            // UPDATE replace access for current user
            $prof             = new PluginUninstallProfile();
            $input['id']      = $_SESSION['glpiactiveprofile']['id'];
            $input['replace'] = 1;
            $prof->update($input);
         }

      // plugin never installed
      } else {
         $query = "CREATE TABLE `".$table."` (
                    `id` int(11) NOT NULL DEFAULT '0',
                    `profile` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
                    `use` varchar(1) DEFAULT '',
                    `replace` tinyint(1) NOT NULL default '0',
                    PRIMARY KEY (`id`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->queryOrDie($query, $DB->error());

         self::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
      }
      return true;
   }


   static function uninstall() {
      global $DB;

      $DB->query("DROP TABLE IF EXISTS `".getTableForItemType(__CLASS__)."`");
   }

}
?>