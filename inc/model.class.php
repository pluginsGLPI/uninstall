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

class PluginUninstallModel extends CommonDBTM
{
    public static $rightname         = "uninstall:profile";
    public $dohistory         = true;
    public $first_level_menu  = "plugins";
    public $second_level_menu = "uninstall";

    const TYPE_MODEL_UNINSTALL   = 1;
    const TYPE_MODEL_REPLACEMENT = 2;
    const TYPE_MODEL_REPLACEMENT_UNINSTALL = 3;

    // do nothing
    const PLUGIN_FIELDS_ACTION_NONE = 0;
    // delete values, uninstall only
    const PLUGIN_FIELDS_ACTION_RAZ = 1;
    // copy values, replace only
    const PLUGIN_FIELDS_ACTION_COPY = 2;
    // choose action for each container individually
    const PLUGIN_FIELDS_ACTION_ADVANCED = 3;


    public static function getTypeName($nb = 0)
    {
        return _n("Template", "Templates", $nb);
    }

    public static function canDelete()
    {
        return self::canUpdate();
    }

    public static function canPurge()
    {
        return self::canUpdate();
    }

    public static function canCreate()
    {
        return self::canUpdate();
    }

    public static function canReplace()
    {
        return Session::haveRight(self::$rightname, PluginUninstallProfile::RIGHT_REPLACE) ? true : false;
    }

