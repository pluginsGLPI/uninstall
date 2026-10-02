/*!
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

/* global $, glpi_html_dialog */

/**
 * Open the uninstall/replace actions modal.
 *
 * The opener buttons/links are rendered by the Twig templates with a
 * data-uninstall-target attribute pointing at an inert <template> element that
 * holds the form. Binding is delegated on the document because the "Lifecycle"
 * tab (and its links) can be injected asynchronously.
 */
$(document).on('click', '.plugin-uninstall-open-model', function (event) {
    event.preventDefault();

    const template = document.getElementById(this.dataset.uninstallTarget);
    if (template) {
        glpi_html_dialog({ body: template.innerHTML });
    }
});

/**
 * When the plugin replaces the native status dropdown, the replacement markup is
 * emitted at the end of the item form (post_item_form hook). On ready we move it
 * into the status field cell so it visually takes the select's place, keeping the
 * modal <template> in the DOM so the delegated opener above can still find it.
 */
$(function () {
    document.querySelectorAll('.plugin-uninstall-state-replace').forEach(function (holder) {
        if (holder.dataset.uninstallApplied) {
            return;
        }

        const select = document.querySelector('#page select[name="states_id"]');
        if (!select || !select.parentElement) {
            return;
        }

        holder.dataset.uninstallApplied = '1';
        const parent = select.parentElement;
        parent.replaceChildren();
        while (holder.firstChild) {
            parent.appendChild(holder.firstChild);
        }
        holder.remove();
    });
});
