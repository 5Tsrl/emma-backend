<?php
declare(strict_types=1);

namespace App\Exporter;

use Cake\Core\Configure;
use Cake\Utility\Text;
use QuickChart;

trait UtilityExporterTrait
{
    private $scala = 0.4;
    private $Companies;

    //Assicura che i colori siano nel formato #RRGGBB oppure Gray, Red, Blue, etc.

    private function chartColors(): array
    {
        $chartColors = Configure::read('chartColors');
        $chartColors = array_map(
            function ($color) {
                if (preg_match('/^[0-9A-Fa-f]/', $color)) {
                    return '#' . $color;
                }

                return $color;
            },
            $chartColors
        );

        return $chartColors;
    }

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

    /**
     * Build pillar-like groups from the flat measures table.
     *
     * Pillars are no longer a separate model: an axis is a Measure with type=0,
     * with child measures (type 1/2) following it by `weight`. This returns a
     * list of stdClass groups shaped like the old Pillars->contain(['Measures'])
     * result: each has `id`, `name`, `description` and a `measures` array. The
     * group `id` is the original pillar id parsed from the axis slug
     * ('pillar-<id>') so PSCL plan keys (pscl[pillar_id][measure_id]) still match.
     *
     * If the `measures` table is unavailable it returns an empty list.
     *
     * @return array<int,\stdClass>
     */
    protected function getPillarsFromMeasures(): array
    {
        $groups = [];
        try {
            $Measures = \Cake\ORM\TableRegistry::getTableLocator()->get('Measures');
            $measures = $Measures->find('ordered')->toArray();
        } catch (\Exception $e) {
            return $groups;
        }

        $current = null;
        foreach ($measures as $m) {
            if ((int)$m->type === 0) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = new \stdClass();
                // Derive the original pillar id from the slug 'pillar-<id>'.
                $current->id = (int)str_replace('pillar-', '', (string)$m->slug);
                $current->name = $m->name;
                $current->description = $m->description;
                $current->measures = [];
                continue;
            }
            if ($current === null) {
                // Measures before any axis: synthetic group keyed 0.
                $current = new \stdClass();
                $current->id = 0;
                $current->name = '';
                $current->description = '';
                $current->measures = [];
            }
            $current->measures[] = $m;
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

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
                    if ($m['value'] > 0) {
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
                        'backgroundColor' => $this->chartColors(),
                    ],
                ],
            ],
        ];

        $jres = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        if ($jres) {
            $jres = str_replace('"#!!', '', $jres);
            $jres = str_replace('!!#"', '', $jres);
            $jres = str_replace('\\', '', $jres);
            $qc->setConfig($jres);
            $qc->setBackgroundColor('white');
        } else {
            // Handle JSON encoding error
            error_log('JSON encoding error: ' . json_last_error_msg());

            return null;
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

        //Accorcio le etichette a 50 char
        foreach ($labels as $i => $label) {
            $labels[$i] = Text::truncate((string)$label, 50);
        }

        $qc = new QuickChart([
            'width' => 600 * $this->scala * 2,
            'height' => 568 * $this->scala * 2,
            'host' => Configure::read('Quickchart'),
        ]);

        $res = [
            'type' => 'outlabeledDoughnut',
            //'type' => 'doughnut',
            'options' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'align' => 'center',
                    'boxWidth' => 400,
                    ],
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
                    'outlabels' => false, /* [
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
                    ], */
                ],
            ],
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $series[0],
                        'backgroundColor' => $this->chartColors(),
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
