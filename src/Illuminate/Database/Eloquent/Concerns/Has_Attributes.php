<?php

declare (strict_types=1);
namespace Illuminate\Database\Eloquent\Concerns;

use Backed_Enum;
use Brick\Math\Big_Decimal;
use Brick\Math\Exception\Math_Exception as BrickMathException;
use Brick\Math\Rounding_Mode;
use Carbon\Carbon_Immutable;
use Carbon\Carbon_Interface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\Casts_Inbound_Attributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Casts\As_Array_Object;
use Illuminate\Database\Eloquent\Casts\As_Collection;
use Illuminate\Database\Eloquent\Casts\As_Encrypted_Array_Object;
use Illuminate\Database\Eloquent\Casts\As_Encrypted_Collection;
use Illuminate\Database\Eloquent\Casts\As_Enum_Array_Object;
use Illuminate\Database\Eloquent\Casts\As_Enum_Collection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Invalid_Cast_Exception;
use Illuminate\Database\Eloquent\Json_Encoding_Exception;
use Illuminate\Database\Eloquent\Missing_Attribute_Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Lazy_Loading_Violation_Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as BaseCollection;
use function Illuminate\Support\enum_value;
use Illuminate\Support\Exceptions\Math_Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Stringable;
use Value_Error;
trait Has_Attributes
{
    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];
    /**
     * The model attribute's original state.
     *
     * @var array<string, mixed>
     */
    protected $original = [];
    /**
     * The changed model attributes.
     *
     * @var array<string, mixed>
     */
    protected $changes = [];
    /**
     * The previous state of the changed model attributes.
     *
     * @var array<string, mixed>
     */
    protected $previous = [];
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];
    /**
     * The attributes that have been cast using custom classes.
     *
     * @var array
     */
    protected $class_cast_cache = [];
    /**
     * The attributes that have been cast using "Attribute" return type mutators.
     *
     * @var array
     */
    protected $attribute_cast_cache = [];
    /**
     * The built-in, primitive cast types supported by Eloquent.
     *
     * @var string[]
     */
    protected static $primitive_cast_types = ['array', 'bool', 'boolean', 'collection', 'custom_datetime', 'date', 'datetime', 'decimal', 'double', 'encrypted', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object', 'float', 'hashed', 'immutable_date', 'immutable_datetime', 'immutable_custom_datetime', 'int', 'integer', 'json', 'json:unicode', 'object', 'real', 'string', 'timestamp'];
    /**
     * The storage format of the model's date columns.
     *
     * @var string|null
     */
    protected $date_format;
    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];
    /**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
    public static $snake_attributes = true;
    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static $mutator_cache = [];
    /**
     * The cache of the "Attribute" return type marked mutated attributes for each class.
     *
     * @var array
     */
    protected static $attribute_mutator_cache = [];
    /**
     * The cache of the "Attribute" return type marked mutated, gettable attributes for each class.
     *
     * @var array
     */
    protected static $get_attribute_mutator_cache = [];
    /**
     * The cache of the "Attribute" return type marked mutated, settable attributes for each class.
     *
     * @var array
     */
    protected static $set_attribute_mutator_cache = [];
    /**
     * The cache of the converted cast types.
     *
     * @var array
     */
    protected static $cast_type_cache = [];
    /**
     * The encrypter instance that is used to encrypt attributes.
     *
     * @var \Illuminate\Contracts\Encryption\Encrypter|null
     */
    public static $encrypter;
    /**
     * Initialize the trait.
     *
     * @return void
     */
    protected function initialize_has_attributes()
    {
        $this->casts = $this->ensure_casts_are_string_values(array_merge($this->casts, $this->casts()));
    }
    /**
     * Convert the model's attributes to an array.
     *
     * @return array<string, mixed>
     */
    public function attributes_to_array()
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->add_date_attributes_to_array($attributes = $this->get_arrayable_attributes());
        $attributes = $this->add_mutated_attributes_to_array($attributes, $mutated_attributes = $this->get_mutated_attributes());
        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->add_cast_attributes_to_array($attributes, $mutated_attributes);
        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->get_arrayable_appends() as $key) {
            $attributes[$key] = $this->mutate_attribute_for_array($key, null);
        }
        return $attributes;
    }
    /**
     * Add the date attributes to the attributes array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function add_date_attributes_to_array(array $attributes): array
    {
        foreach ($this->get_dates() as $key) {
            if (is_null($key)) {
                continue;
            }
            if (!isset($attributes[$key])) {
                continue;
            }
            $attributes[$key] = $this->serialize_date($this->as_date_time($attributes[$key]));
        }
        return $attributes;
    }
    /**
     * Add the mutated attributes to the attributes array.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $mutatedAttributes
     * @return array<string, mixed>
     */
    protected function add_mutated_attributes_to_array(array $attributes, array $mutated_attributes): array
    {
        foreach ($mutated_attributes as $key) {
            // We want to spin through all the mutated attributes for this model and call
            // the mutator for the attribute. We cache off every mutated attributes so
            // we don't have to constantly check on attributes that actually change.
            if (!array_key_exists($key, $attributes)) {
                continue;
            }
            // Next, we will call the mutator for this attribute so that we can get these
            // mutated attribute's actual values. After we finish mutating each of the
            // attributes we will return this final array of the mutated attributes.
            $attributes[$key] = $this->mutate_attribute_for_array($key, $attributes[$key]);
        }
        return $attributes;
    }
    /**
     * Add the casted attributes to the attributes array.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $mutatedAttributes
     * @return array<string, mixed>
     */
    protected function add_cast_attributes_to_array(array $attributes, array $mutated_attributes): array
    {
        foreach ($this->get_casts() as $key => $value) {
            if (!array_key_exists($key, $attributes)) {
                continue;
            }
            if (in_array($key, $mutated_attributes)) {
                continue;
            }
            // Here we will cast the attribute. Then, if the cast is a date or datetime cast
            // then we will serialize the date for the array. This will convert the dates
            // to strings based on the date format specified for these Eloquent models.
            $attributes[$key] = $this->cast_attribute($key, $attributes[$key]);
            // If the attribute cast was a date or a datetime, we will serialize the date as
            // a string. This allows the developers to customize how dates are serialized
            // into an array without affecting how they are persisted into the storage.
            if (isset($attributes[$key]) && in_array($value, ['date', 'datetime', 'immutable_date', 'immutable_datetime'])) {
                $attributes[$key] = $this->serialize_date($attributes[$key]);
            }
            if (isset($attributes[$key]) && ($this->is_custom_date_time_cast($value) || $this->is_immutable_custom_date_time_cast($value))) {
                $attributes[$key] = $attributes[$key]->format(explode(':', (string) $value, 2)[1]);
            }
            if ($attributes[$key] instanceof DateTimeInterface && $this->is_class_castable($key)) {
                $attributes[$key] = $this->serialize_date($attributes[$key]);
            }
            if (isset($attributes[$key]) && $this->is_class_serializable($key)) {
                $attributes[$key] = $this->serialize_class_castable_attribute($key, $attributes[$key]);
            }
            if ($this->is_enum_castable($key) && !($attributes[$key] ?? null) instanceof Arrayable) {
                $attributes[$key] = isset($attributes[$key]) ? $this->get_storable_enum_value($this->get_casts()[$key], $attributes[$key]) : null;
            }
            if ($attributes[$key] instanceof Arrayable) {
                $attributes[$key] = $attributes[$key]->to_array();
            }
        }
        return $attributes;
    }
    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array<string, mixed>
     */
    protected function get_arrayable_attributes()
    {
        return $this->get_arrayable_items($this->get_attributes());
    }
    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function get_arrayable_appends()
    {
        if (!count($this->appends)) {
            return [];
        }
        return $this->get_arrayable_items(array_combine($this->appends, $this->appends));
    }
    /**
     * Get the model's relationships in array form.
     */
    public function relations_to_array(): array
    {
        $attributes = [];
        foreach ($this->get_arrayable_relations() as $key => $value) {
            // If the values implement the Arrayable interface we can just call this
            // toArray method on the instances which will convert both models and
            // collections to their proper array form and we'll set the values.
            if ($value instanceof Arrayable) {
                $relation = $value->to_array();
            } elseif (is_null($value)) {
                $relation = $value;
            }
            // If the relationships snake-casing is enabled, we will snake case this
            // key so that the relation attribute is snake cased in this returned
            // array to the developers, making this consistent with attributes.
            if (static::$snake_attributes) {
                $key = Str::snake($key);
            }
            // If the relation value has been set, we will set it on this attributes
            // list for returning. If it was not arrayable or null, we'll not set
            // the value on the array because it is some type of invalid value.
            if (array_key_exists('relation', get_defined_vars())) {
                // check if $relation is in scope (could be null)
                $attributes[$key] = $relation ?? null;
            }
            unset($relation);
        }
        return $attributes;
    }
    /**
     * Get an attribute array of all arrayable relations.
     *
     * @return array
     */
    protected function get_arrayable_relations()
    {
        return $this->get_arrayable_items($this->relations);
    }
    /**
     * Get an attribute array of all arrayable values.
     *
     * @return array
     */
    protected function get_arrayable_items(array $values)
    {
        if (count($this->get_visible()) > 0) {
            $values = array_intersect_key($values, array_flip($this->get_visible()));
        }
        if (count($this->get_hidden()) > 0) {
            return array_diff_key($values, array_flip($this->get_hidden()));
        }
        return $values;
    }
    /**
     * Determine whether an attribute exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function has_attribute($key)
    {
        if (!$key) {
            return false;
        }
        if (array_key_exists($key, $this->attributes)) {
            return true;
        }
        if (array_key_exists($key, $this->casts)) {
            return true;
        }
        if ($this->has_get_mutator($key)) {
            return true;
        }
        if ($this->has_attribute_mutator($key)) {
            return true;
        }
        return (bool) $this->is_class_castable($key);
    }
    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get_attribute($key)
    {
        if (!$key) {
            return;
        }
        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if ($this->has_attribute($key)) {
            return $this->get_attribute_value($key);
        }
        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(self::class, $key)) {
            return $this->throw_missing_attribute_exception_if_applicable($key);
        }
        return $this->is_relation($key) || $this->relation_loaded($key) ? $this->get_relation_value($key) : $this->throw_missing_attribute_exception_if_applicable($key);
    }
    /**
     * Either throw a missing attribute exception or return null depending on Eloquent's configuration.
     *
     * @param  string  $key
     *
     * @throws \Illuminate\Database\Eloquent\MissingAttributeException
     */
    protected function throw_missing_attribute_exception_if_applicable($key)
    {
        if ($this->exists && !$this->was_recently_created && static::prevents_accessing_missing_attributes()) {
            if (isset(static::$missing_attribute_violation_callback)) {
                return call_user_func(static::$missing_attribute_violation_callback, $this, $key);
            }
            throw new Missing_Attribute_Exception($this, $key);
        }
        return null;
    }
    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    public function get_attribute_value($key)
    {
        return $this->transform_model_value($key, $this->get_attribute_from_array($key));
    }
    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function get_attribute_from_array($key)
    {
        $this->merge_attribute_from_cached_casts($key);
        return $this->attributes[$key] ?? null;
    }
    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get_relation_value($key)
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relation_loaded($key)) {
            return $this->relations[$key];
        }
        if (!$this->is_relation($key)) {
            return;
        }
        if ($this->attempt_to_autoload_relation($key)) {
            return $this->relations[$key];
        }
        if ($this->prevents_lazy_loading) {
            $this->handle_lazy_loading_violation($key);
        }
        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's value on the "relationships" array.
        return $this->get_relationship_from_method($key);
    }
    /**
     * Determine if the given key is a relationship method on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function is_relation($key)
    {
        if ($this->has_attribute_mutator($key)) {
            return false;
        }
        return method_exists($this, $key) || $this->relation_resolver(static::class, $key);
    }
    /**
     * Handle a lazy loading violation.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function handle_lazy_loading_violation($key)
    {
        if (isset(static::$lazy_loading_violation_callback)) {
            return call_user_func(static::$lazy_loading_violation_callback, $this, $key);
        }
        if (!$this->exists || $this->was_recently_created) {
            return;
        }
        throw new Lazy_Loading_Violation_Exception($this, $key);
    }
    /**
     * Get a relationship value from a method.
     *
     * @param  string  $method
     * @return mixed
     *
     * @throws \LogicException
     */
    protected function get_relationship_from_method($method)
    {
        $relation = $this->{$method}();
        if (!$relation instanceof Relation) {
            if (is_null($relation)) {
                throw new LogicException(sprintf('%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method));
            }
            throw new LogicException(sprintf('%s::%s must return a relationship instance.', static::class, $method));
        }
        return tap($relation->get_results(), function ($results) use ($method): void {
            $this->set_relation($method, $results);
        });
    }
    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     */
    public function has_get_mutator($key): bool
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }
    /**
     * Determine if a "Attribute" return type marked mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function has_attribute_mutator($key)
    {
        if (isset(static::$attribute_mutator_cache[$this::class][$key])) {
            return static::$attribute_mutator_cache[$this::class][$key];
        }
        if (!method_exists($this, $method = Str::camel($key))) {
            return static::$attribute_mutator_cache[$this::class][$key] = false;
        }
        $return_type = (new ReflectionMethod($this, $method))->get_return_type();
        return static::$attribute_mutator_cache[$this::class][$key] = $return_type instanceof ReflectionNamedType && $return_type->get_name() === Attribute::class;
    }
    /**
     * Determine if a "Attribute" return type marked get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function has_attribute_get_mutator($key)
    {
        if (isset(static::$get_attribute_mutator_cache[$this::class][$key])) {
            return static::$get_attribute_mutator_cache[$this::class][$key];
        }
        if (!$this->has_attribute_mutator($key)) {
            return static::$get_attribute_mutator_cache[$this::class][$key] = false;
        }
        return static::$get_attribute_mutator_cache[$this::class][$key] = is_callable($this->{Str::camel($key)}()->get);
    }
    /**
     * Determine if any get mutator exists for an attribute.
     *
     * @param  string  $key
     */
    public function has_any_get_mutator($key): bool
    {
        if ($this->has_get_mutator($key)) {
            return true;
        }
        return (bool) $this->has_attribute_get_mutator($key);
    }
    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutate_attribute($key, $value)
    {
        $this->merge_attributes_from_cached_casts();
        return $this->{'get' . Str::studly($key) . 'Attribute'}($value);
    }
    /**
     * Get the value of an "Attribute" return type marked attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutate_attribute_marked_attribute($key, $value)
    {
        if (array_key_exists($key, $this->attribute_cast_cache)) {
            return $this->attribute_cast_cache[$key];
        }
        $this->merge_attributes_from_cached_casts();
        $attribute = $this->{Str::camel($key)}();
        $value = call_user_func($attribute->get ?: fn($value) => $value, $value, $this->attributes);
        if ($attribute->with_caching || is_object($value) && $attribute->with_object_caching) {
            $this->attribute_cast_cache[$key] = $value;
        } else {
            unset($this->attribute_cast_cache[$key]);
        }
        return $value;
    }
    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutate_attribute_for_array($key, $value)
    {
        if ($this->is_class_castable($key)) {
            $value = $this->get_class_castable_attribute_value($key, $value);
        } elseif (isset(static::$get_attribute_mutator_cache[$this::class][$key]) && static::$get_attribute_mutator_cache[$this::class][$key] === true) {
            $value = $this->mutate_attribute_marked_attribute($key, $value);
            $value = $value instanceof DateTimeInterface ? $this->serialize_date($value) : $value;
        } else {
            $value = $this->mutate_attribute($key, $value);
        }
        return $value instanceof Arrayable ? $value->to_array() : $value;
    }
    /**
     * Merge new casts with existing casts on the model.
     *
     * @param  array  $casts
     * @return $this
     */
    public function merge_casts($casts)
    {
        $casts = $this->ensure_casts_are_string_values($casts);
        $this->casts = array_merge($this->casts, $casts);
        return $this;
    }
    /**
     * Ensure that the given casts are strings.
     */
    protected function ensure_casts_are_string_values(array $casts): array
    {
        foreach ($casts as $attribute => $cast) {
            $casts[$attribute] = match (true) {
                is_object($cast) => value(function () use ($cast, $attribute): string {
                    if ($cast instanceof Stringable) {
                        return (string) $cast;
                    }
                    throw new InvalidArgumentException("The cast object for the {$attribute} attribute must implement Stringable.");
                }),
                is_array($cast) => value(function () use ($cast) {
                    if (count($cast) === 1) {
                        return $cast[0];
                    }
                    [$cast, $arguments] = [array_shift($cast), $cast];
                    return $cast . ':' . implode(',', $arguments);
                }),
                default => $cast,
            };
        }
        return $casts;
    }
    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function cast_attribute($key, $value)
    {
        $cast_type = $this->get_cast_type($key);
        if (is_null($value) && in_array($cast_type, static::$primitive_cast_types)) {
            return $value;
        }
        // If the key is one of the encrypted castable types, we'll first decrypt
        // the value and update the cast type so we may leverage the following
        // logic for casting this value to any additionally specified types.
        if ($this->is_encrypted_castable($key)) {
            $value = $this->from_encrypted_string($value);
            $cast_type = Str::after($cast_type, 'encrypted:');
        }
        switch ($cast_type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->from_float($value);
            case 'decimal':
                return $this->as_decimal($value, explode(':', (string) $this->get_casts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->from_json($value, true);
            case 'array':
            case 'json':
            case 'json:unicode':
                return $this->from_json($value);
            case 'collection':
                return new Base_Collection($this->from_json($value));
            case 'date':
                return $this->as_date($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->as_date_time($value);
            case 'immutable_date':
                return $this->as_date($value)->to_immutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->as_date_time($value)->to_immutable();
            case 'timestamp':
                return $this->as_timestamp($value);
        }
        if ($this->is_enum_castable($key)) {
            return $this->get_enum_castable_attribute_value($key, $value);
        }
        if ($this->is_class_castable($key)) {
            return $this->get_class_castable_attribute_value($key, $value);
        }
        return $value;
    }
    /**
     * Cast the given attribute using a custom cast class.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function get_class_castable_attribute_value($key, $value)
    {
        $caster = $this->resolve_caster_class($key);
        $object_caching_disabled = $caster->without_object_caching ?? false;
        if (isset($this->class_cast_cache[$key]) && !$object_caching_disabled) {
            return $this->class_cast_cache[$key];
        }
        $value = $caster instanceof Casts_Inbound_Attributes ? $value : $caster->get($this, $key, $value, $this->attributes);
        if ($caster instanceof Casts_Inbound_Attributes || !is_object($value) || $object_caching_disabled) {
            unset($this->class_cast_cache[$key]);
        } else {
            $this->class_cast_cache[$key] = $value;
        }
        return $value;
    }
    /**
     * Cast the given attribute to an enum.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function get_enum_castable_attribute_value($key, $value)
    {
        if (is_null($value)) {
            return;
        }
        $cast_type = $this->get_casts()[$key];
        if ($value instanceof $cast_type) {
            return $value;
        }
        return $this->get_enum_case_from_value($cast_type, $value);
    }
    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function get_cast_type($key)
    {
        $cast_type = $this->get_casts()[$key];
        if (isset(static::$cast_type_cache[$cast_type])) {
            return static::$cast_type_cache[$cast_type];
        }
        if ($this->is_custom_date_time_cast($cast_type)) {
            $converted_cast_type = 'custom_datetime';
        } elseif ($this->is_immutable_custom_date_time_cast($cast_type)) {
            $converted_cast_type = 'immutable_custom_datetime';
        } elseif ($this->is_decimal_cast($cast_type)) {
            $converted_cast_type = 'decimal';
        } elseif (class_exists($cast_type)) {
            $converted_cast_type = $cast_type;
        } else {
            $converted_cast_type = trim(strtolower((string) $cast_type));
        }
        return static::$cast_type_cache[$cast_type] = $converted_cast_type;
    }
    /**
     * Increment or decrement the given attribute using the custom cast class.
     *
     * @param  string  $method
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function deviate_class_castable_attribute($method, $key, $value)
    {
        return $this->resolve_caster_class($key)->{$method}($this, $key, $value, $this->attributes);
    }
    /**
     * Serialize the given attribute using the custom cast class.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize_class_castable_attribute($key, $value)
    {
        return $this->resolve_caster_class($key)->serialize($this, $key, $value, $this->attributes);
    }
    /**
     * Compare two values for the given attribute using the custom cast class.
     *
     * @param  string  $key
     * @param  mixed  $original
     * @param  mixed  $value
     * @return bool
     */
    protected function compare_class_castable_attribute($key, $original, $value)
    {
        return $this->resolve_caster_class($key)->compare($this, $key, $original, $value);
    }
    /**
     * Determine if the cast type is a custom date time cast.
     *
     * @param  string  $cast
     */
    protected function is_custom_date_time_cast($cast): bool
    {
        return str_starts_with($cast, 'date:') || str_starts_with($cast, 'datetime:');
    }
    /**
     * Determine if the cast type is an immutable custom date time cast.
     *
     * @param  string  $cast
     */
    protected function is_immutable_custom_date_time_cast($cast): bool
    {
        return str_starts_with($cast, 'immutable_date:') || str_starts_with($cast, 'immutable_datetime:');
    }
    /**
     * Determine if the cast type is a decimal cast.
     *
     * @param  string  $cast
     */
    protected function is_decimal_cast($cast): bool
    {
        return str_starts_with($cast, 'decimal:');
    }
    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function set_attribute($key, $value)
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->has_set_mutator($key)) {
            return $this->set_mutated_attribute_value($key, $value);
        }
        if ($this->has_attribute_set_mutator($key)) {
            return $this->set_attribute_marked_mutated_attribute_value($key, $value);
        }
        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        if (!is_null($value) && $this->is_date_attribute($key)) {
            $value = $this->from_date_time($value);
        }
        if ($this->is_enum_castable($key)) {
            $this->set_enum_castable_attribute($key, $value);
            return $this;
        }
        if ($this->is_class_castable($key)) {
            $this->set_class_castable_attribute($key, $value);
            return $this;
        }
        if (!is_null($value) && $this->is_json_castable($key)) {
            $value = $this->cast_attribute_as_json($key, $value);
        }
        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (str_contains($key, '->')) {
            return $this->fill_json_attribute($key, $value);
        }
        if (!is_null($value) && $this->is_encrypted_castable($key)) {
            $value = $this->cast_attribute_as_encrypted_string($key, $value);
        }
        if (!is_null($value) && $this->has_cast($key, 'hashed')) {
            $value = $this->cast_attribute_as_hashed_string($key, $value);
        }
        $this->attributes[$key] = $value;
        return $this;
    }
    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string  $key
     */
    public function has_set_mutator($key): bool
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Attribute');
    }
    /**
     * Determine if an "Attribute" return type marked set mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function has_attribute_set_mutator($key)
    {
        $class = $this::class;
        if (isset(static::$set_attribute_mutator_cache[$class][$key])) {
            return static::$set_attribute_mutator_cache[$class][$key];
        }
        if (!method_exists($this, $method = Str::camel($key))) {
            return static::$set_attribute_mutator_cache[$class][$key] = false;
        }
        $return_type = (new ReflectionMethod($this, $method))->get_return_type();
        return static::$set_attribute_mutator_cache[$class][$key] = $return_type instanceof ReflectionNamedType && $return_type->get_name() === Attribute::class && is_callable($this->{$method}()->set);
    }
    /**
     * Set the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function set_mutated_attribute_value($key, $value)
    {
        $this->merge_attributes_from_cached_casts();
        return $this->{'set' . Str::studly($key) . 'Attribute'}($value);
    }
    /**
     * Set the value of a "Attribute" return type marked attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function set_attribute_marked_mutated_attribute_value($key, $value)
    {
        $this->merge_attributes_from_cached_casts();
        $attribute = $this->{Str::camel($key)}();
        $callback = $attribute->set ?: function ($value) use ($key): void {
            $this->attributes[$key] = $value;
        };
        $this->attributes = array_merge($this->attributes, $this->normalize_cast_class_response($key, $callback($value, $this->attributes)));
        if ($attribute->with_caching || is_object($value) && $attribute->with_object_caching) {
            $this->attribute_cast_cache[$key] = $value;
        } else {
            unset($this->attribute_cast_cache[$key]);
        }
        return $this;
    }
    /**
     * Determine if the given attribute is a date or date castable.
     *
     * @param  string  $key
     */
    protected function is_date_attribute($key): bool
    {
        return in_array($key, $this->get_dates(), true) || $this->is_date_castable($key);
    }
    /**
     * Set a given JSON attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function fill_json_attribute($key, $value)
    {
        [$key, $path] = explode('->', $key, 2);
        $value = $this->as_json($this->get_array_attribute_with_value($path, $key, $value), $this->get_json_cast_flags($key));
        $this->attributes[$key] = $this->is_encrypted_castable($key) ? $this->cast_attribute_as_encrypted_string($key, $value) : $value;
        if ($this->is_class_castable($key)) {
            unset($this->class_cast_cache[$key]);
        }
        return $this;
    }
    /**
     * Set the value of a class castable attribute.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    protected function set_class_castable_attribute($key, $value)
    {
        $caster = $this->resolve_caster_class($key);
        $this->attributes = array_replace($this->attributes, $this->normalize_cast_class_response($key, $caster->set($this, $key, $value, $this->attributes)));
        if ($caster instanceof Casts_Inbound_Attributes || !is_object($value) || ($caster->without_object_caching ?? false)) {
            unset($this->class_cast_cache[$key]);
        } else {
            $this->class_cast_cache[$key] = $value;
        }
    }
    /**
     * Set the value of an enum castable attribute.
     *
     * @param  string  $key
     * @param  \UnitEnum|string|int|null  $value
     * @return void
     */
    protected function set_enum_castable_attribute($key, $value)
    {
        $enum_class = $this->get_casts()[$key];
        if (!isset($value)) {
            $this->attributes[$key] = null;
        } elseif (is_object($value)) {
            $this->attributes[$key] = $this->get_storable_enum_value($enum_class, $value);
        } else {
            $this->attributes[$key] = $this->get_storable_enum_value($enum_class, $this->get_enum_case_from_value($enum_class, $value));
        }
    }
    /**
     * Get an enum case instance from a given class and value.
     *
     * @param  string|int  $value
     * @return \UnitEnum
     */
    protected function get_enum_case_from_value(string $enum_class, $value)
    {
        return is_subclass_of($enum_class, Backed_Enum::class) ? $enum_class::from($value) : constant($enum_class . '::' . $value);
    }
    /**
     * Get the storable value from the given enum.
     *
     * @param  string  $expectedEnum
     * @param  \UnitEnum  $value
     * @return string|int
     */
    protected function get_storable_enum_value($expected_enum, $value)
    {
        if (!$value instanceof $expected_enum) {
            throw new Value_Error(sprintf('Value [%s] is not of the expected enum type [%s].', var_export($value, true), $expected_enum));
        }
        return enum_value($value);
    }
    /**
     * Get an array attribute with the given key and value set.
     *
     * @param  string  $path
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    protected function get_array_attribute_with_value($path, $key, $value)
    {
        return tap($this->get_array_attribute_by_key($key), function (array &$array) use ($path, $value): void {
            Arr::set($array, str_replace('->', '.', $path), $value);
        });
    }
    /**
     * Get an array attribute or return an empty array if it is not set.
     *
     * @param  string  $key
     * @return array
     */
    protected function get_array_attribute_by_key($key)
    {
        if (!isset($this->attributes[$key])) {
            return [];
        }
        return $this->from_json($this->is_encrypted_castable($key) ? $this->from_encrypted_string($this->attributes[$key]) : $this->attributes[$key]);
    }
    /**
     * Cast the given attribute to JSON.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return string
     */
    protected function cast_attribute_as_json($key, $value)
    {
        $value = $this->as_json($value, $this->get_json_cast_flags($key));
        if ($value === false) {
            throw Json_Encoding_Exception::for_attribute($this, $key, json_last_error_msg());
        }
        return $value;
    }
    /**
     * Get the JSON casting flags for the given attribute.
     *
     * @param  string  $key
     */
    protected function get_json_cast_flags($key): int
    {
        $flags = 0;
        if ($this->has_cast($key, ['json:unicode'])) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }
        return $flags;
    }
    /**
     * Encode the given value as JSON.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function as_json($value, int $flags = 0): mixed
    {
        return Json::encode($value, $flags);
    }
    /**
     * Decode the given JSON back into an array or object.
     *
     * @param  string|null  $value
     * @param  bool  $asObject
     * @return mixed
     */
    public function from_json($value, $as_object = false)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Json::decode($value, !$as_object);
    }
    /**
     * Decrypt the given encrypted string.
     *
     * @param  string  $value
     * @return mixed
     */
    public function from_encrypted_string($value)
    {
        return static::current_encrypter()->decrypt($value, false);
    }
    /**
     * Cast the given attribute to an encrypted string.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return string
     */
    protected function cast_attribute_as_encrypted_string(
        $key,
        #[\Sensitive_Parameter]
        $value
    )
    {
        return static::current_encrypter()->encrypt($value, false);
    }
    /**
     * Set the encrypter instance that will be used to encrypt attributes.
     *
     * @param  \Illuminate\Contracts\Encryption\Encrypter|null  $encrypter
     */
    public static function encrypt_using($encrypter): void
    {
        static::$encrypter = $encrypter;
    }
    /**
     * Get the current encrypter being used by the model.
     *
     * @return \Illuminate\Contracts\Encryption\Encrypter
     */
    public static function current_encrypter()
    {
        return static::$encrypter ?? Crypt::get_facade_root();
    }
    /**
     * Cast the given attribute to a hashed string.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return string
     */
    protected function cast_attribute_as_hashed_string(
        $key,
        #[\Sensitive_Parameter]
        $value
    )
    {
        if ($value === null) {
            return null;
        }
        if (!Hash::is_hashed($value)) {
            return Hash::make($value);
        }
        /** @phpstan-ignore staticMethod.notFound */
        if (!Hash::verify_configuration($value)) {
            throw new RuntimeException("Could not verify the hashed value's configuration.");
        }
        return $value;
    }
    /**
     * Decode the given float.
     *
     * @param  mixed  $value
     */
    public function from_float($value): float
    {
        return match ((string) $value) {
            'Infinity' => INF,
            '-Infinity' => -INF,
            'NaN' => NAN,
            default => (float) $value,
        };
    }
    /**
     * Return a decimal as string.
     *
     * @param  float|string  $value
     * @param  int  $decimals
     */
    protected function as_decimal($value, $decimals): string
    {
        try {
            return (string) Big_Decimal::of((string) $value)->to_scale($decimals, Rounding_Mode::HALF_UP);
        } catch (Brick_Math_Exception $e) {
            throw new Math_Exception('Unable to cast value to a decimal.', previous: $e);
        }
    }
    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon
     */
    protected function as_date($value)
    {
        return $this->as_date_time($value)->start_of_day();
    }
    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon
     */
    protected function as_date_time($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon_Interface) {
            return Date::instance($value);
        }
        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return Date::parse($value->format('Y-m-d H:i:s.u'), $value->get_timezone());
        }
        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Date::create_from_timestamp($value, date_default_timezone_get());
        }
        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->is_standard_date_format($value)) {
            return Date::instance(Carbon::create_from_format('Y-m-d', $value)->start_of_day());
        }
        $format = $this->get_date_format();
        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        try {
            $date = Date::create_from_format($format, $value);
        } catch (InvalidArgumentException) {
            $date = false;
        }
        return $date ?: Date::parse($value);
    }
    /**
     * Determine if the given value is a standard date format.
     *
     * @param  string  $value
     */
    protected function is_standard_date_format($value): int|false
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }
    /**
     * Convert a DateTime to a storable string.
     *
     * @param  mixed  $value
     * @return string|null
     */
    public function from_date_time($value)
    {
        return empty($value) ? $value : $this->as_date_time($value)->format($this->get_date_format());
    }
    /**
     * Return a timestamp as unix timestamp.
     *
     * @param  mixed  $value
     * @return int
     */
    protected function as_timestamp($value)
    {
        return $this->as_date_time($value)->get_timestamp();
    }
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @return string
     */
    protected function serialize_date(DateTimeInterface $date)
    {
        return $date instanceof DateTimeImmutable ? Carbon_Immutable::instance($date)->to_json() : Carbon::instance($date)->to_json();
    }
    /**
     * Get the attributes that should be converted to dates.
     *
     * @return array<int, string|null>
     */
    public function get_dates(): array
    {
        return $this->uses_timestamps() ? [$this->get_created_at_column(), $this->get_updated_at_column()] : [];
    }
    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function get_date_format()
    {
        return $this->date_format ?: $this->get_connection()->get_query_grammar()->get_date_format();
    }
    /**
     * Set the date format used by the model.
     *
     * @param  string  $format
     * @return $this
     */
    public function set_date_format($format)
    {
        $this->date_format = $format;
        return $this;
    }
    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param  string  $key
     * @param  array|string|null  $types
     * @return bool
     */
    public function has_cast($key, $types = null)
    {
        if (array_key_exists($key, $this->get_casts())) {
            return $types ? in_array($this->get_cast_type($key), (array) $types, true) : true;
        }
        return false;
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array
     */
    public function get_casts()
    {
        if ($this->get_incrementing()) {
            return array_merge([$this->get_key_name() => $this->get_key_type()], $this->casts);
        }
        return $this->casts;
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function is_date_castable($key)
    {
        return $this->has_cast($key, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
    }
    /**
     * Determine whether a value is Date / DateTime custom-castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function is_date_castable_with_custom_format($key)
    {
        return $this->has_cast($key, ['custom_datetime', 'immutable_custom_datetime']);
    }
    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function is_json_castable($key)
    {
        return $this->has_cast($key, ['array', 'json', 'json:unicode', 'object', 'collection', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object']);
    }
    /**
     * Determine whether a value is an encrypted castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function is_encrypted_castable($key)
    {
        return $this->has_cast($key, ['encrypted', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object']);
    }
    /**
     * Determine if the given key is cast using a custom class.
     *
     * @param  string  $key
     *
     * @throws \Illuminate\Database\Eloquent\InvalidCastException
     */
    protected function is_class_castable($key): bool
    {
        $casts = $this->get_casts();
        if (!array_key_exists($key, $casts)) {
            return false;
        }
        $cast_type = $this->parse_caster_class($casts[$key]);
        if (in_array($cast_type, static::$primitive_cast_types)) {
            return false;
        }
        if (class_exists($cast_type)) {
            return true;
        }
        throw new Invalid_Cast_Exception($this->get_model(), $key, $cast_type);
    }
    /**
     * Determine if the given key is cast using an enum.
     *
     * @param  string  $key
     * @return bool
     */
    protected function is_enum_castable($key)
    {
        $casts = $this->get_casts();
        if (!array_key_exists($key, $casts)) {
            return false;
        }
        $cast_type = $casts[$key];
        if (in_array($cast_type, static::$primitive_cast_types)) {
            return false;
        }
        if (is_subclass_of($cast_type, Castable::class)) {
            return false;
        }
        return enum_exists($cast_type);
    }
    /**
     * Determine if the key is deviable using a custom class.
     *
     * @param  string  $key
     * @return bool
     *
     * @throws \Illuminate\Database\Eloquent\InvalidCastException
     */
    protected function is_class_deviable($key)
    {
        if (!$this->is_class_castable($key)) {
            return false;
        }
        $cast_type = $this->resolve_caster_class($key);
        return method_exists($cast_type::class, 'increment') && method_exists($cast_type::class, 'decrement');
    }
    /**
     * Determine if the key is serializable using a custom class.
     *
     * @param  string  $key
     *
     * @throws \Illuminate\Database\Eloquent\InvalidCastException
     */
    protected function is_class_serializable($key): bool
    {
        return !$this->is_enum_castable($key) && $this->is_class_castable($key) && method_exists($this->resolve_caster_class($key), 'serialize');
    }
    /**
     * Determine if the key is comparable using a custom class.
     *
     * @param  string  $key
     */
    protected function is_class_comparable($key): bool
    {
        return !$this->is_enum_castable($key) && $this->is_class_castable($key) && method_exists($this->resolve_caster_class($key), 'compare');
    }
    /**
     * Resolve the custom caster class for a given key.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function resolve_caster_class($key)
    {
        $cast_type = $this->get_casts()[$key];
        $arguments = [];
        if (is_string($cast_type) && str_contains($cast_type, ':')) {
            $segments = explode(':', $cast_type, 2);
            $cast_type = $segments[0];
            $arguments = explode(',', $segments[1]);
        }
        if (is_subclass_of($cast_type, Castable::class)) {
            $cast_type = $cast_type::cast_using($arguments);
        }
        if (is_object($cast_type)) {
            return $cast_type;
        }
        return new $cast_type(...$arguments);
    }
    /**
     * Parse the given caster class, removing any arguments.
     *
     * @param  string  $class
     * @return string
     */
    protected function parse_caster_class($class)
    {
        return !str_contains($class, ':') ? $class : explode(':', $class, 2)[0];
    }
    /**
     * Merge the cast class and attribute cast attributes back into the model.
     *
     * @return void
     */
    protected function merge_attributes_from_cached_casts()
    {
        $this->merge_attributes_from_class_casts();
        $this->merge_attributes_from_attribute_casts();
    }
    /**
     * Merge the a cast class and attribute cast attribute back into the model.
     *
     * @return void
     */
    protected function merge_attribute_from_cached_casts(string $key)
    {
        $this->merge_attribute_from_class_casts($key);
        $this->merge_attribute_from_attribute_casts($key);
    }
    /**
     * Merge the cast class attributes back into the model.
     *
     * @return void
     */
    protected function merge_attributes_from_class_casts()
    {
        foreach ($this->class_cast_cache as $key => $value) {
            $this->merge_attribute_from_class_casts($key);
        }
    }
    /**
     * Merge the cast class attribute back into the model.
     */
    protected function merge_attribute_from_class_casts(string $key): void
    {
        if (!isset($this->class_cast_cache[$key])) {
            return;
        }
        $value = $this->class_cast_cache[$key];
        $caster = $this->resolve_caster_class($key);
        $this->attributes = array_merge($this->attributes, $caster instanceof Casts_Inbound_Attributes ? [$key => $value] : $this->normalize_cast_class_response($key, $caster->set($this, $key, $value, $this->attributes)));
    }
    /**
     * Merge the cast class attributes back into the model.
     *
     * @return void
     */
    protected function merge_attributes_from_attribute_casts()
    {
        foreach ($this->attribute_cast_cache as $key => $value) {
            $this->merge_attribute_from_attribute_casts($key);
        }
    }
    /**
     * Merge the cast class attribute back into the model.
     */
    protected function merge_attribute_from_attribute_casts(string $key): void
    {
        if (!isset($this->attribute_cast_cache[$key])) {
            return;
        }
        $value = $this->attribute_cast_cache[$key];
        $attribute = $this->{Str::camel($key)}();
        if ($attribute->get && !$attribute->set) {
            return;
        }
        $callback = $attribute->set ?: function ($value) use ($key): void {
            $this->attributes[$key] = $value;
        };
        $this->attributes = array_merge($this->attributes, $this->normalize_cast_class_response($key, $callback($value, $this->attributes)));
    }
    /**
     * Normalize the response from a custom class caster.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function normalize_cast_class_response($key, $value): array
    {
        return is_array($value) ? $value : [$key => $value];
    }
    /**
     * Get all of the current attributes on the model.
     *
     * @return array<string, mixed>
     */
    public function get_attributes()
    {
        $this->merge_attributes_from_cached_casts();
        return $this->attributes;
    }
    /**
     * Get all of the current attributes on the model for an insert operation.
     *
     * @return array
     */
    protected function get_attributes_for_insert()
    {
        return $this->get_attributes();
    }
    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  bool  $sync
     * @return $this
     */
    public function set_raw_attributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;
        if ($sync) {
            $this->sync_original();
        }
        $this->class_cast_cache = [];
        $this->attribute_cast_cache = [];
        return $this;
    }
    /**
     * Get the model's original attribute values.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function get_original($key = null, $default = null)
    {
        return (new static())->set_raw_attributes($this->original, $sync = true)->get_original_without_rewinding_model($key, $default);
    }
    /**
     * Get the model's original attribute values.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    protected function get_original_without_rewinding_model($key = null, $default = null)
    {
        if ($key) {
            return $this->transform_model_value($key, Arr::get($this->original, $key, $default));
        }
        return (new Collection($this->original))->map_with_keys(fn($value, $key): array => [$key => $this->transform_model_value($key, $value)])->all();
    }
    /**
     * Get the model's raw original attribute values.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function get_raw_original($key = null, $default = null)
    {
        return Arr::get($this->original, $key, $default);
    }
    /**
     * Get a subset of the model's attributes.
     *
     * @param  array<string>|mixed  $attributes
     * @return array<string, mixed>
     */
    public function only($attributes): array
    {
        $results = [];
        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            $results[$attribute] = $this->get_attribute($attribute);
        }
        return $results;
    }
    /**
     * Get all attributes except the given ones.
     *
     * @param  array<string>|mixed  $attributes
     */
    public function except($attributes): array
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $results = [];
        foreach ($this->get_attributes() as $key => $value) {
            if (!in_array($key, $attributes)) {
                $results[$key] = $this->get_attribute($key);
            }
        }
        return $results;
    }
    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function sync_original()
    {
        $this->original = $this->get_attributes();
        return $this;
    }
    /**
     * Sync a single original attribute with its current value.
     *
     * @param  string  $attribute
     * @return $this
     */
    public function sync_original_attribute($attribute)
    {
        return $this->sync_original_attributes($attribute);
    }
    /**
     * Sync multiple original attribute with their current values.
     *
     * @param  array<string>|string  $attributes
     * @return $this
     */
    public function sync_original_attributes($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $model_attributes = $this->get_attributes();
        foreach ($attributes as $attribute) {
            $this->original[$attribute] = $model_attributes[$attribute];
        }
        return $this;
    }
    /**
     * Sync the changed attributes.
     *
     * @return $this
     */
    public function sync_changes()
    {
        $this->changes = $this->get_dirty();
        $this->previous = array_intersect_key($this->get_raw_original(), $this->changes);
        return $this;
    }
    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     *
     * @param  array<string>|string|null  $attributes
     * @return bool
     */
    public function is_dirty($attributes = null)
    {
        return $this->has_changes($this->get_dirty(), is_array($attributes) ? $attributes : func_get_args());
    }
    /**
     * Determine if the model or all the given attribute(s) have remained the same.
     *
     * @param  array<string>|string|null  $attributes
     */
    public function is_clean($attributes = null): bool
    {
        return !$this->is_dirty(...func_get_args());
    }
    /**
     * Discard attribute changes and reset the attributes to their original state.
     *
     * @return $this
     */
    public function discard_changes()
    {
        [$this->attributes, $this->changes, $this->previous] = [$this->original, [], []];
        $this->class_cast_cache = [];
        $this->attribute_cast_cache = [];
        return $this;
    }
    /**
     * Determine if the model or any of the given attribute(s) were changed when the model was last saved.
     *
     * @param  array<string>|string|null  $attributes
     * @return bool
     */
    public function was_changed($attributes = null)
    {
        return $this->has_changes($this->get_changes(), is_array($attributes) ? $attributes : func_get_args());
    }
    /**
     * Determine if any of the given attributes were changed when the model was last saved.
     *
     * @param  array<string>  $changes
     * @param  array<string>|string|null  $attributes
     * @return bool
     */
    protected function has_changes($changes, $attributes = null)
    {
        // If no specific attributes were provided, we will just see if the dirty array
        // already contains any attributes. If it does we will just return that this
        // count is greater than zero. Else, we need to check specific attributes.
        if (empty($attributes)) {
            return count($changes) > 0;
        }
        // Here we will spin through every attribute and see if this is in the array of
        // dirty attributes. If it is, we will return true and if we make it through
        // all of the attributes for the entire array we will return false at end.
        foreach (Arr::wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get the attributes that have been changed since the last sync.
     *
     * @return array<string, mixed>
     */
    public function get_dirty(): array
    {
        $dirty = [];
        foreach ($this->get_attributes() as $key => $value) {
            if (!$this->original_is_equivalent($key)) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }
    /**
     * Get the attributes that have been changed since the last sync for an update operation.
     *
     * @return array<string, mixed>
     */
    protected function get_dirty_for_update()
    {
        return $this->get_dirty();
    }
    /**
     * Get the attributes that were changed when the model was last saved.
     *
     * @return array<string, mixed>
     */
    public function get_changes()
    {
        return $this->changes;
    }
    /**
     * Get the attributes that were previously original before the model was last saved.
     *
     * @return array<string, mixed>
     */
    public function get_previous()
    {
        return $this->previous;
    }
    /**
     * Determine if the new and old values for a given key are equivalent.
     *
     * @param  string  $key
     * @return bool
     */
    public function original_is_equivalent($key)
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }
        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);
        if ($attribute === $original) {
            return true;
        }
        if (is_null($attribute)) {
            return false;
        }
        if ($this->is_date_attribute($key) || $this->is_date_castable_with_custom_format($key)) {
            return $this->from_date_time($attribute) === $this->from_date_time($original);
        }
        if ($this->has_cast($key, ['object', 'collection'])) {
            return $this->from_json($attribute) === $this->from_json($original);
        }
        if ($this->has_cast($key, ['real', 'float', 'double'])) {
            if ($original === null) {
                return false;
            }
            return abs($this->cast_attribute($key, $attribute) - $this->cast_attribute($key, $original)) < PHP_FLOAT_EPSILON * 4;
        }
        if ($this->is_encrypted_castable($key) && !empty(static::current_encrypter()->get_previous_keys())) {
            return false;
        }
        if ($this->has_cast($key, static::$primitive_cast_types)) {
            return $this->cast_attribute($key, $attribute) === $this->cast_attribute($key, $original);
        }
        if ($this->is_class_castable($key) && Str::starts_with($this->get_casts()[$key], [As_Array_Object::class, As_Collection::class])) {
            return $this->from_json($attribute) === $this->from_json($original);
        }
        if ($this->is_class_castable($key) && Str::starts_with($this->get_casts()[$key], [As_Enum_Array_Object::class, As_Enum_Collection::class])) {
            return $this->from_json($attribute) === $this->from_json($original);
        }
        if ($this->is_class_castable($key) && $original !== null && Str::starts_with($this->get_casts()[$key], [As_Encrypted_Array_Object::class, As_Encrypted_Collection::class])) {
            if (empty(static::current_encrypter()->get_previous_keys())) {
                return $this->from_encrypted_string($attribute) === $this->from_encrypted_string($original);
            }
            return false;
        }
        if ($this->is_class_comparable($key)) {
            return $this->compare_class_castable_attribute($key, $original, $attribute);
        }
        return is_numeric($attribute) && is_numeric($original) && strcmp((string) $attribute, (string) $original) === 0;
    }
    /**
     * Transform a raw model value using mutators, casts, etc.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform_model_value($key, $value)
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->has_get_mutator($key)) {
            return $this->mutate_attribute($key, $value);
        }
        if ($this->has_attribute_get_mutator($key)) {
            return $this->mutate_attribute_marked_attribute($key, $value);
        }
        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->has_cast($key)) {
            if (static::prevents_accessing_missing_attributes() && !array_key_exists($key, $this->attributes) && ($this->is_enum_castable($key) || in_array($this->get_cast_type($key), static::$primitive_cast_types))) {
                $this->throw_missing_attribute_exception_if_applicable($key);
            }
            return $this->cast_attribute($key, $value);
        }
        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($value !== null && \in_array($key, $this->get_dates(), false)) {
            return $this->as_date_time($value);
        }
        return $value;
    }
    /**
     * Append attributes to query when building a query.
     *
     * @param  array<string>|string  $attributes
     * @return $this
     */
    public function append($attributes)
    {
        $this->appends = array_values(array_unique(array_merge($this->appends, is_string($attributes) ? func_get_args() : $attributes)));
        return $this;
    }
    /**
     * Get the accessors that are being appended to model arrays.
     *
     * @return array
     */
    public function get_appends()
    {
        return $this->appends;
    }
    /**
     * Set the accessors to append to model arrays.
     *
     * @return $this
     */
    public function set_appends(array $appends)
    {
        $this->appends = $appends;
        return $this;
    }
    /**
     * Merge new appended attributes with existing appended attributes on the model.
     *
     * @param  array<string>  $appends
     * @return $this
     */
    public function merge_appends(array $appends)
    {
        $this->appends = array_values(array_unique(array_merge($this->appends, $appends)));
        return $this;
    }
    /**
     * Return whether the accessor attribute has been appended.
     *
     * @param  string  $attribute
     */
    public function has_appended($attribute): bool
    {
        return in_array($attribute, $this->appends);
    }
    /**
     * Remove all appended properties from the model.
     *
     * @return $this
     */
    public function without_appends()
    {
        return $this->set_appends([]);
    }
    /**
     * Get the mutated attributes for a given instance.
     *
     * @return array
     */
    public function get_mutated_attributes()
    {
        if (!isset(static::$mutator_cache[static::class])) {
            static::cache_mutated_attributes($this);
        }
        return static::$mutator_cache[static::class];
    }
    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param  object|string  $classOrInstance
     */
    public static function cache_mutated_attributes($class_or_instance): void
    {
        $reflection = new ReflectionClass($class_or_instance);
        $class = $reflection->get_name();
        static::$get_attribute_mutator_cache[$class] = (new Collection($attribute_mutator_methods = static::get_attribute_marked_mutator_methods($class_or_instance)))->map_with_keys(fn($match): array => [lcfirst(static::$snake_attributes ? Str::snake($match) : $match) => true])->all();
        static::$mutator_cache[$class] = (new Collection(static::get_mutator_methods($class)))->merge($attribute_mutator_methods)->map(fn($match): string => lcfirst(static::$snake_attributes ? Str::snake($match) : $match))->all();
    }
    /**
     * Get all of the attribute mutator methods.
     *
     * @param  mixed  $class
     * @return array
     */
    protected static function get_mutator_methods($class)
    {
        preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);
        return $matches[1];
    }
    /**
     * Get all of the "Attribute" return typed attribute mutator methods.
     *
     * @param  mixed  $class
     * @return array
     */
    protected static function get_attribute_marked_mutator_methods($class)
    {
        $instance = is_object($class) ? $class : new $class();
        return (new Collection((new ReflectionClass($instance))->get_methods()))->filter(function ($method) use ($instance): bool {
            $return_type = $method->get_return_type();
            if (!$return_type instanceof ReflectionNamedType) {
                return false;
            }
            if (!($return_type->get_name() === Attribute::class)) {
                return false;
            }
            if (is_callable($method->invoke($instance)->get)) {
                return true;
            }
            return false;
        })->map->name->values()->all();
    }
}