<?php
declare(strict_types=1);

namespace App\Exporter;

interface ExporterInterface
{
    public function __construct($company_id, $office, $survey_id, $ignore_office, $year);

    public function fillSurvey();

    public function fillOfficeSurvey($survey);

    public function fillPSCLBlock();

    public function generatePSCL($response);
}
