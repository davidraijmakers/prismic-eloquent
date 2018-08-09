<?php

namespace RobinDrost\PrismicEloquent;

use Illuminate\Support\Collection;
use RobinDrost\PrismicEloquent\Contracts\DocumentResolver as DocumentResolverContract;
use RobinDrost\PrismicEloquent\Contracts\Model as ModelContract;
use RobinDrost\PrismicEloquent\Contracts\QueryBuilder as QueryBuilderContract;
use stdClass;

abstract class Model implements ModelContract
{
    /**
     * @var stdClass
     */
    protected $document;

    /**
     * @var bool
     */
    protected $fieldsToSnakeCase = true;

    /**
     * @var array
     */
    protected $resolvers = [];

    /**
     * Create a new instance of the model with a Prismic document.
     *
     * @param mixed $document
     */
    public function __construct($document = null)
    {
        if (! empty($document)) {
            $this->attachDocument($document);
        }
    }

    /**
     * @inheritdoc
     */
    public function attribute(string $name)
    {
        if ($this->fieldsToSnakeCase) {
            $name = snake_case($name);
        }

        return $this->document->{$name};
    }

    /**
     * @inheritdoc
     */
    public function hasAttribute(string $name): bool
    {
        return property_exists($this->document, $name);
    }

    /**
     * @inheritdoc
     */
    public function field(string $name)
    {
        if ($this->fieldsToSnakeCase) {
            $name = snake_case($name);
        }

        return $this->document->data->{$name};
    }

    /**
     * @inheritdoc
     */
    public function hasField(string $name): bool
    {
        return property_exists($this->document->data, $name);
    }

    /**
     * @inheritdoc
     */
    public function newQuery(): QueryBuilderContract
    {
        return (new QueryBuilder($this))->whereType(static::getTypeName());
    }

    /**
     * @inheritdoc
     */
    public function getDocumentResolver(): DocumentResolverContract
    {
        return new DocumentResolver();
    }

    /**
     * Execute all resolvers set through the 'with' method.
     *
     * @return ModelContract
     */
    public function resolveDocuments() : ModelContract
    {
        foreach ($this->resolvers as $resolver) {
            $this->{$resolver}();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getTypeName(): string
    {
        $fullPath = explode('\\', get_called_class());
        return snake_case(array_pop($fullPath));
    }

    /**
     * Store resolve methods to call and return a query builder.
     *
     * @param mixed ...$resolvers
     * @return ModelContract
     */
    public static function with(...$resolvers) : ModelContract
    {
        $model = new static;
        $model->resolvers = array_map(function ($resolver) {
            return $resolver . 'Resolver';
        }, $resolvers);

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function attachDocument($document) : ModelContract
    {
        $this->document = $document;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function newInstance($document) : ModelContract
    {
        return new static($document);
    }

    /**
     * @inheritdoc
     */
    public static function newCollection(array $models) : Collection
    {
        return collect($models);
    }

    /**
     * Return a new instance of the query builder.
     *
     * @param string $method
     * @param mixed $arguments
     * @return mixed
     */
    public function __call(string $method, $arguments)
    {
        return $this->newQuery()->{$method}(...$arguments);
    }

    /**
     * Create a new instance of the model.
     *
     * @param string $name
     * @param mixed $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, $arguments)
    {
        return (new static)->{$name}(...$arguments);
    }

    /**
     * Check if the given name is either an existing attribute or field and
     * return that.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        if ($this->hasAttribute($name)) {
            return $this->attribute($name);
        }

        if ($this->hasField($name)) {
            $fieldMethod = 'get' . ucfirst(camel_case($name)) . 'Field';

            if (method_exists($this, $fieldMethod)) {
                return $this->{$fieldMethod}($this->field($name));
            }

            return $this->field($name);
        }

        return null;
    }
}
