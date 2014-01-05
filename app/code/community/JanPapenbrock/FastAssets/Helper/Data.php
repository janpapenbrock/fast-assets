<?php

class JanPapenbrock_FastAssets_Helper_Data extends Mage_Core_Helper_Abstract
{

    const CONFIG_ASSETS_ENABLED     = 'dev/fast_assets/enabled';
    const CONFIG_ASSET_TYPE_ENABLED = 'dev/fast_assets/%s_enabled';

    const MAGE_CONFIG_MERGE_FILES   = 'dev/%s/merge_files';

    /**
     * Check if asset generation is enabled.
     *
     * @return bool
     */
    public function assetsEnabled()
    {
        return Mage::getStoreConfigFlag(self::CONFIG_ASSETS_ENABLED);
    }

    /**
     * Check if asset generation for this type is enabled.
     *
     * @param string $type Asset type.
     *
     * @return bool
     */
    public function assetTypeEnabled($type)
    {
        if ($this->mageMergeFilesEnabled($type)) {
            return false;
        }
        $configId = sprintf(self::CONFIG_ASSET_TYPE_ENABLED, $type);
        return Mage::getStoreConfigFlag($configId);
    }

    /**
     * Check if Magento merging of JS/CSS files is enabled.
     *
     * @param string $type Asset type.
     *
     * @return bool
     */
    protected function mageMergeFilesEnabled($type)
    {
        $configId = sprintf(self::MAGE_CONFIG_MERGE_FILES, $type);
        return Mage::getStoreConfigFlag($configId);
    }
}