    public static function getMenuContent()
    {
        $menu = [];

       // get Menu name :
        $menu['title'] = __("Item's Lifecycle", 'uninstall');
        $menu['page']  = '/' . Plugin::getWebDir('uninstall', false) . '/front/model.php';
        $menu['icon']  = self::getIcon();

        if (Session::haveRight(PluginUninstallProfile::$rightname, READ)) {
            $menu['options']['model']['title'] = self::getTypeName(Session::getPluralNumber());
            $menu['options']['model']['page'] = Toolbox::getItemTypeSearchUrl('PluginUninstallModel', false);
            $menu['options']['model']['links']['search'] = Toolbox::getItemTypeSearchUrl('PluginUninstallModel', false);

            if (Session::haveRight(PluginUninstallProfile::$rightname, UPDATE)) {
                $menu['options']['model']['links']['add'] = Toolbox::getItemTypeFormUrl('PluginUninstallModel', false);
            }
        }

        return $menu;
    }

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForadd($input);
        if ($input['types_id'] == self::TYPE_MODEL_REPLACEMENT_UNINSTALL && $input['replace_method'] == PluginUninstallReplace::METHOD_PURGE) {
            Session::addMessageAfterRedirect(
                __("The purge archiving method is not available for this type of model"),
                true,
                ERROR
            );
            $input = [];
        }
        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInputForAdd($input);
    }

   /**
    * Dropdown of model type
    *
    * @param $name   select name (default 'types_id')
    * @param $value  default value (default '')
    * @param bool $display echo or return string
    * @return string|void
   **/
    public static function dropdownType($name = 'types_id', $value = '', $display = true)
    {

        $values[1] = __('Uninstallation', 'uninstall');
        if (self::canReplace()) {
            $values[2] = __('Replacement', 'uninstall');
            $values[3] = __('Replacement then uninstallation', 'uninstall');
        }
        return Dropdown::showFromArray($name, $values, [
            'value' => $value,
            'display' => $display
        ]);
    }


    public static function getReplacementMethods()
    {

        $plug = new Plugin();
        if ($plug->isActivated('PDF')) {
            $archive_method = " - " . __('PDF Archiving', 'uninstall');
        } else {
            $archive_method = " - " . __('CSV Archiving', 'uninstall');
        }

        return [PluginUninstallReplace::METHOD_PURGE => __('Purge', 'uninstall') . $archive_method,
            PluginUninstallReplace::METHOD_DELETE_AND_COMMENT => __('Delete + Comment', 'uninstall'),
            PluginUninstallReplace::METHOD_KEEP_AND_COMMENT => __('Keep + Comment', 'uninstall')
        ];
    }


   /**
    * Dropdown of method remplacement
    *
    * @param $name   select name
    * @param $type   int types_id
    * @param $value  default value (default '')
   **/
    public static function dropdownMethodReplacement($name, $value = '', $type = self::TYPE_MODEL_REPLACEMENT)
    {
        $methods = self::getReplacementMethods();
        if ($type == PluginUninstallModel::TYPE_MODEL_REPLACEMENT_UNINSTALL) {
            // can't purge to be able to apply uninstall after replacement
            unset($methods[PluginUninstallReplace::METHOD_PURGE]);
        }
        Dropdown::showFromArray(
            $name,
            $methods,
            ['value' => $value]
        );
    }


   /**
    * @param $value  (default 0)
   **/
    public static function getMethodReplacement($value = 0)
    {

        $values = self::getReplacementMethods();
        if (isset($values[$value])) {
            return $values[$value];
        }
        return "";
    }

   /**
    * Définition du nom de l'onglet
    **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        switch ($item->getType()) {
            case 'Preference':
                return PluginUninstallUninstall::getTypeName(1);

            case __CLASS__:
                $tab = [];
                $tab[1] = self::getTypeName(1);
                $tab[2] = __('Replacing data', 'uninstall');
                $plugin = new Plugin();
                if ($plugin->isActivated('fields') && $item->fields['action_plugin_fields'] == self::PLUGIN_FIELDS_ACTION_ADVANCED) {
                    $tab[3] = __('Additional fields options', 'uninstall');
                }
                return $tab;
        }
        return '';
    }

   /**
    * Définition du contenu de l'onglet
    **/
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case __CLASS__:
                switch ($tabnum) {
                    case 1:
                        $item->showForm($item->getID());
                        break;
                    case 2:
                        $item->showFormAction($item);
                        break;
                    case 3:
                        $item->showFormPluginFields($item);
                        break;
                }
        }
        return true;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    public function showForm($ID, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $entities = (isset($_SESSION['glpiparententities']) ? $_SESSION['glpiparententities'] : 0);

        echo "<tr class='tab_bg_1'><td>" . __('Name') . "</td>";
        echo "<td>";
        echo Html::input(
            'name',
            [
                'value' => $this->fields['name'],
            ]
        );
        echo "</td>";
        echo "<td>" . __('Type of template', 'uninstall') . "</td>";
        echo "<td>";
        $value = (isset($this->fields["types_id"]) ? $this->fields["types_id"] : 0);
        self::dropdownType('types_id', $value);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
            echo "<td>" . __("Transfer's model to use", "uninstall") . "</td>";
            echo "<td>";
            if ($ID == -1) {
                $value = PluginUninstallUninstall::getUninstallTransferModelid();
            } else {
                $value = $this->fields["transfers_id"];
            }
            Transfer::dropdown(['value'               => $value,
                'display_emptychoice' => false
            ]);
        } else {
            echo "<td></td>";
            echo "<td></td>";
            echo "<input type='hidden' name='transfers_id' value='0'";
        }
        echo "</td>";
        echo "<td rowspan='4'>" . __('Comments') . "</td>";
        echo "<td rowspan='4'>";
        echo "<textarea cols='60' rows='4' name='comment'>" . $this->fields["comment"] . "</textarea>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __('New status of the computer', 'uninstall') . "</td>";
        echo "<td>";
        State::dropdown(['value'       => $this->fields['states_id'],
            'emptylabel'  => __('None')
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
            echo "<td>" . __('Action on group', 'uninstall') . "</td><td>";
            Dropdown::showFromArray(
                "groups_action",
                [
                    'old' => __('Keep in the current group', 'uninstall'),
                    'set' => __('Affect to a new group', 'uninstall')
                ],
                [
                    'on_change' => '$("#show_groups").toggle($(this).val() === "set");',
                    'value'     => $this->fields["groups_action"],
                ]
            );
            echo "</td>";
        } else {
            echo "<td colspan='2'></td>";
        }
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
            echo "<td>" . __('New group', 'uninstall') . "</td><td>";
            echo "<span id='show_groups' " . ($this->fields["groups_action"] === 'old' ? 'style="display:none;"' : '') . ">";
            Group::dropdown(['value'       => $this->fields["groups_id"],
                'entity'      => $this->fields["entities_id"],
                'entity_sons' => $entities,
                'emptylabel'  => __('None')
            ]);
            echo "</span></td>";
        } else {
            echo "<td colspan='2'></td>";
        }
        echo "</tr>";

        if (
            !Session::isMultiEntitiesMode()
            && Session::haveRight("transfer", READ)
        ) {
            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='2'>";
            echo "<a href='" . $CFG_GLPI["root_doc"] .
                "/front/transfer.form.php'\">" . __('Add template', 'uninstall') . "</td>";
            echo "<td colspan='2'>";
            echo "<a href='" . $CFG_GLPI["root_doc"] . "/front/transfer.php'\">" .
                __('Manage templates', 'uninstall') . "</a></td>";
            echo "</tr>";
        }

        $this->showFormButtons($options);

        return true;
    }

    public function showPartFormUninstall()
    {
        echo "<tr class='tab_bg_1 center'>";
        echo "<th colspan='4'>" . __('Uninstallation', 'uninstall') . ' - ' . __('Erase datas', 'uninstall') . "</th></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . __('Delete software history (computers)', 'uninstall') . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_soft_history",
            (isset($this->fields["raz_soft_history"])
            ? $this->fields["raz_soft_history"] : 1)
        );
        echo "</td><td>" . __('Delete the whole history', 'uninstall') . "</td><td>";
        Dropdown::showYesNo(
            "raz_history",
            (isset($this->fields["raz_history"])
            ? $this->fields["raz_history"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Name')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_name",
            (isset($this->fields["raz_name"])
            ? $this->fields["raz_name"] : 1)
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Alternate username')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_contact",
            (isset($this->fields["raz_contact"])
            ? $this->fields["raz_contact"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Alternate username number')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_contact_num",
            (isset($this->fields["raz_contact_num"])
            ? $this->fields["raz_contact_num"] : 1)
        );
        echo "</td><td colspan='2'></td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('User')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_user",
            (isset($this->fields["raz_user"])
            ? $this->fields["raz_user"] : 1)
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Operating system')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_os",
            (isset($this->fields["raz_os"]) ? $this->fields["raz_os"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Network')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_network",
            (isset($this->fields["raz_network"])
            ? $this->fields["raz_network"] : 1)
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Domain')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_domain",
            (isset($this->fields["raz_domain"])
            ? $this->fields["raz_domain"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(
            __('%1$s %2$s'),
            __('Blank'),
            __('IP') . " & " . __('Subnet mask') . " & " . __('Gateway') . " & " .
            __('Subnet')
        ) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_ip",
            (isset($this->fields["raz_ip"]) ? $this->fields["raz_ip"] : 1)
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Budget')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_budget",
            (isset($this->fields["raz_budget"])
            ? $this->fields["raz_budget"] : 0)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Blank'), __('Antivirus')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_antivirus",
            (isset($this->fields["raz_antivirus"])
            ? $this->fields["raz_antivirus"] : 1)
        );
        echo "</td>";
        echo "<td>" . __('Delete inventory data (dynamic flag, locks, agent information, ...)', 'uninstall') . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "raz_glpiinventory",
            (isset($this->fields["raz_glpiinventory"])
            ? $this->fields["raz_glpiinventory"] : 0),
            -1
        );
        echo "</td></tr>";
    }

    public function showPartFormRemplacement()
    {
        echo "<tr class='tab_bg_1 center'>";
        echo "<th colspan='4'>" . sprintf(
            __('%1$s - %2$s'),
            __('Informations replacement', 'uninstall'),
            __('General informations', 'uninstall')
        ) . "</th></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Name')) . "</td><td>";
        Dropdown::showYesNo(
            "replace_name",
            (isset($this->fields["replace_name"])
                           ? $this->fields["replace_name"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Serial number')) . "</td><td>";
        Dropdown::showYesNo(
            "replace_serial",
            (isset($this->fields["replace_serial"])
            ? $this->fields["replace_serial"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Inventory number')) . "</td><td>";
        Dropdown::showYesNo(
            "replace_otherserial",
            (isset($this->fields["replace_otherserial"])
                           ? $this->fields["replace_otherserial"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "<td>" . __('Overwrite informations (from old item to the new)', 'uninstall') . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "overwrite",
            (isset($this->fields["overwrite"]) ? $this->fields["overwrite"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . __('Archiving method of the old material', 'uninstall') . "</td>";
        echo "<td colspan='2'>";
        $value = (isset($this->fields["replace_method"]) ? $this->fields["replace_method"] : 0);
        self::dropdownMethodReplacement('replace_method', $value, $this->fields['types_id']);
        echo "</td>";
        echo "<td>";
        $plug = new Plugin();
        if ($plug->isActivated('PDF')) {
            echo "<span class='green b tracking_small'>" .
                __('Plugin PDF is installed and activated', 'uninstall') . "</span>";
        } else {
            echo "<span class='red b tracking_small'>" .
                __(
                    "Plugin PDF is not installed, you won't be able to use PDF format for archiving",
                    "uninstall"
                ) . "</span>";
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<th colspan='4'>" . sprintf(
            __('%1$s - %2$s'),
            __('Informations replacement', 'uninstall'),
            __('Connections with other materials', 'uninstall')
        );
        echo "</th></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), _n('Document', 'Documents', 2)) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_documents",
            (isset($this->fields["replace_documents"])
                           ? $this->fields["replace_documents"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), _n('Contract', 'Contracts', 2)) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_contracts",
            (isset($this->fields["replace_contracts"])
            ? $this->fields["replace_contracts"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(
            __('%1$s %2$s'),
            __('Copy'),
            __('Financial and administratives information')
        ) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_infocoms",
            (isset($this->fields["replace_infocoms"])
                           ? $this->fields["replace_infocoms"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), _n('Reservation', 'Reservations', 2));
        echo "</td>";
        echo "<td>";
        if (isset($this->fields["replace_reservations"])) {
            $reservation = $this->fields["replace_reservations"];
        } else {
            $reservation = 1;
        }
        Dropdown::showYesNo("replace_reservations", $reservation);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('User')) . "</td>";
        echo "<td>";
        if (isset($this->fields["replace_users"])) {
            $user = $this->fields["replace_users"];
        } else {
            $user = 1;
        }
        Dropdown::showYesNo("replace_users", $user, -1, ['width' => '100%']);
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Group')) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_groups",
            (isset($this->fields["replace_groups"])
            ? $this->fields["replace_groups"] : 1)
        );
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), _n('Ticket', 'Tickets', 2)) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_tickets",
            (isset($this->fields["replace_tickets"])
                           ? $this->fields["replace_tickets"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "<td>" . sprintf(
            __('%1$s %2$s'),
            __('Copy'),
            sprintf(
                __('%1$s %2$s'),
                _n('Connection', 'Connections', 2),
                _n('Network', 'Networks', 2)
            )
        ) . "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_netports",
            (isset($this->fields["replace_netports"])
            ? $this->fields["replace_netports"] : 1)
        );
        echo "</td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Direct connections', 'uninstall'));
        echo "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_direct_connections",
            (isset($this->fields["replace_direct_connections"])
                           ? $this->fields["replace_direct_connections"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Alternate username'));
        echo "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_contact",
            (isset($this->fields["replace_contact"])
                           ? $this->fields["replace_contact"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__('%1$s %2$s'), __('Copy'), __('Alternate username number'));
        echo "</td>";
        echo "<td>";
        Dropdown::showYesNo(
            "replace_contact_num",
            (isset($this->fields["replace_contact_num"])
                           ? $this->fields["replace_contact_num"] : 1),
            -1,
            ['width' => '100%']
        );
        echo "</td>";
        echo "</tr>";
    }

   /**
    * @param $item
   **/
    public function showFormAction($item)
    {
        $spotted = false;
        $id      = $item->getID();

        if ($id > 0) {
            if ($this->can($id, READ)) {
                $spotted = true;
            }
        } else {
           //$use_cache = false;
            if ($this->can(-1, UPDATE)) {
                $spotted = true;
                $this->getEmpty();
            }
        }

        if (! $spotted) {
            echo "<span class='center b'>" . __('No item found') . "</span>";
            return false;
        }

        $canedit = $this->can($id, UPDATE);
        echo "<form action='" . $item->getFormURL() . "' method='post'>";
        echo "<table class='tab_cadre_fixe' cellpadding='5'>";

        if ($this->fields["types_id"] != self::TYPE_MODEL_UNINSTALL) {
            // if Replacement or Replacement then uninstall is selected
            self::showPartFormRemplacement();
        }
        if ($this->fields["types_id"] != self::TYPE_MODEL_REPLACEMENT) {
           // if Uninstall or Replacement then uninstall is selected
            self::showPartFormUninstall();
        }

        $plug = new Plugin();
        if ($plug->isActivated('ocsinventoryng')) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . _n('OCSNG link', 'OCSNG links', 2, 'ocsinventoryng') .
              "</th></tr>";
            echo "<th colspan='4'>" . __(
                'These options only apply to computers coming from OCSNG',
                'uninstall'
            ) . "</th></tr>";

            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Delete computer in OCSNG', 'ocsinventoryng') . "</td>";
            echo "<td>";
            Dropdown::showYesNo(
                "remove_from_ocs",
                (isset($this->fields["remove_from_ocs"])
                ? $this->fields["remove_from_ocs"] : 0),
                -1,
                ['width' => '100%']
            );
            echo "</td>";
            echo "<td>" . __('Delete link with computer in OCSNG', 'uninstall') . "</td>";
            echo "<td>";
            Dropdown::showYesNo(
                "delete_ocs_link",
                (isset($this->fields["delete_ocs_link"])
                ? $this->fields["delete_ocs_link"] : 0)
            );
            echo "</td></tr>";
        }

        if ($plug->isActivated('fusioninventory')) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __('FusionInventory') .
              "</th></tr>";

            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Delete computer in FusionInventory', 'uninstall') . "</td>";
            echo "<td>";
            Dropdown::showYesNo(
                "raz_fusioninventory",
                (isset($this->fields["raz_fusioninventory"])
                ? $this->fields["raz_fusioninventory"] : 0),
                -1,
                ['width' => '100%']
            );
            echo "</td>";
            echo "<td colspan='2'></td>";
            echo "</tr>";
        }

        // noticed that the field was never used in replace.class.php
        if ($plug->isActivated('fields')) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='4'>" . __("Additionnal fields", "fields") .
              "</th></tr>";

            echo "<tr class='tab_bg_1 center'>";
            echo "<td>" . __('Fields plugin informations', 'uninstall') . "</td>";
            echo "<td>";
            $choices = [
                self::PLUGIN_FIELDS_ACTION_NONE => __('Do nothing')
            ];
            if ($this->fields['types_id'] == self::TYPE_MODEL_UNINSTALL) {
                $choices[self::PLUGIN_FIELDS_ACTION_RAZ] = __('Blank');
            } else {
                $choices[self::PLUGIN_FIELDS_ACTION_COPY] = __('Copy');
            }
            $choices[self::PLUGIN_FIELDS_ACTION_ADVANCED] = __('Advanced options', 'uninstall');
            $defaultValue = $this->fields['types_id'] == self::TYPE_MODEL_UNINSTALL ?
                self::PLUGIN_FIELDS_ACTION_RAZ : self::PLUGIN_FIELDS_ACTION_NONE;
            Dropdown::showFromArray(
                "action_plugin_fields",
                $choices,
                [
                    'value' => isset($this->fields["action_plugin_fields"]) ?
                        $this->fields["action_plugin_fields"] : $defaultValue,
                    'width' => '100%'
                ]
            );
            echo "</td>";
            echo "<td colspan='2'></td>";
            echo "</tr>";
        }

        if ($canedit) {
            echo "<tr class='tab_bg_1 center'>";
            echo "<td colspan='4' class='center'>";
            echo "<input type='hidden' name='id' value='" . $this->fields["id"] . "'>";
            echo "<input type='submit' name='update' value=\"" . _sx('button', 'Save') . "\" class='submit'>";
            echo "</td></tr>";
        }

        echo "</table>";

        echo "<input type='hidden' name='entities_id' value='" . $this->fields["entities_id"] . "'>";
        Html::closeForm();

        return true;
    }

    /**
     * @param $item
     **/
    public function showFormPluginFields($item)
    {
        $plugin = new Plugin();
        if ($plugin->isActivated('fields')) {
            if ($item->fields['action_plugin_fields'] === self::PLUGIN_FIELDS_ACTION_ADVANCED) {
                echo "<table class='tab_cadre_fixe mb-3' cellpadding='5'>";
                echo "<tr class='tab_bg_1 center'>";
                echo "<th colspan='4'>" . __('Plugin additionnal fields blocks', 'uninstall') .
                    "</th></tr></table>";
                $parameters = [
                    'start'      => 0,
                    'is_deleted' => 0,
                    'sort'       => 1,
                    'order'      => 'DESC',
                    'reset'      => 'reset',
                    'criteria'   => [],
                ];
                Search::showList(PluginUninstallModelcontainer::class, $parameters);
            } else {
                echo "<span class='center b'>" . __("Select 'Advanced options' for the field 'Fields plugin informations' to access this tab.") . "</span>";
                return false;
            }
        } else {
            echo "<span class='center b'>" . __("Activate the plugin 'fields' to access this tab.") . "</span>";
            return false;
        }

        return true;
    }


   /**
    * @param $model_id
   **/
    public function getConfig($model_id)
    {
        if (! $this->getFromDB($model_id)) {
            $this->fields = [];
        }
    }


    public function cleanDBonPurge()
    {
        PluginUninstallPreference::deleteUserPreferenceForModel($this->fields['id']);
    }


    public function rawSearchOptions()
    {

        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => self::getTypeName(),
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => $this->getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'itemlink_type'      => $this->getType(),
            'autocomplete'       => true,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'raz_name',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Name')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'raz_soft_history',
            'name'               => __('Delete software history (computers)', 'uninstall'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'raz_contact',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Alternate username')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '33',
            'table'              => $this->getTable(),
            'field'              => 'raz_contact_num',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Alternate username number')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'raz_user',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('User')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => 'glpi_states',
            'field'              => 'name',
            'name'               => __('Status'),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => $this->getTable(),
            'field'              => 'raz_os',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Operating system')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => $this->getTable(),
            'field'              => 'raz_network',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Network')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '10',
            'table'              => $this->getTable(),
            'field'              => 'raz_domain',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Domain')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => $this->getTable(),
            'field'              => 'raz_ip',
            'name'               => sprintf(
                __('%1$s %2$s'),
                __('Blank'),
                __('IP') . " & " . __('Subnet mask') . " & " . __('Gateway') . " & " . __('Subnet')
            ),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => $this->getTable(),
            'field'              => 'raz_budget',
            'name'               => sprintf(__('%1$s %2$s'), __('Blank'), __('Budget')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => $this->getTable(),
            'field'              => 'is_recursive',
            'name'               => __('Child entities'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '15',
            'table'              => 'glpi_transfers',
            'field'              => 'name',
            'name'               => __('Transfer\'s model to use', 'uninstall'),
            'datatype'           => 'itemlink',
            'itemlink_type'      => 'Transfer',
        ];

        $tab[] = [
            'id'                 => '17',
            'table'              => $this->getTable(),
            'field'              => 'comment',
            'name'               => __('Comments'),
            'datatype'           => 'text',
        ];

        $plug = new Plugin();
        if ($plug->isActivated('ocsinventoryng')) {
            $tab[] = [
                'id'                 => '18',
                'table'              => $this->getTable(),
                'field'              => 'remove_from_ocs',
                'name'               => __('Delete computer in OCSNG', 'ocsinventoryng'),
                'datatype'           => 'bool',
            ];

            $tab[] = [
                'id'                 => '19',
                'table'              => $this->getTable(),
                'field'              => 'delete_ocs_link',
                'name'               => __('Delete link with computer in OCSNG', 'uninstall'),
                'datatype'           => 'bool',
            ];
        }

        $tab[] = [
            'id'                 => '20',
            'table'              => $this->getTable(),
            'field'              => 'types_id',
            'name'               => __('Type of template', 'uninstall'),
            'linkfield'          => '',
            'datatype'           => 'specific',
            'searchtype'         => 'equals',
        ];

        $tab[] = [
            'id'                 => '21',
            'table'              => $this->getTable(),
            'field'              => 'groups_id',
            'name'               => __('Action on group', 'uninstall'),
            'linkfield'          => '',
            'datatype'           => 'specific',
            'additionalfields'   => ['groups_action'],
            'searchtype'         => 'equals',
        ];

        $tab[] = [
            'id'                 => '22',
            'table'              => $this->getTable(),
            'field'              => 'replace_method',
            'name'               => __('Archiving method of the old material', 'uninstall'),
            'linkfield'          => '',
            'datatype'           => 'specific',
            'searchtype'         => 'equals',
        ];

        $tab[] = [
            'id'                 => '23',
            'table'              => $this->getTable(),
            'field'              => 'raz_history',
            'name'               => __('Delete the whole history', 'uninstall'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '24',
            'table'              => $this->getTable(),
            'field'              => 'replace_users',
            'name'               => sprintf(__('%1$s %2$s'), __('Copy'), __('User')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '25',
            'table'              => $this->getTable(),
            'field'              => 'replace_name',
            'name'               => sprintf(__('%1$s %2$s'), __('Copy'), __('Name')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '26',
            'table'              => $this->getTable(),
            'field'              => 'replace_serial',
            'name'               => sprintf(__('%1$s %2$s'), __('Copy'), __('Serial number')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '27',
            'table'              => $this->getTable(),
            'field'              => 'replace_otherserial',
            'name'               => sprintf(__('%1$s %2$s'), __('Copy'), __('Inventory number')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '28',
            'table'              => $this->getTable(),
            'field'              => 'replace_documents',
            'name'               => sprintf(
                __('%1$s %2$s'),
                __('Copy'),
                _n('Document', 'Documents', 2)
            ),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '29',
            'table'              => $this->getTable(),
            'field'              => 'replace_contracts',
            'name'               => sprintf(
                __('%1$s %2$s'),
                __('Copy'),
                _n('Contract', 'Contracts', 2)
            ),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '30',
            'table'              => $this->getTable(),
            'field'              => 'replace_infocoms',
            'name'               => sprintf(
                __('%1$s %2$s'),
                __('Copy'),
                __('Financial and administratives information')
            ),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '31',
            'table'              => $this->getTable(),
            'field'              => 'replace_reservations',
            'name'               => sprintf(
                __('%1$s %2$s'),
                __('Copy'),
                _n('Reservation', 'Reservations', 2)
            ),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '32',
            'table'              => $this->getTable(),
            'field'              => 'replace_groups',
            'name'               => sprintf(__('%1$s %2$s'), __('Copy'), __('Group')),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => __('Entity'),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $values
    * @param $options   array
   **/
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'replace_method':
                if ($values['replace_method'] != 0) {
                    return self::getMethodReplacement($values['replace_method']);
                }
                return Dropdown::EMPTY_VALUE;
            break;

            case 'types_id':
                switch ($values['types_id']) {
                    case self::TYPE_MODEL_UNINSTALL:
                        return __('Uninstallation', 'uninstall');
                    case self::TYPE_MODEL_REPLACEMENT:
                        return __('Replacement', 'uninstall');
                    case self::TYPE_MODEL_REPLACEMENT_UNINSTALL:
                        return __('Replacement then uninstallation', 'uninstall');
                }
            break;

            case 'groups_id':
                if ($values['groups_action'] === 'old') {
                    return __('Keep in the current group', 'uninstall');
                } else if ($values['groups_id'] === 0) {
                    return __('None');
                }
                return Dropdown::getDropdownName('glpi_groups', $values['groups_id']);

            break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $name               (default '')
    * @param $values             (defaut '')
    * @param $options   array
   **/
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'replace_method':
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, self::getReplacementMethods(), $options);

            case 'types_id':
                return self::dropdownType($name, $values[$field], false);
            case 'groups_id':
                $options['name']        = $name;
                $options['value']       = $values[$field];
                $options['emptylabel']  = __('None');
                return Group::dropdown($options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

   /**
    * Get the standard massive actions which are forbidden
    *
    * @since version 0.84
    *
    * @return an array of massive actions
    **/
    public function getForbiddenStandardMassiveAction()
    {

        $forbidden = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }


    public static function install($migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

       // From 0.2 to 1.0.0
        if ($DB->tableExists('glpi_plugin_uninstallcomputer_config')) {
            $table = 'glpi_plugin_uninstall_models';
            $migration->renameTable('glpi_plugin_uninstallcomputer_config', $table);
            $migration->addField($table, 'FK_entities', 'integer');
            $migration->addField($table, 'recursive', 'int NOT NULL DEFAULT 1');
            $migration->addField($table, 'name', 'string');
            $migration->addField($table, `comments`, 'text', ['value' => 'NOT NULL']);

            $migration->migrationOneTable($table);
            $ID = PluginUninstallUninstall::getUninstallTransferModelID();
            $DB->insertOrDie('glpi_plugin_uninstall_models', [
                'FK_entities' => 0,
                'recursive'   => 1,
                'name'        => 'Uninstall',
                'transfer_id' => $ID,
                'state'       => 0,
                'raz_name'    => 1,
                'raz_contact' => 1,
                'raz_ip'      => 1,
                'raz_os'      => 1,
                'raz_domain'  => 1,
                'raz_network' => 1,
                'raz_soft_history' => 0,
                'raz_budget'  => 0
            ], "add uninstall model in $table");
        }

       // Plugin already installed
        $table = 'glpi_plugin_uninstall_models';
        if ($DB->tableExists($table)) {
           // From 1.0.0 to 1.1.0
            if (!$DB->fieldExists($table, 'group')) {
                $migration->addField($table, 'group', 'integer');
                $migration->addField($table, 'remove_from_ocs', 'int NOT NULL DEFAULT 0');
            }

           // From 1.1.0 to 1.2.1
            if (!$DB->fieldExists($table, 'delete_ocs_link')) {
                $migration->addField($table, 'delete_ocs_link', 'int NOT NULL DEFAULT 0');
            }

           // from 1.2.1 to 1.3.0
            if ($DB->fieldExists($table, 'ID')) {
                $migration->changeField($table, 'ID', 'id', 'autoincrement');
                $migration->changeField($table, 'FK_entities', 'entities_id', 'integer');
                $migration->changeField(
                    $table,
                    'recursive',
                    'is_recursive',
                    "bool",
                    ['value' => 1]
                );
                $migration->changeField($table, 'transfer_id', 'transfers_id', "int {$default_key_sign} NOT NULL DEFAULT 0");
                $migration->changeField($table, 'state', 'states_id', "integer");
                $migration->changeField($table, 'group', 'groups_id', "integer");
            }

           // from 1.3.0 to 2.0.0
            if (!$DB->fieldExists($table, 'types_id')) {
                $migration->addField($table, 'types_id', "int {$default_key_sign} NOT NULL DEFAULT 0");
                $migration->migrationOneTable($table);
                $DB->updateOrDie($table, [
                    'types_id' => 1
                ], [
                    'types_id' => 0
                ], "update types_id of $table");

                $migration->addField($table, 'replace_name', "bool");
                $migration->addField($table, 'replace_serial', "bool");
                $migration->addField($table, 'replace_otherserial', "bool");
                $migration->addField($table, 'replace_documents', "bool");
                $migration->addField($table, 'replace_contracts', "bool");
                $migration->addField($table, 'replace_infocoms', "bool");
                $migration->addField($table, 'replace_reservations', "bool");
                $migration->addField($table, 'replace_users', "bool");
                $migration->addField($table, 'replace_groups', "bool");
                $migration->addField($table, 'replace_tickets', "bool");
                $migration->addField($table, 'replace_netports', "bool");
                $migration->addField($table, 'replace_direct_connections', "bool");
                $migration->addField($table, 'overwrite', "bool");
                $migration->addField($table, 'replace_method', "integer", ['value' => 2]);

                $migration->migrationOneTable($table);
                self::createTransferModel('Replace');
            }

           // from 2.0.0 to 2.0.1
            if (!$DB->fieldExists($table, 'raz_history')) {
                $migration->addField($table, 'raz_history', 'integer', ['after' => 'raz_network']);
            }

            if (!$DB->fieldExists($table, 'raz_ocs_registrykeys')) {
                $migration->addField($table, 'raz_ocs_registrkeys', "integer");
            }

            if (!$DB->fieldExists($table, 'raz_fusioninventory')) {
                $migration->addField($table, 'raz_fusioninventory', "integer");
            }
            if ($migration->addField($table, 'raz_contact_num', "bool")) {
                $migration->migrationOneTable($table);
                $DB->updateOrDie('glpi_plugin_uninstall_models', [
                    'raz_contact_num' => new QueryExpression($DB::quoteName('raz_contact'))
                ], [new QueryExpression('1')], "Fill raz_contact_num");
            }

            if ($migration->addField($table, 'replace_contact', "bool")) {
                $migration->migrationOneTable($table);
                $DB->updateOrDie('glpi_plugin_uninstall_models', [
                    'replace_contact' => new QueryExpression($DB::quoteName('replace_users'))
                ], [new QueryExpression('1')], "Fill replace_contact");
            }

            if ($migration->addField($table, 'replace_contact_num', "bool")) {
                $migration->migrationOneTable($table);
                $DB->updateOrDie('glpi_plugin_uninstall_models', [
                    'replace_contact_num' => new QueryExpression($DB::quoteName('replace_contact'))
                ], [new QueryExpression('1')], "Fill replace_contact_num");
            }
            if (!$DB->fieldExists($table, 'raz_plugin_fields')) {
                $migration->addField($table, 'raz_plugin_fields', "bool");
            }

            if (!$DB->fieldExists($table, 'raz_antivirus')) {
                $migration->addField($table, 'raz_antivirus', "bool");
            }

           // 2.7.2
            if (!$DB->fieldExists($table, 'groups_action')) {
                $migration->addField($table, 'groups_action', "varchar(10) NOT NULL DEFAULT 'set'");
                $migration->addPostQuery(
                    $DB->buildUpdate(
                        $table,
                        ['groups_action' => 'set'],
                        ['NOT' => ['groups_id' => '-1']]
                    )
                );
                $migration->addPostQuery(
                    $DB->buildUpdate(
                        $table,
                        ['groups_action' => 'old', 'groups_id' => '0'],
                        ['groups_id' => '-1']
                    )
                );
            }

            if (!$DB->fieldExists($table, 'raz_glpiinventory')) {
                $migration->addField($table, 'raz_glpiinventory', "integer");
            }

            // from 2.9.1 to 2.10.0
            if (!$DB->fieldExists($table, 'action_plugin_fields')) {
                $migration->addField($table, 'action_plugin_fields', "int NOT NULL DEFAULT '".self::PLUGIN_FIELDS_ACTION_NONE."'");
                $migration->addPostQuery(
                    // uninstall with no raz
                    $DB->buildUpdate(
                        $table,
                        ['action_plugin_fields' => '0'],
                        [
                            'raz_plugin_fields' => '0',
                            'types_id' => '1'
                        ]
                    )
                );
                $migration->addPostQuery(
                    // uninstall with raz
                    $DB->buildUpdate(
                        $table,
                        ['action_plugin_fields' => '1'],
                        [
                            'raz_plugin_fields' => '1',
                            'types_id' => '1'
                        ]
                    )
                );
                $migration->addPostQuery(
                    // replace default value
                    $DB->buildUpdate(
                        $table,
                        ['action_plugin_fields' => '0'],
                        ['types_id' => '2']
                    )
                );
            }
        } else {
           // plugin never installed
            $query = "CREATE TABLE IF NOT EXISTS `" . getTableForItemType(__CLASS__) . "` (
                    `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                    `entities_id` int {$default_key_sign} DEFAULT '0',
                    `is_recursive` tinyint NOT NULL DEFAULT '1',
                    `name` varchar(255) NOT NULL DEFAULT '',
                    `transfers_id` int {$default_key_sign} NOT NULL,
                    `states_id` int {$default_key_sign} NOT NULL,
                    `raz_name` int NOT NULL DEFAULT '1',
                    `raz_contact` int NOT NULL DEFAULT '1',
                    `raz_contact_num` int NOT NULL DEFAULT '1',
                    `raz_ip` int NOT NULL DEFAULT '1',
                    `raz_os` int NOT NULL DEFAULT '1',
                    `raz_domain` int NOT NULL DEFAULT '1',
                    `raz_network` int NOT NULL DEFAULT '1',
                    `raz_history` int NOT NULL DEFAULT '1',
                    `raz_soft_history` int NOT NULL DEFAULT '1',
                    `raz_budget` int NOT NULL DEFAULT '1',
                    `raz_antivirus` int NOT NULL DEFAULT '1',
                    `raz_user` int NOT NULL DEFAULT '1',
                    `raz_ocs_registrykeys` int NOT NULL DEFAULT '1',
                    `comment` text NOT NULL,
                    `groups_action` varchar(10) NOT NULL DEFAULT 'set',
                    `groups_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                    `remove_from_ocs` int NOT NULL DEFAULT '0',
                    `delete_ocs_link` int NOT NULL DEFAULT '0',
                    `types_id` int {$default_key_sign} NOT NULL default '0',
                    `replace_name` tinyint NOT NULL DEFAULT '0',
                    `replace_serial` tinyint NOT NULL DEFAULT '0',
                    `replace_otherserial` tinyint NOT NULL DEFAULT '0',
                    `replace_documents` tinyint NOT NULL DEFAULT '0',
                    `replace_contracts` tinyint NOT NULL DEFAULT '0',
                    `replace_infocoms` tinyint NOT NULL DEFAULT '0',
                    `replace_reservations` tinyint NOT NULL DEFAULT '0',
                    `replace_users` tinyint NOT NULL DEFAULT '0',
                    `replace_groups` tinyint NOT NULL DEFAULT '0',
                    `replace_tickets` tinyint NOT NULL DEFAULT '0',
                    `replace_netports` tinyint NOT NULL DEFAULT '0',
                    `replace_direct_connections` tinyint NOT NULL DEFAULT '0',
                    `overwrite` tinyint NOT NULL DEFAULT '0',
                    `replace_method` int NOT NULL DEFAULT '2',
                    `raz_glpiinventory` int NOT NULL DEFAULT '1',
                    `raz_fusioninventory` int NOT NULL DEFAULT '1',
                    `action_plugin_fields` int NOT NULL DEFAULT '0',
                    `replace_contact` tinyint NOT NULL DEFAULT '0',
                    `replace_contact_num` tinyint NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->queryOrDie($query, $DB->error());

            self::createTransferModel('Uninstall');
            self::createTransferModel('Replace');
        }
        return true;
    }


    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `" . getTableForItemType(__CLASS__) . "`");

       //If a transfer model exists for this plugin -> delete it
        $transfer_id     = PluginUninstallUninstall::getUninstallTransferModelID(false);
        if ($transfer_id) {
            $tr = new Transfer();
            $tr->delete(['id' => $transfer_id], true);
        }

       //Delete history
        $log = new Log();
        $log->dohistory = false;
        $log->deleteByCriteria(['itemtype' => __CLASS__]);
    }


   /**
    * @param $name   (default 'Uninstall')
   **/
    public static function createTransferModel($name = 'Uninstall')
    {

        $transfers_id = PluginUninstallUninstall::getUninstallTransferModelID();

        if (!countElementsInTable('glpi_plugin_uninstall_models', ['name' => $name])) {
            $model = new self();
            $tmp['entities_id']                = 0;
            $tmp['is_recursive']               = 1;
            $tmp['name']                       = $name;
            $tmp['transfers_id']               = $transfers_id;
            $tmp['states_id']                  = 0;
            $tmp['raz_name']                   = 1;
            $tmp['raz_contact']                = 1;
            $tmp['raz_contact_num']            = 1;
            $tmp['raz_ip']                     = 1;
            $tmp['raz_os']                     = 1;
            $tmp['raz_domain']                 = 1;
            $tmp['raz_network']                = 1;
            $tmp['raz_soft_history']           = 1;
            $tmp['raz_budget']                 = 1;
            $tmp['raz_user']                   = 1;
            $tmp['raz_ocs_registrykeys']       = 1;
            $tmp['raz_glpiinventory']          = 1;
            $tmp['raz_fusioninventory']        = 1;
            $tmp['action_plugin_fields']          = 1;
            $tmp['comment']                    = '';
            $tmp['groups_action']              = 'set';
            $tmp['groups_id']                  = 0;
            $tmp['remove_from_ocs']            = 0;
            $tmp['delete_ocs_link']            = 0;
            if ($name == 'Uninstall') {
                $tmp['types_id']                = self::TYPE_MODEL_UNINSTALL;
            } else {
                $tmp['types_id']                = self::TYPE_MODEL_REPLACEMENT;
            }
            $tmp['replace_name']               = 1;
            $tmp['replace_serial']             = 1;
            $tmp['replace_otherserial']        = 1;
            $tmp['replace_documents']          = 1;
            $tmp['replace_contracts']          = 1;
            $tmp['replace_infocoms']           = 1;
            $tmp['replace_reservations']       = 1;
            $tmp['replace_users']              = 1;
            $tmp['replace_contact']            = 1;
            $tmp['replace_contact_num']        = 1;
            $tmp['replace_groups']             = 1;
            $tmp['replace_tickets']            = 1;
            $tmp['replace_netports']           = 1;
            $tmp['replace_direct_connections'] = 1;
            $tmp['overwrite']                  = 0;
            $tmp['replace_method']             = PluginUninstallReplace::METHOD_DELETE_AND_COMMENT;
            $model->add($tmp);
        }
    }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
    **/
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'transfert':
                Entity::dropdown();
                echo "&nbsp;" .
                  Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;
        }
        return "";
    }

    public function getSpecificMassiveActions($checkitem = null)
    {

        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin) {
            if (
                Session::haveRight('transfer', READ)
                && Session::isMultiEntitiesMode()
            ) {
                $actions['PluginUninstallModel:transfert'] = __('Transfer');
            }
        }

        return $actions;
    }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    **/
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case "transfert":
                $input = $ma->getInput();
                $entities_id = $input['entities_id'];

                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        $item->update([
                            "id" => $id,
                            "entities_id" => $entities_id,
                            "update" => __('Update'),
                        ]);
                         $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    }
                }
                return;
               break;
        }
        return;
    }

    public static function getIcon()
    {
        return "fas fa-recycle";
    }

    /**
     * Create all non-existing relations between plugin fields containers and a model
     * @param $modelId int
     */
    public function createPluginFieldsRelations($modelId) {
        global $DB;
        if ($DB->tableExists('glpi_plugin_fields_containers')) {
            $uninstallContainer = new PluginUninstallModelcontainer();
            $uninstallContainers = $uninstallContainer->find(['plugin_uninstall_models_id' => $modelId]);
            $existingContainersIds = array_map(fn($e) => $e['plugin_fields_containers_id'], $uninstallContainers);

            $fieldsContainer = new PluginFieldsContainer();
            $condition = count($existingContainersIds) ? ['NOT' => [
                'id' => $existingContainersIds
            ]] : [];
            $fieldsContainers = $fieldsContainer->find($condition);

            $fieldsField = new PluginFieldsField();
            $uninstallField = new PluginUninstallModelcontainerfield();

            foreach($fieldsContainers as $container) {
                $newId = $uninstallContainer->add([
                    'plugin_uninstall_models_id' => $modelId,
                    'plugin_fields_containers_id' => $container['id']
                ]);

                $fieldsFields = $fieldsField->find(['plugin_fields_containers_id' => $container['id']]);
                foreach($fieldsFields as $field) {
                    $uninstallField->add([
                        'plugin_fields_fields_id' => $field['id'],
                        'plugin_uninstall_modelcontainers_id' => $newId,
                        'action' => $uninstallField::ACTION_RAZ
                    ]);
                }
            }
        }
    }
}
