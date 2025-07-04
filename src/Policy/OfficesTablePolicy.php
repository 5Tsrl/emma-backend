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
            foreach ($polygon as &$value) {
                $filters[] = "ST_CONTAINS(ST_GeomFromText('$value'),POINT(Offices.lat, Offices.lon))";
            }

            return $query
            ->where(function (QueryExpression $exp, Query $q) use ($user, $filters) {
                if (!empty(array_column($user->area, 'province'))) {
                    $filters[] = $exp->in('Offices.province', array_column($user->area, 'province'));
                }
                if (!empty(array_column($user->area, 'city'))) {
                    $filters[] = $exp->in('Offices.city', array_column($user->area, 'city'));
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
}
