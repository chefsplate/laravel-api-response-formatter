# Laravel API Response Formatter [WIP]
The Laravel API Response Formatter allows developers to generate consistent, yet, customizable JSON responses for your front-end interfaces. This response formatter integrates seamlessly with the Doctrine ORM/ODM and is compatible with our [Laravel Doctrine 2 ODM for MongoDB](https://github.com/chefsplate/laravel-doctrine-odm).
 
Please check out the [chefsplate/laravel-doctrine-odm-example](https://github.com/chefsplate/laravel-doctrine-odm-example) repo for a fully-working example of how this package can be used to create a Doctrine-based API on top of Mongo.

Requirements
------------
- PHP 5.4+
- Laravel 5.1+

Installation
------------

Require the latest version of this package with Composer:

    composer require chefsplate/laravel-api-response-formatter:"0.1.x"


Response Formats
----------------

This Laravel API Response Formatter allows you to customize which fields you want returned to the front-end from your APIs.

There are two simple steps you'll need to follow to make use of response formats in your code.

## Step One: Define your response formats

First, we define which fields in the model we want to have returned. For example, let's assume your user has the following fields: 
`id`, `username`, `first_name`, `last_name`, `email` and `password`. Upon returning a user object to the front-end, 
we don't ever want to return the `password` field. This can be done by creating a `default` response format within the 
`User.php` model:

    protected static $response_formats = [
        'default' => ['password'],
    ]

The response format is a blacklist array of fields you **_don't_** want in the response. By default, all fields are returned.

So if you don't need the first name, last name or password, you would specify:

    protected static $response_formats = [
        'default' => ['first_name', 'last_name', 'password'],
    ]

Note that this gets pretty cumbersome if there are more fields you don't want then the fields that you do want. As an
alternative, you can specify to exclude all fields using the `*` symbol, and include only the ones you want using the special `|except:` syntax. 
This makes the response format behave more like a whitelist.

As an example:

    'default'   => ['*|except:first_name,email'],

Which means, exclude everything except `first_name` and `email`.

### Multiple response formats for a model

You can add as many named formats as you want here:

For example, if we wanted to add a new response format for formatting emails that only contains the user's first name 
and email address, we could do something like:

    protected static $response_formats = [
        'default' => ['password'],
        'email'   => ['*|except:first_name,email'],
    ]    

### Nested response formats

If your model references other models, you can form complex response formats that restrict what is returned by the referenced models.
For example, if a `Project` model contains a reference to the `User` model, you can specify which user fields you want 
returned (again, all fields for each model are returned by default).

Within `Project.php`:

    protected static $response_formats = [
        'listing_view' => ['created_at', 'updated_at', 'user.*|except:id,username'],
    ]

This example combines both exclusion and inclusion type filters. This corresponds to saying: don't return me
the `created_at` and `updated_at` properties, and also don't return any of the `user` fields except `user.id` and 
`user.username`.

This allows for some very powerful nested response formats while maintaining simplicity in syntax.


## Step Two: Inform your controller endpoints of which response formats to use
 
Now that the formats have been defined in the models, you can specify which models you would like to use when 
returning the payload back to the front-end.
 
Within your controller:
 
    return (new ResponseObject($projects))
        ->setResponseFormatsForModels(
            [
                Project::class   => 'listing_view',
                User::class      => 'email',
            ]
        );

To demonstrate the power of response formats, consider the following:

Here `$projects` is an array of `Project`s, which contain references to `User`s and an embedded list of `Comment`s, 
and the `Comment` model references the `User` model. Since the response format for `Comment` is not defined here, `default` 
is assumed. If the `default` response format is not defined within `Comment.php`, then all fields will be returned. 

However, since we specified the response format to use for `User`s, our API response formatter will format the `User` 
entity referenced within `Comment` using the `email` response format automatically. The `listing_view` on `Project` (as we described 
above) already indicates how it would like to format its `User`s references, so it is not formatted using the `email` 
response format.