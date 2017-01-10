<?php

namespace Oro\Bundle\CustomerBundle\Layout\DataProvider;

use Doctrine\Common\Persistence\ManagerRegistry;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\LayoutBundle\Layout\DataProvider\AbstractFormProvider;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Form\Type\FrontendAccountUserRegistrationType;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;

class FrontendAccountUserRegistrationFormProvider extends AbstractFormProvider
{
    const ACCOUNT_USER_REGISTER_ROUTE_NAME = 'oro_customer_frontend_account_user_register';
    
    /** @var ManagerRegistry */
    protected $managerRegistry;

    /** @var ConfigManager */
    private $configManager;

    /** @var WebsiteManager */
    private $websiteManager;

    /** @var UserManager */
    private $userManager;

    /**
     * @param FormFactoryInterface    $formFactory
     * @param ManagerRegistry         $managerRegistry
     * @param ConfigManager           $configManager
     * @param WebsiteManager          $websiteManager
     * @param UserManager             $userManager
     * @param UrlGeneratorInterface   $router
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        ManagerRegistry $managerRegistry,
        ConfigManager $configManager,
        WebsiteManager $websiteManager,
        UserManager $userManager,
        UrlGeneratorInterface $router
    ) {
        parent::__construct($formFactory, $router);

        $this->managerRegistry = $managerRegistry;
        $this->configManager = $configManager;
        $this->websiteManager = $websiteManager;
        $this->userManager = $userManager;
    }

    /**
     * @return FormView
     */
    public function getRegisterFormView()
    {
        $accountUser = $this->createAccountUser();

        $options['action'] = $this->generateUrl(self::ACCOUNT_USER_REGISTER_ROUTE_NAME);

        return $this->getFormView(FrontendAccountUserRegistrationType::NAME, $accountUser, $options);
    }

    /**
     * @return FormInterface
     */
    public function getRegisterForm()
    {
        $accountUser = $this->createAccountUser();

        $options['action'] = $this->generateUrl(self::ACCOUNT_USER_REGISTER_ROUTE_NAME);

        return $this->getForm(FrontendAccountUserRegistrationType::NAME, $accountUser, $options);
    }

    /**
     * @return CustomerUser
     *
     * TODO: remove logic with creating new account user from data provider
     */
    private function createAccountUser()
    {
        $accountUser = new CustomerUser();

        $defaultOwnerId = $this->configManager->get('oro_customer.default_account_owner');
        if (!$defaultOwnerId) {
            throw new \RuntimeException('Application Owner is empty');
        }

        $website = $this->websiteManager->getCurrentWebsite();
        if (!$website) {
            throw new \RuntimeException('Website is empty');
        }

        /** @var Organization $organization */
        $organization = $website->getOrganization();
        if (!$organization) {
            throw new \RuntimeException('Website organization is empty');
        }

        $defaultRole = $this->managerRegistry
            ->getManagerForClass('OroCustomerBundle:CustomerUserRole')
            ->getRepository('OroCustomerBundle:CustomerUserRole')
            ->getDefaultCustomerUserRoleByWebsite($website);

        if (!$defaultRole) {
            throw new \RuntimeException(sprintf('Role "%s" was not found', CustomerUser::ROLE_DEFAULT));
        }

        /** @var User $owner */
        $owner = $this->userManager->getRepository()->find($defaultOwnerId);

        $accountUser
            ->setOwner($owner)
            ->addOrganization($organization)
            ->setOrganization($organization)
            ->addRole($defaultRole);

        return $accountUser;
    }
}
