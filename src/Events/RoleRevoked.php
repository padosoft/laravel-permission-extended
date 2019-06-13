<?php
/**
 * Copyright (c) Padosoft.com 2018.
 */

namespace Padosoft\Laravel\PermissionExtended\Events;

use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class RoleRevoked
{
    use SerializesModels;

    /**
     * @var Collection
     */
    public $roles;
    /**
     * @var Model
     */
    public $target;

    public function __construct(Collection $roles, Model $target)
    {
        $this->roles = $roles;
        $this->target = $target;
    }
}
