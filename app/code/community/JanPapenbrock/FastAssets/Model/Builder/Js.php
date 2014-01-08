<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Abstract
 */
class JanPapenbrock_FastAssets_Model_Builder_Js extends JanPapenbrock_FastAssets_Model_Builder_Abstract
{

    protected $_type           = "js";
    protected $_assetType      = "skin_js";
    protected $_assetBlock     = '<script type="text/javascript" src="%s"></script>';
    protected $_precompilePath = "js/scripts-%s.js";
    protected $_itemTypes      = array("js", "skin_js");

}
