<?php
/**
 * Created by PhpStorm.
 * User: geoffroycochard
 * Date: 20/10/2015
 * Time: 21:29
 */

namespace Letsweb\Bundle\OroGeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Container;

class BundleGeneratorCommand extends ContainerAwareCommand
{

    protected $skeletonDir;// = '/../Resources/skeleton';

    protected function configure()
    {
        $this
            ->setName('lw:oro:generator:bundle')
            ->setDescription('Generate a bundle with Oro requirements')
            ->setDefinition(array(
                new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace of the bundle to create'),
                new InputOption('dir', '', InputOption::VALUE_REQUIRED, 'The directory where to create the bundle', 'src/'),
                new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The optional bundle name'),
                new InputOption('format', '', InputOption::VALUE_REQUIRED, 'Use the format for configuration files (php, xml, yml, or annotation)'),
                new InputOption('shared', '', InputOption::VALUE_NONE, 'Are you planning on sharing this bundle across multiple applications?'),
            ))
            ->setHelp(<<<EOT
The <info>%command.name%</info> command helps you generates new bundles.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction
(<comment>--namespace</comment> is the only one needed if you follow the
conventions):

<info>php %command.full_name% --namespace=Acme/BlogBundle</info>

Note that you can use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any
problems.

If you want to disable any user interaction, use <comment>--no-interaction</comment> but don't forget to pass all needed options:

<info>php %command.full_name% --namespace=Acme/BlogBundle --dir=src [--bundle-name=...] --no-interaction</info>

Note that the bundle namespace must end with "Bundle".
EOT
            )
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When namespace doesn't end with Bundle
     * @throws \RuntimeException         When bundle can't be executed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $questionHelper = $this->getHelper('question');

        $output->writeln(array(
            'Give your bundle a descriptive name, like <comment>BlogBundle</comment>.',
        ));

        /*
         * Namespace / Bundle name
         */
        $question = new Question('<question>Namespace ?</question> : ', 'Letsweb/Bundle/DemoBundle');
        $question->setValidator(function ($inputNamespace) {
            return Validators::validateBundleNamespace($inputNamespace, false);
        });
        $namespace = $questionHelper->ask($input, $output, $question);
        $input->setOption('namespace', $namespace);

        $bundleName = strtr($namespace, array('\\Bundle\\' => '', '\\' => ''));
        $input->setOption('bundle-name', $bundleName);

        $output->writeln('bundle name : '.$input->getOption('bundle-name'));
        $output->writeln('namespace : '.$input->getOption('namespace'));

        // Target directory
        $projectRootDir = dirname($this->getContainer()->getParameter('kernel.root_dir'));
        $dir = $projectRootDir.'/src/'.str_replace('\\','/',$namespace);
        $output->writeln('dir : '.$dir);

        // Check if targetDirectory is ok ?
        if (file_exists($dir)) {
            if (!is_dir($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" exists but is a file.', realpath($dir)));
            }
            $files = scandir($dir);
            if ($files != array('.', '..')) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not empty.', realpath($dir)));
            }
            if (!is_writable($dir)) {
                throw new \RuntimeException(sprintf('Unable to generate the bundle as the target directory "%s" is not writable.', realpath($dir)));
            }
        }

        $parameters = array(
            'namespace'         => $namespace,
            'bundleName'        => $bundleName,
            'bundleBaseName'    => substr($bundleName, 0, -6),
            'bundleAlias'       => Container::underscore($bundleName)
        );

        // Bundle oro autoloader
        $this->renderFile('bundle/Bundle.php.twig', $dir.'/'.$bundleName.'.php', $parameters);
        $this->renderFile('bundle/bundles.yml.twig', $dir.'/Resources/config/oro/bundles.yml', $parameters);
        $this->renderFile('bundle/Extension.php.twig', $dir.'/DependencyInjection/'.$bundleName.'Extension.php', $parameters);
        $this->renderFile('bundle/Configuration.php.twig', $dir.'/DependencyInjection/Configuration.php', $parameters);
        $this->renderFile('bundle/services.yml.twig', $dir.'/Resources/config/services.yml', $parameters);


    }

    protected function render($template, $parameters)
    {
        $twig = $this->getTwigEnvironment();

        return $twig->render($template, $parameters);
    }

    /**
     * Get the twig environment that will render skeletons.
     *
     * @return \Twig_Environment
     */
    protected function getTwigEnvironment()
    {
        return new \Twig_Environment(new \Twig_Loader_Filesystem(__DIR__.'/../Resources/skeleton'), array(
            'debug' => true,
            'cache' => false,
            'strict_variables' => true,
            'autoescape' => false,
        ));
    }

    protected function renderFile($template, $target, $parameters)
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        return file_put_contents($target, $this->render($template, $parameters));
    }



}