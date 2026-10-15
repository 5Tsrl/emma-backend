<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * CompanyTypesI18n seed.
 *
 * Semina le traduzioni francesi del campo `name` di company_types nella
 * tabella EAV `i18n` usata dal Translate behavior (vedi CompanyTypesTable).
 * Il valore di `model` deve essere 'CompanyTypes' (referenceName del behavior,
 * cioè il nome della classe Table senza il suffisso 'Table').
 *
 * Idempotente: rimuove eventuali righe FR preesistenti prima di reinserire,
 * così può essere rieseguito senza creare duplicati.
 *
 * Esecuzione: bin/cake migrations seed --seed CompanyTypesI18nSeed
 */
class CompanyTypesI18nSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * @return void
     */
    public function run()
    {
        // foreign_key => traduzione francese (gli id rispecchiano CompanyTypesSeed):
        // 1 Azienda Privata, 2 Ente Pubblico, 3 Università o Accademia,
        // 4 Scuola Superiore, 5 Scuola Media Inferiore, 6 Scuola Primaria,
        // 7 Scuola Materna, 9 Operatore TPL.
        $translations = [
            1 => 'Entreprise privée',
            2 => 'Établissement public',
            3 => 'Université ou académie',
            4 => 'Lycée',
            5 => 'Collège',
            6 => 'École primaire',
            7 => 'École maternelle',
            9 => 'Opérateur de transport public',
        ];

        // Pulisco le righe FR esistenti per questo model/field (idempotenza).
        $this->execute("DELETE FROM i18n WHERE model = 'CompanyTypes' AND field = 'name' AND locale = 'fr'");

        $rows = [];
        foreach ($translations as $foreignKey => $content) {
            $rows[] = [
                'locale' => 'fr',
                'model' => 'CompanyTypes',
                'foreign_key' => $foreignKey,
                'field' => 'name',
                'content' => $content,
            ];
        }

        $this->table('i18n')->insert($rows)->save();
    }
}
