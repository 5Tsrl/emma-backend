<?php
declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\Auth\DefaultPasswordHasher;

/**
 * InitialAdmin seed.
 * 
 * This seed creates the initial admin user for a new EMMA installation.
 */
class InitialAdminSeed extends AbstractSeed
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
        // Generate UUID for admin user
        $adminId = $this->generateUuid();
        
        // Hash the default password
        $hasher = new DefaultPasswordHasher();
        $hashedPassword = $hasher->hash('password');
        
        $data = [
            [
                'id' => $adminId,
                'username' => 'admin',
                'email' => 'admin@nomail.com',
                'password' => $hashedPassword,
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'token' => null,
                'token_expires' => null,
                'api_token' => null,
                'activation_date' => date('Y-m-d H:i:s'),
                'secret' => null,
                'secret_verified' => null,
                'tos_date' => date('Y-m-d H:i:s'),
                'active' => 1,
                'is_superuser' => 1,
                'role' => 'admin',
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
                'additional_data' => null,
                'company_id' => null,
                'office_id' => null,
                'mobile' => null,
                'cf' => null,
                'badge_number' => null,
                'subcompany' => null,
            ],
        ];

        $table = $this->table('users');
        $table->insert($data)->save();
        
        // Output admin user info
        $this->output->writeln('');
        $this->output->writeln('<info>Initial admin user created successfully!</info>');
        $this->output->writeln('');
        $this->output->writeln('<comment>Admin User Details:</comment>');
        $this->output->writeln('- ID: ' . $adminId);
        $this->output->writeln('- Username: admin@nomail.com');
        $this->output->writeln('- Email: admin@nomail.com');
        $this->output->writeln('- Password: password');
        $this->output->writeln('- Role: admin');
        $this->output->writeln('- Active: true');
        $this->output->writeln('- Superuser: true');
        $this->output->writeln('');
        $this->output->writeln('<error>⚠️  IMPORTANT: Change the password immediately after first login!</error>');
        $this->output->writeln('');
    }
    
    /**
     * Generate a UUID v4
     *
     * @return string
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
