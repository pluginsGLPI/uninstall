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

namespace GlpiPlugin\Uninstall\Tests\Units;

use Computer;
use Glpi\Tests\DbTestCase;
use PluginUninstallModel;
use PluginUninstallProfile;
use PluginUninstallUninstall;
use Software;
use Transfer;

class PluginUninstallUninstallLinksTest extends DbTestCase
{
    private function createModel(string $name, array $override = []): PluginUninstallModel
    {
        $transfer = $this->createItem(Transfer::class, ['name' => 'Transfer ' . $name]);

        return $this->createItem(PluginUninstallModel::class, array_merge([
            'name'         => $name,
            'types_id'     => PluginUninstallModel::TYPE_MODEL_UNINSTALL,
            'transfers_id' => $transfer->getID(),
            'states_id'    => 0,
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 0,
            'comment'      => '',
        ], $override));
    }

    private function createComputer(string $entity_name = '_test_root_entity'): Computer
    {
        return $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => getItemByTypeName('Entity', $entity_name, true),
        ]);
    }

    /**
     * The right is set in the session: the CLI install grants no plugin right to the
     * profile, unlike a local base that kept them from earlier installs.
     */
    private function setUninstallRight(int $right): void
    {
        $_SESSION['glpiactiveprofile'][PluginUninstallUninstall::$rightname] = $right;
    }

    private function renderLinks($item): string
    {
        ob_start();
        $result = PluginUninstallUninstall::showLinksUninstallation(['item' => $item]);
        $html = ob_get_clean();

        $this->assertNull($result);

        return $html;
    }

    /**
     * @return array<int, string> model id => model name, of the links rendered
     */
    private function getRenderedLinks(string $html): array
    {
        preg_match_all(
            '/data-uninstall-target="plugin_uninstall_model_(\d+)">([^<]*)<\/button>/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $links = [];
        foreach ($matches as $match) {
            $links[(int) $match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
        }

        return $links;
    }

    public function testNothingIsRenderedWithoutTheReadRight(): void
    {
        $this->login();
        $this->createModel('Model without right');
        $computer = $this->createComputer();

        $this->setUninstallRight(0);

        $this->assertSame('', $this->renderLinks($computer));
    }

    public function testNothingIsRenderedForAnUnsupportedItem(): void
    {
        $this->login();
        $this->createModel('Model of unsupported item');
        $this->setUninstallRight(READ | PluginUninstallProfile::RIGHT_REPLACE);

        $software = $this->createItem(Software::class, [
            'name'        => 'Software ' . $this->getUniqueString(),
            'entities_id' => $this->getTestRootEntity(true),
        ]);
        $this->assertSame('', $this->renderLinks($software));

        // The creation form of a supported item has no link either
        $this->assertSame('', $this->renderLinks(new Computer()));
    }

    public function testOnlyTheModelsVisibleFromTheEntityOfTheItemAreLinked(): void
    {
        $this->login();
        $this->setUninstallRight(READ | PluginUninstallProfile::RIGHT_REPLACE);

        $same_entity = $this->createModel('Same entity', [
            'entities_id' => getItemByTypeName('Entity', '_test_child_1', true),
        ]);
        $recursive_parent = $this->createModel('Recursive parent', ['is_recursive' => 1]);
        $other_entity = $this->createModel('Other entity', [
            'entities_id' => getItemByTypeName('Entity', '_test_child_2', true),
        ]);
        $not_recursive_parent = $this->createModel('Not recursive parent');

        $links = $this->getRenderedLinks($this->renderLinks($this->createComputer('_test_child_1')));

        $this->assertArrayHasKey($same_entity->getID(), $links);
        $this->assertArrayHasKey($recursive_parent->getID(), $links);
        $this->assertArrayNotHasKey($other_entity->getID(), $links);
        $this->assertArrayNotHasKey($not_recursive_parent->getID(), $links);
    }

    public function testReplacementModelsNeedTheReplaceRight(): void
    {
        $this->login();
        $uninstall = $this->createModel('Uninstall model');
        $replacement = $this->createModel('Replacement model', [
            'types_id' => PluginUninstallModel::TYPE_MODEL_REPLACEMENT,
        ]);
        $replacement_uninstall = $this->createModel('Replacement uninstall model', [
            'types_id' => PluginUninstallModel::TYPE_MODEL_REPLACEMENT_UNINSTALL,
        ]);
        $computer = $this->createComputer();

        $this->setUninstallRight(READ);
        $links = $this->getRenderedLinks($this->renderLinks($computer));
        $this->assertArrayHasKey($uninstall->getID(), $links);
        $this->assertArrayNotHasKey($replacement->getID(), $links);
        $this->assertArrayNotHasKey($replacement_uninstall->getID(), $links);

        $this->setUninstallRight(READ | PluginUninstallProfile::RIGHT_REPLACE);
        $links = $this->getRenderedLinks($this->renderLinks($computer));
        $this->assertArrayHasKey($uninstall->getID(), $links);
        $this->assertArrayHasKey($replacement->getID(), $links);
        $this->assertArrayHasKey($replacement_uninstall->getID(), $links);
    }

    public function testEachLinkCarriesTheFormOfItsModel(): void
    {
        $this->login();
        $this->setUninstallRight(READ | PluginUninstallProfile::RIGHT_REPLACE);
        $model = $this->createModel('<b>Escaped</b> model');
        $computer = $this->createComputer();

        $html = $this->renderLinks($computer);

        // The name comes from the database: escaped, never rendered as markup
        $this->assertStringNotContainsString('<b>Escaped</b>', $html);
        $this->assertSame('<b>Escaped</b> model', $this->getRenderedLinks($html)[$model->getID()]);

        // The form of the model is shipped inert, in a template the button opens
        $this->assertMatchesRegularExpression(
            '/<template id="plugin_uninstall_model_' . $model->getID() . '">(.*?)<\/template>/s',
            $html,
        );
        preg_match('/<template id="plugin_uninstall_model_' . $model->getID() . '">(.*?)<\/template>/s', $html, $template);
        $form = $template[1];
        $this->assertStringContainsString('/plugins/uninstall/front/action.php', $form);
        $this->assertStringContainsString('name="device_type" value="Computer"', $form);
        $this->assertStringContainsString('name="id" value="' . $computer->getID() . '"', $form);
        $this->assertMatchesRegularExpression('/<option value="' . $model->getID() . '" selected/', $form);
    }
}
