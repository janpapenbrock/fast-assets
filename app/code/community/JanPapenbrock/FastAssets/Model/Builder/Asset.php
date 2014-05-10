<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Asset
 *
 * @method string getType
 * @method string getName
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
        echo "regex: $regex";
        if ($regex != "") {
            try {
                $isExternal = preg_match($regex, $this->getName());
                return !$isExternal;
            } catch (Exception $e) {
            }
        }
        return true;
    }
}
