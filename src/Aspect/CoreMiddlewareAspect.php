<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/12/14 14:11:28
 * E-mail: fly@eyabc.cn
 */
declare (strict_types=1);

namespace Lynnfly\HyperfDto\Aspect;

use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\CoreMiddleware;
use InvalidArgumentException;
use Lynnfly\HyperfDto\AbstractDataTransferObject;
use Psr\Container\ContainerInterface;

class CoreMiddlewareAspect extends AbstractAspect
{
    public array $classes = [
        CoreMiddleware::class . '::getInjections',
    ];

    private NormalizerInterface $normalizer;

    public function __construct(
        protected ContainerInterface $container
    )
    {
        $this->normalizer = $this->container->get(NormalizerInterface::class);
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $definitions = $proceedingJoinPoint->arguments['keys']['definitions'];
        $callableName = $proceedingJoinPoint->arguments['keys']['callableName'];
        $arguments = $proceedingJoinPoint->arguments['keys']['arguments'];
        return $this->getInjections($definitions, $callableName, $arguments);
    }

    private function getInjections(array $definitions, string $callableName, array $arguments): array
    {
        $injections = [];
        foreach ($definitions as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($this->container->has($definition->getName())) {
                    $class = $definition->getName();
                    if (is_subclass_of($class, AbstractDataTransferObject::class)) {
                        $request = $this->container->get(RequestInterface::class);
                        $injections[] = $class::make($request->all());
                    } else {
                        $injections[] = $this->container->get($definition->getName());
                    }
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } else {
                    throw new InvalidArgumentException("Parameter '{$definition->getMeta('name')}' "
                        . "of {$callableName} should not be null");
                }
            } else {
                $injections[] = $this->normalizer->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }
}
