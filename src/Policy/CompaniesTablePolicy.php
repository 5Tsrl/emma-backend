<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\Policy\BeforeScopeInterface;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;

/**
 * Companies policy
 */
class CompaniesTablePolicy implements BeforeScopeInterface
{
    public function beforeScope($user, $query, $action)
    {
        //If the user is moma-area can only see if province and its area
        if ($user->role == 'moma_area') {
            $polygon = array_column($user->area, 'polygon');
            $filters = [];
            foreach ($polygon as $value) {
                $filters[] = "ST_CONTAINS(ST_GeomFromText('$value'),POINT(Offices.lat, Offices.lon))";
            }
            if ($action == 'exportList') {
                $query->matching('Offices', function ($q) use ($user, $filters) {
                    return $q->where(function (QueryExpression $exp, Query $q) use ($user, $filters) {
                        if (!empty(array_column($user->area, 'province'))) {
                            $filters[] = $exp->in('Offices.province', array_column($user->area, 'province'));
                        }
                        if (!empty(array_column($user->area, 'city'))) {
                            $filters[] = $exp->in('Offices.city', array_column($user->area, 'city'));
                        }

                        return $exp->or($filters);
                    });
                });

                return $query->contain('Offices', function ($q) use ($user, $filters) {
                    return $q->where(function (QueryExpression $exp, Query $q) use ($user, $filters) {
                        if (!empty(array_column($user->area, 'province'))) {
                            $filters[] = $exp->in('Offices.province', array_column($user->area, 'province'));
                        }
                        if (!empty(array_column($user->area, 'city'))) {
                            $filters[] = $exp->in('Offices.city', array_column($user->area, 'city'));
                        }

                        return $exp->or($filters);
                    });
                });
            } else {
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
        } else {
            //L'utente moma può vedere solo la sua azienda
            $company_id = $user->company_id;
            if (!empty($company_id)) {
                return $query->where(['Companies.id' =>  $company_id]);
            }
        }
    }

    public function scopeList($user, $query)
    {
        return $query;
    }

    public function scopeView($user, $query)
    {
        return $query;
    }

    public function scopeExportList($user, $query)
    {
        return $query;
    }
}
