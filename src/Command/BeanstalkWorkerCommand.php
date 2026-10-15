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
 * HTTP_HOST=api.example.test bin/cake beanstalk_worker
 *
 * @package App\Command
 */
class BeanstalkWorkerCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io)
    {
        // $io->createFile('/var/www/html/logs/errorbean.log', 'BeanstalkWorkerCommand started', true);
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
                    } elseif (is_bool($value)) {
                        // AnswersController::exportSurveyData($survey_id = null,
                        // $all = true, $allAnswers = true) defaults these two to
                        // REAL PHP booleans - they only arrive as the strings
                        // "true"/"false" when the caller supplies the optional URL
                        // segments (which the SPA does, but
                        // POST /answers/exportSurveyData/{id}.json does not, and
                        // that route resolves). Such a job carries JSON booleans,
                        // and strtolower(true) is a TypeError under PHP 8: the
                        // worker died before executeCommand() ever ran, then
                        // release()d the job, so it retried forever and only ever
                        // appended to errorbean.log. Normalize to the same
                        // "true"/"false" the segment-supplying callers send.
                        //
                        // This branch must stay ABOVE the `$value == null` check
                        // below: `false == null` is true under PHP's loose
                        // comparison, so a false used to be forwarded as null.
                        $options[] = $value ? 'true' : 'false';
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
            // Append (don't overwrite) so errorbean.log accumulates as a normal, rotatable log.
            file_put_contents(LOGS . 'errorbean.log', date('Y-m-d H:i:s') . ' ' . $errorMsg . PHP_EOL, FILE_APPEND);
            $this->log($errorMsg, 'error');
            Cache::delete('beanstalk_job');
            $pheanstalk->release($job);
        }
    }
}
