<?php

namespace Padosoft\Laravel\PermissionExtended\Traits;

use Spatie\Permission\Traits\HasPermissions as HasPermissionBase;
use Padosoft\Laravel\PermissionExtended\Events\PermissionRevoked;
use Padosoft\Laravel\PermissionExtended\Events\PermissionSynched;
use Padosoft\Laravel\PermissionExtended\Events\PermissionAssigned;

trait HasPermissions
{
    protected $disablePermissionEvents = false;

    use HasPermissionBase {
        HasPermissionBase::givePermissionTo as givePermissionToBase;
        HasPermissionBase::syncPermissions as syncPermissionsBase;
        HasPermissionBase::revokePermissionTo as revokePermissionToBase;
    }

    /**
     * disable Permission Events Firing.
     *
     * @return $this
     */
    public function disablePermissionEvents()
    {
        $this->disablePermissionEvents = true;

        return $this;
    }

    /**
     * enable Permission Events Firing.
     * @return $this
     */
    public function enablePermissionEvents()
    {
        $this->disablePermissionEvents = false;

        return $this;
    }

    /**
     * Return true if permissions modifications events firing is enabled
     * @return bool
     */
    public function isPermissionEventsEnabled()
    {
        return (config('permission-extended.events_enabled') && !$this->disablePermissionEvents);
    }

    /**
     * @param $event
     */
    protected function firePermissionEvent($event)
    {
        if (!$this->isPermissionEventsEnabled()) {
            return;
        }

        event($event);
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return $this
     */
    public function givePermissionTo(...$permissions)
    {
        $this->givePermissionToBase($permissions);
        if ($this->isPermissionEventsEnabled()) {
            $model = $this->getModel();
            if ($model->exists) {
                $this->firePermissionEvent(new PermissionAssigned(collect($permissions), $model));
            } else {
                $class = \get_class($model);

                $class::saved(
                    function ($object) use ($permissions, $model) {
                        static $modelLastFiredOn;
                        if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                            return;
                        }
                        $model->firePermissionEvent(new PermissionAssigned(collect($permissions), $model));
                        $modelLastFiredOn = $object;
                    }
                );
            }
        }

        return $this;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param string|array|\Spatie\Permission\Contracts\Permission|\Illuminate\Support\Collection $permissions
     *
     * @return $this
     */
    public function syncPermissions(...$permissions)
    {
        $permission_event_was_enabled = $this->isPermissionEventsEnabled();
        if ($permission_event_was_enabled) {
            $old_permissions = collect($this->permissions()->get());
            $this->disablePermissionEvents();
        }
        $this->syncPermissionsBase($permissions);
        if ($permission_event_was_enabled) {
            $new_permissions = collect($this->permissions()->get());
            $this->enablePermissionEvents();
            $model = $this->getModel();
            if ($model->exists) {
                $this->firePermissionEvent(new PermissionSynched($old_permissions->diff($new_permissions),
                    $new_permissions->diff($old_permissions), $new_permissions, $model));
            } else {
                $class = \get_class($model);

                $class::saved(
                    function ($object) use ($old_permissions, $new_permissions, $model) {
                        static $modelLastFiredOn;
                        if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                            return;
                        }
                        $model->firePermissionEvent(new PermissionSynched($old_permissions->diff($new_permissions),
                            $new_permissions->diff($old_permissions), $new_permissions, $model));
                        $modelLastFiredOn = $object;
                    }
                );
            }
        }

        return $this;
    }

    /**
     * Revoke the given permission.
     *
     * @param \Spatie\Permission\Contracts\Permission|\Spatie\Permission\Contracts\Permission[]|string|string[] $permission
     *
     * @return $this
     */
    public function revokePermissionTo($permission)
    {
        $this->revokePermissionToBase($permission);

        $model = $this->getModel();

        if ($model->exists) {
            $this->firePermissionEvent(new PermissionRevoked(collect($permission), $model));
        }

        return $this;
    }

}
