<?php
declare(strict_types=1);

namespace App\Exporter;

use Cake\Core\Configure;
use QuickChart;

trait UtilityExporterTrait
{
    private $scala = 0.4;
    private $Companies;

    //Pulisce la stringa da caratteri speciali

    protected function cleanStr($answer)
    {
        if (!is_string($answer)) {
            $answer = strval($answer);
        }

        return htmlspecialchars($answer);
    }

    //Prende l'array Json in input e la trasforma in un array associativa con le sole azioni attive
    // measure_id -> measure

    protected function reducePSCLToActive($pscl)
    {
        $result = [];
        if (empty($pscl)) {
            return $result;
        }
        array_shift($pscl);
        foreach ($pscl as $ms) {
            foreach ($ms as $m) {
                if (isset($m['pillar_id']) && isset($m['measure_id'])) {
                    if ($m['value']) {
                        $result[$m['measure_id']] = $m;
                    }
                }
            }
        }

        return $result;
    }

    private function getHistogram($labels, $series, $title)
    {
        if (empty($labels) || empty($series)) {
            return null;
        }

        $qc = new QuickChart([
            'width' => 1024 * $this->scala,
            'height' => 768 * $this->scala,
            'host' => Configure::read('Quickchart'),
        ]);

        //Accorcio le etichette a 20 char
        $labels = array_map(
            function ($l) {
                return substr((string)$l, 0, 40);
            },
            $labels
        );

        $f = "#!!(value) => {return (value + ' (' + Math.round((value /" . array_sum($series[0]) . ")*100)+'%)');}!!#";

        $res = [
            'type' => 'bar',
            // 'type' => 'horizontalBar',
            // 'type' => 'progressBar',
            'options' => [
                /*'legend' => [
                    'position' => 'top',
                    'fontFamily' => 'Sofia Sans',
                ],*/

                'backgroundColor' => 'white',
                /*'plugins' =>  [
                    'datalabels' =>  [
                        'anchor' => 'end',
                        'align' =>  'top',
                        'color' =>  '#3366cc',
                        'font' =>  [
                            'fontFamily' => 'Sofia Sans',
                            'weight' => 'bold',
                        ],
                        'formatter' => $f,
                        'display' => 'auto',
                    ],
                ],*/

            ],
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $title,
                        'data' => $series[0],
                        'backgroundColor' => Configure::read('chartColors'),
                    ],
                ],
            ],
        ];

        $jres = json_encode($res, JSON_UNESCAPED_UNICODE);
        if ($jres) {
            $jres = str_replace('"#!!', '', $jres);
            $jres = str_replace('!!#"', '', $jres);
            $jres = str_replace('\\', '', $jres);
            $qc->setConfig($jres);
            $qc->setBackgroundColor('white');
        }

        //Se faccio così è troppo lento
        //$data = $qc->toBinary();
        //return   "data:image/png;base64," . base64_encode($data);

        return $qc->getUrl();
    }

    private function getPie($labels, $series, $title = null)
    {
        //Trovo il primo elemento dell'array che ha zero come valore
        $m = count($series[0]) - 1;
        $zero = -1;
        for ($i = $m; $i > 0; $i--) {
            if ($series[0][$i] == 0) {
                $zero = $i;
            } else {
                break;
            }
        }

        //Elimino gli elementi di $series e $label che sono dopo l'indice $zero
        if ($zero > 0) {
            $series[0] = array_slice($series[0], 0, $zero);
            $labels = array_slice($labels, 0, $zero);
        }

        //Accorcio le etichette a 20 char
        $labels = array_map(
            function ($l) {
                return substr((string)$l, 0, 20);
            },
            $labels
        );

        $qc = new QuickChart([
            'width' => 1024 * $this->scala * 2,
            'height' => 768 * $this->scala * 2,
            'host' => Configure::read('Quickchart'),
        ]);

        $res = [
            'type' => 'outlabeledDoughnut',
            //'type' => 'doughnut',
            'options' => [
                'legend' => false,

                'title' => [
                    'display' => false,
                    'text' => $title,
                ],
                [
                    'position' => 'right',
                    'fontFamily' => 'Sofia Sans',
                    'borderWidth' => 0,
                ],
                'plugins' =>  [
                    /*'datalabels' =>  [
                        'anchor' =>  'center',
                        'align' =>  'center',
                        'color' =>  'navy',
                        'borderWidth' =>  1,
                        'borderRadius' =>  5,
                        'borderColor' =>  'white',
                        'backgroundColor' =>  'white',
                        'display' => 'auto',
                        'font' => [
                            'resizable' => true,
                            'minSize' => 10,
                            'maxSize' => 15,
                            'fontFamily' => 'Sofia Sans',
                        ],
                    ],*/
                    'outlabels' => [
                        'text' => '%l (%p)',
                        'color' => 'white',
                        'stretch' => 35,
                        'display' => 'auto',
                        'font' => [
                            'resizable' => true,
                            'minSize' => 10,
                            'maxSize' => 15,
                            'weight' => 'bold',
                            'fontFamily' => 'Sofia Sans',
                        ],
                    ],
                ],
            ],
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $series[0],
                        'backgroundColor' => Configure::read('chartColors'),
                        'borderWidth' =>  0,
                    ],
                ],
            ],
        ];

        $jres = json_encode($res);
        $qc->setConfig($jres);
        $qc->setBackgroundColor('white');

        //Se faccio così è troppo lento
        //$data = $qc->toBinary();
        //return   "data:image/png;base64,". base64_encode($data);

        // Print the chart URL
        return $qc->getUrl();
    }
}
