<?php
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
        return $root . ltrim( $this->getMediaGalleryApiConfig( 'base_path' ), '/' );
    }
}
