<?php
declare(strict_types=1);

namespace App\Policy;

use Authorization\Policy\BeforeScopeInterface;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;

/**
 * Companies policy
 */
class QuestionsTablePolicy implements BeforeScopeInterface
{
    public function beforeScope($user, $query, $action)
    {
        //If the user is moma-area can only see if province and its area
        if ($user->role == 'moma_area') {

            return $query->where(['moma_area' =>  true]);
        }else{
            return $query;
        }
    }

    public function scopeList($user, $query)
    {
        return $query;
    }

    // public function scopeView($user, $query)
    // {
    //     return $query;
    // }

    // public function scopeExportList($user, $query)
    // {
    //     return $query;
    // }
}
