<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Companies seed.
 */
class CompaniesSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'id' => '1',
                'name' => 'Azienda Test',
                'num_employees' => null,
                'ateco' => null,
                'company_code' => null,
                'web' => null,
                'email' => null,
                'city' => null,
                'province' => null,
                'address' => null,
                'pec' => null,
                'tel' => null,
                'type' => '1',
                'survey' => '{"cap": "00101", "nome": "test", "comune": "test", "mm_eta": "meno di 30 anni", "nr_sedi": "1", "district": "Primo Maggio", "mm_email": "test", "mm_sesso": "F", "indirizzo": "test", "mm_telefono": "test", "pec_azienda": "test", "codice_ateco": "test", "nr_dipendenti": "9", "mm_nome_cognome": "test", "mm_orario_lavoro": "part time", "mm_luogo_servizio": "test", "rapporto_lavorativo": "Dipendente aziendale", "nome_mobility_manager": "si", "inquadramento_lavorativo": "impiegato", "inquadramento_lavorativo_altro": null}',
                'emissions' => null,
                'partner' => null,
                'additional_data' => null,
                'years' => '[]'
            ]
        ];

        $table = $this->table('companies');
        $table->insert($data)->save();
    }
}
