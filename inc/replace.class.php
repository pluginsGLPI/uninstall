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


use Glpi\Asset\Asset_PeripheralAsset;

use function Safe\fclose;
use function Safe\fopen;
use function Safe\fwrite;

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

class PluginUninstallReplace extends CommonDBTM
{
    public const METHOD_PURGE              = 1;

    public const METHOD_DELETE_AND_COMMENT = 2;

    public const METHOD_KEEP_AND_COMMENT   = 3;

    public static $rightname = "uninstall:profile";

    public static function getTypeName($nb = 0)
    {
        return __s("Item's replacement", 'uninstall');
    }


    /**
     * @param $type
     * @param $model_id
     * @param $tab_ids
     * @param $location
    **/
    public static function replace($type, $model_id, $tab_ids, $location)
    {
        /**
         * @var array $CFG_GLPI
         * @var array $PLUGIN_HOOKS
         */
        global $CFG_GLPI, $PLUGIN_HOOKS;

        $plug = new Plugin();

        $model = new PluginUninstallModel();
        $model->getConfig($model_id);

        $overwrite = $model->fields["overwrite"];

        echo "<div class='center'>";
        echo "<table class='tab_cadre_fixe'><tr><th>" . __s('Replacement', 'uninstall') . "</th></tr>";
        echo "<tr class='tab_bg_2'><td>";
        $count = 0;
        $tot   = count($tab_ids);

        foreach ($tab_ids as $olditem_id => $newitem_id) {
            $count++;

            if (!class_exists($type) || !is_a($type, CommonDBTM::class, true)) {
                continue;
            }

            $olditem = new $type();
            $olditem->getFromDB($olditem_id);

            $newitem = new $type();
            $newitem->getFromDB($newitem_id);

            //Hook to perform actions before item is being replaced
            $olditem->fields['_newid'] = $newitem_id;
            $olditem->fields['_uninstall_event'] = $model_id;
            $olditem->fields['_action'] = 'replace';
            Plugin::doHook("plugin_uninstall_replace_before", $olditem);

            // Retrieve information

            //States
            if ($model->fields['states_id'] != 0) {
                $olditem->update(
                    [
                        'id'           => $olditem_id,
                        'is_dynamic'   => $olditem->getField('is_dynamic'), #to prevent locked field
                        'states_id'    => $model->fields['states_id'],
                    ],
                    false,
                );
            }

            // METHOD REPLACEMENT 1 : Archive
            if ($model->fields['replace_method'] == self::METHOD_PURGE) {
                $name_out = str_shuffle(Toolbox::getRandomString(5) . time());

                if ($plug->isActivated('PDF')) {
                    // USE PDF EXPORT
                    $plug->load('pdf', true);

                    $tab = self::getPdfUserPreference($olditem);

                    $out = "";
                    if (class_exists(PluginPdfCommon::class) && is_a($PLUGIN_HOOKS['plugin_pdf'][$type], PluginPdfCommon::class, true)) {
                        $itempdf = new $PLUGIN_HOOKS['plugin_pdf'][$type]($olditem);
                        $out = $itempdf->generatePDF([$olditem_id], $tab, 1, false);
                    }

                    $name_out .= ".pdf";

                } else {
                    //TODO Which datas ? Add Defaults...
                    $out = __s('Replacement', 'uninstall') . "\r\n";

                    $datas = $olditem->fields;
                    // hack for phpstan to avoid error
                    $datas['comment'] = '';
                    unset($datas['comment']);
                    foreach ($datas as $k => $v) {
                        $out .= $k . ";";
                    }

                    $out .= "\r\n";
                    foreach ($datas as $v) {
                        $out .= $v . ";";
                    }

                    // USE CSV EXPORT
                    $name_out .= ".csv";
                }

                // Write document
                $out_file  = GLPI_UPLOAD_DIR . "/" . $name_out;
                $open_file = fopen($out_file, 'a');
                fwrite($open_file, $out);
                fclose($open_file);
                // Compute comment text
                $comment  = __s('This document is the archive of this replaced item', 'uninstall') . " "
                        . self::getCommentsForReplacement($olditem, false, false);

                // Create & Attach new document to current item
                $doc   = new Document();
                $input = ['name'                  => addslashes(__s('Archive of old material', 'uninstall')),
                    'upload_file'           => $name_out,
                    'comment'               => addslashes($comment),
                    'add'                   => __s('Add'),
                    'entities_id'           => $newitem->getEntityID(),
                    'is_recursive'          => $newitem->isRecursive(),
                    'link'                  => "",
                    'documentcategories_id' => 0,
                    'items_id'              => $olditem_id,
                    'itemtype'              => $type,
                ];

                //Attached the document to the old item, to generate an accurate name
                $document_added = $doc->add($input);

                //Attach the document to the new item, once the document's name is correct

                $docItem = new Document_Item();
                $docItemId = $docItem->add([
                    'documents_id' => $document_added,
                    'itemtype'     => $type,
                    'items_id'     => (int) $newitem_id,
                ]);
            }

            // General Information - NAME
            if ($model->fields["replace_name"] && ($overwrite || empty($newitem->fields['name']))) {
                $newitem->update(
                    ['id'  => $newitem_id,
                        'name' => $olditem->getField('name'),
                    ],
                    false,
                );
            }

            $data['id'] = $newitem->getID();
            // General Informations - SERIAL
            if ($model->fields["replace_serial"] && ($overwrite || empty($newitem->fields['serial']))) {
                $newitem->update(
                    ['id'     => $newitem_id,
                        'serial' => $olditem->getField('serial'),
                    ],
                    false,
                );
            }

            // General Informations - OTHERSERIAL
            if ($model->fields["replace_otherserial"] && ($overwrite || empty($newitem->fields['otherserial']))) {
                $newitem->update(
                    ['id'          => $newitem_id,
                        'otherserial' => $olditem->getField('otherserial'),
                    ],
                    false,
                );
            }

            // Documents
            if (
                $model->fields["replace_documents"]
                && in_array($type, $CFG_GLPI["document_types"])
            ) {
                $doc_item = new Document_Item();
                foreach (self::getAssociatedDocuments($olditem) as $document) {
                    $existing = $doc_item->find([
                        'documents_id' => $document['assocID'],
                        'itemtype'     => $type,
                        'items_id'     => $newitem_id,
                    ]);
                    if (empty($existing)) {
                        $doc_item->update(
                            [
                                'id'       => $document['assocID'],
                                'itemtype' => $type,
                                'items_id' => $newitem_id,
                            ],
                            false,
                        );
                    } else {
                        $doc_item->deleteByCriteria([
                            'documents_id' => $document['assocID'],
                            'itemtype'     => $type,
                            'items_id'     => $olditem->getID(),
                        ]);
                    }
                }
            }

            // Contracts
            if (
                $model->fields["replace_contracts"]
                && in_array($type, $CFG_GLPI["contract_types"])
            ) {
                $contract_item = new Contract_Item();
                foreach (self::getAssociatedContracts($olditem) as $contract) {
                    $contract_item->update(
                        [
                            'id'       => $contract['id'],
                            'itemtype' => $type,
                            'items_id' => $newitem_id,
                        ],
                        false,
                    );
                }
            }

            // Infocoms
            if (
                $model->fields["replace_infocoms"]
                && in_array($type, $CFG_GLPI["infocom_types"])
            ) {
                $infocom = new Infocom();
                // Delete current Infocoms of new item
                if ($overwrite && $infocom->getFromDBforDevice($type, $newitem_id)) {
                    //Do not log infocom deletion in the new item's history
                    $infocom->dohistory = false;
                    $infocom->deleteFromDB(true);
                }

                // Checks that the itemtype/items_id key doesn't already exist to avoid duplication
                // Update current Infocoms of old item
                if (!$infocom->getFromDBforDevice($type, $newitem_id) && $infocom->getFromDBforDevice($type, $olditem_id)) {
                    $infocom->update(
                        [
                            'id'       => $infocom->getID(),
                            'itemtype' => $type,
                            'items_id' => $newitem_id,
                        ],
                        false,
                    );
                }
            }

            // Reservations
            if (
                $model->fields["replace_reservations"]
                && in_array($type, $CFG_GLPI["reservation_types"])
            ) {
                $resaitem = new ReservationItem();
                if ($overwrite) {
                    // Delete current reservation of new item
                    $resa_new = new Reservation();
                    $resa_new->getFromDB($newitem_id);

                    if ($resa_new->is_reserved()) {
                        $resa_new = new ReservationItem();
                        $resa_new->getFromDBbyItem($type, $newitem_id);

                        if (count($resa_new->fields)) {
                            $resa_new->deleteFromDB(true);
                        }
                    }
                }

                // Update old reservation for attribute to new item
                $resa_old = new Reservation();
                $resa_old->getFromDB($olditem_id);

                if ($resa_old->is_reserved()) {
                    $resa_old = new ReservationItem();
                    $resa_old->getFromDBbyItem($type, $olditem_id);

                    if (count($resa_old->fields)) {
                        $resa_old->update(
                            ['id'       => $resa_old->getID(),
                                'itemtype' => $type,
                                'items_id' => $newitem_id,
                            ],
                            false,
                        );
                    }
                }
            }

            // User
            if (in_array($type, $CFG_GLPI["linkuser_types"])) {
                $data        = [];
                $data['id']  = $newitem->getID();

                if (
                    $model->fields["replace_users"]
                    && $newitem->isField('users_id')
                    && ($overwrite || empty($newitem->getField('users_id')))
                ) {
                    $data['users_id'] = $olditem->getField('users_id');
                }

                if (
                    $model->fields["replace_contact"]
                    && $newitem->isField('contact')
                    && ($overwrite || empty($newitem->getField('contact')))
                ) {
                    $data['contact'] = $olditem->getField('contact');
                }

                if (
                    $model->fields["replace_contact_num"]
                    && $newitem->isField('contact_num')
                    && ($overwrite || empty($newitem->getField('contact_num')))
                ) {
                    $data['contact_num'] = $olditem->getField('contact_num');
                }

                $newitem->update($data, false);
            }

            // Group
            if (
                $model->fields["replace_groups"] && in_array($type, $CFG_GLPI["linkgroup_types"]) && ($newitem->isField('groups_id') && ($overwrite || empty($newitem->fields['groups_id'])))
            ) {
                $newitem->update(
                    ['id'        => $newitem_id,
                        'groups_id' => $olditem->getField('groups_id'),
                    ],
                    false,
                );
            }

            // Tickets
            if (
                $model->fields["replace_tickets"]
                && in_array($type, $CFG_GLPI["ticket_types"])
            ) {
                $ticket_item = new Item_Ticket();
                foreach (self::getAssociatedTickets($type, $olditem_id) as $ticket) {
                    $ticket_item->update(
                        ['id'       => $ticket['id'],
                            'items_id' => $newitem_id,
                        ],
                        false,
                    );
                }
            }

            $networkport_types = $CFG_GLPI["networkport_types"];
            // NetPorts
            if (
                $model->fields["replace_netports"]
                && in_array($type, $networkport_types)
            ) {
                $netport_item = new NetworkPort();
                foreach (self::getAssociatedNetports($type, $olditem_id) as $netport) {
                    $netport_item->update(
                        ['id'       => $netport['id'],
                            'itemtype' => $type,
                            'items_id' => $newitem_id,
                        ],
                        false,
                    );
                }
            }

            // Directs connections
            if (
                $model->fields["replace_direct_connections"]
                && ($type === 'Computer')
                && $newitem_id
            ) { #do not update computer_item if no computer
                $comp_item = new Asset_PeripheralAsset();
                if ($olditem instanceof Computer) {
                    foreach (self::getAssociatedItems($olditem) as $itemtype => $connections) {
                        foreach ($connections as $connection) {
                            $comp_item->update(
                                ['id'           => $connection['id'],
                                    'computers_id' => $newitem_id,
                                    'itemtype'     => $itemtype,
                                ],
                                false,
                            );
                        }
                    }
                }

            }

            // Location
            if ((int) $location != 0 && $olditem->isField('locations_id')) {
                $olditem->getFromDB($olditem_id);
                switch ($location) {
                    case -1:
                        break;

                    default:
                        $olditem->update(
                            ['id'           => $olditem_id,
                                'is_dynamic'   => $olditem->getField('is_dynamic'), #to prevent locked field
                                'locations_id' => (int) $location,
                            ],
                            false,
                        );
                        break;
                }
            }

            if ($plug->isActivated('ocsinventoryng')) {
                //Delete computer from OCS
                if ($model->fields["remove_from_ocs"] == 1) {
                    PluginUninstallUninstall::deleteComputerInOCSByGlpiID($olditem_id);
                }

                //Delete link in glpi_ocs_link
                if ($model->fields["delete_ocs_link"] || $model->fields["remove_from_ocs"]) {
                    PluginUninstallUninstall::deleteOcsLink($olditem_id);
                }
            }

            if ($plug->isActivated('fusioninventory') && $model->fields['raz_fusioninventory']) {
                PluginUninstallUninstall::deleteFusionInventoryLink($olditem::class, $olditem_id);
            }

            // METHOD REPLACEMENT 1 : Purge
            switch ($model->fields['replace_method']) {
                case self::METHOD_PURGE:
                    // Retrieve, Compute && Update NEW comment field
                    $comment = empty($newitem->fields['comment']) ? "" : stripslashes((string) $newitem->fields['comment']);

                    $comment .= self::getCommentsForReplacement($olditem, true);
                    $comment .= "\n- " . __s('See attached document', 'uninstall');
                    $newitem->update(
                        ['id'      => $newitem_id,
                            'comment' => addslashes($comment),
                        ],
                        false,
                    );

                    // If old item is attached in PDF/CSV
                    // Delete AND Purge it in DB
                    if (isset($document_added) && $document_added) {
                        $olditem->delete(['id' => $olditem_id], true);
                    }

                    break;

                case self::METHOD_DELETE_AND_COMMENT:
                case self::METHOD_KEEP_AND_COMMENT:
                    // Retrieve && Compute comment for newitem (with olditem)
                    $commentnew = empty($newitem->fields['comment']) ? "" : stripslashes((string) $newitem->fields['comment']);

                    $commentnew .= self::getCommentsForReplacement($olditem, true);
                    // Retrieve && Compute comment for olditem (with newitem)
                    $commentold = empty($olditem->getField('comment')) ? "" : stripslashes((string) $olditem->getField('comment'));

                    $commentold .= self::getCommentsForReplacement($newitem, false);

                    // Update comment for newitem
                    $newitem->update(
                        ['id'      => $newitem_id,
                            'comment' => $commentnew,
                        ],
                        false,
                    );

                    // Update comment for olditem
                    $olditem->update(
                        ['id'           => $olditem_id,
                            'is_dynamic'   => $olditem->getField('is_dynamic'), #to prevent locked field
                            'comment'      => $commentold,
                        ],
                        false,
                    );

                    // Delete OLD item from DB (not PURGE) only if delete is requested
                    PluginUninstallUninstall::addUninstallLog([
                        'itemtype'  => $type,
                        'items_id'  => $olditem_id,
                        'action'    => 'replaced_by',
                        'models_id' => $model_id,
                    ]);
                    if ($model->fields['replace_method'] == self::METHOD_DELETE_AND_COMMENT) {
                        $olditem->delete(['id' => $olditem_id], false, false);
                    }

                    break;
            }

            //Plugin hook after replacement
            Plugin::doHook("plugin_uninstall_replace_after", $olditem);

            //Add history
            PluginUninstallUninstall::addUninstallLog([
                'itemtype'  => $type,
                'items_id'  => $newitem_id,
                'action'    => 'replace',
                'models_id' => $model_id,
            ]);
            $percent = (int) (($count / $tot) * 100);
            Html::getProgressBar($percent);
        }

        echo "</td></tr>";
        echo "</table></div>";

        if ($model->fields['types_id'] == PluginUninstallModel::TYPE_MODEL_REPLACEMENT_UNINSTALL) {
            $uninstallArray = [];
            foreach ($tab_ids as $olditem_id => $newitem_id) {
                $uninstallArray[$olditem_id] = $olditem_id;
            }

            PluginUninstallUninstall::uninstall(
                $type,
                $model->getID(),
                [$type => $uninstallArray],
                $location,
            );
        }
    }


