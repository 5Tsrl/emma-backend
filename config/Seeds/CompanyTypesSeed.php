<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * CompanyTypes seed.
 */
class CompanyTypesSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Azienda Privata',
                'survey_template' => 'azienda',
                'icon' => 'building-o'
            ],
            [
                'id' => 2,
                'name' => 'Ente Pubblico',
                'survey_template' => 'azienda',
                'icon' => 'building-o'
            ],
            [
                'id' => 3,
                'name' => 'Università o Accademia',
                'survey_template' => 'scuola',
                'icon' => 'pencil'
            ],
            [
                'id' => 4,
                'name' => 'Scuola Superiore',
                'survey_template' => 'scuola',
                'icon' => 'pencil'
            ],
            [
                'id' => 5,
                'name' => 'Scuola Media Inferiore',
                'survey_template' => 'scuola',
                'icon' => 'pencil'
            ],
            [
                'id' => 6,
                'name' => 'Scuola Primaria',
                'survey_template' => 'scuola',
                'icon' => 'pencil'
            ],
            [
                'id' => 7,
                'name' => 'Scuola Materna',
                'survey_template' => 'scuola',
                'icon' => 'pencil'
            ],
            [
                'id' => 9,
                'name' => 'Operatore TPL',
                'survey_template' => 'azienda',
                'icon' => 'building-o'
            ]
        ];

        $table = $this->table('company_types');
        $table->insert($data)->save();
    }
}
