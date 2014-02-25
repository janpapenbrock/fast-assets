<?php

class JanPapenbrock_FastAssets_Model_Observer
{

    /**
     * Catch adminhtml_cache_flush_system event to purge our cache.
     *
     * @return void
     */
    public function cacheFlushSystem()
    {
        $this->getCacheHelper()->purge();
    }

    /**
     * Catch adminhtml_cache_refresh_type event to purge our cache,
     * if type refresh is requested.
     *
     * @param Varien_Event_Observer $observer Event observer.
     *
     * @return void
     */
    public function cacheRefreshType($observer)
    {
        $type = $observer->getType();
        $this->getCacheHelper()->purgeIfTypeMatches($type);
    }
    /**
     * When layout xml is built, handle assets.
     *
     * If fast asset file was built, use it in layout.
     * If not, remember to build it (via cron job).
     *
     * @param Varien_Event_Observer $observer Observer.
     *
     * @return void
     */
    public function onLayoutReady($observer)
    {

    }

    /**
     * When cron job is triggered, compile the assets.
     *
     * @return string
     */
    public function onCronCompile()
    {
        // do nothing if asynchronous compiling is disabled
        if (!$this->getHelper()->compileAsynchronously()) {
            return "";
        }

        $results = array('success' => array(), 'error' => array());

        $cacheHelper = $this->getCacheHelper();
        $requests = $cacheHelper->flushMergeRequests();
        foreach ($requests as $request) {
            $type = $request['type'];
            $builder = $this->getHelper()->getBuilder($type);
            $result = null;
            if ($builder) {
                $result = $builder->asynchronousMerge($request);
            }
            $type = ($result) ? 'success' : 'error';
            $results[$type][] = sprintf("%d (%s) %s", $request['store_id'], $request['type'], $result);
        }

        // prepare response string

        $returns = array();
        $beginWith = "SUCCESS: ";

        if (count($results['error']) > 0) {
            $beginWith = "ERROR: ";
            $returns[] = sprintf(
                "Errors when building assets: \n %s", implode("; ", $results['error'])
            );
        }

        if (count($results['success']) > 0) {
            $returns[] = sprintf(
                "Success when building assets: \n %s", implode("; ", $results['success'])
            );
        }

        if (count($returns)) {
            return $beginWith.implode("\n", $returns);
        }

        return "";
    }

    /**
     * Get FastAssets helper.
     *
     * @return JanPapenbrock_FastAssets_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper("fast_assets");
    }

    /**
     * Get FastAssets cache helper.
     *
     * @return JanPapenbrock_FastAssets_Helper_Cache
     */
    protected function getCacheHelper()
    {
        return Mage::helper("fast_assets/cache");
    }

}
