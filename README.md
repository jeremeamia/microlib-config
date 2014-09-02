MicroLib — Config
=================

A small, PHP, configuration library consisting mostly of functions. Yep,
_functions_, because working with configuration arrays should be simple and
fast.

Features
--------

* Small library. No bloat. Easy-to-use functions.
* Supports PHP array, JSON, INI, YAML, and XML config file formats.
* Easy to apply defaults, enforce required settings, and filter out
  extraneous data.
* Can specify a schema to recursively validate the data. Uses callables for
  validations and transformations.
* Easily fetch values from nested config structure.

Usage
-----

Validating Against a Schema
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Let's say you have a config file that looks like this:

```json
{
    "first_name": "Jeremy ",
    "last_name": "Lindblom",
    "phone": {"number": "5559997777"},
    "gender": "whocares"
}
```

In your PHP script…

1. Include the Config library:

```php
require '/path/to/vendor/autoload.php';

use MicroLib\Config as cfg;
```

2. Load the config file.

```php
$config = cfg\load('config.json');
php

3. Define a schema for the data. Using the ``schema`` function is optional,
   but is useful for validating that the schema is defined correctly. (Note:
   The schema can be placed in a separate file and loaded with the ``load``
   function like other config data.)

```php
$schema = cfg\schema([
    'first_name' => [
        'required'  => true,
        'transform' => 'trim',
        'validate'  => 'ctype_alpha'
    ],
    'last_name' => [
        'required'  => true,
        'transform' => 'trim',
        'validate'  => 'ctype_alpha'
    ],
    'phone' => [
        'schema' => [
             'type' => [
                 'default'   => 'mobile',
                 'transform' => 'trim',
                 'validate'  => 'ctype_alpha',
             ],
             'number' => [
                 'required' => true,
                 'validate' => 'ctype_digit',
             ]
        ]
    ],
]);
```

4. Validate the configuration data with the schema. If no exception is thrown,
   then the data is valid.

```php
$config = cfg\validate($config, $schema);
```

5. Fetch data from the config using ``get``.

```php
echo cfg\get($config, 'phone.number');
#> 5559997777

echo cfg\get($config, 'phone.type');
#> mobile
```
