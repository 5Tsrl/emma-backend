<?php
declare(strict_types=1);

namespace App\Policy;

use App\Model\Entity\Company;
use Authorization\IdentityInterface;
use Authorization\Policy\Result;

/**
 * Company policy
 */
class CompanyPolicy
{
    /**
     * Check if $user can add Company
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Company $company
     * @return bool
     */
    public function canAdd(IdentityInterface $user, Company $company)
    {
    }

    /**
     * Check if $user can edit Company
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Company $company
     * @return bool
     */
    public function canEdit(IdentityInterface $user, Company $company)
    {
    }

    /**
     * Check if $user can delete Company
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Company $company
     * @return bool
     */
    public function canDelete(IdentityInterface $user, Company $company)
    {
    }

    /**
     * Check if $user can view Company
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \App\Model\Entity\Company $company
     * @return bool
     */
    public function canView(IdentityInterface $user, Company $company)
    {
        if ($user->role == 'moma_area' || $user->role == 'admin') {
            return new Result(true);
        } else {
            if ($user->company_id == $company->id) {
                return new Result(true);
            }
            // Results let you define a 'reason' for the failure.
            return new Result(false, 'not-owner');
        }
    }
}
