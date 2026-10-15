<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * MapGalQuestion seed.
 * 
 * Seed for map-gal question type (GAL movement survey)
 */
class MapGalQuestionSeed extends AbstractSeed
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
    public function run(): void
    {
        $table = $this->table('questions');

        $data = [
            [
                'id' => 700,
                'name' => 'spostamenti-gal',
                'description' => 'I tuoi spostamenti su mappa (GAL)',
                'options' => null,
                'type' => 'map-gal',
                'section_id' => 1,
                'creator_id' => '7aab5817-8f9f-4a34-91b2-3c22a0c6e3d7',
                'conditions' => null,
                'compulsory_answer' => 1,
                'long_description' => json_encode([
                    'description' => 'Indica gli spostamenti per te più importanti o frequenti',
                    'tipiSpostamento' => ['scuola', 'lavoro', 'figli', 'sport', 'shopping', 'salute', 'serali']
                ]),
                'moma_area' => 0,
            ],
        ];

        $table->insert($data)->saveData();
    }
}
