# laravel-permission-extended
Extension of spatie/laravel-permission with event firing when permissions or roles are assigned or removed

* [Installation](#installation)
* [Usage](#usage)

This package use spatie/laravel-permission and allows to manage user permissions and roles in a database.

It also fire specific events when a permission or role is assigned or revoked.  

## Installation

This package can be used in Laravel 5.4 or higher.

You can install the package via composer:
``` bash
composer require padosoft/laravel-permission-extended
```

In Laravel 5.5 the service provider will automatically get registered. In older versions of the framework just add the service provider in `config/app.php` file:
```php
'providers' => [
    // ...
    Spatie\Permission\PermissionServiceProvider::class,
    Padosoft\Laravel\PermissionExtended\PermissionExtendedServiceProvider::class,
];
```
Then publish and run migrations :

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
```

```bash
php artisan migrate
```
Publish configs :
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```
```bash
php artisan vendor:publish --provider="Padosoft\Laravel\PermissionExtended\PermissionExtendedServiceProvider" --tag="config"
```
When published, open the `config/permission.php` config file and change it to uses
this package's specific models 
```php
return [
    'models' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Spatie\Permission\Contracts\Permission` contract.
         */

        'permission' => Padosoft\Laravel\PermissionExtended\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Spatie\Permission\Contracts\Role` contract.
         */

        'role' => Padosoft\Laravel\PermissionExtended\Models\Role::class,

    ],
...
```

## Usage
For general usage of the package please look at [documentation of spatie/laravel-permission](https://github.com/spatie/laravel-permission/).

If you want to listen the events fired when a permission/role is assigned or revoked you can do that as follows.

1) Create a Listener class:
```php

class PermissionsEventSubscriber
{
    public function onPermissionAssigned(PermissionAssigned $event)
    {
        //$event->permission is a Collection of Permissions
        //$event->target is an Eloquent model 
    }
}
```
