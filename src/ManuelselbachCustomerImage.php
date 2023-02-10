<?php

declare(strict_types=1);

namespace ManuelselbachCustomerImage;

use ManuelselbachCustomerImage\Service\CustomerCustomFieldService;
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
        $customFieldService = new CustomerCustomFieldService(
            $this->container,
            $this->container->get('custom_field_set.repository')
        );

        $customFieldService->addCustomFields($installContext->getContext());
    }
}
