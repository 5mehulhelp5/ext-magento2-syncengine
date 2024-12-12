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

    public function isEnabled()
    {
        return $this->getConfig( 'general/enable' );
    }

    public function isMediaApiEnabled()
    {
        return $this->isMediaApiPassUrlEnabled() || $this->isMediaApiPassPathEnabled();
    }

    public function isMediaApiPassUrlEnabled()
    {
        return $this->isEnabled() && $this->getConfig( 'media_api/pass_url' );
    }

    public function isMediaApiPassPathEnabled()
    {
        return $this->isEnabled() && $this->getConfig( 'media_api/pass_path' );
    }

    public function getMediaApiBasePath()
    {
        return $this->getConfig( 'media_api/base_path' );
    }
}
