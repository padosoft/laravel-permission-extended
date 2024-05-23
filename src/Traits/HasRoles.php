<?php

namespace Padosoft\Laravel\PermissionExtended\Traits;

use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;
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

    use HasPermissions;

    private $roleClass;

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
        if (!$this->isRoleEventsEnabled()) {
            return;
        }
        //check if the model target is a permission interface
        if (is_a($event, RoleAssigned::class, true) && is_a($event->target,
                \Padosoft\Laravel\PermissionExtended\Models\Permission::class,
                true) && $event->target->isPermissionEventsEnabled()) {
            foreach ($event->roles as $role) {
                event(new PermissionAssigned(collect([$event->target]), is_object($role)?$role:\Padosoft\Laravel\PermissionExtended\Models\Role::findByName($role)));
            }

            return;
        }

        if (is_a($event, RoleRevoked::class, true) && is_a($event->target,
                \Padosoft\Laravel\PermissionExtended\Models\Permission::class,
                true) && $event->target->isPermissionEventsEnabled()) {
            foreach ($event->roles as $role) {
                event(new PermissionRevoked(collect([$event->target]), is_object($role)?$role:\Padosoft\Laravel\PermissionExtended\Models\Role::findByName($role)));
            }

            return;
        }

        if (is_a($event, RoleSynched::class, true) && is_a($event->target,
                \Padosoft\Laravel\PermissionExtended\Models\Permission::class,
                true) && $event->target->isPermissionEventsEnabled()) {
            foreach ($event->roles_revoked as $role) {
                event(new PermissionRevoked(collect([$event->target]), is_object($role)?$role:\Padosoft\Laravel\PermissionExtended\Models\Role::findByName($role)));
            }
            foreach ($event->roles_added as $role) {
                event(new PermissionAssigned(collect([$event->target]), is_object($role)?$role:\Padosoft\Laravel\PermissionExtended\Models\Role::findByName($role)));
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

        $this->syncRolesBase($roles);

        if ($roles_event_was_enabled) {
            $new_roles = collect($this->roles()->get());
            $this->enableRoleEvents();
            $model = $this->getModel();
            if ($model->exists) {
                $this->fireRoleEvent(new RoleSynched($old_roles->diff($new_roles), $new_roles->diff($old_roles),
                    $new_roles,
                    $model));
            } else {
                $class = \get_class($model);

                $class::saved(
                    function ($object) use ($old_roles, $new_roles, $model) {
                        static $modelLastFiredOn;
                        if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                            return;
                        }
                        $this->fireRoleEvent(new RoleSynched($old_roles->diff($new_roles), $new_roles->diff($old_roles),
                            $new_roles,
                            $model));
                        $modelLastFiredOn = $object;
                    }
                );
            }
        }

        return $this;
    }

    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->roles()->detach();
        });
    }

    public function getRoleClass()
    {
        if (!isset($this->roleClass)) {
            $this->roleClass = app(PermissionRegistrar::class)->getRoleClass();
        }

        return $this->roleClass;
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        );
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     * @param string $guard
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRole(Builder $query, $roles, $guard = null): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) use ($guard) {
            if ($role instanceof Role) {
                return $role;
            }

            $method = is_numeric($role) ? 'findById' : 'findByName';
            $guard = $guard ?: $this->getDefaultGuardName();

            return $this->getRoleClass()->{$method}($role, $guard);
        }, $roles);

        return $query->whereHas('roles', function ($query) use ($roles) {
            $query->where(function ($query) use ($roles) {
                foreach ($roles as $role) {
                    $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                }
            });
        });
    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|\Spatie\Permission\Contracts\Role ...$roles
     *
     * @return $this
     */
    public function assignRoleBase(...$roles)
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                if (empty($role)) {
                    return false;
                }

                return $this->getStoredRole($role);
            })
            ->filter(function ($role) {
                return $role instanceof Role;
            })
            ->each(function ($role) {
                $this->ensureModelSharesGuard($role);
            })
            ->map->id
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->roles()->sync($roles, false);
            $model->load('roles');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($roles, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->roles()->sync($roles, false);
                    $object->load('roles');
                    $modelLastFiredOn = $object;
                });
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Spatie\Permission\Contracts\Role $role
     */
    public function removeRoleBase($role)
    {
        $this->roles()->detach($this->getStoredRole($role));

        $this->load('roles');

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|\Spatie\Permission\Contracts\Role|string ...$roles
     *
     * @return $this
     */
    public function syncRolesBase(...$roles)
    {
        $this->roles()->detach();

        return $this->assignRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasRole($roles): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        if (is_int($roles)) {
            return $this->roles->contains('id', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', $roles->id);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($this->roles)->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * @param string|array|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @param string|\Spatie\Permission\Contracts\Role|\Illuminate\Support\Collection $roles
     *
     * @return bool
     */
    public function hasAllRoles($roles): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect($this->getRoleNames()) == $roles;
    }

    /**
     * Return all permissions directly coupled to the model.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    protected function getStoredRole($role): Role
    {
        $roleClass = $this->getRoleClass();

        if (is_numeric($role)) {
            return $roleClass->findById($role, $this->getDefaultGuardName());
        }

        if (is_string($role)) {
            return $roleClass->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (!in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }

}
