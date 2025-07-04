<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * BatchesUser Entity
 *
 * @property int $id
 * @property string $user_id
 * @property string $batch_id
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Batch $batch
 */
class BatchesUser extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'user_id' => true,
        'batch_id' => true,
        'user' => true,
        'batch' => true,
    ];
}
