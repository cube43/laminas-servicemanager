<?php

namespace LaminasBench\ServiceManager;

use Laminas\ServiceManager\ServiceManager;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;
use stdClass;

/**
 * @Revs(1000)
 * @Iterations(10)
 * @Warmup(2)
 */
class SetNewServicesBench
{
    private const NUM_SERVICES = 100;

    /** @var ServiceManager */
    private $sm;

    public function __construct()
    {
        $config = [
            'factories'          => [
                'factory1' => BenchAsset\FactoryFoo::class,
            ],
            'invokables'         => [
                'invokable1' => BenchAsset\Foo::class,
            ],
            'services'           => [
                'service1' => new stdClass(),
            ],
            'aliases'            => [
                'factoryAlias1'          => 'factory1',
                'recursiveFactoryAlias1' => 'factoryAlias1',
                'recursiveFactoryAlias2' => 'recursiveFactoryAlias1',
            ],
            'abstract_factories' => [
                BenchAsset\AbstractFactoryFoo::class,
            ],
        ];

        for ($i = 0; $i <= self::NUM_SERVICES; $i++) {
            $config['factories']["factory_$i"] = BenchAsset\FactoryFoo::class;
            $config['aliases']["alias_$i"]     = "service_$i";
        }

        $this->sm = new ServiceManager($config);
    }

    public function benchSetService(): void
    {
        // @todo @link https://github.com/phpbench/phpbench/issues/304
        $sm = clone $this->sm;

        $sm->setService('service2', new stdClass());
    }

    public function benchSetFactory(): void
    {
        // @todo @link https://github.com/phpbench/phpbench/issues/304
        $sm = clone $this->sm;

        $sm->setFactory('factory2', BenchAsset\FactoryFoo::class);
    }

    public function benchSetAlias(): void
    {
        // @todo @link https://github.com/phpbench/phpbench/issues/304
        $sm = clone $this->sm;

        $sm->setAlias('factoryAlias2', 'factory1');
    }

    public function benchSetAliasOverrided(): void
    {
        // @todo @link https://github.com/phpbench/phpbench/issues/304
        $sm = clone $this->sm;

        $sm->setAlias('recursiveFactoryAlias1', 'factory1');
    }
}
