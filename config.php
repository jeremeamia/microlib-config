<?php

namespace MicroLib\Config;

const DELIMITER = '.';

/**
 * Creates a configuration array, applies defaults, and enforces requirements.
 *
 * @param array $config   Configuration array.
 * @param array $required List of required config values.
 * @param array $defaults Set of default values.
 *
 * @return array
 * @throws Exception if required values are missing.
 */
function create(array $config, array $required = [], array $defaults = [])
{
    $config += $defaults;
    foreach ($required as $key) {
        if (!isset($config[$key])) {
            throw Exception::missingRequiredValue($key);
        }
    }

    return $config;
}

/**
 * Retrieves a value from the configuration array by key or path.
 *
 * @param array  $config    Configuration array.
 * @param string $path      Path to follow through an array. The path is
 *                          delimited by "." unless overwritten with the
 *                          $delimiter parameter.
 * @param string $delimiter Delimiter for paths. Defaults to ".", but can be
 *                          changed if the keys contain ".".
 *
 * @return mixed
 */
function get(array $config, $path, $delimiter = DELIMITER)
{
    $path = is_array($path) ? $path : explode($delimiter, $path);

    $key = array_shift($path);
    $value = isset($config[$key]) ? $config[$key] : null;
    if (is_array($value) && $path) {
        $value = get($value, $path);
    }

    if ($value instanceof _LazyValue) {
        $value = $value();
    }

    return $value;
}

/**
 * Returns a new array that contains only the specified keys from the original.
 *
 * @param array $config Original configuration array.
 * @param array $keep   List of keys that should be kept from the original.
 * @param bool  $fill   If true, missing keys from $keep will be set to null.
 *
 * @return array
 */
function keep(array $config, array $keep, $fill = false)
{
    $kept = [];
    foreach ($keep as $key) {
        if (isset($config[$key])) {
            $kept[$key] = $config[$key];
        } elseif ($fill) {
            $kept[$key] = null;
        }
    }

    return $kept;
}

/**
 * Wraps value in a callable class to be evaluated lazily when it is retrieved.
 *
 * @param callable $valueFactory
 *
 * @return callable
 */
function lazy(callable $valueFactory)
{
    return new _LazyValue($valueFactory);
}

/**
 * Loads configuration data from a file. Supports php, json, ini, xml, and yaml.
 *
 * @param string $path Path to the configuration file.
 * @param string $type Type of configuration (php, json, ini, xml, or yaml).
 *                     This parameter is optional, unless the type cannot be
 *                     determined from the configuration file's file extension.
 *
 * @return array
 * @throws Exception if loading the file or parsing the config data fails.
 */
function load($path, $type = null)
{
    if (!is_readable($path)) {
        throw Exception::cannotLoadFile($path);
    }

    $type = $type ?: strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($type) {
        case 'php':
            $data = include $path;
            break;
        case 'json':
            $data = json_decode(file_get_contents($path), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw Exception::cannotParseJson();
            }
            break;
        case 'ini':
            $data = parse_ini_file($path);
            break;
        case 'xml':
            $data = json_decode(json_encode(simplexml_load_file($path)), true);
            break;
        case 'yaml':
            if (function_exists('yaml_parse_file')) {
                $parseFn = 'yaml_parse_file';
            } elseif (class_exists('Symfony\Component\Yaml\Yaml')) {
                $parseFn = 'Symfony\Component\Yaml\Yaml::parse';
            } else {
                throw Exception::missingYamlLib();
            }
            $data = $parseFn($path);
            break;
        default:
            throw Exception::formatNotSupported();
    }

    if (!is_array($data)) {
        throw Exception::invalidParseResult();
    }

    return $data;
}

/**
 * Validates a schema to ensure it is correctly defined.
 *
 * @param array $schema Schema to validate.
 *
 * @return array
 * @throws Exception if the schema is not valid.
 */
function schema(array $schema)
{
    static $valid = ['default', 'required', 'schema', 'transform', 'validate'];

    foreach ($schema as $key => $providedRules) {
        // Ensure present schema members are correctly typed
        $rules = keep($providedRules, $valid, false);
        if (count($rules) !== count($providedRules)
            || (isset($rules['required']) && !is_bool($rules['required']))
            || (isset($rules['schema']) && !is_array($rules['schema']))
            || (isset($rules['transform']) && !is_callable($rules['transform']))
            || (isset($rules['validate']) && !is_callable($rules['validate']))
        ) {
            throw Exception::invalidSchemaRule($key);
        }

        // Validate sub-schemas.
        if (isset($rules['schema'])) {
            schema($rules['schema']);
        }
    }

    return $schema;
}

/**
 * Validates a configuration array with the provided schema.
 *
 * @param array  $config    Configuration array.
 * @param array  $schema    Schema used to validate the configuration. A schema
 *                          can contain the following keys: default (mixed),
 *                          required (bool), schema (array), transform
 *                          (callable), validate (callable).
 * @param string $delimiter Delimiter used to construct the path.
 * @param null   $namespace (internal-only) Used to track recursion depth.
 *
 * @return array
 * @throws Exception if validation fails.
 */
function validate(
    array $config,
    array $schema = [],
    $delimiter = DELIMITER,
    $namespace = null
) {
    foreach ($schema as $key => $s) {
        $path = trim("{$namespace}{$delimiter}{$key}", $delimiter);
        // Apply default value.
        $value = isset($config[$key])
            ? $config[$key]
            : (isset($s['default']) ? $s['default'] : null);

        // Apply transformations.
        if (isset($s['transform']) && is_callable($s['transform'])) {
            $value = $s['transform']($value);
        }
        // Enforce requirements.
        if (isset($s['required']) && $s['required'] && $value === null) {
            throw Exception::missingRequiredValue($key);
        }
        // Handle sub-schemes.
        if (is_array($value) && isset($s['schema']) && is_array($s['schema'])) {
            validate($value, $s['schema'], $path);
        }
        // Enforce validation callbacks.
        if (isset($s['validate']) && is_callable($s['validate'])
            && !is_null($value) && !$s['validate']($value)
        ) {
            throw Exception::doesNotMatchSchema($key);
        }

        $config[$key] = $value;
    }

    return $config;
}

/**
 * Exception class for the MicroConfig library.
 *
 * @package MicroConfig
 */
class Exception extends \Exception
{
    public static function cannotLoadFile($file)
    {
        return new self("Cannot load the configuration file: {$file}.");
    }

    public static function cannotParseJson()
    {
        return new self('Cannot parse the provided json configuration data.');
    }

    public static function missingYamlLib()
    {
        return new self('There is no YAML parser available. Try Symfony YAML.');
    }

    public static function formatNotSupported()
    {
        return new self('The configuration format is not supported.');
    }

    public static function invalidParseResult()
    {
        return new self('The parsed configuration data should be an array.');
    }

    public static function missingRequiredValue($key)
    {
        return new self("Missing required value for \"{$key}\" in the config.");
    }

    public static function doesNotMatchSchema($key)
    {
        return new self("Invalid key \"{$key}\" does not match the schema.");
    }

    public static function invalidSchemaRule($key)
    {
        return new self("Invalid rule provided in the schema for \"{$key}\".");
    }
}

/**
 * Used to provide lazy values via the lazy() function.
 *
 * Do not use this class directly, it is an implementation detail.
 *
 * @internal
 */
final class _LazyValue
{
    private $createValue;

    public function __construct(callable $createValue)
    {
        $this->createValue = $createValue;
    }

    public function __invoke()
    {
        return call_user_func($this->createValue);
    }
}
