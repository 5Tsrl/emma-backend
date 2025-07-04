<?php
declare(strict_types=1);

//<editor-fold desc="Preamble">
/**
 * EMMA(tm) : Electronic Mobility Management Applications
 * Copyright (c) 5T Torino, Regione Piemonte, Città Metropolitana di Torino
 *
 * SPDX-License-Identifier: EUPL-1.2
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 5T - https://5t.torino.it
 * @link      https://emma.5t.torino.it
 * @author    Massimo INFUNTI - https://github.com/impronta48
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>
namespace App\View\Cell;

use Cake\View\Cell;

/**
 * Footer cell
 */
class FooterCell extends Cell
{
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array
     */
    protected $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        if (empty($id)) {
            $this->set('footer', null);

            return;
        }

        $c = $this->fetchTable('SurveyDeliveryConfigs')->find()
            ->where(['survey_id' => $id])
            ->first();
        if (isset($c->mail_footer)) {
            $this->set('footer', $c->mail_footer);
        } else {
            $this->set('footer', null);
        }
    }
}
