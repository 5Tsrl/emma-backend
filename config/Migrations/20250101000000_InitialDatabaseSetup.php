<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Initial Database Setup Migration
 * 
 * This migration creates the complete database structure for a new EMMA installation
 * with empty tables and only an admin user.
 * 
 * Generated on: 2025-01-01 (renamed to run first)
 */
class InitialDatabaseSetup extends AbstractMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change()
    {
        // Create users table
        $this->createUsersTable();
        
        // Create companies table
        $this->createCompaniesTable();
        
        // Create company types table
        $this->createCompanyTypesTable();
        
        // Create offices table
        $this->createOfficesTable();
        
        // Create employees table
        $this->createEmployeesTable();
        
        // Create origins table
        $this->createOriginsTable();
        
        // Create surveys table
        $this->createSurveysTable();
        
        // Create questions table
        $this->createQuestionsTable();
        
        // Create answers table
        $this->createAnswersTable();
        
        // Create areas table
        $this->createAreasTable();
        
        // Create sections table
        $this->createSectionsTable();
        
        // Create survey participants table
        $this->createSurveyParticipantsTable();
        
        // Create additional missing tables
        $this->createMissingTables();
        
        // Create supporting tables
        $this->createSupportingTables();
        
        // Add foreign key constraints
        $this->addForeignKeys();
    }
    
    /**
     * Create users table
     */
    private function createUsersTable()
    {
        $table = $this->table('users', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', [
            'default' => null,
            'null' => false,
            'limit' => 36,
        ]);
        $table->addColumn('username', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('email', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('password', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('first_name', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
        ]);
        $table->addColumn('last_name', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
        ]);
        $table->addColumn('token', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('token_expires', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('api_token', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('activation_date', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('secret', 'string', [
            'default' => null,
            'limit' => 32,
            'null' => true,
        ]);
        $table->addColumn('secret_verified', 'boolean', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('tos_date', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('active', 'boolean', [
            'default' => 0,
            'null' => false,
        ]);
        $table->addColumn('is_superuser', 'boolean', [
            'default' => 0,
            'null' => false,
        ]);
        $table->addColumn('role', 'string', [
            'default' => 'user',
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('additional_data', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('company_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('office_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('mobile', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('cf', 'string', [
            'default' => null,
            'limit' => 16,
            'null' => true,
        ]);
        $table->addColumn('badge_number', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('subcompany', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('years', 'json', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addIndex(['email']);
        $table->addIndex(['company_id']);
        $table->addIndex(['office_id']);
        $table->addIndex(['role']);
        $table->addIndex(['active']);
        
        $table->create();
    }
    
    /**
     * Create companies table
     */
    private function createCompaniesTable()
    {
        $table = $this->table('companies');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('num_employees', 'integer', [
            'default' => null,
            'null' => true,
            'signed' => false,
        ]);
        $table->addColumn('ateco', 'string', [
            'default' => null,
            'limit' => 20,
            'null' => true,
        ]);
        $table->addColumn('company_code', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('web', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('email', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('city', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('province', 'string', [
            'default' => null,
            'limit' => 2,
            'null' => true,
        ]);
        $table->addColumn('address', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('pec', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('tel', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('type', 'integer', [
            'default' => 1,
            'null' => false,
        ]);
        $table->addColumn('survey', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('emissions', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('partner', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('additional_data', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('years', 'json', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->create();
    }
    
    /**
     * Create company types table
     */
    private function createCompanyTypesTable()
    {
        $table = $this->table('company_types');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('survey_template', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('icon', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        
        $table->create();
    }
    
    /**
     * Create offices table
     */
    private function createOfficesTable()
    {
        $table = $this->table('offices');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('address', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('cap', 'string', [
            'default' => null,
            'limit' => 6,
            'null' => true,
        ]);
        $table->addColumn('city', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('province', 'string', [
            'default' => null,
            'limit' => 2,
            'null' => true,
        ]);
        $table->addColumn('company_id', 'integer', [
            'default' => 0,
            'null' => true,
        ]);
        $table->addColumn('lat', 'double', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('lon', 'double', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('company_code', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('office_code', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('extended_name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('office_type', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('office_code_external', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('office_type_extra', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('survey', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('label_survey', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('num_employees', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('email', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('PSCL', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('coworking', 'boolean', [
            'default' => 0,
            'null' => true,
        ]);
        $table->addColumn('pascal_measures', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('info_coworking', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('info_parking', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('private_coworking', 'boolean', [
            'default' => 0,
            'null' => true,
        ]);
        $table->addColumn('years', 'json', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addIndex(['company_id']);
        
        $table->create();
    }
    
    /**
     * Create employees table
     */
    private function createEmployeesTable()
    {
        $table = $this->table('employees');
        $table->addColumn('user_id', 'char', [
            'default' => null,
            'limit' => 36,
            'null' => true,
        ]);
        $table->addColumn('office_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('role_description', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('orario', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('gender', 'char', [
            'default' => null,
            'limit' => 1,
            'null' => true,
        ]);
        $table->addColumn('dob', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('shift', 'boolean', [
            'default' => 0,
            'null' => true,
        ]);
        $table->addColumn('origin_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addIndex(['user_id']);
        $table->addIndex(['office_id']);
        $table->addIndex(['origin_id']);
        
        $table->create();
    }
    
    /**
     * Create origins table
     */
    private function createOriginsTable()
    {
        $table = $this->table('origins');
        $table->addColumn('address', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => true,
        ]);
        $table->addColumn('postal_code', 'string', [
            'default' => null,
            'limit' => 10,
            'null' => true,
        ]);
        $table->addColumn('city', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
        ]);
        $table->addColumn('province', 'char', [
            'default' => null,
            'limit' => 2,
            'null' => true,
        ]);
        $table->addColumn('lat', 'float', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('lon', 'float', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('company_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('survey_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('user_id', 'string', [
            'default' => null,
            'limit' => 36,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
        ]);
        $table->addColumn('geocoded_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('distance', 'float', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addIndex(['company_id']);
        $table->addIndex(['survey_id']);
        $table->addIndex(['user_id']);
        $table->addIndex(['created']);
        
        $table->create();
    }
    
    /**
     * Create surveys table
     */
    private function createSurveysTable()
    {
        $table = $this->table('surveys');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('company_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('version_tag', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('date', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
            'null' => true,
        ]);
        $table->addColumn('user_id', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('opening_date', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('closing_date', 'date', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('welcome', 'text', [
            'default' => null,
            'null' => true,
            'limit' => 'long',
        ]);
        $table->addColumn('sending_mode', 'char', [
            'default' => null,
            'limit' => 1,
            'null' => true,
        ]);
        $table->addColumn('logo', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('partnership', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('show_translation', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('year', 'integer', [
            'default' => null,
            'null' => true,
            'signed' => false,
        ]);
        
        $table->addIndex(['company_id']);
        $table->addIndex(['date']);
        
        $table->create();
    }
    
    /**
     * Create questions table
     */
    private function createQuestionsTable()
    {
        $table = $this->table('questions');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 20,
            'null' => true,
        ]);
        $table->addColumn('description', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('options', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('type', 'char', [
            'default' => 'single',
            'limit' => 10,
            'null' => true,
        ]);
        $table->addColumn('section_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('creator_id', 'string', [
            'default' => null,
            'limit' => 36,
            'null' => true,
        ]);
        $table->addColumn('conditions', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('compulsory_answer', 'boolean', [
            'default' => 0,
            'null' => true,
        ]);
        $table->addColumn('long_description', 'json', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addIndex(['section_id']);
        
        $table->create();
    }
    
    /**
     * Create answers table
     */
    private function createAnswersTable()
    {
        $table = $this->table('answers');
        $table->addColumn('question_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('survey_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('user_id', 'string', [
            'default' => null,
            'limit' => 36,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('answer', 'text', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->addIndex(['question_id', 'survey_id', 'user_id'], ['unique' => true]);
        $table->addIndex(['user_id', 'survey_id']);
        $table->addIndex(['created']);
        
        $table->create();
    }
    
    /**
     * Create areas table
     */
    private function createAreasTable()
    {
        $table = $this->table('areas');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('city', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('province', 'string', [
            'default' => null,
            'limit' => 2,
            'null' => true,
        ]);
        $table->addColumn('polygon', 'geometry', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->create();
    }
    
    /**
     * Create sections table
     */
    private function createSectionsTable()
    {
        $table = $this->table('sections');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('weight', 'integer', [
            'default' => 0,
            'null' => true,
        ]);
        
        $table->create();
    }
    
    /**
     * Create survey_participants table
     */
    private function createSurveyParticipantsTable()
    {
        $table = $this->table('survey_participants', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', [
            'default' => null,
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('survey_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('user_id', 'char', [
            'default' => null,
            'limit' => 36,
            'null' => false,
        ]);
        $table->addColumn('survey_completed_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('invitation_delivered_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('first_reminder_delivered_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('second_reminder_delivered_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        
        $table->create();
    }
    
    /**
     * Create essential missing tables from reference database
     */
    private function createMissingTables()
    {
        // Create the most essential missing tables
        $this->createI18nTable();
        $this->createNotificationsTable();
        $this->createEmailQueueTable();
        $this->createEventsTable();
        $this->createMeasuresTable();
        $this->createMonitoringsTable();
        $this->createOdTable();
        $this->createParticipantsTable();
        $this->createReportsTable();
        $this->createSurveyDeliveryConfigsTable();
        $this->createTagsTable();
        $this->createSocialAccountsTable();
        $this->createSubscriptionsTable();
        $this->createTimeslotsTable();
        $this->createTimetablesTable();
        $this->createArticlesTable();
        $this->createArticlesTagsTable();
        $this->createCompanySurveyHistoryTable();
        $this->createDestinationsTable();
        $this->createPillarsTable();
        $this->createPsclTable();
        $this->createTplOperatorsTable();
        $this->createUnionRollbackTable();
    }
    
    /**
     * Create essential tables with minimal structure
     */
    private function createI18nTable()
    {
        $table = $this->table('i18n');
        $table->addColumn('locale', 'string', [
            'default' => null,
            'limit' => 6,
            'null' => false,
        ]);
        $table->addColumn('model', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('foreign_key', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('field', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('content', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->create();
    }
    
    // Create placeholder methods for other tables
    private function createNotificationsTable() {
        $table = $this->table('notifications');
        $table->addColumn('channel', 'string', ['limit' => 30, 'null' => false])
              ->addColumn('type', 'string', ['limit' => 30, 'null' => false])
              ->addColumn('created', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('delivered_at', 'datetime', ['null' => true])
              ->addColumn('errors', 'text', ['null' => true])
              ->addColumn('participant_id', 'char', ['limit' => 36, 'null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->addColumn('from_mail', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('to_mail', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('subject', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('template', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('payload', 'text', ['limit' => 4294967295, 'null' => true])
              ->create();
    }
    private function createEmailQueueTable() {
        $table = $this->table('email_queue', ['id' => false]);
        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false])
              ->addColumn('email', 'string', ['limit' => 129, 'null' => false])
              ->addColumn('from_name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('from_email', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('subject', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('config', 'string', ['limit' => 30, 'null' => false])
              ->addColumn('template', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('layout', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('theme', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('format', 'string', ['limit' => 5, 'null' => false])
              ->addColumn('template_vars', 'text', ['null' => false])
              ->addColumn('headers', 'text', ['null' => true])
              ->addColumn('sent', 'boolean', ['default' => 0, 'null' => false])
              ->addColumn('locked', 'boolean', ['default' => 0, 'null' => false])
              ->addColumn('send_tries', 'integer', ['default' => 0, 'null' => false])
              ->addColumn('send_at', 'datetime', ['null' => true])
              ->addColumn('created', 'datetime', ['null' => false])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->addColumn('attachments', 'text', ['null' => true])
              ->addColumn('campaign_id', 'integer', ['null' => true])
              ->addColumn('error', 'text', ['null' => true])
              ->addPrimaryKey(['id'])
              ->create();
    }
    private function createEventsTable() {
        $table = $this->table('events');
        $table->addColumn('title', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('organizer_email', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('max_pax', 'integer', ['null' => true])
              ->addColumn('place', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('destination_id', 'integer', ['null' => true])
              ->addColumn('start_time', 'datetime', ['null' => true])
              ->addColumn('end_time', 'datetime', ['null' => true])
              ->addColumn('min_year', 'integer', ['null' => true])
              ->addColumn('max_year', 'integer', ['null' => true])
              ->addColumn('created', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('user_id', 'integer', ['null' => true])
              ->create();
    }
    private function createMeasuresTable() {
        $table = $this->table('measures');
        $table->addColumn('slug', 'string', ['limit' => 50, 'null' => true])
              ->addColumn('pillar_id', 'integer', ['null' => true])
              ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('img', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('target', 'enum', ['values' => ['scuola', 'azienda', 'pascal'], 'null' => true])
              ->addColumn('type', 'integer', ['null' => true])
              ->addColumn('service_url', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('labels', 'json', ['null' => true])
              ->addColumn('indicator', 'string', ['limit' => 255, 'null' => true])
              ->create();
    }
    private function createMonitoringsTable() {
        $table = $this->table('monitorings');
        $table->addColumn('name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('dt', 'date', ['null' => true])
              ->addColumn('created', 'datetime', ['null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->addColumn('measure_id', 'integer', ['null' => true])
              ->addColumn('office_id', 'integer', ['null' => true])
              ->addColumn('jvalues', 'json', ['null' => true])
              ->addColumn('objective', 'boolean', ['null' => true])
              ->addColumn('survey_id', 'integer', ['null' => true])
              ->addColumn('pscl_id', 'integer', ['null' => true])
              ->addIndex(['office_id'])
              ->create();
    }
    private function createOdTable() {
        $table = $this->table('od', ['id' => false]);
        $table->addColumn('quale_distanza', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('distanza_h', 'float', ['null' => true])
              ->addColumn('survey_id', 'integer', ['null' => true])
              ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
              ->addColumn('question_id', 'integer', ['null' => true])
              ->addColumn('answer', 'text', ['null' => true])
              ->addColumn('ori_city', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('ori_province', 'string', ['limit' => 2, 'null' => true])
              ->addColumn('ori_lat', 'float', ['null' => true])
              ->addColumn('ori_lon', 'float', ['null' => true])
              ->addColumn('office_id', 'integer', ['null' => true])
              ->addColumn('total_distance', 'float', ['null' => true])
              ->addColumn('num_mezzi', 'integer', ['null' => true])
              ->addColumn('sede', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('dest_city', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('dest_province', 'string', ['limit' => 2, 'null' => true])
              ->addColumn('dest_lat', 'float', ['null' => true])
              ->addColumn('dest_lon', 'float', ['null' => true])
              ->addColumn('dest_geohash', 'string', ['limit' => 15, 'null' => true])
              ->addColumn('minuti_andata', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('minuti_ritorno', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('mezzo_prevalente', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->addColumn('anno', 'integer', ['null' => true])
              ->addColumn('incentivo', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('eta', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('classe_distanza', 'string', ['limit' => 12, 'null' => false, 'default' => ''])
              ->addColumn('frequenza', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('altre_attivita', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('total_cost', 'float', ['null' => true])
              ->addColumn('costo_mezzo_prevalente', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('costo_km_medio', 'float', ['null' => true])
              ->addColumn('classe_costo_mezzo_prevalente', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('distance', 'float', ['null' => true])
              ->addColumn('abbonamento_possiedi', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('flessibilita_oraria', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('ora_parti', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('ora_esci', 'string', ['limit' => 30, 'null' => true])
              ->addColumn('motivo_tpl', 'string', ['limit' => 30, 'null' => true])
              ->create();
    }
    private function createParticipantsTable() {
        $table = $this->table('participants');
        $table->addColumn('name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('surname', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('tel', 'string', ['limit' => 50, 'null' => true])
              ->addColumn('privacy', 'boolean', ['default' => 1, 'null' => false])
              ->addColumn('dob', 'date', ['null' => true])
              ->addColumn('diet', 'string', ['limit' => 50, 'null' => true])
              ->addColumn('note', 'text', ['null' => true])
              ->addColumn('created', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'timestamp', ['null' => true])
              ->addColumn('event_id', 'integer', ['null' => false])
              ->addColumn('destination_id', 'integer', ['null' => false])
              ->addColumn('pob', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('city', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('address', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('facebook', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('ente', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('forum_id_prima_scelta', 'integer', ['null' => false])
              ->addColumn('forum_id_seconda_scelta', 'integer', ['null' => false])
              ->create();
    }
    private function createReportsTable() {
        $table = $this->table('reports');
        $table->addColumn('name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('q', 'text', ['null' => false])
              ->create();
    }
    private function createSurveyDeliveryConfigsTable() {
        $table = $this->table('survey_delivery_configs');
        $table->addColumn('survey_id', 'integer', ['null' => false])
              ->addColumn('days_before_first_reminder', 'integer', ['null' => false])
              ->addColumn('days_before_second_reminder', 'integer', ['null' => false])
              ->addColumn('invitation_template', 'text', ['null' => true])
              ->addColumn('reminder_template', 'text', ['null' => true])
              ->addColumn('is_active', 'boolean', ['default' => 1, 'null' => false])
              ->addColumn('invitation_subject', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('reminder_subject', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('sender_name', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('sender_email', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('mailer_config', 'string', ['limit' => 45, 'null' => true, 'default' => 'default'])
              ->addColumn('mail_footer', 'text', ['null' => true])
              ->create();
    }
    private function createTagsTable() {
        $table = $this->table('tags');
        $table->addColumn('title', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('created', 'datetime', ['null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->create();
    }
    private function createSocialAccountsTable() {
        $table = $this->table('social_accounts', ['id' => false]);
        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false])
              ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
              ->addColumn('provider', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('username', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('reference', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('link', 'string', ['limit' => 255, 'null' => true, 'default' => '#'])
              ->addColumn('token', 'string', ['limit' => 500, 'null' => false])
              ->addColumn('token_secret', 'string', ['limit' => 500, 'null' => true])
              ->addColumn('token_expires', 'datetime', ['null' => true])
              ->addColumn('active', 'boolean', ['default' => 1, 'null' => false])
              ->addColumn('data', 'text', ['null' => false])
              ->addColumn('created', 'datetime', ['null' => false])
              ->addColumn('modified', 'datetime', ['null' => false])
              ->addPrimaryKey(['id'])
              ->create();
    }
    private function createSubscriptionsTable() {
        $table = $this->table('subscriptions');
        $table->addColumn('office_id', 'integer', ['null' => true])
              ->addColumn('title', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('active', 'boolean', ['default' => 1, 'null' => true])
              ->addColumn('created', 'datetime', ['null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->create();
    }
    private function createTimeslotsTable() {
        $table = $this->table('timeslots');
        $table->addColumn('timetable_id', 'integer', ['null' => true])
              ->addColumn('qty', 'integer', ['null' => true])
              ->addColumn('slot', 'char', ['limit' => 255, 'null' => true])
              ->addColumn('is_out', 'boolean', ['default' => 0, 'null' => true])
              ->addColumn('day', 'integer', ['null' => true])
              ->create();
    }
    private function createTimetablesTable() {
        $table = $this->table('timetables');
        $table->addColumn('office_id', 'integer', ['null' => true])
              ->addColumn('valid_from', 'date', ['null' => true])
              ->addColumn('note', 'text', ['null' => true])
              ->addColumn('type', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('approved', 'boolean', ['default' => 0, 'null' => true])
              ->addColumn('created', 'datetime', ['null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->addColumn('user_id', 'char', ['limit' => 36, 'null' => true])
              ->addColumn('notify', 'boolean', ['default' => 0, 'null' => true])
              ->create();
    }
    private function createArticlesTable() {
        $table = $this->table('articles');
        $table->addColumn('title', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('slug', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('body', 'text', ['null' => true])
              ->addColumn('published', 'boolean', ['default' => 0, 'null' => true])
              ->addColumn('created', 'datetime', ['null' => true])
              ->addColumn('modified', 'datetime', ['null' => true])
              ->addColumn('destination_id', 'integer', ['null' => true])
              ->addColumn('archived', 'boolean', ['default' => 0, 'null' => true])
              ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
              ->addColumn('promoted', 'boolean', ['default' => 0, 'null' => false])
              ->addColumn('slider', 'boolean', ['default' => 0, 'null' => true])
              ->addColumn('keywords', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('url_canonical', 'string', ['limit' => 255, 'null' => true])
              ->create();
    }
    private function createArticlesTagsTable() {
        $table = $this->table('articles_tags', ['id' => false, 'primary_key' => ['article_id', 'tag_id']]);
        $table->addColumn('article_id', 'integer', ['null' => false])
              ->addColumn('tag_id', 'integer', ['null' => false])
              ->create();
    }
    private function createCompanySurveyHistoryTable() {
        $table = $this->table('company_survey_history');
        $table->addColumn('company_id', 'integer', ['null' => false])
              ->addColumn('type', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('answer', 'text', ['limit' => 'long', 'null' => true])
              ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->create();
    }
    private function createDestinationsTable() {
        $table = $this->table('destinations');
        $table->addColumn('name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('slug', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('show_in_list', 'boolean', ['default' => 1, 'null' => false])
              ->addColumn('facebook', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('instagram', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('modified', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
              ->addColumn('lc', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('ef', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('anno_attivazione', 'integer', ['null' => true])
              ->addColumn('comuni', 'text', ['null' => true])
              ->addColumn('tipologia', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('presidente', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('coach', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('fondazione_locale', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('descrizione', 'text', ['null' => true])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('chiuso', 'boolean', ['default' => 0, 'null' => false])
              ->create();
    }
    private function createPillarsTable() {
        $table = $this->table('pillars');
        $table->addColumn('name', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('description', 'text', ['null' => true])
              ->create();
    }
    private function createPsclTable() { 
        $table = $this->table('pscl');
        $table->addColumn('version_tag', 'string', [
            'default' => null,
            'limit' => 45,
            'null' => true,
        ]);
        $table->addColumn('company_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('office_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('survey_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('plan', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('year', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->create();
    }
    private function createTplOperatorsTable() {
        $table = $this->table('tpl_operators');
        $table->addColumn('name', 'char', ['limit' => 255, 'null' => false])
              ->addColumn('company_id', 'integer', ['null' => false])
              ->addColumn('code', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
              ->addColumn('modified', 'timestamp', ['null' => true])
              ->create();
    }
    private function createUnionRollbackTable() {
        $table = $this->table('union_rollback');
        $table->addColumn('date', 'datetime', ['null' => true])
              ->addColumn('remove_question_id', 'integer', ['null' => true])
              ->addColumn('destination_question_id', 'integer', ['null' => true])
              ->addColumn('questions_survey_id', 'text', ['null' => true])
              ->addColumn('answers_id', 'text', ['null' => true])
              ->addColumn('remove_question', 'json', ['null' => true])
              ->addColumn('name_union_questions', 'string', ['limit' => 200, 'null' => true])
              ->create();
    }
    
    /**
     * Create supporting tables
     */
    private function createSupportingTables()
    {
        // Areas users junction table
        $areasUsers = $this->table('areas_users', ['id' => false, 'primary_key' => ['user_id', 'area_id']]);
        $areasUsers->addColumn('user_id', 'char', [
            'default' => null,
            'limit' => 36,
            'null' => false,
        ]);
        $areasUsers->addColumn('area_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $areasUsers->create();
        
        // Questions surveys junction table
        $questionsSurveys = $this->table('questions_surveys');
        $questionsSurveys->addColumn('survey_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $questionsSurveys->addColumn('question_id', 'integer', [
            'default' => null,
            'null' => false,
        ]);
        $questionsSurveys->addColumn('weight', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $questionsSurveys->addColumn('section_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $questionsSurveys->addColumn('compulsory', 'boolean', [
            'default' => 0,
            'null' => false,
        ]);
        $questionsSurveys->addColumn('hidden', 'boolean', [
            'default' => 0,
            'null' => false,
        ]);
        $questionsSurveys->addColumn('description', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $questionsSurveys->addColumn('long_description', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $questionsSurveys->addColumn('options', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $questionsSurveys->addColumn('conditions', 'json', [
            'default' => null,
            'null' => true,
        ]);
        $questionsSurveys->addColumn('compulsory_answer', 'boolean', [
            'default' => 0,
            'null' => false,
        ]);
        $questionsSurveys->addIndex(['survey_id']);
        $questionsSurveys->addIndex(['question_id']);
        $questionsSurveys->create();
    }
    
    /**
     * Add foreign key constraints
     */
    private function addForeignKeys()
    {
        // Users table foreign keys
        $this->table('users')
            ->addForeignKey('company_id', 'companies', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('office_id', 'offices', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Offices table foreign keys
        $this->table('offices')
            ->addForeignKey('company_id', 'companies', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Employees table foreign keys
        $this->table('employees')
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('office_id', 'offices', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('origin_id', 'origins', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Origins table foreign keys
        $this->table('origins')
            ->addForeignKey('company_id', 'companies', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('survey_id', 'surveys', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Surveys table foreign keys
        $this->table('surveys')
            ->addForeignKey('company_id', 'companies', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Questions table foreign keys
        $this->table('questions')
            ->addForeignKey('section_id', 'sections', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Answers table foreign keys
        $this->table('answers')
            ->addForeignKey('question_id', 'questions', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('survey_id', 'surveys', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Areas users junction table foreign keys
        $this->table('areas_users')
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('area_id', 'areas', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Questions surveys junction table foreign keys
        $this->table('questions_surveys')
            ->addForeignKey('question_id', 'questions', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('survey_id', 'surveys', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Survey participants table foreign keys
        $this->table('survey_participants')
            ->addForeignKey('survey_id', 'surveys', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Pscl table foreign keys
        $this->table('pscl')
            ->addForeignKey('company_id', 'companies', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('office_id', 'offices', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('survey_id', 'surveys', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Monitorings table foreign keys
        $this->table('monitorings')
            ->addForeignKey('measure_id', 'measures', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('office_id', 'offices', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('survey_id', 'surveys', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addForeignKey('pscl_id', 'pscl', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
        
        // Measures table foreign keys
        $this->table('measures')
            ->addForeignKey('pillar_id', 'pillars', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->update();
    }
}
