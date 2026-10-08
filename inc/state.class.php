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

use Glpi\Application\View\TemplateRenderer;

class PluginUninstallState
{
    public static function replaceState($params = [])
    {
        /** @var array $UNINSTALL_TYPES */
        global $UNINSTALL_TYPES;

        if (
            !array_key_exists('item', $params)
            || !in_array($params['item']::class, $UNINSTALL_TYPES)
            || !isset($params['item']->fields['id'])
            || !$params['item']->can($params['item']->fields['id'], UPDATE)
        ) {
            return false;
        }

        $item        = $params['item'];
        $items_id    = $item->fields['id'];
        $users_id    = Session::getLoginUserID();
        $state       = new State();
        $state->getFromDB($item->fields['states_id']);

        $states_name = $state->getName([
            'complete' => true,
        ]);

        // Get the uninstall actions form as a string (no output buffering).
        $html_modal = PluginUninstallUninstall::showFormUninstallation($items_id, $item, $users_id, 0, false);

        // The state select is swapped at runtime by scripts/uninstall.js, which
        // also wires the modal opener; nothing is echoed inline here.
        TemplateRenderer::getInstance()->display('@uninstall/state_replace.html.twig', [
            'rand'        => mt_rand(),
            'states_name' => $states_name,
            'modal'       => $html_modal,
        ]);

        return null;
    }
}
