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

include ('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkSeveralRightsOr(['uninstall:profile' => READ,
                               'uninstall:profile' => PluginUninstallProfile::RIGHT_REPLACE]);

if (Session::haveRight(PluginUninstallUninstall::$rightname, READ)
    && $_POST['templates_id']) {
   $location = PluginUninstallPreference::getLocationByUserByEntity($_POST["users_id"],
                                                                    $_POST["templates_id"],
                                                                    $_POST["entity"]);
   Location::dropdown(['value'     => ($location == '' ? 0 : $location),
                       'comments'  => 1,
                       'entity'    => $_POST["entity"],
                       'toadd'     => [-1 => __('Keep previous location', 'uninstall'),
                                       0  => __('Empty location', 'uninstall')]]);
}
