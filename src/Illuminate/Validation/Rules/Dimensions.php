<?php

declare(strict_types=1);

namespace Illuminate\Validation\Rules;

use Illuminate\Support\Traits\Conditionable;
use Stringable;

class Dimensions implements Stringable
{
    use Conditionable;

    /**
     * Create a new dimensions rule instance.
     */
    public function __construct(
        /**
         * The constraints for the dimensions rule.
         */
        protected array $constraints = []
    ) {
    }

    /**
     * Set the "width" constraint.
     *
     * @param  int  $value
     * @return $this
     */
    public function width($value): static
    {
        $this->constraints['width'] = $value;

        return $this;
    }

    /**
     * Set the "height" constraint.
     *
     * @param  int  $value
     * @return $this
     */
    public function height($value): static
    {
        $this->constraints['height'] = $value;

        return $this;
    }

    /**
     * Set the "min width" constraint.
     *
     * @param  int  $value
     * @return $this
     */
    public function minWidth($value): static
    {
        $this->constraints['min_width'] = $value;

        return $this;
    }

    /**
     * Set the "min height" constraint.
     *
     * @param  int  $value
     * @return $this
     */
    public function minHeight($value): static
    {
        $this->constraints['min_height'] = $value;

        return $this;
    }

    /**
     * Set the "max width" constraint.
     *
     * @param  int  $value
     * @return $this
     */
    public function maxWidth($value): static
    {
        $this->constraints['max_width'] = $value;

        return $this;
    }

    /**
     * Set the "max height" constraint.
     *
     * @param  int  $value
     * @return $this
     */
    public function maxHeight($value): static
    {
        $this->constraints['max_height'] = $value;

        return $this;
    }

    /**
     * Set the "ratio" constraint.
     *
     * @param  float  $value
     * @return $this
     */
    public function ratio($value): static
    {
        $this->constraints['ratio'] = $value;

        return $this;
    }

    /**
     * Set the minimum aspect ratio.
     *
     * @param  float  $value
     * @return $this
     */
    public function minRatio($value): static
    {
        $this->constraints['min_ratio'] = $value;

        return $this;
    }

    /**
     * Set the maximum aspect ratio.
     *
     * @param  float  $value
     * @return $this
     */
    public function maxRatio($value): static
    {
        $this->constraints['max_ratio'] = $value;

        return $this;
    }

    /**
     * Set the aspect ratio range.
     *
     * @param  float  $min
     * @param  float  $max
     * @return $this
     */
    public function ratioBetween($min, $max): static
    {
        $this->constraints['min_ratio'] = $min;
        $this->constraints['max_ratio'] = $max;

        return $this;
    }

    /**
     * Convert the rule to a validation string.
     */
    public function __toString(): string
    {
        $result = '';

        foreach ($this->constraints as $key => $value) {
            $result .= "$key=$value,";
        }

        return 'dimensions:'.substr($result, 0, -1);
    }
}
