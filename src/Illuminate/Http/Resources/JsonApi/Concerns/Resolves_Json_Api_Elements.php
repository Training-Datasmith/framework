<?php

declare (strict_types=1);
namespace Illuminate\Http\Resources\Json_Api\Concerns;

use Generator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Belongs_To_Many;
use Illuminate\Database\Eloquent\Relations\Concerns\As_Pivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\Json_Resource;
use Illuminate\Http\Resources\Json_Api\Exceptions\Resource_Identification_Exception;
use Illuminate\Http\Resources\Json_Api\Json_Api_Request;
use Illuminate\Http\Resources\Json_Api\Json_Api_Resource;
use Illuminate\Http\Resources\Json_Api\Relation_Resolver;
use Illuminate\Http\Resources\Missing_Value;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Lazy_Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use WeakMap;
trait Resolves_Json_Api_Elements
{
    /**
     * Determine whether resources respect inclusions and fields from the request.
     */
    protected bool $uses_request_query_string = true;
    /**
     * Determine whether included relationship for the resource from eager loaded relationship.
     */
    protected bool $includes_previously_loaded_relationships = false;
    /**
     * Cached loaded relationships map.
     *
     * @var array<int, array{0: \Illuminate\Http\Resources\JsonApi\JsonApiResource, 1: string, 2: string, 3: bool}|null
     */
    public $loaded_relationships_map;
    /**
     * Cached loaded relationships identifers.
     */
    protected array $loaded_relationship_identifiers = [];
    /**
     * The maximum relationship depth.
     */
    public static int $max_relationship_depth = 5;
    /**
     * Specify the maximum relationship depth.
     */
    public static function max_relationship_depth(int $depth): void
    {
        static::$max_relationship_depth = max(0, $depth);
    }
    /**
     * Resolves `data` for the resource.
     */
    protected function resolve_resource_object(Json_Api_Request $request): array
    {
        $resource_type = $this->resolve_resource_type($request);
        return ['id' => $this->resolve_resource_identifier($request), 'type' => $resource_type, ...(new Collection(['attributes' => $this->resolve_resource_attributes($request, $resource_type), 'relationships' => $this->resolve_resource_relationship_identifiers($request), 'links' => $this->resolve_resource_links($request), 'meta' => $this->resolve_resource_meta_information($request)]))->filter()->map(fn($value): \stdClass => (object) $value)];
    }
    /**
     * Resolve the resource's identifier.
     *
     *
     * @throws ResourceIdentificationException
     */
    public function resolve_resource_identifier(Json_Api_Request $request): string
    {
        if (!is_null($resource_id = $this->to_id($request))) {
            return $resource_id;
        }
        if (!($this->resource instanceof Model || method_exists($this->resource, 'getKey'))) {
            throw Resource_Identification_Exception::attempting_to_determine_id_for($this);
        }
        return (string) $this->resource->get_key();
    }
    /**
     * Resolve the resource's type.
     *
     *
     * @throws ResourceIdentificationException
     */
    public function resolve_resource_type(Json_Api_Request $request): string
    {
        if (!is_null($resource_type = $this->to_type($request))) {
            return $resource_type;
        }
        if (static::class !== Json_Api_Resource::class) {
            return Str::of(static::class)->class_basename()->basename('Resource')->snake()->plural_studly();
        }
        if (!$this->resource instanceof Model) {
            throw Resource_Identification_Exception::attempting_to_determine_type_for($this);
        }
        $model_class_name = $this->resource::class;
        $morph_map = Relation::get_morph_alias($model_class_name);
        return Str::of($morph_map !== $model_class_name ? $morph_map : class_basename($model_class_name))->snake()->plural_studly();
    }
    /**
     * Resolve the resource's attributes.
     *
     *
     * @throws \RuntimeException
     */
    protected function resolve_resource_attributes(Json_Api_Request $request, string $resource_type): array
    {
        $data = $this->to_attributes($request);
        if ($data instanceof Arrayable) {
            $data = $data->to_array();
        } elseif ($data instanceof JsonSerializable) {
            $data = $data->jsonSerialize();
        }
        $sparse_fieldset = match ($this->uses_request_query_string) {
            true => $request->sparse_fields($resource_type),
            default => [],
        };
        $data = (new Collection($data))->map_with_keys(fn($value, $key): array => is_int($key) ? [$value => $this->resource->{$value}] : [$key => $value])->when(!empty($sparse_fieldset), fn($attributes): \Illuminate\Support\Collection => $attributes->only($sparse_fieldset))->transform(fn($value) => value($value, $request))->all();
        return $this->filter($data);
    }
    /**
     * Resolves `relationships` for the resource's data object.
     *
     *
     * @throws \RuntimeException
     */
    protected function resolve_resource_relationship_identifiers(Json_Api_Request $request): array
    {
        if (!$this->resource instanceof Model) {
            return [];
        }
        $this->compile_resource_relationships($request);
        return [...(new Collection($this->filter($this->loaded_relationship_identifiers)))->map(fn($relation): mixed => !is_null($relation) ? $relation : ['data' => null])->all()];
    }
    /**
     * Compile resource relationships.
     */
    protected function compile_resource_relationships(Json_Api_Request $request): void
    {
        if (!is_null($this->loaded_relationships_map)) {
            return;
        }
        $sparse_included = match (true) {
            $this->includes_previously_loaded_relationships => array_keys($this->resource->get_relations()),
            default => $request->sparse_included(),
        };
        $resource_relationships = (new Collection($this->to_relationships($request)))->transform(fn($value, $key): \Illuminate\Http\Resources\Json_Api\Relation_Resolver => is_int($key) ? new Relation_Resolver($value) : new Relation_Resolver($key, $value))->map_with_keys(fn($relation_resolver): array => [$relation_resolver->relation_name => $relation_resolver])->filter(fn($value, $key): bool => in_array($key, $sparse_included));
        $resource_relationship_keys = $resource_relationships->keys();
        $this->resource->load_missing($resource_relationship_keys->all() ?? []);
        $this->loaded_relationships_map = [];
        $this->loaded_relationship_identifiers = (new Lazy_Collection(function () use ($request, $resource_relationships) {
            foreach ($resource_relationships as $relation_name => $relation_resolver) {
                $related_models = $relation_resolver->handle($this->resource);
                $related_resource_class = $relation_resolver->resource_class();
                if (!is_null($related_models) && $this->includes_previously_loaded_relationships === false) {
                    if (!empty($relations = $request->sparse_included($relation_name))) {
                        $related_models->load_missing($relations);
                    }
                }
                yield from $this->compile_resource_relationship_using_resolver($request, $this->resource, $relation_resolver, $related_models);
            }
        }))->all();
    }
    /**
     * Compile resource relations.
     */
    protected function compile_resource_relationship_using_resolver(Json_Api_Request $request, mixed $resource, Relation_Resolver $relation_resolver, Collection|Model|null $related_models): Generator
    {
        $relation_name = $relation_resolver->relation_name;
        $resource_class = $relation_resolver->resource_class();
        // Relationship is a collection of models...
        if ($related_models instanceof Collection) {
            $related_models = $related_models->values();
            if ($related_models->is_empty()) {
                yield $relation_name => ['data' => $related_models];
                return;
            }
            $relationship = $resource->{$relation_name}();
            $is_unique = !$relationship instanceof Belongs_To_Many;
            yield $relation_name => ['data' => $related_models->map(function ($related_model) use ($request, $resource_class, $is_unique) {
                $related_resource = rescue(fn() => $related_model->to_resource($resource_class), new Json_Api_Resource($related_model));
                return transform([$related_resource->resolve_resource_type($request), $related_resource->resolve_resource_identifier($request)], function (array $unique_key) use ($request, $related_model, $related_resource, $is_unique): array {
                    $this->loaded_relationships_map[] = [$related_resource, ...$unique_key, $is_unique];
                    $this->compile_included_nested_relationships_map($request, $related_model, $related_resource);
                    return ['id' => $unique_key[1], 'type' => $unique_key[0]];
                });
            })->all()];
            return;
        }
        // Relationship is a single model...
        $related_model = $related_models;
        if (is_null($related_model)) {
            yield $relation_name => null;
            return;
        }
        if ($related_model instanceof Pivot || in_array(As_Pivot::class, class_uses_recursive($related_model), true)) {
            yield $relation_name => new Missing_Value();
            return;
        }
        $related_resource = rescue(fn(): \Illuminate\Http\Resources\Json\Json_Resource => $related_model->to_resource($resource_class), new Json_Api_Resource($related_model));
        yield $relation_name => ['data' => transform([$related_resource->resolve_resource_type($request), $related_resource->resolve_resource_identifier($request)], function (array $unique_key) use ($related_model, $related_resource, $request): array {
            $this->loaded_relationships_map[] = [$related_resource, ...$unique_key, true];
            $this->compile_included_nested_relationships_map($request, $related_model, $related_resource);
            return ['id' => $unique_key[1], 'type' => $unique_key[0]];
        })];
    }
    /**
     * Compile included relationships map.
     */
    protected function compile_included_nested_relationships_map(Json_Api_Request $request, Model $relation, Json_Api_Resource $resource): void
    {
        (new Collection($resource->to_relationships($request)))->transform(fn($value, $key): \Illuminate\Http\Resources\Json_Api\Relation_Resolver => is_int($key) ? new Relation_Resolver($value) : new Relation_Resolver($key, $value))->map_with_keys(fn($relation_resolver): array => [$relation_resolver->relation_name => $relation_resolver])->filter(fn($value, $key): bool => in_array($key, array_keys($relation->get_relations())))->each(function ($relation_resolver, $key) use ($relation, $request): void {
            $this->compile_resource_relationship_using_resolver($request, $relation, $relation_resolver, $relation->get_relation($key));
        });
    }
    /**
     * Resolves `included` for the resource.
     */
    public function resolve_included_resource_objects(Json_Api_Request $request): Collection
    {
        if (!$this->resource instanceof Model) {
            return new Collection();
        }
        $this->compile_resource_relationships($request);
        $relations = new Collection();
        $index = 0;
        // Track visited objects by instance + type to prevent infinite loops from circular
        // references created by "chaperone()". We use object instances rather than type
        // and ID for any possible cases like BelongsToMany with different pivot data.
        // We'll track types to allow the same models with different resource types.
        $visited_objects = new WeakMap();
        $visited_objects[$this->resource] = [$this->resolve_resource_type($request) => true];
        while ($index < count($this->loaded_relationships_map)) {
            [$resource_instance, $type, $id, $is_unique] = $this->loaded_relationships_map[$index];
            $underlying_resource = $resource_instance->resource;
            if (is_object($underlying_resource)) {
                if (isset($visited_objects[$underlying_resource][$type])) {
                    $index++;
                    continue;
                }
                $visited_objects[$underlying_resource] ??= [];
                $visited_objects[$underlying_resource][$type] = true;
            }
            if (!$resource_instance instanceof Json_Api_Resource && $resource_instance instanceof Json_Resource) {
                $resource_instance = new Json_Api_Resource($resource_instance->resource);
            }
            $relations_data = $resource_instance->include_previously_loaded_relationships()->resolve($request);
            array_push($this->loaded_relationships_map, ...$resource_instance->loaded_relationships_map ?? []);
            $relations->push(array_filter(['id' => $id, 'type' => $type, '_uniqueKey' => implode(':', $is_unique === true ? [$id, $type] : [$id, $type, (string) Str::random()]), 'attributes' => Arr::get($relations_data, 'data.attributes'), 'relationships' => Arr::get($relations_data, 'data.relationships'), 'links' => Arr::get($relations_data, 'data.links'), 'meta' => Arr::get($relations_data, 'data.meta')]));
            $index++;
        }
        return $relations;
    }
    /**
     * Resolve the links for the resource.
     *
     * @return array<string, mixed>
     */
    protected function resolve_resource_links(Json_Api_Request $request): array
    {
        return $this->to_links($request);
    }
    /**
     * Resolve the meta information for the resource.
     *
     * @return array<string, mixed>
     */
    protected function resolve_resource_meta_information(Json_Api_Request $request): array
    {
        return $this->to_meta($request);
    }
    /**
     * Indicate that relationship loading should respect the request's "includes" query string.
     *
     * @return $this
     */
    public function respect_fields_and_includes_in_query_string(bool $value = true)
    {
        $this->uses_request_query_string = $value;
        return $this;
    }
    /**
     * Indicate that relationship loading should not rely on the request's "includes" query string.
     *
     * @return $this
     */
    public function ignore_fields_and_includes_in_query_string()
    {
        return $this->respect_fields_and_includes_in_query_string(false);
    }
    /**
     * Determine relationship should include loaded relationships.
     *
     * @return $this
     */
    public function include_previously_loaded_relationships()
    {
        $this->includes_previously_loaded_relationships = true;
        return $this;
    }
}