<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Asset
 *
 * @method string getType
 * @method string getName
 * @method setPath(string)
 */
class JanPapenbrock_FastAssets_Model_Builder_Asset extends Mage_Core_Model_Abstract
{

    /**
     * Get FastAssets helper.
     *
     * @return JanPapenbrock_FastAssets_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper("fast_assets");
    }

    public function isLocal()
    {
        $regex = $this->_getHelper()->getExternalAssetPathRegex();
        if ($regex != "") {
            try {
                return !preg_match($regex, $this->getName());
            } catch (Exception $e) {
                $this->_getHelper()->log("Could not execute external asset path regex: ".$e->getMessage());
            }
        }
        return true;
    }

    /**
     * Get filesystem path to this asset.
     *
     * @return string
     */
    public function getPath()
    {
        if (!$this->hasPath()) {
            $url = $this->getFastAssetsUrl();
            $path = parse_url($url, PHP_URL_PATH);
            $this->setPath($path);
        }
        return parent::getPath();
    }

    /**
     * Get URL for this asset.
     *
     * @return string
     */
    public function getFastAssetsUrl()
    {
        if (!$this->hasFastAssetsUrl()) {
            $type = $this->getType();
            $name = $this->getName();
            if (strpos($type, "skin") !== false) {
                $designPackage = Mage::getDesign();
                $url = $designPackage->getSkinUrl($name, array());
            } elseif (strpos($type, 'js') === 0) {
                $url = Mage::getBaseUrl('js') . $name;
            } else {
                $url = Mage::getBaseUrl() . $name;
            }
            $this->setFastAssetsUrl($url);
        }
        return parent::getFastAssetsUrl();
    }
}
