<?php

/*
 * This file is part of the overtrue/laravel-revaluationable.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\LaravelRevaluation\Valuators;

/**
 * Class RmbCent.
 */
class RmbCent extends Valuator
{
    protected function inYuan()
    {
        return round($this->value / 100, 2);
    }

    public function storeableValue($value)
    {
        return $value * 100;
    }

    public function asCurrency($format = '￥%i')
    {
        return money_format($format, $this->inYuan());
    }
}
