<?php

namespace Edgar\EzBundleGenerator\Generator\Validator;

use Sensio\Bundle\GeneratorBundle\Command\Validators as BaseValidators;

class Validators extends BaseValidators
{
    public static function validateBundleNamespace($namespace, $requireVendorNamespace = true): string
    {
        $namespaceArray = explode('/', strtr($namespace, ['\\' => '/']));
        if (!preg_match('/Ez/', $namespaceArray[1])) {
            throw new \InvalidArgumentException('The bundle in namespace must start with Ez.');
        }

        return parent::validateBundleNamespace($namespace, $requireVendorNamespace);
    }

    public static function validateVendorName(string $vendor)
    {
        if ($vendor[0] != strtoupper($vendor[0])) {
            throw new \InvalidArgumentException('The vendor name must start with an uppercase.');
        }

        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $vendor)) {
            throw new \InvalidArgumentException('The vendor name contains invalid characters.');
        }

        $reserved = self::getReservedWords();
        if (in_array(strtolower($vendor), $reserved)) {
            throw new \InvalidArgumentException('The vendor name cannot contain PHP reserved words.');
        }

        return $vendor;
    }
}
