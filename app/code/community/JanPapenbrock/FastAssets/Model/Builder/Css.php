<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Abstract
 */
class JanPapenbrock_FastAssets_Model_Builder_Css extends JanPapenbrock_FastAssets_Model_Builder_Abstract
{

    protected $_type           = "css";
    protected $_assetType      = "skin_css";
    protected $_precompilePath = "css/styles-%s.css";
    protected $_itemTypes      = array("css", "skin_css", "js_css");

}
