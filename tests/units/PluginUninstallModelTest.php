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

use Glpi\Tests\DbTestCase;
use PluginUninstallModel;
use PluginUninstallReplace;
use Transfer;

class PluginUninstallModelTest extends DbTestCase
{
    private function getRequiredModelInput(array $override = []): array
    {
        $transfer = $this->createItem(Transfer::class, ['name' => 'Test transfer']);

        return array_merge([
            'name'         => 'Test model',
            'types_id'     => PluginUninstallModel::TYPE_MODEL_UNINSTALL,
            'transfers_id' => $transfer->getID(),
            'states_id'    => 0,
            'comment'      => '',
        ], $override);
    }

    public function testCreateModel(): void
    {
        $this->login();

        $model = $this->createItem(PluginUninstallModel::class, $this->getRequiredModelInput([
            'name' => 'Test model',
        ]));

        $this->assertGreaterThan(0, $model->getID());
        $this->assertSame('Test model', $model->fields['name']);
        $this->assertSame(PluginUninstallModel::TYPE_MODEL_UNINSTALL, (int) $model->fields['types_id']);
    }

    public function testUpdateModel(): void
    {
        $this->login();

        $model = $this->createItem(PluginUninstallModel::class, $this->getRequiredModelInput([
            'name' => 'Original name',
        ]));

        $this->updateItem(PluginUninstallModel::class, $model->getID(), [
            'name' => 'Updated name',
        ]);

        $model->getFromDB($model->getID());
        $this->assertSame('Updated name', $model->fields['name']);
    }

    public function testPurgeMethodBlockedForReplacementUninstallType(): void
    {
        $this->login();

        $model = new PluginUninstallModel();
        $input = $model->prepareInputForAdd([
            'name'           => 'Invalid model',
            'types_id'       => PluginUninstallModel::TYPE_MODEL_REPLACEMENT_UNINSTALL,
            'replace_method' => PluginUninstallReplace::METHOD_PURGE,
        ]);

        $this->assertSame([], $input);
        $this->hasSessionMessages(ERROR, ['The purge archiving method is not available for this model type']);
    }

    public function testPurgeMethodAllowedForReplacementType(): void
    {
        $this->login();

        $model = new PluginUninstallModel();
        $input = $model->prepareInputForAdd([
            'name'           => 'Valid model',
            'types_id'       => PluginUninstallModel::TYPE_MODEL_REPLACEMENT,
            'replace_method' => PluginUninstallReplace::METHOD_PURGE,
        ]);

        $this->assertNotEmpty($input);
        $this->assertSame('Valid model', $input['name']);
    }
}
