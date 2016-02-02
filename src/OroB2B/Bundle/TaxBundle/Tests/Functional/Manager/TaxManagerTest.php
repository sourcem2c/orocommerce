<?php

namespace OroB2B\Bundle\TaxBundle\Tests\Functional\Manager;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Gedmo\Tool\Logging\DBAL\QueryAnalyzer;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Yaml\Yaml;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

use OroB2B\Bundle\TaxBundle\Tests\ResultComparatorTrait;

/**
 * @dbIsolation
 */
class TaxManagerTest extends WebTestCase
{
    use ResultComparatorTrait;

    /** @var ConfigManager */
    protected $configManager;

    /** @var PropertyAccessor */
    protected $propertyAccessor;

    /** @var ManagerRegistry */
    protected $doctrine;

    protected function setUp()
    {
        $this->initClient();

        $this->loadFixtures(
            [
                'OroB2B\Bundle\TaxBundle\Tests\Functional\DataFixtures\LoadTaxRules',
            ]
        );

        $this->configManager = $this->getContainer()->get('oro_config.global');
        $this->propertyAccessor = $this->getContainer()->get('property_accessor');
        $this->doctrine = $this->getContainer()->get('doctrine');
    }

    protected function tearDown()
    {
        /** @var EntityRepository $objectRepository */
        $registry = $this->getContainer()->get('doctrine');
        $objectRepository = $registry->getRepository('OroB2BTaxBundle:TaxValue');
        $objectRepository->clear();

        $this->configManager->reset('orob2b_tax.product_prices_include_tax');
        $this->configManager->reset('orob2b_tax.use_as_base_by_default');
        $this->configManager->flush();

        parent::tearDown();
    }

    /**
     * @dataProvider methodsDataProvider
     * @param string $method
     * @param string $reference
     * @param int $expectedQueries
     * @param array $configuration
     * @param array $databaseBefore
     * @param array $expectedResult
     * @param array $databaseAfter
     */
    public function testMethods(
        $method,
        $reference,
        $expectedQueries,
        array $configuration,
        array $databaseBefore = [],
        array $expectedResult = [],
        array $databaseAfter = []
    ) {
        foreach ($configuration as $key => $value) {
            $this->configManager->set(sprintf('orob2b_tax.%s', $key), $value);
        }

        $this->prepareDatabase($databaseBefore);

        $this->executeMethod($method, $this->getReference($reference), $expectedResult, $expectedQueries);

        $this->assertDatabase($databaseAfter);
        $this->clearDatabase($databaseAfter);
    }

    /**
     * @return array
     */
    public function methodsDataProvider()
    {
        $finder = new Finder();

        $finder
            ->files()
            ->in(__DIR__)
            ->name('*.yml');

        $cases = [];

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $cases[$file->getRelativePathname()] = Yaml::parse($file);
        }

        return $cases;
    }

    /**
     * @param string $method
     * @param object $object
     * @param array $expectedResult
     * @param int $expectedQueries
     */
    protected function executeMethod($method, $object, $expectedResult, $expectedQueries)
    {
        $manager = $this->getContainer()->get('orob2b_tax.manager.tax_manager');

        $em = $this->getContainer()->get('doctrine')->getManagerForClass('OroB2BTaxBundle:TaxValue');

        $queryAnalyzer = new QueryAnalyzer($em->getConnection()->getDatabasePlatform());

        $prevLogger = $em->getConnection()->getConfiguration()->getSQLLogger();
        $em->getConnection()->getConfiguration()->setSQLLogger($queryAnalyzer);

        $this->compareResult($expectedResult, $manager->{$method}($object));

//        $this->assertCount(
//            $expectedQueries,
//            $queryAnalyzer->getExecutedQueries(),
//            implode(PHP_EOL, $queryAnalyzer->getExecutedQueries())
//        );

        // cache trigger
        $this->compareResult($expectedResult, $manager->{$method}($object));

//        $this->assertCount(
//            $expectedQueries,
//            $queryAnalyzer->getExecutedQueries(),
//            implode(PHP_EOL, $queryAnalyzer->getExecutedQueries())
//        );

        $em->getConnection()->getConfiguration()->setSQLLogger($prevLogger);
    }

    /**
     * @param array $databaseBefore
     */
    protected function prepareDatabase(array $databaseBefore)
    {
        foreach ($databaseBefore as $class => $items) {
            /** @var EntityManager $em */
            $em = $this->doctrine->getManagerForClass($class);

            foreach ($items as $reference => $item) {
                $object = new $class();

                $this->fillData($object, $item);

                $em->persist($object);
                $em->flush($object);

                $this->getReferenceRepository()->setReference($reference, $object);
            }

            $em->clear();
        }
    }

    /**
     * @param object $object
     * @param array $item
     * @return object
     */
    private function fillData($object, array $item)
    {
        foreach ($item as $property => $config) {
            $value = $this->extractValues($config);
            $isArray = is_array($config);

            if ($isArray && array_key_exists('property_value', $config) && is_array($config['property_value'])) {
                $value = $this->fillData($value, $config['property_value']);
            }

            if ($isArray && array_key_exists('property', $config)) {
                $value = $this->propertyAccessor->getValue($value, $config['property']);
            }

            $propertyPath = $this->getPropertyPath($object, $property);
            $this->propertyAccessor->setValue($object, $propertyPath, $value);
        }

        return $object;
    }

    /**
     * @param mixed $value
     * @param string $path
     * @return string
     */
    private function getPropertyPath($value, $path)
    {
        return is_array($value) || $value instanceof \ArrayAccess ? sprintf('[%s]', $path) : (string)$path;
    }

    /**
     * @param array|string $config
     * @return mixed
     */
    private function extractValues($config)
    {
        if (!is_array($config)) {
            return $config;
        }

        $hasClass = array_key_exists('class', $config);
        if ($hasClass && array_key_exists('query', $config)) {
            return $this->doctrine
                ->getRepository($config['class'])
                ->findOneBy($config['query']);
        } elseif ($hasClass) {
            return new $config['class']();
        } elseif (array_key_exists('reference', $config)) {
            return $this->getReference($config['reference']);
        } elseif (array_key_exists('property_value', $config)) {
            return $config['property_value'];
        }

        return $config;
    }

    /**
     * @param array $databaseAfter
     */
    protected function assertDatabase(array $databaseAfter)
    {
        foreach ($databaseAfter as $class => $items) {
            $repository = $this->doctrine->getRepository($class);

            foreach ($items as $reference => $item) {
                $this->assertNotEmpty($repository->findBy($item), sprintf('%s %s', $class, json_encode($item)));
            }
        }
    }

    /**
     * @param array $databaseBefore
     */
    protected function clearDatabase(array $databaseBefore)
    {
        foreach ($databaseBefore as $class => $items) {
            /** @var EntityRepository $repository */
            $repository = $this->doctrine->getRepository($class);

            $repository
                ->createQueryBuilder('e')
                ->delete($class, 'e')
                ->getQuery()
                ->execute();
        }
    }
}
