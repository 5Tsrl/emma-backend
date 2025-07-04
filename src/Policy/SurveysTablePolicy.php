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
            $polygon = array_column($user->area, 'polygon');
            $filters = [];
            foreach ($polygon as &$value) {
                array_push($filters, ["ST_CONTAINS(ST_GeomFromText('$value'),POINT(Offices.lat, Offices.lon))"]);
            }

            return $query
            ->join([
                'table' => 'offices',
                'alias' => 'Offices',
                'type' => 'LEFT',
                'conditions' => 'Offices.company_id = Surveys.company_id',
            ])
            ->where(function (QueryExpression $exp, Query $q) use ($user, $filters) {
                if (!empty(array_column($user->area, 'province'))) {
                    array_push($filters, [$exp->in('Offices.province', array_column($user->area, 'province'))]);
                }
                if (!empty(array_column($user->area, 'city'))) {
                    array_push($filters, [$exp->in('Offices.city', array_column($user->area, 'city'))]);
                }

                return $exp->or(
                    $filters
                );
            })
            ->group([
                'Offices.company_id']);
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
