<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Asset
 *
 * @method string getType
 * @method string getName
 * @method string getIf
 * @method string getCond
 * @method string getParams
 * @method setPath(string)
 * @method setName(string)
 * @method setType(string)
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

    /**
     * Can this asset be merged?
     *
     * @param string[] $allowedTypes List of allowed asset types.
     *
     * @return bool
     */
    public function canBeMerged($allowedTypes)
    {
        if (is_null($this->getName())) {
            return false;
        }
        // do not merge conditional assets
        if (!is_null($this->getIf()) || !is_null($this->getCond())) {
            return false;
        }
        // only merge certain asset types
        if (!in_array($this->getType(), $allowedTypes)) {
            return false;
        }
        // do not merge CSS assets for specific media
        if (strpos($this->getType(), "css") !== false && $this->getParams() && $this->getParams() != 'media="all"') {
            return false;
        }
        // do not merge external assets
        if (strpos($this->getName(), "//") !== false) {
            return false;
        }

        return true;
    }

    /**
     * Is this asset a locally available file?
     *
     * @return bool
     */
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
            $path = Mage::getBaseDir() . $path;
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

            $name = str_replace(DS, "/", $name);

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
