<?php

/*
 * This file is part of the overtrue/laravel-revaluation.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\LaravelRevaluation\Traits;

/**
 * Trait HasRevaluableAttributes.
 */
trait HasRevaluableAttributes
{
    /**
     * Revaluated attributes.
     *
     * @var array
     */
    protected $revaluated;

    /**
     * @param string $attribute
     * @param bool   $all
     *
     * @return bool
     */
    public function isRevaluated($attributes, $all = false)
    {
        $attributes = is_array($attributes)
            ? $attributes : func_get_args();

        $intersect = array_intersect($this->revaluated, $attributes);

        if ($all) {
            return count(array_diff($intersect, $attributes)) === 0;
        }

        return count($intersect) > 0;
    }

    /**
     * @return array
     */
    public function getRevaluated()
    {
        return $this->revaluated;
    }

    /**
     * Return valuator instance of attribute.
     *
     * @param string $attribute
     *
     * @return Overtrue\LaravelRevaluation\Revaluable
     */
    public function getRevaluatedAttribute($attribute)
    {
        $attribute = snake_case($attribute);

        if ($valuator = $this->getAttributeValuator($attribute)) {
            return new $valuator(parent::getAttribute($attribute), $attribute, $this);
        }

        return false;
    }

    /**
     * Return revaluable attributes.
     *
     * @example
     *
     * <pre>
     * // 1. Using default valuator:
     * protected $revaluable = [
     *     'foo', 'bar', 'baz'
     * ];
     *
     * // 2. Use the specified valuator:
     * protected $revaluable = [
     *     'foo' => '\Foo\Support\Valuator\Foo',
     *     'bar' => '\Foo\Support\Valuator\Bar',
     *     'baz',
     * ];
     * </pre>
     *
     * @return array
     */
    public function getRevaluableAttributes()
    {
        if (!property_exists($this, 'revaluable') || !is_array($this->revaluable)) {
            return [];
        }

        $revaluable = [];

        foreach ($this->revaluable as $key => $valuator) {
            if (is_integer($key)) {
                $revaluable[$valuator] = config('revaluation.default_valuator');
            } else {
                $revaluable[$valuator] = $valuator;
            }
        }

        return $revaluable;
    }

    /**
     * Return the additional attribute revaluate mutators.
     *
     * @return array
     */
    public function getRevaluateMutators()
    {
        return property_exists($this, 'revaluateMutators') ? (array) $this->revaluateMutators : [];
    }

    /**
     * Fetch attribute.
     *
     * @example
     * <pre>
     * $object->getRevaluatedPriceAttribute();
     * $object->getRevaluatedXXXAttribute();
     * </pre>
     *
     * @param string $method
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (preg_match('/getRevaluated(?<attribute>\w+)Attribute/i', $method, $matches)) {
            return $this->getRevaluatedAttribute($matches['attribute']);
        }

        return parent::__call($method, $args);
    }

    /**
     * @example
     * <pre>
     * $object->revaluated_price;
     * $object->raw_price;
     * </pre>
     *
     * @param string $attribute
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function getAttribute($attribute)
    {
        if (starts_with($attribute, 'raw_')) {
            return $this->getRevaluatedAttribute(substr($attribute, strlen('raw_')))->getRaw();
        }

        if (starts_with($attribute, 'revaluated_')) {
            return $this->getRevaluatedAttribute(substr($attribute, strlen('revaluated_')));
        }

        if ($valuator = $this->getRevaluatedAttribute($attribute)) {
            return $valuator->toDefaultFormat();
        }

        /**
         * <pre>
         * $revaluateMutators = [
         *     'display_price' => ['price', 'asCurrency'],
         * ];
         * </pre>.
         *
         * @var array
         */
        $revaluateMutators = $this->getRevaluateMutators();

        if (isset($revaluateMutators[$attribute])) {
            list($sourceAttribute, $method) = $revaluateMutators[$attribute];
            $revaluated = $this->getRevaluatedAttribute($sourceAttribute);

            if (!is_callable([$revaluated, $method])) {
                throw new \Exception("$method not an callable method.");
            }

            return call_user_func([$revaluated, $method]);
        }

        return parent::getAttribute($attribute);
    }

    /**
     * Set attribute.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttribute($attribute, $value)
    {
        if ($valuator = $this->getAttributeValuator($attribute)) {
            $this->revaluated[] = $attribute;
            $value = forward_static_call([$valuator, 'toStorableValue'], $value);
        }

        return parent::setAttribute($attribute, $value);
    }

    /**
     * Return revaluated value of attribute.
     *
     * @param string $attribute
     *
     * @return mixed
     */
    protected function getStorableValue($attribute)
    {
        if ($valuator = $this->getAttributeValuator($attribute)) {
            if (is_callable($valuator, 'toStorableValue')) {
                $value = forward_static_call([$valuator, 'toStorableValue'], $this->attributes[$attribute]);
            }
        }

        return $value;
    }

    /**
     * Get attribute valuator.
     *
     * @param string $attribute
     *
     * @return string
     */
    protected function getAttributeValuator($attribute)
    {
        return array_get($this->getRevaluableAttributes(), $attribute);
    }
}