    /**
     * Build the comments associated with an item
     *
     * @param $item            CommonDBTM object
     * @param $new is          the item a new item (true) or the old one (false) (true by default)
     * @param $display_message (true by default)
     *
     * @return string comments generated
    **/
    public static function getCommentsForReplacement(CommonDBTM $item, $new = true, $display_message = true)
    {

        $string = "";

        if ($display_message) {
            if ($new) {
                $string .= "\n" . __s('This item is a replacement for item', 'uninstall') . " ";
            } else {
                $string .= "\n" . __s('This item was replaced by', 'uninstall') . " ";
            }
        }

        if ($item->isField('id')) {
            $string .= "\n " . sprintf(__s('%1$s: %2$s'), __s('ID'), $item->getField('id'));
        }

        if ($item->isField('name')) {
            $string .= "\n " . sprintf(__s('%1$s: %2$s'), __s('Name'), $item->getField('name'));
        }

        if ($item->isField('serial')) {
            $string .= "\n " . sprintf(__s('%1$s: %2$s'), __s('Serial number'), $item->getField('serial'));
        }

        if ($item->isField('otherserial')) {
            $string .= "\n " . sprintf(
                __s('%1$s: %2$s'),
                __s('Inventory number'),
                $item->getField('otherserial'),
            );
        }

        return $string;
    }


