<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\Policy\BeforeScopeInterface;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;

/**
 * Offices policy
 */
class OfficesTablePolicy implements BeforeScopeInterface
{
    public function beforeScope($user, $query, $action)
    {
        //If the user is moma-area can only see if province and its area
        if ($user->role == 'moma_area') {
            $polygon = array_column($user->area, 'polygon');
            $filters = [];
            foreach ($polygon as $value) {
                // SQLi hardening: il poligono è interpolato in ST_GeomFromText(), quindi
                // accetta solo WKT con caratteri-coordinata (cifre, '.', ',', spazi,
                // parentesi, '-'). Valori vuoti/malformati/ostili sono scartati (fail-closed).
                if (!preg_match('/^(MULTI)?POLYGON\s*\(\([0-9.,\s()\-]+\)\)$/i', (string)$value)) {
                    continue;
                }
                $filters[] = "ST_CONTAINS(ST_GeomFromText('$value'),POINT(Offices.lat, Offices.lon))";
            }

            return $query
                ->where(function (QueryExpression $exp, Query $q) use ($user, $filters) {
                    // $q->newExpr() (non $exp->in()): $exp->in() muta $exp e
                    // restituisce $this, quindi due chiamate successive su $exp
                    // condividono lo stesso oggetto e finiscono per essere ANDate
                    // e duplicate quando poi passate a $exp->or($filters).
                    if (!empty(array_column($user->area, 'province'))) {
                        $filters[] = $q->newExpr()->in('Offices.province', array_column($user->area, 'province'));
                    }
                    if (!empty(array_column($user->area, 'city'))) {
                        $filters[] = $q->newExpr()->in('Offices.city', array_column($user->area, 'city'));
                    }

                    // Fail-closed: un moma_area senza alcun criterio d'area valido (nessun
                    // poligono valido, nessuna provincia/città) non deve vedere nulla.
                    // $exp->or([]) compila a '' e renderebbe lo scope illimitato.
                    if (empty($filters)) {
                        $filters[] = '1 = 0';
                    }

                    return $exp->or($filters);
                });
        }
    }

    public function scopeIndex($user, $query)
    {
        //If the user is moma-area can only see if province and its area

        if ($user->role != 'moma_area') {
            //L'utente moma può vedere solo la sua azienda
            $company_id = $user->company_id;
            if (!empty($company_id)) {
                $query->where(['OR' => [
                    'company_id' => $company_id,
                    [
                        'coworking' => 1,
                        'private_coworking' => 0,
                    ],
                ]]);
            }

            //L'utente moma può vedere solo la sua azienda
            $office_id = $user->office_id;
            if (!empty($office_id)) {
                $query
                    // ->where(['Offices.id' =>  $office_id]);
                    ->where(['OR' => [
                        'Offices.id' =>  $office_id,
                        [
                            'coworking' => 1,
                            'private_coworking' => 0,
                        ],
                    ]]);
            }
        }
    }

    public function scopeView($user, $query)
    {
        //If the user is moma-area can only see if province and its area
        if ($user->role != 'moma_area') {
            //L'utente moma può vedere solo la sua azienda
            if ($user->company_id) {
                $query
                    ->where(['company_id' => $user->company_id]);
            }

            if ($user->office_id) {
                return $query->where(['id' => $user->office_id]);
            } else {
                return $query;
            }
        }
    }

    public function scopeExport($user, $query)
    {
        if ($user->company_id) {
            $query
                ->where(['Offices.company_id' => $user->company_id]);
        }

        if ($user->office_id) {
            return $query->where(['Offices.id' => $user->office_id]);
        }

        return $query;
    }

    public function canExport($user)
    {
        return in_array($user->role, ['admin','moma','moma_area']);
    }
}
