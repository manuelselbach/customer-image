<?php

declare(strict_types=1);

namespace ManuelselbachCustomerImage\Service;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomerCustomFieldService
{
    public const CUSTOM_FIELD_AVATAR = 'avatar';
    public const ID_CUSTOM_FIELD_SET = 'ce9adee7f8bd4a63930b7c89b9a3fe29';
    public const ID_GO_2_SKATE_TOKEN = '1b8455779f2147289a840ab6085fa149';

    protected ContainerInterface $container;
    protected EntityRepositoryInterface $customFieldSetRepository;

    public function __construct(
        ContainerInterface $container,
        EntityRepositoryInterface $customFieldSetRepository
    ) {
        $this->container                = $container;
        $this->customFieldSetRepository = $customFieldSetRepository;
    }

    public function addCustomFields(Context $context): void
    {
        /** @var CustomerDefinition $customerDefinition */
        $customerDefinition = $this->container->get(CustomerDefinition::class);

        if ($customerDefinition == null) {
            return;
        }

        $this->customFieldSetRepository->upsert(
            [
                [
                    'id'     => static::ID_CUSTOM_FIELD_SET,
                    'name'   => 'customerImage',
                    'config' => [
                        'label' => [
                            'en-GB' => 'Customer Image',
                            'de-DE' => 'Bild vom Kunden',
                        ],
                    ],
                    'customFields' => [
                        [
                            'id'     => static::ID_GO_2_SKATE_TOKEN,
                            'name'   => static::CUSTOM_FIELD_AVATAR,
                            'type'   => CustomFieldTypes::MEDIA,
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Avatar',
                                    'de-DE' => 'Avatar',
                                ],
                                'componentName'   => 'sw-media-field',
                                'customFieldType' => 'media',
                            ],
                        ],
                    ],
                    'relations' => [
                        [
                            'id'         => static::ID_GO_2_SKATE_TOKEN,
                            'entityName' => $customerDefinition->getEntityName(),
                        ],
                    ],
                ],
            ],
            $context
        );
    }
}
