<?php
declare(strict_types=1);

/* src/View/Helper/LinkHelper.php */
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

namespace App\View\Helper;

use Cake\View\Helper;

class OptionsHelper extends Helper
{
    //TODO Gestire correttamente array questions

    public function toCheckBox($o)
    {
        $result = '';
        if (empty($o)) {
            return '';
        }
        $oArray = $o;
        foreach ($oArray as $k => $e) {
            if (is_array($e)) {
                $result .= "<label for=\"\">$k</label> ";
                $result .= '<select>';
                foreach ($e as $k => $e1) {
                    if (is_array($e1)) {
                        $result .= "<optgroup label=\"${e1['label']}\">${e1['label']}</option>";
                        foreach ($e1['options'] as $k2 => $e2) {
                            $result .= "<option value=\"$e2\">$e2</option>";
                        }
                        $result .= '</optgroup>';
                    } else {
                        $result .= "<option value=\"$e1\">$e1</option>";
                    }
                }
                $result .= '</select><br>';
            } else {
                $result .= '<input type="checkbox" value="' . h($e) . '">&nbsp;<label>' . h($e) . '</label><br>';
            }
        }

        return $result;
    }

    public function toAnswerList($o)
    {
        $result = '';
        if (is_null($o)) {
            return '';
        }
        $oArray = $o;
        foreach ($oArray as $key => $val) {
            $result .= "$key: <b>$val</b><br>";
        }

        return $result;
    }
}
