<?php

namespace Edgar\EzBundleGenerator\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\KernelInterface;

class BundleGenerator extends Generator
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * BundleGenerator constructor.
     *
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Generate new Bundle.
     *
     * @param string $namespace
     * @param string $bundle
     * @param string $dir
     */
    public function generate(string $namespace, string $bundle, string $dir, string $vendor)
    {
        $dir .= '/' . strtr($namespace, '\\', '/');
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to generate the bundle as the target directory "%s" exists but is a file.',
                        realpath($dir)
                    )
                );
            }

            $files = scandir($dir);
            if ($files != ['.', '..']) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to generate the bundle as the target directory "%s" is not empty.',
                        realpath($dir)
                    )
                );
            }

            if (!is_writable($dir)) {
                throw new \RuntimeException(
                    sprintf(
                        'Unable to generate the bundle as the target directory "%s" is not writable.',
                        realpath($dir)
                    )
                );
            }
        }

        $namespaceArray = explode('\\', $namespace);

        $basename = substr($bundle, 0, -6);
        $parameters = [
            'namespace' => str_replace('Bundle', '', $namespace),
            'namespace_slash' => str_replace(['Bundle','\\'], ['', '\\\\'], $namespace),
            'bundle' => $bundle,
            'bundle_basename' => $basename,
            'bundle_basename_lower' => strtolower($basename),
            'vendor_name' => $vendor,
            'bundle_short' => substr($namespaceArray[1], 2, -6),
            'bundle_short_lower' => strtolower(substr($namespaceArray[1], 2, -6)),
            'vendor_name_lower' => strtolower($vendor)
        ];

        $this->setSkeletonDirs([
            $this->kernel->locateResource('@EdgarEzBundleGeneratorBundle/Resources/skeleton'),
        ]);

        $this->renderFile('bundle/.gitignore.html.twig', $dir . '/.gitignore', $parameters);
        $this->renderFile('bundle/.php_cs.html.twig', $dir . '/.php_cs', $parameters);
        $this->renderFile('bundle/composer.json.html.twig', $dir . '/composer.json', $parameters);
        $this->renderFile('bundle/LICENSE.html.twig', $dir . '/LICENSE', $parameters);
        $this->renderFile('bundle/README.md.html.twig', $dir . '/README.md', $parameters);

        $this->renderFile('bundle/docs/INSTALL.md.html.twig', $dir . '/docs/INSTALL.md', $parameters);
        $this->renderFile('bundle/docs/USAGE.md.html.twig', $dir . '/docs/USAGE.md', $parameters);
        $this->renderFile('bundle/src/bundle/Bundle.php.html.twig', $dir . '/src/bundle/' . $bundle . '.php', $parameters);
        $this->renderFile('bundle/src/bundle/DependencyInjection/Extension.php.html.twig', $dir . '/src/bundle/DependencyInjection/' . $bundle . 'Extension.php', $parameters);

        self::mkdir($dir . '/src/lib/');
        self::mkdir($dir . '/src/bundle/Resources/config');
        self::mkdir($dir . '/src/bundle/Resources/views');
    }
}
