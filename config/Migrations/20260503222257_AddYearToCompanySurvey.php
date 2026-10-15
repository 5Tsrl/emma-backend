<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddYearToCompanySurvey extends AbstractMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-up-method
     * @return void
     */
    public function up()
    {
        
        $this->table('company_survey_history')
            ->addColumn('year', 'integer', [
                'after' => 'company_id',
                'default' => null,
                'length' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('office_id', 'integer', [
                'after' => 'type',
                'default' => null,
                'length' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'timestamp', [
                'after' => 'created',
                'default' => 'CURRENT_TIMESTAMP',
                'length' => null,
                'null' => true,
            ])
            ->update();

        // Change answer column to JSON type if it is not already
        $this->table('company_survey_history')
            ->changeColumn('answer', 'json', [
                'null' => true,
            ])
            ->update();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-down-method
     * @return void
     */
    public function down()
    {
        $this->table('company_survey_history')
            ->removeColumn('year')
            ->removeColumn('office_id')
            ->removeColumn('modified')
            ->update();

        $this->table('company_survey_history')
            ->changeColumn('answer', 'text', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG,
                'null' => true,
            ])
            ->update();
    }
}
