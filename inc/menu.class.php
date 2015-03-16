<?php

class PluginUninstallMenu extends CommonGLPI {

   static function getTypeName($nb=0) {
      return __('truc', 'itilcategorygroups'); //Link ItilCategory - Groups
   }
   
   static function getMenuName() {
      $tab = plugin_version_uninstall();
      return $tab["name"];
   }

   static function getMenuContent() {
      global $CFG_GLPI;
      $menu          = array();
      $menu['title'] = self::getMenuName();
      $menu['page']  = '/plugins/uninstall/front/model.php';
      
      /*
      //if (Session::haveRight('config', READ)) {
         
         $menu['options']['model']['title'] = PluginItilcategorygroupsMenu::getTypeName()."a";
         $menu['options']['model']['page'] = Toolbox::getItemTypeSearchUrl('PluginItilcategorygroupsCategory', false)."b";
         $menu['options']['model']['links']['search'] = Toolbox::getItemTypeSearchUrl('PluginItilcategorygroupsCategory', false)."c";

         //if (Session::haveRight('config', UPDATE)) {
            $menu['options']['model']['links']['add'] = Toolbox::getItemTypeFormUrl('PluginItilcategorygroupsCategory', false)."d";
         //}
      
      //}
       */

      return $menu;
   }

}