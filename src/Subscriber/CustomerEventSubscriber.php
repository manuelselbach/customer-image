<?php

declare(strict_types=1);

namespace ManuelselbachCustomerImage\Subscriber;

use Exception;
use ManuelselbachCustomerImage\Service\CustomerCustomFieldService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerEventSubscriber implements EventSubscriberInterface
{
    private const FORM_FIELD_CUSTOMER_IMAGE = 'customer_image';
    private RequestStack $requestStack;
    private EntityRepository $customerRepository;
    private EntityRepositoryInterface $mediaRepository;
    private FileSaver $mediaUpdater;
    private FileNameProvider $fileNameProvider;
    private bool $alreadyCalled = false;

    public function __construct(
        EntityRepository $customerRepository,
        RequestStack $requestStack,
        EntityRepositoryInterface $mediaRepository,
        FileSaver $mediaUpdater,
        FileNameProvider $fileNameProvider
    ) {
        $this->customerRepository = $customerRepository;
        $this->requestStack       = $requestStack;
        $this->mediaRepository    = $mediaRepository;
        $this->mediaUpdater       = $mediaUpdater;
        $this->fileNameProvider   = $fileNameProvider;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomerEvents::MAPPING_REGISTER_CUSTOMER => 'onMappingCustomer',
            CustomerEvents::CUSTOMER_WRITTEN_EVENT    => 'onCustomerWritten',
        ];
    }

    public function onMappingCustomer(DataMappingEvent $event): void
    {
        if ($this->alreadyCalled === true) {
            return;
        }
        $this->alreadyCalled = true;

        $fileUploaded = $this->uploadFile($event->getContext());

        if ($fileUploaded === '') {
            return;
        }

        // add custom field avatar
        $customer                                                                  = $event->getOutput();
        $customer['customFields'][CustomerCustomFieldService::CUSTOM_FIELD_AVATAR] = $fileUploaded;

        $event->setOutput($customer);
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        if ($this->alreadyCalled === true) {
            return;
        }
        $this->alreadyCalled = true;

        $fileUploaded = $this->uploadFile($event->getContext());

        if ($fileUploaded === '') {
            return;
        }

        $customerId = $event->getIds()[0];

        /** @var CustomerEntity $customer */
        $customer = $this->retrieveActiveCustomerById($customerId, $event->getContext());

        if ($customer === null) {
            return;
        }

        $this->customerRepository->upsert([
            [
                'id'           => $customer->getId(),
                'customFields' => [
                    CustomerCustomFieldService::CUSTOM_FIELD_AVATAR => $fileUploaded,
                ],
            ],
        ], $event->getContext());
    }

    public function retrieveActiveCustomerById(string $id, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $id));

        return $this->customerRepository->search($criteria, $context)->first();
    }

    private function uploadFile($context): string
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return '';
        }

        $file = $request->files->get(static::FORM_FIELD_CUSTOMER_IMAGE);

        if ($file === null) {
            return '';
        }

        $testSupportedExtension = ['gif', 'png', 'jpg', 'jpeg'];

        $fileName  = $file->getClientOriginalName();
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (!in_array($extension, $testSupportedExtension, true)) {
            return '';
        }

        $fileName = str_replace('.' . $extension, '', $fileName) . '_' . Random::getInteger(100, 1000) . '.' . $extension;

        $mediaId = Uuid::randomHex();
        $media   = [
            [
                'id'            => $mediaId,
                'name'          => $fileName,
                'fileName'      => $fileName,
                'mimeType'      => $file->getClientMimeType(),
                'fileExtension' => $file->guessExtension(),
            ],
        ];

        $mediaId = $this->mediaRepository->create($media, Context::createDefaultContext())
            ->getEvents()
            ->getElements()[1]
            ->getIds()[0];

        if (is_array($mediaId)) {
            $mediaId = $mediaId['mediaId'];
        }

        try {
            $this->upload($file, $fileName, $mediaId, $context);
        } catch (Exception $exception) {
            // TODO: lets see
        }

        return $mediaId;
    }

    private function upload($file, string $fileName, string $mediaId, $context): void
    {
        $this->mediaUpdater->persistFileToMedia(
            new MediaFile(
                $file->getRealPath(),
                $file->getMimeType(),
                $file->guessExtension(),
                $file->getSize()
            ),
            $this->fileNameProvider->provide(
                $fileName,
                $file->getExtension(),
                $mediaId,
                $context
            ),
            $mediaId,
            $context
        );
    }
}
