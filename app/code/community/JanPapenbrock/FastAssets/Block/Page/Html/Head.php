<?php

class JanPapenbrock_FastAssets_Block_Page_Html_Head extends Mage_Page_Block_Html_Head
{

    public function getCssJsHtml()
    {
        $compiledHtml = $this->getCompiledCssJsHtml();
        $parentHtml   = parent::getCssJsHtml();

        return $parentHtml.$compiledHtml;
    }

    protected function getCompiledCssJsHtml()
    {
        /** @var JanPapenbrock_FastAssets_Helper_Data $helper */
        $helper = Mage::helper("fast_assets");

        if (!$helper->assetsEnabled()) {
            return "";
        }

        /** @var Mage_Core_Model_Layout $layout */
        $layout = $this->getLayout();

        if (!$layout) {
            return "";
        }

        $builderTypes = array( 'css', 'js' );
        $html = array();
        foreach ($builderTypes as $builderType) {
            $builder = $helper->getBuilder($builderType);
            if (!$builder) {
                continue;
            }
            $builder->setLayout($layout);
            $html[] = $builder->replaceAssets();
        }
        return implode("\n", $html);
    }

}
