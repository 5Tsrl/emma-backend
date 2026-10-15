<?php
declare(strict_types=1);

namespace App\Policy;

use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;

/**
 * surveys policy
 */
class surveysTablePolicy
{
    public function scopegetList($user, $query)
    {
        //If the user is moma-area can only see if province and its area
        if ($user->role == 'moma_area') {
            return $query;
        } else {
            if ($user->company_id) {
                $company_id = $user->company_id;
            }
            if (!empty($company_id)) {
                return $query->where(['company_id' => $company_id]);
            }
        }
    }

    public function scopeIndex($user, $query)
    {
        //If the user is moma-area can only see if province and its area
        if ($user->role == 'moma_area') {
            // `area` può mancare (identità senza Areas caricate): lista vuota = fail-closed, solo template.
            $areas = (array)($user->area ?? []);
            $polygon = array_column($areas, 'polygon');
            $filters = [];
            foreach ($polygon as $value) {
                // SQLi hardening: il poligono è interpolato in ST_GeomFromText(), quindi
                // accetta solo WKT con caratteri-coordinata (cifre, '.', ',', spazi,
                // parentesi, '-'). Valori vuoti/malformati/ostili sono scartati (fail-closed).
                if (!preg_match('/^(MULTI)?POLYGON\s*\(\([0-9.,\s()\-]+\)\)$/i', (string)$value)) {
                    continue;
                }
                array_push($filters, ["ST_CONTAINS(ST_GeomFromText('$value'),POINT(Offices.lat, Offices.lon))"]);
            }

            return $query
            ->join([
                'table' => 'offices',
                'alias' => 'Offices',
                'type' => 'LEFT',
                'conditions' => 'Offices.company_id = Surveys.company_id',
            ])
            ->where(function (QueryExpression $exp, Query $q) use ($user, $filters, $areas) {
                // $q->newExpr() (non $exp->in()): $exp->in() muta $exp e
                // restituisce $this, quindi due chiamate successive su $exp
                // condividono lo stesso oggetto e finiscono per essere ANDate
                // e duplicate quando poi passate a $exp->or($filters).
                if (!empty(array_column($areas, 'province'))) {
                    array_push($filters, [
                        $q->newExpr()->in('Offices.province', array_column($areas, 'province')),
                    ]);
                }
                if (!empty(array_column($areas, 'city'))) {
                    array_push($filters, [$q->newExpr()->in('Offices.city', array_column($areas, 'city'))]);
                }

                // Fail-closed: un moma_area senza alcun criterio d'area valido (nessun
                // poligono valido, nessuna provincia/città) non deve vedere nulla.
                // $exp->or([]) compila a '' e renderebbe lo scope illimitato.
                if (empty($filters)) {
                    array_push($filters, ['1 = 0']);
                }

                // I template (version_tag='template', senza azienda) sono punti di
                // partenza condivisi: sempre visibili al moma_area, a prescindere
                // dal perimetro. Aggiunto dopo il fail-closed, così un moma_area
                // senza aree vede solo i template.
                array_push($filters, [$q->newExpr()->eq('Surveys.version_tag', 'template')]);

                return $exp->or(
                    $filters
                );
            })
            // Surveys.id, non Offices.company_id: il group serve solo a eliminare i
            // duplicati del LEFT JOIN (una riga per sede); per company_id collassava
            // tutti i questionari di un'azienda in una sola riga.
            ->group(['Surveys.id']);
        } else {
            $myRole = $user->role;
            $company_id = $user->company_id;
            //L'utente moma può vedere solo la sua azienda
            if ($myRole == 'moma' && !empty($company_id)) {
                return $query->where(['Surveys.company_id' =>  $company_id]);
            }
        }
    }
}
