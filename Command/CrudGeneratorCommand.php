<?php

namespace Letsweb\Bundle\OroGeneratorBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Container;

class CrudGeneratorCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('lw:oro:generate:crud')
            ->setDescription('Generates a crud based on a Doctrine entity')
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
                new InputOption('vendor', '', InputOption::VALUE_REQUIRED, 'The vendor name'),
            ))
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getHelper('question');

        $shortcut = Validators::validateEntityName($input->getOption('entity'));

        $entity = str_replace('/', '\\', $shortcut);

        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The entity name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/Post)', $entity));
        }

        list($bundle, $entity) = array(substr($entity, 0, $pos), substr($entity, $pos + 1));

        $parameters = array();

        $output->writeln('Bundle : '.$bundle);
        $output->writeln('Entity shortcut annotation : '.$entity);
        $output->writeln('Entity shortcut annotation : '.$input->getOption('entity'));
        $output->writeln('route-prefix : '.$input->getOption('route-prefix'));

        try {
            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
            $factory = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
            $metadata = $factory->getClassMetadata($entityClass)->getMetadata();
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Entity "%s" does not exist in the "%s" bundle. You may have mistyped the bundle name or maybe the entity doesn\'t exist yet (create it first with the "doctrine:generate:entity" command).', $entity, $bundle));
        }

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);
        $dir = $bundle->getPath();
        $parameters = array(
            'namespace'         => $bundle->getNamespace(),
            'bundleName'        => $bundle->getName(),
            'bundleBaseName'    => substr($bundle->getName(), 0, -6),
            'bundleAlias'       => Container::underscore($bundle->getName()),
            'entity'            => $entity,
            'routePrefix'       => $input->getOption('route-prefix'),
            'vendor'            => $input->getOption('vendor'),
            'shortcutEntity'    => $shortcut,
            'entityClass'       => $entityClass
        );

        // Controllers
        $this->renderFile('crud/Controller/Api/Rest/Controller.php.twig', $dir.'/Controller/Api/Rest/'.$entity.'Controller.php', $parameters);
        $this->renderFile('crud/Controller/Controller.php.twig', $dir.'/Controller/'.$entity.'Controller.php', $parameters);

        // Views
        $this->renderFile('crud/views/index.html.twig', $dir.'/Resources/views/'.$entity.'/index.html.twig', $parameters);
        //$this->renderFile('crud/views/update.html.twig', $dir.'/Resources/views/'.$entity.'/update.html.twig', $parameters);
        $this->renderFile('crud/views/view.html.twig', $dir.'/Resources/views/'.$entity.'/view.html.twig', $parameters);

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