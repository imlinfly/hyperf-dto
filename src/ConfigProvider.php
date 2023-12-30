<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/12/14 14:11:28
 * E-mail: fly@eyabc.cn
 */
declare(strict_types=1);

namespace Lynnfly\HyperfDto;

use Lynnfly\HyperfDto\Aspect\CoreMiddlewareAspect;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'aspects' => [
                CoreMiddlewareAspect::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config of ',
                    'source' => __DIR__ . '/../publish/dto.php',
                    'destination' => BASE_PATH . '/config/autoload/dto.php',
                ],
            ],
        ];
    }
}
