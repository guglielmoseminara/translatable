# Translatable Eloquent models (Laravel 5.2+)

**Still in development - stable release will be available soon!**

This package provides a powerful and extremely easy way of managing multilingual models in Eloquent.

It makes use of Laravel's 5.2 enhanced global scopes to join translated attributes to every query rather than utilizing
relations (the way it's done in this excellent package by dimsav: https://github.com/dimsav/laravel-translatable). As a
result only a single query is needed to fetch translated attributes and there is no need to create separate models for
translation tables, making this package more powerful and easier to use. 

## Quick demo

To enable translations for your models, you first need to prepare your schema according to the convention. Then you can
pull in the ``Translatable`` trait:

```php
use Laraplus\Data\Translatable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Translatable;
}
```

And that's it! No other configuration is required. The translated attributes will be automatically cached and all your
queries will start returning translated attributes:

```php
Post::first();
$post->title; // title in the current locale

Post::translateInto('de')->first();
$post->title; // title in 'de' locale

Post::translateInto('de')->withFallback('en')->first();
$post->title; // title in 'de' if available, otherwise in 'en'
```

Since translations are joined to the query, it is also very easy to filter and order by translated attributes:

```php
Post::where('body', 'LIKE', '%Laravel%')->orderBy('title', 'desc');
```

Or even return only translated records:

```php
Post::onlyTranslated()->all()
```

Multiple helpers are available for all basic CRUD operations. For all available options, read the full documentation below.


## Installation

This package can be used within Laravel or Lumen applications as well as any other application that utilizes Laravel's
database component https://github.com/illuminate/database. The package can be installed through composer:

```
composer require laraplus/translatable
```

### Configuration in Laravel

To configure the package, add a service provider to your ``app.php`` configuration file, under the ``providers`` key:

```php
'providers' => [
    // other providers
    Laraplus\Data\TranslatableServiceProvider::class
];
```

Optionally you can also configure some other options by publishing the ``translatable.php`` configuration file:

```
php artisan vendor:publish --provider="Laraplus\Data\TranslatableServiceProvider" --tag="config"
```

See the configuration file to check all available configuration options:
https://github.com/laraplus/translatable/blob/master/config/translatable.php

### Configuration outside Laravel

When using this package outside Laravel, you can configure it using ``TranslatableConfig`` class:

```php
TranslatableConfig::currentLocaleGetter(function() {
    // return the current locale of the application
});
TranslatableConfig::fallbackLocaleGetter(function() {
    // return the fallback locale of the application
});
```

You can optionally adjust other settings as well. To see all available options inspect Laravel's Service Provider:
https://github.com/laraplus/translatable/blob/master/src/TranslatableServiceProvider.php

## Creating migrations

To utilize multilingual models you need to prepare your database tables in a certain was. Each translatable table
consists of translatable and non translatable attributes. While non translatable attributes can be added to your table
normally, translatable fields need to be in their own table named according to the convention.

Below you can see a sample migration for the ``posts`` table:

```php
Schema::create('posts', function(Blueprint $table)
{
    $table->increments('id');
    $table->datetime('published_at');
    $table->timestamps();
});

Schema::create('posts_i18n', function(Blueprint $table)
{
    $table->integer('post_id')->unsigned();
    $table->string('locale', 6);
    $table->string('title');
    $table->string('body');
    
    $table->primary('post_id', 'locale');
});
```

By default, translation tables must end with ``_i18`` suffix but that can be changed in the configuration file. Also,
translation table must contain a foreign key to the parent table as well as a ``locale`` field (also configurable) 
which will contain the locale of translated attributes. Incrementing keys are not allowed on translation models. A
composite key containing ``locale`` and foreign key reference to the parent model needs to be defined instead.
Optionally you may also define foreign key constraints, but the package will work without them as well.

**Important: make sure that no translated attributes are named the same as any non translated attribute since that will
break the queries. This also applies to timestamps (which should not be added to the translation tables but to primary
tables only) and for incrementing keys (not allowed on translation tables).**

## Configuring models

To make your models aware of translated attributes you need to pull in the ``Translatable`` trait:

```php
use Laraplus\Data\Translatable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Translatable;
}
```

Optionally you may also define an array of ``$translatable`` attributes, but the package is designed to work without it.
In that case translatable attributes will be automatically determined from the database schema and cached indefinitely.
If you are using the cache approach don't forget to clear the cache every time the schema changes.

By default, if the model is not translated into the current locale, fallback translations will be selected instead.
If no translations are available, null will be returned for all translatable attributes. If you wish to change that
behavior you can either modify the ``translatable.php`` configuration file or adjust the behavior on "per model" basis:

```php
class Post extends Model
{
    use Translatable;
    
    protected $withFallback = false;
    
    protected $onlyTranslated = true;
}
```

## Basic CRUD operations

### Selecting rows

TODO

### Inserting new rows

TODO

### Updating existing rows

TODO

### Deleting rows

TODO

## Translations as HasMany relation

TODO

