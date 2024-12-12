<?php
/**
 * Orange Cat
 * Copyright (C) 2018 Orange Cat
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://opensource.org/licenses/gpl-3.0.html
 *
 * @category Orangecat
 * @package Orangecat_MediaGalleryProcessor
 * @copyright Copyright (c) 2018 Orange Cat
 * @license http://opensource.org/licenses/gpl-3.0.html GNU General Public License,version 3 (GPL-3.0)
 * @author Oliverio Gombert <olivertar@gmail.com>
 */

namespace SyncEngine\Connector\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Gallery\DeleteValidator;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Customer\Api\AccountManagementCustomAttributesTest;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Catalog\Model\ProductRepository\MediaGalleryProcessor;
use Magento\Framework\Api\ImageProcessorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Api\ImageContentFactory;
use SyncEngine\Connector\Helper\Data;
use Magento\Catalog\Model\Product\Media\ConfigInterface;

class MediaGalleryProcessorPlugin extends \Magento\Catalog\Model\ProductRepository\MediaGalleryProcessor
{
    private Data $syncengineData;
    private ObjectManager $objectManager;
    private ConfigInterface $mediaConfig;
    private Filesystem $filesystem;

    /**
     * @param Processor $processor
     * @param ImageContentInterfaceFactory $contentFactory
     * @param ImageProcessorInterface $imageProcessor
     * @param DeleteValidator|null $deleteValidator
     */
    public function __construct(
        Processor $processor,
        ImageContentInterfaceFactory $contentFactory,
        ImageProcessorInterface $imageProcessor,
        ?DeleteValidator $deleteValidator = null
    ) {
        // @todo Fixme, could not load with autowiring for some reason.
        $this->objectManager = ObjectManager::getInstance();
        $this->syncengineData = $this->objectManager->get(Data::class);

        parent::__construct($processor, $contentFactory, $imageProcessor, $deleteValidator);
    }

    public function _getProductMediaPath( $path ): string
    {
        if ( ! isset( $this->mediaConfig ) ) {
            $this->mediaConfig = $this->objectManager->get( ConfigInterface::class );
        }
        if ( ! isset( $this->filesystem ) ) {
            $this->filesystem = $this->objectManager->get(Filesystem::class);
        }

        $dir = $this->filesystem->getDirectoryRead( DirectoryList::MEDIA );
        return $dir->getAbsolutePath( $this->mediaConfig->getMediaPath( $path ) );
    }

    /**
     * @return ImageContentInterface
     */
    public function _createImageContent()
    {
        $imageFactory = $this->objectManager->create(\Magento\Framework\Api\ImageContentFactory::class);
        return $imageFactory->create();
    }

    /**
     * @param $url
     *
     * @return ImageContentInterface|null
     */
    public function fetchImageContentFromUrl( $url )
    {
        if ( ! $this->syncengineData->isMediaGalleryApiPassUrlEnabled() ) {
            return null;
        }

        /** @var Curl $curl */
        $curl = $this->objectManager->create(Curl::class);
        $curl->get( $url );

        $name     = pathinfo( $url, PATHINFO_FILENAME );
        $image    = base64_encode( $curl->getBody() );
        $headers  = $curl->getHeaders();
        $mimeType = $headers['Content-Type'] ?? $headers['content-type'] ?? $headers['Content_Type'] ?? $headers['content_type'];

        return $this->_createImageContent()->setType( $mimeType )->setName( $name )->setBase64EncodedData( $image );
    }

    /**
     * @param $path
     *
     * @return ImageContentInterface|null
     */
    public function fetchImageContentFromPath( $path )
    {
        if ( ! $this->syncengineData->isMediaGalleryApiPassPathEnabled() ) {
            return null;
        }

        $base = $this->syncengineData->getMediaGalleryApiBasePath();
        $base = rtrim( $base, '/' ) . '/';

        $file = $base . ltrim( $path, '/' );

        $name     = pathinfo( $file, PATHINFO_FILENAME );
        $image    = base64_encode( file_get_contents( $file ) );
        $mimeType = mime_content_type( $file );

        return $this->_createImageContent()->setType( $mimeType )->setName( $name )->setBase64EncodedData( $image );
    }

    /**
     * @param $path
     *
     * @return ImageContentInterface|null|string
     */
    public function fetchImageContent( $path_or_url )
    {
        try {
            $path = pathinfo( $path_or_url );
            if ( empty( $path['extension'] ) ) {
                return $path_or_url;
            }
        } catch ( \Exception $e ) {
            return $path_or_url;
        }

        if ( ! str_starts_with( $path_or_url, 'http' ) ) {
            return $this->fetchImageContentFromPath( $path_or_url );
        }

        return $this->fetchImageContentFromUrl( $path_or_url );
    }

    public function aroundProcessMediaGallery(\Magento\Catalog\Model\ProductRepository\MediaGalleryProcessor $subject, \Closure $proceed, ProductInterface $product, $mediaGalleryEntries)
    {
        if ( $this->syncengineData?->isMediaGalleryApiEnabled() ) {
            foreach ($mediaGalleryEntries as $k => $entry) {
                $base64image = $entry['content']['data'][ImageContentInterface::BASE64_ENCODED_DATA] ?? null;

                if ( '' !== $base64image || empty( $entry['file'] ) ) {
                    continue;
                }

                $imageContent = $this->fetchImageContent( $entry['file'] );

                if ( $imageContent ) {
                    $entry['content']['data'] = [
                        ImageContentInterface::BASE64_ENCODED_DATA => $imageContent->getBase64EncodedData(),
                        ImageContentInterface::TYPE => $imageContent->getType(),
                        ImageContentInterface::NAME => $imageContent->getName(),
                    ];
                } else {
                    unset( $entry['content'] );
                }

                $mediaGalleryEntries[$k] = $entry;
            }
        }

        if ( $this->syncengineData->isMediaGalleryApiSkipUnchangedEnabled() ) {

            $existingMediaGallery = $product->getMediaGalleryEntries();

            if ( ! empty( $existingMediaGallery ) ) {

                $existingById = [];
                foreach ( $existingMediaGallery as $existingMediaGalleryItem ) {
                    $existingById[ $existingMediaGalleryItem->getId() ] = $existingMediaGalleryItem;
                }

                foreach ( $mediaGalleryEntries as $k => $entry ) {
                    $id = $entry['value_id'] ?? null;
                    if ( ! empty( $id ) ) {

                        // Check if the existing entity has the same image.
                        if ( isset( $existingById[ $id ] ) ) {
                            $existingEntry = $existingById[ $id ];
                            if ( isset( $entry['content'] ) ) {
                                $base64image = $entry['content']['data'][ImageContentInterface::BASE64_ENCODED_DATA] ?? null;
                                $existingBase64image = $existingEntry->getContent()?->getBase64EncodedData();

                                if ( empty( $existingBase64image ) ) {
                                    $path = $this->_getProductMediaPath( $existingEntry->getFile() );
                                    $existingBase64image = base64_encode( file_get_contents( $path ) );
                                }

                                if ( empty( $existingBase64image ) ) {
                                    throw new \Exception( 'SyncEngine: Could not load existing image content: ' . $path );
                                }

                                // Remove if unchanged.
                                if ( $existingBase64image === $base64image ) {
                                    unset( $entry['content'] );
                                    $mediaGalleryEntries[ $k ] = $entry;
                                }
                            }
                        }
                    }
                }
            }
        }

        $returnValue = $proceed($product, $mediaGalleryEntries);
        return $returnValue;
    }

}
