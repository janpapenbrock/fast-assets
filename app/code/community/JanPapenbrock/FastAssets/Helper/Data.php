<?php

class JanPapenbrock_FastAssets_Helper_Data extends Mage_Core_Helper_Abstract
{

    const CONFIG_ASSETS_ENABLED          = 'dev/fast_assets/enabled';
    const CONFIG_ASSET_TYPE_ENABLED      = 'dev/fast_assets/%s_enabled';
    const CONFIG_COMPILE_ASYNCHRONOUSLY  = 'dev/fast_assets/compile_asynchronously';
    const CONFIG_STORE_IN_SKIN_TOP_LEVEL = 'dev/fast_assets/store_files_in_skin_top_level';

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

    /**
     * Check if assets should be generated asynchronously.
     *
     * @return bool
     */
    public function compileAsynchronously()
    {
        return Mage::getStoreConfigFlag(self::CONFIG_COMPILE_ASYNCHRONOUSLY);
    }

    /**
     * Check if assets should be stored in a directory directly in {MAGENTO-ROOT}/skin/.
     *
     * @return bool
     */
    public function storeInSkinTopLevel()
    {
        return Mage::getStoreConfigFlag(self::CONFIG_STORE_IN_SKIN_TOP_LEVEL);
    }


}
