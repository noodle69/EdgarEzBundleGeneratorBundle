<?php

namespace Edgar\EzBundleGeneratorBundle\Command;

use Edgar\EzBundleGenerator\Generator\BundleGenerator;
use Edgar\EzBundleGenerator\Generator\Validator\Validators;
use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\KernelInterface;

class GenerateBundleCommand extends GeneratorCommand
{
    /**
     * Configure eZ Bundle generator command.
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace of the bundle to create'),
                new InputOption('with-security', '', InputOption::VALUE_OPTIONAL, 'Generate Security code'),
                new InputOption('platform-ui', '', InputOption::VALUE_OPTIONAL, 'Is it a bundle for eZ Platform Admin UI'),
                new InputOption('dir', '', InputOption::VALUE_REQUIRED, 'The directory where to create the bundle'),
                new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The optional bundle name'),
            ])
            ->setHelp(<<<EOT
The <info>edgarez:generate:bundle</info> command helps you generates new eZ Platform Bundle.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction:

<info>php bin/console edgarez:generate:bundle --namespace=Acme/EzFooBundle</info>

Note that you can use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any
problem.

If you want to disable any user interaction, use <comment>--no-interaction</comment> but don't forget to pass 
all needed options:

<info>php bin/console edgarez:generate:bundle --namespace=Acme/EzFooBundle --with-security --platform-ui --dir=src --no-interaction</info>

Note that the bundle namespace must end with "Bundle" and next to vendor name, bundle name should start with "Ez".
EOT
            )
            ->setName('edgarez:generate:bundle')
            ->setDescription('Generate Structure code for new eZ Platform Bundle');
    }

    /**
     * Execute FieldType generate command.
     *
     * @param InputInterface $input console input
     * @param OutputInterface $output console output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new Question($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        foreach (['namespace', 'dir'] as $option) {
            if (null === $input->getOption($option)) {
                throw new \RuntimeException(sprintf('The "%s" option must be provided.', $option));
            }
        }

        // validate the namespace, but don't require a vendor namespace
        $namespace = Validators::validateBundleNamespace($input->getOption('namespace'), false);
        if (!$bundle = $input->getOption('bundle-name')) {
            $bundle = strtr($namespace, ['\\' => '']);
        }
        $vendorArray = explode('\\', $namespace);
        $vendor = Validators::validateVendorName($vendorArray[0]);

        $bundle = Validators::validateBundleName($bundle);
        $dir = self::validateTargetDir($input->getOption('dir'));
        $withSecurity = $input->getOption('with-security') ?? false;
        $platformUI = $input->getOption('platform-ui') ?? false;

        $questionHelper->writeSection($output, 'eZ Platform Bundle structure generation');

        if (!$this->getContainer()->get('filesystem')->isAbsolutePath($dir)) {
            $dir = getcwd() . '/' . $dir;
        }

        /** @var BundleGenerator $generator */
        $generator = $this->getGenerator();
        $generator->generate($namespace, $bundle, $dir, $vendor, $withSecurity, $platformUI);

        $output->writeln('Generating the eZ Platform Bundle structure code: <info>OK</info>');

        $errors = [];
        $runner = $questionHelper->getRunner($output, $errors);

        // check that the namespace is already autoloaded
        $runner($this->checkAutoloader($output, $namespace, $bundle));

        // register the bundle in the Kernel class
        $runner(
            $this->updateKernel(
                $questionHelper,
                $input,
                $output,
                $this->getContainer()->get('kernel'),
                $namespace,
                $bundle
            )
        );

        $questionHelper->writeGeneratorSummary($output, $errors);

