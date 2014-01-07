<?php

class JanPapenbrock_FastAssets_Model_Observer
{

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
        $helper = $this->getHelper();
        if (!$helper->assetsEnabled()) {
            return;
        }

        /** @var Mage_Core_Model_Layout $layout */
        $layout = $observer->getEvent()->getLayout();

        if (!$layout) {
            return;
        }

        $builderTypes = array( 'css', 'js' );
        foreach ($builderTypes as $builderType) {
            $builder = $this->getBuilder($builderType);
            if (!$builder) {
                continue;
            }
            $builder->setLayout($layout);
            $builder->replaceAssets();
        }
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
            $builder = $this->getBuilder($type);
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
     * Get builder for the given type, if enabled.
     *
     * @param string $type Builder type.
     *
     * @return JanPapenbrock_FastAssets_Model_Builder_Abstract|null
     */
    protected function getBuilder($type)
    {
        if (!$this->getHelper()->assetTypeEnabled($type)) {
            return null;
        }

        $klass = sprintf('fast_assets/builder_%s', $type);
        $builder = Mage::getModel($klass);

        return $builder;
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
