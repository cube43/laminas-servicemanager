<?php

/**
 * @see       https://github.com/laminas/laminas-servicemanager for the canonical source repository
 * @copyright https://github.com/laminas/laminas-servicemanager/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-servicemanager/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ServiceManager\Tool;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\AbstractFactory\ConfigAbstractFactory;
use Laminas\ServiceManager\Exception\InvalidArgumentException;
use ReflectionClass;
use ReflectionParameter;
use Traversable;

use function array_filter;
use function array_key_exists;
use function class_exists;
use function date;
use function get_class;
use function gettype;
use function implode;
use function interface_exists;
use function is_array;
use function is_int;
use function is_null;
use function is_string;
use function sprintf;
use function str_repeat;
use function var_export;

class ConfigDumper
{
    const CONFIG_TEMPLATE = <<<EOC
<?php

/**
 * This file generated by %s.
 * Generated %s
 */

return %s;
EOC;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param array $config
     * @param string $className
     * @param bool $ignoreUnresolved
     * @return array
     * @throws InvalidArgumentException for invalid $className
     */
    public function createDependencyConfig(array $config, $className, $ignoreUnresolved = false)
    {
        $this->validateClassName($className);

        $reflectionClass = new ReflectionClass($className);

        // class is an interface; do nothing
        if ($reflectionClass->isInterface()) {
            return $config;
        }

        // class has no constructor, treat it as an invokable
        if (! $reflectionClass->getConstructor()) {
            return $this->createInvokable($config, $className);
        }

        $constructorArguments = $reflectionClass->getConstructor()->getParameters();
        $constructorArguments = array_filter(
            $constructorArguments,
            function (ReflectionParameter $argument) {
                return ! $argument->isOptional();
            }
        );

        // has no required parameters, treat it as an invokable
        if (empty($constructorArguments)) {
            return $this->createInvokable($config, $className);
        }

        $classConfig = [];

        foreach ($constructorArguments as $constructorArgument) {
            $argumentType = $constructorArgument->getClass();
            if (is_null($argumentType)) {
                if ($ignoreUnresolved) {
                    // don't throw an exception, just return the previous config
                    return $config;
                }
                // don't throw an exception if the class is an already defined service
                if ($this->container && $this->container->has($className)) {
                    return $config;
                }
                throw new InvalidArgumentException(sprintf(
                    'Cannot create config for constructor argument "%s", '
                    . 'it has no type hint, or non-class/interface type hint',
                    $constructorArgument->getName()
                ));
            }
            $argumentName = $argumentType->getName();
            $config = $this->createDependencyConfig($config, $argumentName, $ignoreUnresolved);
            $classConfig[] = $argumentName;
        }

        $config[ConfigAbstractFactory::class][$className] = $classConfig;

        return $config;
    }

    /**
     * @param $className
     * @throws InvalidArgumentException if class name is not a string or does
     *     not exist.
     */
    private function validateClassName($className)
    {
        if (! is_string($className)) {
            throw new InvalidArgumentException('Class name must be a string, ' . gettype($className) . ' given');
        }

        if (! class_exists($className) && ! interface_exists($className)) {
            throw new InvalidArgumentException('Cannot find class or interface with name ' . $className);
        }
    }

    /**
     * @param array $config
     * @param string $className
     * @return array
     */
    private function createInvokable(array $config, $className)
    {
        $config[ConfigAbstractFactory::class][$className] = [];
        return $config;
    }

    /**
     * @param array $config
     * @return array
     * @throws InvalidArgumentException if ConfigAbstractFactory configuration
     *     value is not an array.
     */
    public function createFactoryMappingsFromConfig(array $config)
    {
        if (! array_key_exists(ConfigAbstractFactory::class, $config)) {
            return $config;
        }

        if (! is_array($config[ConfigAbstractFactory::class])) {
            throw new InvalidArgumentException(
                'Config key for ' . ConfigAbstractFactory::class . ' should be an array, ' . gettype(
                    $config[ConfigAbstractFactory::class]
                ) . ' given'
            );
        }

        foreach ($config[ConfigAbstractFactory::class] as $className => $dependency) {
            $config = $this->createFactoryMappings($config, $className);
        }
        return $config;
    }

    /**
     * @param array $config
     * @param string $className
     * @return array
     */
    public function createFactoryMappings(array $config, $className)
    {
        $this->validateClassName($className);

        if (array_key_exists('service_manager', $config)
            && array_key_exists('factories', $config['service_manager'])
            && array_key_exists($className, $config['service_manager']['factories'])
        ) {
            return $config;
        }

        $config['service_manager']['factories'][$className] = ConfigAbstractFactory::class;
        return $config;
    }

    /**
     * @param array $config
     * @return string
     */
    public function dumpConfigFile(array $config)
    {
        $prepared = $this->prepareConfig($config);
        return sprintf(
            self::CONFIG_TEMPLATE,
            get_class($this),
            date('Y-m-d H:i:s'),
            $prepared
        );
    }

    /**
     * @param array|Traversable $config
     * @param int $indentLevel
     * @return string
     */
    private function prepareConfig($config, $indentLevel = 1)
    {
        $indent = str_repeat(' ', $indentLevel * 4);
        $entries = [];
        foreach ($config as $key => $value) {
            $key = $this->createConfigKey($key);
            $entries[] = sprintf(
                '%s%s%s,',
                $indent,
                $key ? sprintf('%s => ', $key) : '',
                $this->createConfigValue($value, $indentLevel)
            );
        }

        $outerIndent = str_repeat(' ', ($indentLevel - 1) * 4);

        return sprintf(
            "[\n%s\n%s]",
            implode("\n", $entries),
            $outerIndent
        );
    }

    /**
     * @param string|int|null $key
     * @return null|string
     */
    private function createConfigKey($key)
    {
        if (is_string($key) && class_exists($key)) {
            return sprintf('\\%s::class', $key);
        }

        if (is_int($key)) {
            return null;
        }

        return sprintf("'%s'", $key);
    }

    /**
     * @param mixed $value
     * @param int $indentLevel
     * @return string
     */
    private function createConfigValue($value, $indentLevel)
    {
        if (is_array($value) || $value instanceof Traversable) {
            return $this->prepareConfig($value, $indentLevel + 1);
        }

        if (is_string($value) && class_exists($value)) {
            return sprintf('\\%s::class', $value);
        }

        return var_export($value, true);
    }
}
