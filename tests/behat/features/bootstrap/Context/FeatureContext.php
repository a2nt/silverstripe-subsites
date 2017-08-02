<?php

namespace Subsites\Test\Behaviour;

if(!class_exists('SilverStripe\BehatExtension\Context\SilverStripeContext')) return;

use SilverStripe\BehatExtension\Context\SilverStripeContext,
    SilverStripe\BehatExtension\Context\BasicContext,
    SilverStripe\BehatExtension\Context\LoginContext,
    SilverStripe\BehatExtension\Context\FixtureContext,
    SilverStripe\Framework\Test\Behaviour\CmsFormsContext,
    SilverStripe\Framework\Test\Behaviour\CmsUiContext,
    SilverStripe\Cms\Test\Behaviour;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FixtureBlueprint;
use SilverStripe\Security\Member;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BehatFixtureFactory;


// PHPUnit
require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

/**
 * Features context
 *
 * Context automatically loaded by Behat.
 * Uses subcontexts to extend functionality.
 */
class FeatureContext extends SilverStripeContext {
    
    /**
     * @var FixtureFactory
     */
    protected $fixtureFactory;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters) {
        parent::__construct($parameters);

        $this->useContext('BasicContext', new BasicContext($parameters));
        $this->useContext('LoginContext', new LoginContext($parameters));
        $this->useContext('CmsFormsContext', new CmsFormsContext($parameters));
        $this->useContext('CmsUiContext', new CmsUiContext($parameters));

        $fixtureContext = new FixtureContext($parameters);
        $fixtureContext->setFixtureFactory($this->getFixtureFactory());
        $this->useContext('FixtureContext', $fixtureContext);

        // Use blueprints to set user name from identifier
        $factory = $fixtureContext->getFixtureFactory();
        $blueprint = Injector::inst()->create(FixtureBlueprint::class, Member::class);
        $blueprint->addCallback('beforeCreate', function($identifier, &$data, &$fixtures) {
            if(!isset($data['FirstName'])) $data['FirstName'] = $identifier;
        });
        $factory->define(Member::class, $blueprint);

        // Auto-publish pages
        foreach(ClassInfo::subclassesFor(SiteTree::class) as $id => $class) {
            $blueprint = Injector::inst()->create(FixtureBlueprint::class, $class);
            $blueprint->addCallback('afterCreate', function($obj, $identifier, &$data, &$fixtures) {
                $obj->publish('Stage', 'Live');
            });
            $factory->define($class, $blueprint);
        } 
    }

    public function setMinkParameters(array $parameters) {
        parent::setMinkParameters($parameters);
        
        if(isset($parameters['files_path'])) {
            $this->getSubcontext('FixtureContext')->setFilesPath($parameters['files_path']);    
        }
    }

    /**
     * @return FixtureFactory
     */
    public function getFixtureFactory() {
        if(!$this->fixtureFactory) {
            $this->fixtureFactory = Injector::inst()->create(BehatFixtureFactory::class);
        }

        return $this->fixtureFactory;
    }

    public function setFixtureFactory(FixtureFactory $factory) {
        $this->fixtureFactory = $factory;
    }

    //
    // Place your definition and hook methods here:
    //
    //    /**
    //     * @Given /^I have done something with "([^"]*)"$/
    //     */
    //    public function iHaveDoneSomethingWith($argument) {
    //        $container = $this->kernel->getContainer();
    //        $container->get('some_service')->doSomethingWith($argument);
    //    }
    //
}
