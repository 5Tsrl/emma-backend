<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Migrations\AbstractMigration;

class AddMapqsInsertProvinceToOrigineSpostamenti extends AbstractMigration
{
    public function up(): void
    {
        // Read the configured question id for "origine_spostamenti"
        $questionId = (int) (Configure::read('Questions.origine_spostamenti') ?? 0);
        if ($questionId <= 0) {
            // Nothing to do if not configured
            return;
        }

        // Build SQL that only sets the JSON key if missing; also safeguards invalid/empty JSON
        $sql = <<<SQL
UPDATE questions
SET long_description = JSON_SET(
    CASE
        WHEN long_description IS NULL OR long_description = '' OR JSON_VALID(long_description) = 0
            THEN JSON_OBJECT()
        ELSE long_description
    END,
    '$.mapqs.it.insert_province', 'TO'
)
WHERE id = :id
  AND NOT JSON_CONTAINS_PATH(
        CASE
            WHEN long_description IS NULL OR long_description = '' OR JSON_VALID(long_description) = 0
                THEN JSON_OBJECT()
            ELSE long_description
        END,
        'one', '$.mapqs.it.insert_province'
    );
SQL;

        $this->executeWithParams($sql, ['id' => $questionId]);

        // Also ensure the same JSON key is present on related questions_surveys rows
        $sqlQs = <<<SQL
UPDATE questions_surveys
SET long_description = JSON_SET(
    CASE
        WHEN long_description IS NULL OR long_description = '' OR JSON_VALID(long_description) = 0
            THEN JSON_OBJECT()
        ELSE long_description
    END,
    '$.mapqs.it.insert_province', 'TO'
)
WHERE question_id = :id
  AND NOT JSON_CONTAINS_PATH(
        CASE
            WHEN long_description IS NULL OR long_description = '' OR JSON_VALID(long_description) = 0
                THEN JSON_OBJECT()
            ELSE long_description
        END,
        'one', '$.mapqs.it.insert_province'
    );
SQL;

        $this->executeWithParams($sqlQs, ['id' => $questionId]);

        // Ensure an 'enabled' object with required flags set to true on Questions
        $sqlEnabledQ = <<<SQL
UPDATE questions
SET long_description = JSON_SET(
    CASE
        WHEN long_description IS NULL OR long_description = '' OR JSON_VALID(long_description) = 0
            THEN JSON_OBJECT()
        ELSE long_description
    END,
    '$.mapqs.enabled', JSON_OBJECT(
        'emissioni-auto', true,
        'emissioni-moto', true,
        'cilindrata-auto', true,
        'cilindrata-moto', true,
        'alimentazione-auto', true,
        'alimentazione-moto', true
    )
)
WHERE id = :id;
SQL;
        $this->executeWithParams($sqlEnabledQ, ['id' => $questionId]);

        // And on QuestionsSurveys
        $sqlEnabledQS = <<<SQL
UPDATE questions_surveys
SET long_description = JSON_SET(
    CASE
        WHEN long_description IS NULL OR long_description = '' OR JSON_VALID(long_description) = 0
            THEN JSON_OBJECT()
        ELSE long_description
    END,
    '$.mapqs.enabled', JSON_OBJECT(
        'emissioni-auto', true,
        'emissioni-moto', true,
        'cilindrata-auto', true,
        'cilindrata-moto', true,
        'alimentazione-auto', true,
        'alimentazione-moto', true
    )
)
WHERE question_id = :id;
SQL;
        $this->executeWithParams($sqlEnabledQS, ['id' => $questionId]);
    }

    public function down(): void
    {
        $questionId = (int) (Configure::read('Questions.origine_spostamenti') ?? 0);
        if ($questionId <= 0) {
            return;
        }

        // Remove the key if present; leave other JSON content intact
    $sql = <<<SQL
UPDATE questions
SET long_description = JSON_REMOVE(long_description, '$.mapqs.it.insert_province')
WHERE id = :id
  AND long_description IS NOT NULL
  AND JSON_VALID(long_description) = 1
  AND JSON_CONTAINS_PATH(long_description, 'one', '$.mapqs.it.insert_province');
SQL;

        $this->executeWithParams($sql, ['id' => $questionId]);

    $sqlQs = <<<SQL
UPDATE questions_surveys
SET long_description = JSON_REMOVE(long_description, '$.mapqs.it.insert_province')
WHERE question_id = :id
  AND long_description IS NOT NULL
  AND JSON_VALID(long_description) = 1
  AND JSON_CONTAINS_PATH(long_description, 'one', '$.mapqs.it.insert_province');
SQL;

    $this->executeWithParams($sqlQs, ['id' => $questionId]);

    // Remove the enabled flags (do not attempt to drop the parent object)
    $sqlEnabledQ = <<<SQL
UPDATE questions
SET long_description = JSON_REMOVE(
    long_description,
    '$.mapqs.enabled'
)
WHERE id = :id AND long_description IS NOT NULL AND JSON_VALID(long_description) = 1;
SQL;
    $this->executeWithParams($sqlEnabledQ, ['id' => $questionId]);

    $sqlEnabledQS = <<<SQL
UPDATE questions_surveys
SET long_description = JSON_REMOVE(
    long_description,
    '$.mapqs.enabled'
)
WHERE question_id = :id AND long_description IS NOT NULL AND JSON_VALID(long_description) = 1;
SQL;
    $this->executeWithParams($sqlEnabledQS, ['id' => $questionId]);
    }

    /**
     * Helper to execute prepared statements with parameters in Phinx.
     * @param string $sql
     * @param array<string,mixed> $params
     */
    private function executeWithParams(string $sql, array $params = []): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(is_string($k) && $k[0] !== ':' ? ":$k" : $k, $v);
        }
        $stmt->execute();
    }
}
