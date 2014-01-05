<?php

class JanPapenbrock_FastAssets_Helper_Cache extends Mage_Core_Helper_Abstract
{

    const ASSET_CACHE_KEY = 'fast_assets_hash_%s_%d_%s';
    const MERGE_REQUESTS_CACHE_KEY = 'fast_assets_merges';

    /**
     * Add a merge request to the cache.
     *
     * @param array $data The merge request data.
     *
     * @return void
     */
    public function addMergeRequest($data)
    {
        $key = sprintf("%s-%s-%s", $data['store_id'], $data['type'], $data['hash']);
        $requests = $this->getMergeRequests();
        $requests[$key] = $data;
        $this->setMergeRequests($requests);
    }

    /**
     * Get all stored merge requests and empty the storage.
     *
     * @return array
     */
    public function flushMergeRequests()
    {
        $requests = $this->getMergeRequests();
        $this->setMergeRequests(array());
        return $requests;
    }

    /**
     * Get stored merge requests.
     *
     * @return array
     */
    public function getMergeRequests()
    {
        $cacheContents = Mage::app()->loadCache(self::MERGE_REQUESTS_CACHE_KEY);
        return unserialize($cacheContents);
    }

    /**
     * Write merge requests cache.
     *
     * @param array $requests List of merge requests.
     *
     * @return void
     */
    protected function setMergeRequests($requests)
    {
        $cacheContents = serialize($requests);
        Mage::app()->saveCache(
            $cacheContents,
            self::MERGE_REQUESTS_CACHE_KEY,
            array(Mage_Core_Model_Layout_Update::LAYOUT_GENERAL_CACHE_TAG)
        );
    }

    /**
     * Read asset hash for current action from cache.
     *
     * @param string $type Asset type.
     *
     * @return string|null
     */
    public function getAssetHash($type)
    {
        $cacheId = $this->getAssetCacheId($type);
        return Mage::app()->loadCache($cacheId);
    }

    /**
     * Write hash for given type to cache.
     *
     * @param string $type Asset type.
     * @param string $hash Hash.
     *
     * @return void
     */
    public function setAssetHash($type, $hash)
    {
        $cacheId = $this->getAssetCacheId($type);
        Mage::app()->saveCache($hash, $cacheId, array(Mage_Core_Model_Layout_Update::LAYOUT_GENERAL_CACHE_TAG));
    }

    /**
     * Generate cache id for asset type.
     *
     * @param string $type Asset type.
     *
     * @return string
     */
    protected function getAssetCacheId($type)
    {
        $store = Mage::App()->getStore();
        $storeId = $store->getId();

        $actionKey = strtolower($this->getFullActionName());

        return sprintf(self::ASSET_CACHE_KEY, $type, $storeId, $actionKey);
    }

    /**
     * Returns full action name of current request like so:
     * ModuleName_ControllerName_ActionName
     *
     * @see Aoe_Static_Helper_Data::getFullActionName()
     *
     * @return string
     */
    protected function getFullActionName()
    {
        return implode(
            '_',
            array(
                Mage::app()->getRequest()->getModuleName(),
                Mage::app()->getRequest()->getControllerName(),
                Mage::app()->getRequest()->getActionName(),
            )
        );
    }
}