    /**
     * @param $field
    **/
    public static function coloredYN($field)
    {

        return ($field == 1)
               ? "<span class='green b'>" . __s('Yes') . "</span>"
               : "<span class='red b'>" . __s('No') . "</span>";
    }


    public static function showReplacementForm($type, $model_id, $tab_ids, $location)
    {
        /**
         * @var array $CFG_GLPI
         */
        global $CFG_GLPI;

        // Retrieve model information and show details
        // It's just for helping user!
        $model = new PluginUninstallModel();
        $model->getConfig($model_id);

        echo "<div class='first_bloc'>";
        echo "<table class='tab_cadre_fixe' cellpadding='5'>";

        echo "<tr class='tab_bg_1 center'>"
           . "<th colspan='6'>" . sprintf(
               __s('%1$s - %2$s'),
               __s('Reminder of the replacement model', 'uninstall'),
               __s('General informations', 'uninstall'),
           )
           . "</th></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), __s('Name')) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_name"]) . "</td>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), __s('Serial number')) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_serial"]) . "</td>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), __s('Inventory number')) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_otherserial"]) . "</td>";
        echo "</tr>";

        echo "<tr><td colspan='6'></td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td colspan='2'>" . __s('Overwrite informations (from old item to the new)', 'uninstall')
           . "</td>";
        echo "<td>" . self::coloredYN($model->fields["overwrite"]) . "</td>";
        echo "<td colspan='2'>" . __s('Archiving method of the old material', 'uninstall') . "</td>";
        echo "<td>";
        $methods = PluginUninstallModel::getReplacementMethods();
        switch ($model->fields["replace_method"]) {
            case self::METHOD_PURGE:
                echo "<span class='red b'>" . $methods[self::METHOD_PURGE] . "</span>";
                break;
            case self::METHOD_DELETE_AND_COMMENT:
                echo "<span class='green b'>" . $methods[self::METHOD_DELETE_AND_COMMENT] . "</span>";
                break;
            case self::METHOD_KEEP_AND_COMMENT:
                echo "<span class='green b'>" . $methods[self::METHOD_KEEP_AND_COMMENT] . "</span>";
                break;
        }

        echo "</td></tr>";

        echo "<tr><td colspan='6'></td></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td></td><td>" . __s('New location of item', 'uninstall') . "</td>";
        switch ($location) {
            case -1:
                echo "<td><span class='red b'>" . __s('Keep previous location', 'uninstall') . "</span></td>";
                break;

            case 0:
                echo "<td><span class='red b'>" . __s('Empty location', 'uninstall') . "</span></td>";
                break;

            default:
                echo "<td><span class='green b'>";
                echo Dropdown::getDropdownName('glpi_locations', $location);
                echo "</span></td>";
                break;
        }

        echo "<td>" . __s('New status of the computer', 'uninstall') . "</td>";
        echo "<td>";
        if ($model->fields['states_id'] == 0) {
            echo "<span class='red b'>" . __s('Status') . "</span>";
        } else {
            echo "<span class='green b'>";
            echo Dropdown::getDropdownName('glpi_states', $model->fields['states_id']);
            echo "</span>";
        }

        echo "</td>";
        echo "<td></td></tr>";

        echo "</table>";
        echo "</div>";

        // CONNEXIONS with other items
        echo "<div class='firstbloc'>";
        echo "<table class='tab_cadre_fixe' cellpadding='5'>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<th colspan='4'>" . sprintf(
            __s('%1$s - %2$s'),
            __s('Reminder of the replacement model', 'uninstall'),
            __s('Connections with other materials', 'uninstall'),
        )
           . "</th></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), _sn('Document', 'Documents', 2)) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_documents"]) . "</td>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), _sn('Contract', 'Contracts', 2)) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_contracts"]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(
            __s('%1$s %2$s'),
            __s('Copy'),
            __s('Financial and administratives information'),
        ) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_infocoms"]) . "</td>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), _sn('Reservation', 'Reservations', 2)) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_reservations"]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), __s('User')) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_users"]) . "</td>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), __s('Group')) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_groups"]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), _sn('Ticket', 'Tickets', 2)) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_tickets"]) . "</td>";
        echo "<td>" . sprintf(
            __s('%1$s %2$s'),
            __s('Copy'),
            sprintf(
                __s('%1$s %2$s'),
                _sn('Connection', 'Connections', 2),
                _sn('Network', 'Networks', 2),
            ),
        ) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_netports"]) . "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<td>" . sprintf(__s('%1$s %2$s'), __s('Copy'), __s('Direct connections', 'uninstall')) . "</td>";
        echo "<td>" . self::coloredYN($model->fields["replace_direct_connections"]) . "</td>";
        echo "<td colspan='2'></td>";
        echo "</tr></table></div>";

        // Show form for selecting new items

        echo "<form action='../front/action.php' method='post'>";
        echo "<table class='tab_cadre_fixe' cellpadding='5'>";

        echo "<tr class='tab_bg_1 center'>";
        $colspan = count($tab_ids[$type]) > 1 ? 5 : 4;
        echo "<th colspan='" . $colspan . "'>" . __s('Choices for item to replace', 'uninstall') . "</th></tr>";

        echo "<tr class='tab_bg_1 center'>";
        echo "<th>" . __s('Old item', 'uninstall') . "</th>";

        if (Search::getOptionNumber($type, 'otherserial')) {
            echo "<th>" . __s('Inventory number') . "</th>";
        }

        if (Search::getOptionNumber($type, 'serial')) {
            echo "<th>" . __s('Serial number') . "</th>";
        }

        echo "<th>" . __s('New item', 'uninstall') . "</th>";

        if (count($tab_ids[$type]) > 1) {
            echo "<th>" . __s('Remove', 'uninstall') . "</th>";
        }

        echo "</tr>";

        if (class_exists($type) && is_a($type, CommonDBTM::class, true)) {
            $commonitem = new $type();
            foreach ($tab_ids[$type] as $id => $value) {
                $commonitem->getFromDB($id);

                echo "<tr class='tab_bg_1 center'>";
                echo "<td>" . $commonitem->getName() . "</td>";

                if (Search::getOptionNumber($type, 'otherserial')) {
                    echo "<td>" . $commonitem->fields['otherserial'] . "</td>";
                }

                if (Search::getOptionNumber($type, 'serial')) {
                    echo "<td>" . $commonitem->fields['serial'] . "</td>";
                }

                echo "<td>";
                $type::dropdown([
                    'name'        => sprintf('newItems[%s]', $id),
                    'displaywith' => ['serial', 'otherserial'],
                    'url'         => $CFG_GLPI['root_doc'] . "/plugins/uninstall/ajax/dropdownReplaceFindDevice.php",
                    'used'        => array_keys($tab_ids[$type]),
                ]);
                echo "</td>";

                if (count($tab_ids[$type]) > 1) {
                    echo "<td>";
                    $button = "<button type='button' onclick=\"$(this).closest('tr').remove();\" ><i class='ti ti-trash'></i></button>";
                    echo $button;
                    echo "<td>";
                }

                echo"</tr>";
            }
        }


        echo "<tr class='tab_bg_1 center'>";
        echo "<td colspan='4' class='center'>";

        echo "<input type='hidden' name='device_type' value='" . htmlentities((string) $type) . "' />";
        echo "<input type='hidden' name='model_id' value='" . htmlentities((string) $model_id) . "' />";
        echo "<input type='hidden' name='locations_id' value='" . htmlentities((string) $location) . "' />";

        echo "<input type='submit' name='replace' value=\"" . __s('Replace', 'uninstall') . "\"
             class='submit'>";
        echo "</td></tr>";

        echo "</table>";
        Html::closeForm();
    }

    /**
     * Get documents associated to an item
     *
     * @param $item            CommonDBTM object for which associated documents must be displayed
     * @param $withtemplate    (default '')
    **/
    public static function getAssociatedDocuments(CommonDBTM $item, $withtemplate = '')
    {
        /**
         * @var DBmysql $DB
         * @var array   $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        if (
            !(($item instanceof KnowbaseItem)
            && $CFG_GLPI["use_public_faq"]
            && !$item->getEntityID())
        ) {
            if ($item->isNewID($item->getField('id'))) {
                return [];
            }

            switch ($item->getType()) {
                case 'Ticket':
                case 'KnowbaseItem':
                    break;
                default:
                    if (Session::haveRight('document', READ)) {
                        return [];
                    }
            }

            if (!$item->can($item->fields['id'], READ)) {
                return [];
            }
        }

        $criteria = [
            'SELECT' => ['glpi_documents_items.id AS assocID', 'glpi_documents.*'],
            'FROM' => 'glpi_documents_items',
            'LEFT JOIN' => [
                'glpi_documents' => [
                    'ON' => [
                        'glpi_documents_items' => 'documents_id',
                        'glpi_documents' => 'id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_documents' => 'entities_id',
                        'glpi_entities' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_documents_items.items_id' => $item->getField('id'),
                'glpi_documents_items.itemtype' => $item->getType(),
            ],
        ];

        if (Session::getLoginUserID()) {
            $criteria['WHERE'][] = getEntitiesRestrictCriteria('glpi_documents', '', '', true);
        } else {
            $criteria['WHERE']['glpi_documents.entities_id'] = 0;
        }

        $docs = [];
        $it = $DB->request($criteria);
        foreach ($it as $data) {
            $docs[] = $data;
        }

        return $docs;
    }


    /**
     * Get contracts associated to an item
     *
     * @param $item            CommonDBTM : object wanted
     *
     * @return array
    **/
    public static function getAssociatedContracts(CommonDBTM $item)
    {
        /** @var DBmysql $DB */
        global $DB;

        $contracts = [];

        if (!Session::haveRight("contract", READ) || !$item->can($item->fields['id'], READ)) {
            return [];
        }

        $criteria = [
            'SELECT' => ['glpi_contracts_items.*'],
            'FROM' => 'glpi_contracts_items',
            'LEFT JOIN' => [
                'glpi_contracts' => [
                    'ON' => [
                        'glpi_contracts_items' => 'contracts_id',
                        'glpi_contracts' => 'id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_contracts' => 'entities_id',
                        'glpi_entities' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_contracts_items.items_id' => $item->getField('id'),
                'glpi_contracts_items.itemtype' => $item->getType(),
            ] + getEntitiesRestrictCriteria('glpi_contracts', '', '', true),
        ];
        $it = $DB->request($criteria);
        foreach ($it as $data) {
            $contracts[] = $data;
        }

        return $contracts;
    }


    /**
    * Get tickets for an item
     *
     * @param $itemtype
     * @param $items_id
     *
     * @return array
    **/
    public static function getAssociatedTickets($itemtype, $items_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        if (
            !Session::haveRight("ticket", Ticket::READALL)
            || !($item = getItemForItemtype($itemtype))
        ) {
            return [];
        }

        if (!$item->getFromDB($items_id)) {
            return [];
        }

        $tickets = [];
        $it = $DB->request([
            'FROM' => 'glpi_items_tickets',
            'JOIN'  => [
                'glpi_tickets' => [
                    'ON' => [
                        'glpi_tickets' => 'id',
                        'glpi_items_tickets' => 'tickets_id',
                    ],
                ],
            ],
            'WHERE' => [
                'itemtype' => $itemtype,
                'items_id' => $items_id,
            ] + getEntitiesRestrictCriteria('glpi_tickets'),
        ]);
        foreach ($it as $data) {
            $tickets[] = $data;
        }

        return $tickets;
    }


    /**
     * Get ports for an item
     *
     * @param $itemtype     integer  item type
     * @param $ID           integer  item ID
    **/
    public static function getAssociatedNetports($itemtype, $ID)
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!($item = getItemForItemtype($itemtype))) {
            return false;
        }

        if (!Session::haveRight('networking', READ) || !$item->can($ID, READ)) {
            return false;
        }

        $netports = [];
        $it = $DB->request([
            'FROM' => 'glpi_networkports',
            'WHERE' => [
                'itemtype' => $itemtype,
                'items_id' => $ID,
            ],
        ]);
        foreach ($it as $data) {
            $netports[] = $data;
        }

        return $netports;
    }


    /**
     * Get local connection for computer
     *
     * @param $comp Computer object
     *
     * @return array
    **/
    public static function getAssociatedItems(Computer $comp)
    {
        /** @var array $UNINSTALL_DIRECT_CONNECTIONS_TYPE */
        global $UNINSTALL_DIRECT_CONNECTIONS_TYPE;

        $ID = $comp->fields['id'];

        $data  = [];
        foreach ($UNINSTALL_DIRECT_CONNECTIONS_TYPE as $itemtype) {
            if (!class_exists($itemtype)  || !is_a($itemtype, CommonDBTM::class, true)) {
                continue;
            }

            $item = new $itemtype();
            if ($item->canView()) {
                $datas = getAllDataFromTable(
                    'glpi_assets_assets_peripheralassets',
                    ['itemtype_asset' => Computer::class, 'items_id_asset' => $ID, 'itemtype_peripheral' => $itemtype],
                );
                foreach ($datas as $computer_item) {
                    $data[$itemtype][] = $computer_item;
                }
            }
        }

        return $data;
    }

    /**
    * Get tabs to export in PDF, as defined in user's preferences
    * @param $itemtype itemtype to export in PDF
    * @return array of tabs to export
    */
    public static function getPdfUserPreference($item)
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_pdf_preferences',
            'WHERE' => [
                'users_ID' => $_SESSION['glpiID'],
                'itemtype' => $item->getType(),
            ],
        ]);
        if (count($iterator) === 0) {
            //Get all item's tabs
            $tab = array_keys($item->defineTabs());

            //Tell PDF to also export item's main tab, and in first position
            array_unshift($tab, "_main_");

            return $tab;
        } else {
            $tabs = [];
            foreach ($iterator as $data) {
                $tabs[] = $data['tabref'];
            }

            return $tabs;
        }
    }

    public static function getIcon()
    {
        return "ti ti-recycle";
    }
}
