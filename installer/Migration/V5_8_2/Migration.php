<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Installer\Migration\V5_8_2;

use OrangeHRM\Installer\Util\V1\AbstractMigration;

class Migration extends AbstractMigration
{
    public function up(): void
    {
        // Ensure pt_PT is available/enabled in DB
        $this->getLangHelper()->updateLanguageStatusByLangCode('pt_PT', true, true);

        $languageId = $this->getLangHelper()->getLanguageIdByLangCode('pt_PT');
        if (is_null($languageId)) {
            return;
        }

        // Create missing translation rows for pt_PT for every lang string
        // and default them to the source value so there are no missing items.
        $this->getConnection()->executeStatement(
            "INSERT INTO ohrm_i18n_translate (lang_string_id, language_id, value, customized, modified_at)
             SELECT ls.id, :languageId, ls.value, 0, NOW()
             FROM ohrm_i18n_lang_string ls
             LEFT JOIN ohrm_i18n_translate tr
               ON tr.lang_string_id = ls.id AND tr.language_id = :languageId
             WHERE tr.id IS NULL",
            ['languageId' => $languageId]
        );

        // If translation rows exist but have NULL values (and are not customized),
        // set them to the source value to avoid empty translations.
        $this->getConnection()->executeStatement(
            "UPDATE ohrm_i18n_translate tr
             INNER JOIN ohrm_i18n_lang_string ls ON ls.id = tr.lang_string_id
             SET tr.value = ls.value, tr.modified_at = NOW()
             WHERE tr.language_id = :languageId
               AND tr.customized = 0
               AND tr.value IS NULL",
            ['languageId' => $languageId]
        );
    }

    public function getVersion(): string
    {
        return '5.8.2';
    }
}

