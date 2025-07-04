<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;

/**
 * HTTP_HOST=api.mobility48.test bin/cake beanstalk_worker
 *
 * @package App\Command
 */
class BeanstalkWorkerCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io)
    {
        // $io->createFile('/var/www/html/logs/errorbean.log', 'BeanstalkWorkerCommand started', true);
        $log = 'BeanstalkWorkerCommand started/n';
        $sitedir = Configure::read('sitedir');
        $pheanstalk = Pheanstalk::create('127.0.0.1');
        $tube       = new TubeName("$sitedir-pscl-exporter");

        $prev_job = Cache::read('beanstalk_job');
        if ($prev_job) {
            sleep(2);

            return;
        }

        // we want jobs from our tube only
        $pheanstalk->watch($tube);
        

        // wait for 50 seconds to give the worker a chance to put the job in the queue.
        $job = $pheanstalk->reserveWithTimeout(60);
        // $log= $log.'watching tube: '.$job."\n";
        try {
            $io->createFile(LOGS.'errorbean.log', $log, true);
            // is job is null, no job was found in the queue.
            if ($job === null) {
                return;
            }
            $jobPayload = $job->getData();
            $data = json_decode($jobPayload, true);
            // do work.
            $command = $data['command'];
            $options = [];
            foreach ($data as $key => $value) {
                if ($key != 'command') {
                    $options[] = "--$key";
                    if (is_array($value)) {
                        $value = implode(',', $value);
                        $options[] = $value;
                    } elseif (is_int($value)) {
                        // $value is an integer
                        $options[] = strval($value);
                    } else {
                        $options[] = $value == null ? $value : strtolower($value);
                    }
                }
            }
            Cache::write('beanstalk_job', $job->getId());
            $this->executeCommand($command, $options, $io);

            // eventually we're done, delete job.
            $pheanstalk->delete($job);

            Cache::delete('beanstalk_job');
        } catch (\Exception $e) {
            // handle exception.
            // and let some other worker retry.
            $errorMsg = $e->getMessage();
            $io->createFile(LOGS.'errorbean.log', $log.''.$errorMsg, true);
            $this->log($errorMsg, 'error');
            Cache::delete('beanstalk_job');
            $pheanstalk->release($job);
        }
    }
}
