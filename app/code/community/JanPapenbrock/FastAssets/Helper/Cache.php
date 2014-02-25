<?php

class JanPapenbrock_FastAssets_Helper_Cache extends Mage_Core_Helper_Abstract
{

    const ASSET_ACTION_CACHE_KEY   = 'fast_assets_hash_%s_%d_%s';
    const ASSET_VALID_CACHE_KEY    = 'fast_assets_hash_%s_%d_%s';
    const MERGE_REQUESTS_CACHE_KEY = 'fast_assets_merges';

    const CACHE_TAG = 'FAST_ASSETS';

    /**
     * Purge fast assets cache.
     *
     * @return void
     */
    public function purge()
    {
        $tags = array(self::CACHE_TAG);
        Mage::dispatchEvent('fast_assets_clean_cache_before', array('tags' => $tags));
        $cacheInstance = Mage::app()->getCacheInstance();
        $cacheInstance->clean($tags);
        Mage::dispatchEvent('fast_assets_clean_cache_after', array('tags' => $tags));
    }

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
        $this->saveCache($cacheContents, self::MERGE_REQUESTS_CACHE_KEY);
    }

    /**
     * Return whether asset defined by type and hash is marked as valid in cache.
     *
     * @param string $type Asset type.
     * @param string $hash Hash.
     *
     * @return bool
     */
    public function getAssetValid($type, $hash)
    {
        $cacheId = $this->getAssetValidCacheId($type, $hash);
        return (bool) Mage::app()->loadCache($cacheId);
    }

    /**
     * Mark asset defined by given type and hash as valid.
     *
     * @param string $type Asset type.
     * @param string $hash Hash.
     *
     * @return void
     */
    public function setAssetValid($type, $hash)
    {
        $cacheId = $this->getAssetValidCacheId($type, $hash);
        $this->saveCache(1, $cacheId);
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
        $cacheId = $this->getAssetActionCacheId($type);
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
        $cacheId = $this->getAssetActionCacheId($type);
        $this->saveCache($hash, $cacheId);
    }

    /**
     * Generate asset valid cache id for asset type and hash.
     *
     * @param string $type Asset type.
     * @param string $hash Asset hash.
     *
     * @return string
     */
    protected function getAssetValidCacheId($type, $hash)
    {
        $store = Mage::App()->getStore();
        $storeId = $store->getId();

        return sprintf(self::ASSET_VALID_CACHE_KEY, $type, $storeId, $hash);
    }

    /**
     * Generate cache id for asset type.
     *
     * @param string $type Asset type.
     *
     * @return string
     */
    protected function getAssetActionCacheId($type)
    {
        $store = Mage::App()->getStore();
        $storeId = $store->getId();

        $actionKey = strtolower($this->getFullActionName());

        return sprintf(self::ASSET_ACTION_CACHE_KEY, $type, $storeId, $actionKey);
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

    /**
     * Write value to cache.
     *
     * @param string   $data     Data to write.
     * @param string   $id       Cache key.
     * @param int|null $lifeTime Life time of value.
     *
     * @return Mage_Core_Model_App
     */
    protected function saveCache($data, $id, $lifeTime = false)
    {
        $tags = array(self::CACHE_TAG);
        return Mage::app()->saveCache($data, $id, $tags, $lifeTime);
    }
}
