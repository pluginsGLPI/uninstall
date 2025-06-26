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

include('../../../inc/includes.php');

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Toolbox\Sanitizer;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRightsOr('uninstall:profile', [READ, PluginUninstallProfile::RIGHT_REPLACE]);

/**
 * @var array $UNINSTALL_TYPES
 * @var array $UNINSTALL_DIRECT_CONNECTIONS_TYPE
 * @var array $CFG_GLPI
 * @var DBmysql $DB
 */
global $UNINSTALL_TYPES, $UNINSTALL_DIRECT_CONNECTIONS_TYPE, $CFG_GLPI, $DB;

if (!in_array($_REQUEST['itemtype'], array_merge($UNINSTALL_TYPES, $UNINSTALL_DIRECT_CONNECTIONS_TYPE))) {
    throw new AccessDeniedHttpException(__("You don't have permission to perform this action."));
}

if (class_exists($_REQUEST['itemtype']) && is_a($_REQUEST['itemtype'], CommonDBTM::class, true)) {
    $itemtypeisplugin = isPluginItemType($_REQUEST['itemtype']);
    $item             = new $_REQUEST['itemtype']();
    $table            = getTableForItemType($_REQUEST['itemtype']);
    $options          = [];
    $count            = 0;
    $datastoadd       = [];

    $displaywith = false;
    if (isset($_REQUEST['displaywith'])) {
        if (is_array($_REQUEST['displaywith']) && count($_REQUEST['displaywith'])) {
            $displaywith = true;
        }
    }

    $criteria = [
        'FROM' => $table,
        'WHERE' => [],
    ];

    if ($item->isEntityAssign()) {
        // allow opening ticket on recursive object (printer, software, ...)
        $criteria['WHERE'] = getEntitiesRestrictCriteria($table, '', $_SESSION['glpiactiveentities'], $item->maybeRecursive());
    }

    if ($item->maybeDeleted()) {
        $criteria['WHERE']['is_deleted'] = 0;
    }
    if ($item->maybeTemplate()) {
        $criteria['WHERE']['is_template'] = 0;
    }

    if (
        isset($_REQUEST['searchText'])
        && strlen($_REQUEST['searchText']) > 0
        && $_REQUEST['searchText'] != $CFG_GLPI["ajax_wildcard"]
    ) {
        // isset already makes sure the search value isn't null
        $search_val = Search::makeTextSearchValue($_REQUEST['searchText']);
        $criteria['WHERE'][] = [
            'OR' => [
                'name' => ['LIKE', $search_val],
                'id' => ['LIKE', $search_val],
                'serial' => ['LIKE', $search_val],
                'otherserial' => ['LIKE', $search_val],
            ],
        ];
    }

    //If software or plugins : filter to display only the objects that are allowed to be visible in Helpdesk
    if (in_array($_REQUEST['itemtype'], $CFG_GLPI["helpdesk_visible_types"])) {
        $criteria['WHERE']['is_helpdesk_visible'] = 1;
    }

    if (isset($_REQUEST['used'])) {
        $used = $_REQUEST['used'];

        if (count($used)) {
            $criteria['WHERE'][] = [
                'NOT' => ["$table.id" => $used],
            ];
        }
    }

    if (isset($_REQUEST['current_item']) && ($_REQUEST['current_item'] > 0)) {
        $criteria['WHERE']['id'] = ['!=', $_REQUEST['current_item']];
    }

    $criteria['START'] = 0;
    $criteria['LIMIT'] = $CFG_GLPI["dropdown_max"];
    $criteria['ORDER'] = ['name'];

    if (
        isset($_REQUEST['searchText'])
        && $_REQUEST['searchText'] == $CFG_GLPI["ajax_wildcard"]
    ) {
        unset($criteria['LIMIT']);
    }

    $it = $DB->request($criteria);
    foreach ($it as $data) {
        $outputval = $data["name"];

        if ($displaywith) {
            foreach ($_REQUEST['displaywith'] as $key) {
                if (isset($data[$key])) {
                    $withoutput = $data[$key];
                    if (isForeignKeyField($key)) {
                        $withoutput = Dropdown::getDropdownName(
                            getTableNameForForeignKeyField($key),
                            $data[$key],
                        );
                    }
                    if ((strlen($withoutput) > 0) && ($withoutput != '&nbsp;')) {
                        $outputval = sprintf(__('%1$s - %2$s'), $outputval, $withoutput);
                    }
                }
            }
        }
        $ID         = $data['id'];
        $addcomment = "";
        $title      = $outputval;
        if (isset($data["comment"])) {
            $addcomment .= $data["comment"];
            $title = sprintf(__('%1$s - %2$s'), $title, $addcomment);
        }
        if (
            $_SESSION["glpiis_ids_visible"]
            || (strlen($outputval) == 0)
        ) {
            $outputval = sprintf(__('%1$s (%2$s)'), $outputval, $ID);
        }
        array_push($options, ['id'     => $ID,
            'text'  => $outputval,
            'title' => $title,
        ]);
        $count++;
    }


    echo json_encode(['results' => $options,
        'count'    => $count,
    ]);
} else {
    throw new BadRequestHttpException();
}
