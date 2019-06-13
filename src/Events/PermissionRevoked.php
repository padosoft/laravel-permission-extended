<?php
/**
 * Copyright (c) Padosoft.com 2018.
 */

namespace Padosoft\Laravel\PermissionExtended\Events;

use Illuminate\Support\Collection;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class PermissionRevoked
{
    use SerializesModels;

    /**
     * @var Collection
     */
    public $permissions;
    /**
     * @var Model
     */
    public $target;

    public function __construct(Collection $permissions, Model $target)
    {
        $this->permissions = $permissions;
        $this->target = $target;
    }
}