        return 0;
    }

    /**
     * Inform dev to add bundle to composer.json.
     *
     * @param OutputInterface $output
     * @param string $namespace
     * @param string $bundle
     *
     * @return array
     */
    protected function checkAutoloader(OutputInterface $output, string $namespace, string $bundle): array
    {
        $output->write('Checking that the bundle is autoloaded: ');
        if (!class_exists($namespace . '\\' . $bundle)) {
            return [
                '- Edit the <comment>composer.json</comment> file and register the bundle',
                '  namespace in the "autoload" section:',
                '',
            ];
        }

        return [];
    }

    /**
     * Add Bundle to AppKernel.
     *
     * @param QuestionHelper $questionHelper
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param KernelInterface $kernel
     * @param string $namespace
     * @param string $bundle
     *
     * @return array
     */
    protected function updateKernel(
        QuestionHelper $questionHelper,
        InputInterface $input,
        OutputInterface $output,
        KernelInterface $kernel,
        string $namespace, string $bundle
    ) {
        $auto = true;
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion(
                $questionHelper->getQuestion(
                    'Confirm automatic update of your Kernel',
                    'yes',
                    '?'
                ),
                true
            );
            $auto = $questionHelper->ask($input, $output, $question);
        }

        $output->write('Enabling the bundle inside the Kernel: ');
        $manip = new KernelManipulator($kernel);
        try {
            $ret = $auto ? $manip->addBundle($namespace . '\\' . $bundle) : false;

            if (!$ret) {
                $reflected = new \ReflectionObject($kernel);

                return [
                    sprintf('- Edit <comment>%s</comment>', $reflected->getFilename()),
                    '  and add the following bundle in the <comment>AppKernel::registerBundles()</comment> method:',
                    '',
                    sprintf('    <comment>new %s(),</comment>', $namespace . '\\' . $bundle),
                    '',
                ];
            }
        } catch (\RuntimeException $e) {
            return [
                sprintf(
                    'Bundle <comment>%s</comment> is already defined in <comment>AppKernel::registerBundles()</comment>.',
                    $namespace . '\\' . $bundle
                ),
                '',
            ];
        }

        return [];
    }

    /**
     * Interact with dev to get informations to generate new FieldType Bundle.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the eZ Platform Bundle generator');

        $namespace = $this->getNamespace($input, $output, $questionHelper);
        $bundle = $this->getBundle($input, $output, $questionHelper, $namespace);
        $dir = $this->getDir($input, $output, $questionHelper, $bundle, $namespace);
        $this->getWithSecurity($input, $output, $questionHelper);
        $this->getPlatformUI($input, $output, $questionHelper);

        // summary
        $output->writeln([
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf(
                "You are going to generate a \"<info>%s\\%s</info>\" bundle\nin \"<info>%s</info>\".",
                $namespace,
                $bundle,
                $dir
            ),
            '',
        ]);
    }

    /**
     * Get FieldType namespace.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     *
     * @return mixed|null|string
     */
    private function getNamespace(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        // namespace
        $namespace = null;
        try {
            // validate the namespace option (if any) but don't require the vendor namespace
            $namespace = $input->getOption('namespace')
                ? Validators::validateBundleNamespace($input->getOption('namespace'), false)
                : null;
        } catch (\Exception $error) {
            /** @var FormatterHelper $formatterHelper */
            $formatterHelper = $questionHelper->getHelperSet()->get('formatter');
            $output->writeln(
                $formatterHelper->formatBlock($error->getMessage(), 'error')
            );
        }

        if (null === $namespace) {
            $acceptedNamespace = false;
            while (!$acceptedNamespace) {
                $question = new Question(
                    $questionHelper->getQuestion(
                        'FieldType Bundle namespace',
                        $input->getOption('namespace')
                    ),
                    $input->getOption('namespace')
                );
                $question->setValidator(function ($answer) {
                    return Validators::validateBundleNamespace($answer, false);
                });
                $namespace = $questionHelper->ask($input, $output, $question);

                // mark as accepted, unless they want to try again below
                $acceptedNamespace = true;

                // see if there is a vendor namespace. If not, this could be accidental
                if (false === strpos($namespace, '\\')) {
                    // language is (almost) duplicated in Validators
                    $msg = [];
                    $msg[] = '';
                    $msg[] = sprintf(
                        'The namespace sometimes contain a vendor namespace (e.g. <info>VendorName/BlogBundle</info> instead of simply <info>%s</info>).',
                        $namespace,
                        $namespace
                    );
                    $msg[] = 'If you\'ve *did* type a vendor namespace, try using a forward slash <info>/</info> (<info>Acme/BlogBundle</info>)?';
                    $msg[] = '';
                    $output->writeln($msg);

                    $question = new ConfirmationQuestion($questionHelper->getQuestion(
                        sprintf(
                            'Keep <comment>%s</comment> as the fieldtype bundle namespace (choose no to try again)?',
                            $namespace
                        ),
                        'yes'
                    ), true);
                    $acceptedNamespace = $questionHelper->ask($input, $output, $question);
                }
            }
            $input->setOption('namespace', $namespace);
        }

        return $namespace;
    }

    /**
     * Get FieldType bundle name.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     * @param $namespace
     *
     * @return mixed|null|string
     */
    private function getBundle(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
        $namespace
    ) {
        // bundle name
        $bundle = null;
        try {
            $bundle = $input->getOption('bundle-name')
                ? Validators::validateBundleName($input->getOption('bundle-name'))
                : null;
        } catch (\Exception $error) {
            /** @var FormatterHelper $formatterHelper */
            $formatterHelper = $questionHelper->getHelperSet()->get('formatter');
            $output->writeln(
                $formatterHelper->formatBlock($error->getMessage(), 'error')
            );
        }

        if (null === $bundle) {
            $bundle = strtr($namespace, ['\\Bundle\\' => '', '\\' => '']);

            $output->writeln([
                '',
                'In your code, a bundle is often referenced by its name. It can be the',
                'concatenation of all namespace parts but it\'s really up to you to come',
                'up with a unique name (a good practice is to start with the vendor name).',
                'Based on the namespace, we suggest <comment>' . $bundle . '</comment>.',
                '',
            ]);
            $question = new Question($questionHelper->getQuestion('FieldType Bundle name', $bundle), $bundle);
            $question->setValidator(
                ['Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName']
            );
            $bundle = $questionHelper->ask($input, $output, $question);
            $input->setOption('bundle-name', $bundle);
        }

        return $bundle;
    }

    /**
     * Set Bundle dir.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     * @param $bundle
     * @param $namespace
     */
    private function getDir(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
        $bundle,
        $namespace
    ) {
        // target dir
        $dir = null;
        try {
            $dir = $input->getOption('dir')
                ? self::validateTargetDir($input->getOption('dir'))
                : null;
        } catch (\Exception $error) {
            /** @var FormatterHelper $formatterHelp */
            $formatterHelp = $questionHelper->getHelperSet()->get('formatter');
            $output->writeln($formatterHelp->formatBlock($error->getMessage(), 'error'));
        }

        if (null === $dir) {
            $dir = dirname($this->getContainer()->getParameter('kernel.root_dir')) . '/src';

            $output->writeln([
                '',
                'The bundle can be generated anywhere. The suggested default directory uses',
                'the standard conventions.',
                '',
            ]);
            $question = new Question($questionHelper->getQuestion('Target directory', $dir), $dir);
            $question->setValidator(function ($dir) use ($bundle, $namespace) {
                return self::validateTargetDir($dir);
            });
            $dir = $questionHelper->ask($input, $output, $question);
            $input->setOption('dir', $dir);
        }
    }

    private function getWithSecurity(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper
    ) {
        $output->writeln([
            '',
            'Generate Security code',
            '',
        ]);

        $question = new Question($questionHelper->getQuestion('Do you want to generate security code', 'no', '?'), false);
        $withSecurity = $questionHelper->ask($input, $output, $question) == 'yes' ? true : false;
        $input->setOption('with-security', $withSecurity);
    }

    private function getPlatformUI(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper
    ) {
        $output->writeln([
            '',
            'Is it a bundle for eZ Platform  Admin UI ?',
            '',
        ]);

        $question = new Question($questionHelper->getQuestion('Generate code for eZ Platform Admin UI', 'no', '?'), false);
        $platformUI = $questionHelper->ask($input, $output, $question) == 'yes' ? true : false;
        $input->setOption('platform-ui', $platformUI);
    }

    /**
     * Initialize FieldType generator.
     *
     * @return BundleGenerator
     */
    protected function createGenerator(): BundleGenerator
    {
        return new BundleGenerator(
            $this->getContainer()->get('kernel')
        );
    }

    /**
     * Get valid FieldType bundle dir.
     *
     * @param string $dir
     *
     * @return string
     */
    public static function validateTargetDir(string $dir): string
    {
        return '/' === substr($dir, -1, 1) ? $dir : $dir . '/';
    }
}
