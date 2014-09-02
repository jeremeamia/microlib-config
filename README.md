# MicroLib — Config

A small, PHP, configuration library consisting mostly of functions. Yep,
_functions_, because working with configuration arrays should be simple and
fast.

## Features

* Small library. No bloat. Easy-to-use functions.
* Supports PHP array, JSON, INI, YAML, and XML config file formats.
* Easy to apply defaults, enforce required settings, and filter out
  extraneous data.
* Can specify a schema to recursively validate the data. Uses callables for
  validations and transformations.
* Easily fetch values from nested config structure.

## Usage

### Validating Against a Schema

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

Include the Config library.

```php
require '/path/to/vendor/autoload.php';

use MicroLib\Config as cfg;
```

Load the config file.

```php
$config = cfg\load('config.json');
```

Define a schema for the data. Using the `schema` function is optional but is
useful for validating that the schema is defined correctly. (Note: The schema
can be placed in a separate file and loaded with the `load` function like other
config data.)

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

Validate the configuration data with the schema. If no exception is thrown,
then the data is valid.

```php
$config = cfg\validate($config, $schema);
```

Fetch data from the config using `get`.

```php
echo cfg\get($config, 'phone.number');
#> 5559997777

echo cfg\get($config, 'phone.type');
#> mobile
```

### Simple Check for Required Keys

If you don't need the functionality of the schemas, there is still an easy way
to enforce required settings and apply default values.

Let's say you have the following array of configuration data:

```php
$config = [
    'class'  => 'rogue',
    'race'   => 'half-elf',
    'level'  => 5,
    'weapon' => 'short sword',
];
```

If you aren't concerned about all of the items, you can use the `keep`
function to create a new array of only the items you want.

```php
$config = cfg\keep($config, ['class', 'race', 'level']);
```

Then, using the `create` function, you can check requirements and apply
defaults.

```php
$config = cfg\create(
	$config,
	['class', 'race', 'level'],
	['status' => 'normal']
);

print_r($config);
#> Array
#> (
#>     [class] => rogue
#>     [race] => half-elf
#>     [level] => 5
#>     [status] => normal
#> )
```
