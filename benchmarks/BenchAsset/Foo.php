<?php

namespace LaminasBench\ServiceManager\BenchAsset;

class Foo
{
    /** @var mixed */
    protected $options;

    /** @param mixed $options */
    public function __construct($options = null)
    {
        $this->options = $options;
    }
}
