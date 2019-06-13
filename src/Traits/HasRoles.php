<?php

namespace Padosoft\Laravel\PermissionExtended\Traits;

use Illuminate\Support\Collection;
use Padosoft\Laravel\PermissionExtended\Events\PermissionSynched;
use Spatie\Permission\Traits\HasRoles as HasRoleBase;
use Spatie\Permission\Contracts\Role;
use Illuminate\Database\Eloquent\Builder;
use Padosoft\Laravel\PermissionExtended\Events\RoleRevoked;
use Padosoft\Laravel\PermissionExtended\Events\RoleSynched;
use Padosoft\Laravel\PermissionExtended\Events\RoleAssigned;
use Padosoft\Laravel\PermissionExtended\Events\PermissionRevoked;
use Padosoft\Laravel\PermissionExtended\Events\PermissionAssigned;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasRoles
{
    use HasRoleBase{
        HasRoleBase::assignRole as assignRoleBase;
        HasRoleBase::removeRole as removeRoleBase;
        HasRoleBase::syncRoles as syncRolesBase;
    }

    protected $disableRoleEvents = false;

    /**
     * disable Role Events Firing.
     *
     * @return $this
     */
    public function disableRoleEvents()
    {
        $this->disableRoleEvents = true;

        return $this;
    }

    /**
     * enable Role Events Firing.
     * @return $this
     */
    public function enableRoleEvents()
    {
        $this->disableRoleEvents = false;

        return $this;
    }

    /**
     * Return true if roles modifications events firing is enabled
     * @return bool
     */
    public function isRoleEventsEnabled()
    {
        return (config('permission-extended.events_enabled') && !$this->disableRoleEvents);
    }

    protected function fireRoleEvent($event)
    {
        if ($this->isRoleEventsEnabled()) {
            return;
        }
        //check if the model target is a permission interface
        if (is_a($event,RoleAssigned::class,true) && is_a($event->target, \Spatie\Permission\Contracts\Permission::class, true)) {

            foreach ($event->roles as $role) {
                $role->firePermissionEvent(new PermissionAssigned(collect([$event->target]), $role));
            }
            return;
        }

        if (is_a($event,RoleRevoked::class,true) && is_a($event->target, \Spatie\Permission\Contracts\Permission::class, true)) {

            foreach ($event->roles as $role) {
                $role->firePermissionEvent(new PermissionRevoked(collect([$event->target]), $role));
            }
            return;
        }

        if (is_a($event,RoleSynched::class,true) && is_a($event->target, \Spatie\Permission\Contracts\Permission::class, true)) {
            foreach ($event->role_revoked as $revoked){
                $revoked->firePermissionEvent(new PermissionRevoked(collect([$event->target]), $revoked));
            }
            foreach ($event->role_added as $assigned){
                $assigned->firePermissionEvent(new PermissionAssigned(collect([$event->target]), $assigned));
            }
            return;
        }

        event($event);
    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|\Spatie\Permission\Contracts\Role ...$roles
     *
     * @return $this
     */
    public function assignRole(...$roles)
    {
        $this->assignRoleBase($roles);
        if ($this->isRoleEventsEnabled()) {
            $model = $this->getModel();
            if ($model->exists) {
                $this->fireRoleEvent(new RoleAssigned(collect($roles), $model));
            } else {
                $class = \get_class($model);

                $class::saved(
                    function ($object) use ($roles, $model) {
                        static $modelLastFiredOn;
                        if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                            return;
                        }
                        $model->fireRoleEvent(new RoleAssigned(collect($roles), $model));
                        $modelLastFiredOn = $object;
                    }
                );
            }
        }

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Spatie\Permission\Contracts\Role $role
     */
    public function removeRole($role)
    {
        $this->removeRoleBase($role);
        $this->fireRoleEvent(new RoleRevoked(collect([$role]), $this));
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|\Spatie\Permission\Contracts\Role|string ...$roles
     *
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $roles_event_was_enabled = $this->isRoleEventsEnabled();
        $permission_event_was_enabled = false;

        if ($roles_event_was_enabled) {
            $old_roles = collect($this->roles()->get());
            $this->disableRoleEvents();
        }

        //var_dump($roles_event_was_enabled);

        $this->syncRolesBase();

        if ($roles_event_was_enabled) {
            $new_roles = collect($this->roles()->get());
            $this->enableRoleEvents();
            $model = $this->getModel();
            if ($model->exists) {
                $this->fireRoleEvent(new RoleSynched($old_roles->diff($new_roles), $new_roles->diff($old_roles), $new_roles,
                    $model));
            }else{
                $class = \get_class($model);

                $class::saved(
                    function ($object) use ($old_roles, $new_roles, $model) {
                        static $modelLastFiredOn;
                        if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                            return;
                        }
                        $this->fireRoleEvent(new RoleSynched($old_roles->diff($new_roles), $new_roles->diff($old_roles), $new_roles,
                            $model));
                        $modelLastFiredOn = $object;
                    }
                );
            }
        }

        return $this;
    }

}
