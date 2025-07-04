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

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Origin extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
    '*' => true,
    ];

    //Riscrivo la getVisible per estrarre anche le colonne dei campi collegati
    //https://api.cakephp.org/4.0/trait-Cake.Datasource.EntityTrait.html#getVisible
    public function getVisible(): array
    {
        //TODO: Inserire una cache
        //TODO: trasformare in una chiamata ricorsiva
        $fields = parent::getVisible();
        foreach ($fields as $k => $f) {
            if ($this->$f instanceof Entity) {
                $subFields = $this->$f->getVisible();
                foreach ($subFields as $s) {
                    if ($this->$f->$s instanceof Entity) {
                        $subFields2 = $this->$f->$s->getVisible();
                        foreach ($subFields2 as $s2) {
                            $fields[] = "$f.$s.$s2";
                        }
                    } else {
                        $fields[] = "$f.$s";
                    }
                }
                unset($fields[$k]);
            }
        }

        return $fields;
    }
}
