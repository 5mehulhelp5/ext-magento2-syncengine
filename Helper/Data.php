<?php
/**
 * SyncEngine
 * Copyright (C) SyncEngine
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
 * @category SyncEngine
 * @package SyncEngine_Connector
 * @copyright Copyright (c) SyncEngine
 * @license http://opensource.org/licenses/gpl-3.0.html GNU General Public License,version 3 (GPL-3.0)
 * @author Jory Hogeveen <info@syncengine.io>
 */

namespace SyncEngine\Connector\Helper;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Helper\AbstractHelper;


class Data extends AbstractHelper
{
    const XML_CONFIG_PATH = 'syncengine_connector';

    public function getConfig( $setting )
    {
        return $this->scopeConfig->getValue( self::XML_CONFIG_PATH . '/'. $setting );
    }

    public function getMediaGalleryApiConfig( $setting )
    {
        return $this->getConfig( 'media_gallery_api/' . $setting );
    }

    public function isEnabled()
    {
        return $this->getConfig( 'general/enable' );
    }

    public function isMediaGalleryApiEnabled()
    {
        return $this->isMediaGalleryApiPassUrlEnabled() || $this->isMediaGalleryApiPassPathEnabled();
    }

    public function isMediaGalleryApiSkipUnchangedEnabled()
    {
        return $this->isEnabled() && $this->getMediaGalleryApiConfig( 'skip_unchanged' );
    }

    public function isMediaGalleryApiPassUrlEnabled()
    {
        return $this->isEnabled() && $this->getMediaGalleryApiConfig( 'pass_url' );
    }

    public function isMediaGalleryApiPassPathEnabled()
    {
        return $this->isEnabled() && $this->getMediaGalleryApiConfig( 'pass_path' );
    }

    public function getMediaGalleryApiBasePath()
    {
        $root = rtrim( BP, '/' ) . '/';
        $path = $this->getMediaGalleryApiConfig( 'base_path' );
        if ( empty( $path ) ) {
            $path = 'pub/media/import';
        }
        return $root . ltrim( $path, '/' );
    }
}
