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
 * @copyright Copyright (C) 2015-2022 by Teclib'.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/uninstall
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginUninstallState {
   static function replaceState($params = []) {
      global $UNINSTALL_TYPES;

      if (!array_key_exists('item', $params)
          || !in_array(get_class($params['item']), $UNINSTALL_TYPES)
          || !isset($params['item']->fields['id'])
          || !$params['item']->can($params['item']->fields['id'], UPDATE)) {
         return false;
      }
      $item        = $params['item'];
      $items_id    = $item->fields['id'];
      $users_id    = Session::getLoginUserID();
      $state       = new State;
      $state->getFromDB($item->fields['states_id']);
      $states_name = $state->getName([
         'complete' => true,
      ]);

      // get form for uninstall actions
      ob_start();
      PluginUninstallUninstall::showFormUninstallation($items_id, $item, $users_id);
      $html_modal = ob_get_contents();
      ob_end_clean();

      // we json encore to pass it to js (auto-escaping)
      $html= json_encode("
         $states_name
         <a href='#' id='uninstall_actions_open' class='vsubmit'>".
            __("Update").
         "</a>");
      $modal_body = json_encode($html_modal);

      $JS = <<<JAVASCRIPT
      $(function() {
         // replace status select
         var state_span = $("#page select[name=states_id]").parent();
         state_span.html({$html});

         $("#uninstall_actions_open").on("click", function(event) {
            event.preventDefault();

            glpi_html_dialog({
               body: {$modal_body}
            })
         });
      });
JAVASCRIPT;
      echo Html::scriptBlock($JS);
   }
}
