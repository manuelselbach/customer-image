<?php

declare(strict_types=1);

namespace ManuelselbachCustomerImage;

use ManuelselbachCustomerImage\Service\CustomerCustomFieldService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class ManuelselbachCustomerImage extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->addCustomFields($installContext);
    }

    private function addCustomFields(InstallContext $installContext): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        if (!$customFieldSetRepository instanceof EntityRepositoryInterface) {
            return;
        }

        $customFieldService = new CustomerCustomFieldService($this->container, $customFieldSetRepository);
        $customFieldService->addCustomFields($installContext->getContext());
    }
}
