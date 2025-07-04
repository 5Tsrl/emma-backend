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
                'name' => 'Agenzia delle Entrate',
                'address' => '',
                'cap' => '',
                'city' => 'Torino',
                'province' => 'TO',
                'country' => 'IT',
                'num_employees' => '10',
                'moma_id' => '7aab5817-8f9f-4a34-91b2-3c22a0c6e3d7',
                'company_type_id' => 'ente',
                'ateco' => '21.23.22',
            ],
            [
                'id' => '2',
                'name' => 'Avigliana',
                'address' => '',
                'cap' => '',
                'city' => 'Avigliana',
                'province' => 'TO',
                'country' => 'IT',
                'num_employees' => null,
                'moma_id' => '7aab5817-8f9f-4a34-91b2-3c22a0c6e3d7',
                'company_type_id' => 'azienda',
                'ateco' => '',
            ],
        ];

        $table = $this->table('companies');
        $table->insert($data)->save();
    }
}
