<?php

/*
 * EMMA(tm) - Employer Mobility Management Application
 * Copyright (C) 2025 5T Torino / Regione Piemonte
 * Licensed under the EUPL-1.2 or later.
 */

declare(strict_types=1);

use Migrations\AbstractMigration;

class SeedCompanySurveyHistory2025 extends AbstractMigration
{
  /**
   * Up Method.
   *
   * Inserts rows in company_survey_history for year 2025 from:
   *  - companies.survey  → type 'generale'
   *  - offices.survey    → type 'sede'
   *
   * Existing rows for the same (company_id, office_id, year, type) are skipped
   * via INSERT IGNORE to avoid duplicates when re-running.
   *
   * @return void
   */
  public function up(): void
  {
    // companies.survey → type 'generale', office_id NULL
    $this->execute("
      INSERT IGNORE INTO company_survey_history
        (company_id, year, type, office_id, answer, created, modified)
      SELECT
        c.id,
        2025,
        'generale',
        NULL,
        c.survey,
        NOW(),
        NOW()
      FROM companies c
      WHERE c.survey IS NOT NULL
    ");

    // offices.survey → type 'sede', office_id set
    $this->execute("
      INSERT IGNORE INTO company_survey_history
        (company_id, year, type, office_id, answer, created, modified)
      SELECT
        o.company_id,
        2025,
        'sede',
        o.id,
        o.survey,
        NOW(),
        NOW()
      FROM offices o
      WHERE o.survey IS NOT NULL
        AND o.company_id IS NOT NULL
    ");
  }

  /**
   * Down Method.
   *
   * Removes the seeded rows for year 2025.
   *
   * @return void
   */
  public function down(): void
  {
    $this->execute("
      DELETE FROM company_survey_history
      WHERE year = 2025
    ");
  }
}
