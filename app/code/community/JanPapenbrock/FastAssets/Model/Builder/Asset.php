<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Asset
 *
 * @method string getType
 * @method string getName
 * @method string getIf
 * @method string getCond
 * @method string getParams
 * @method bool hasContent
 * @method bool hasPath
 * @method setPath(string)
 * @method setName(string)
 * @method setType(string)
 * @method setContent(string)
 * @method setBuilder(JanPapenbrock_FastAssets_Model_Builder_Abstract $builder)
 * @method JanPapenbrock_FastAssets_Model_Builder_Abstract getBuilder
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

    /**
     * Get file content of current asset.
     *
     * @return string|false
     */
    public function getContent()
    {
        if (!$this->hasContent()) {
            if ($this->isLocal()) {
                $this->_getHelper()->log(
                    sprintf(
                        "Fetching asset '%s' from local filesystem path '%s'.",
                        $this->getName(),
                        $this->getPath()
                    )
                );
                try {
                    $content = file_get_contents($this->getPath());
                } catch (Exception $e) {
                    return false;
                }

            } else {
                $url = $this->getFastAssetsUrl();
                $this->_getHelper()->log(
                    sprintf(
                        "Fetching asset '%s' with web request from '%s'.",
                        $this->getName(),
                        $url
                    )
                );
                $content = $this->request($url);
            }

            if ($content === false) {
                return false;
            }

            $this->setContent($content);
            $this->patchContent();
        }

        return parent::getContent();
    }

    /**
     * Request contents of an URL, if it responds with status 200.
     *
     * @param string $url URL.
     *
     * @return bool|string
     */
    protected function request($url)
    {
        $client = new Zend_Http_Client($url);
        $response = $client->request();
        if (!$response || $response->getStatus() != 200) {
            return false;
        }
        return $response->getBody();
    }

    /**
     * Can this asset be merged?
     *
     * @return bool
     */
    public function canBeMerged()
    {
        if (is_null($this->getName())) {
            $this->_getHelper()->log(sprintf("Cannot merge asset '%s' because it has no name.", $this->getName()));
            return false;
        }
        // only merge certain asset types
        if (!in_array($this->getType(), $this->getBuilder()->getAssetTypes())) {
            return false;
        }
        // do not merge conditional assets
        if (!is_null($this->getIf()) || !is_null($this->getCond())) {
            $this->_getHelper()->log(sprintf("Cannot merge asset '%s' because of conditions.", $this->getName()));
            return false;
        }
        // do not merge CSS assets for specific media
        if (strpos($this->getType(), "css") !== false && $this->getParams() && $this->getParams() != 'media="all"') {
            $this->_getHelper()->log(sprintf("Cannot merge asset '%s' because of media params.", $this->getName()));
            return false;
        }
        // do not merge external assets
        if (strpos($this->getName(), "//") !== false) {
            $this->_getHelper()->log(sprintf("Cannot merge asset '%s' because it is an external file.", $this->getName()));
            return false;
        }

        return true;
    }

    /**
     * Is this asset a locally available file?
     *
     * @return bool
     */
    public function isLocal()
    {
        $regex = $this->_getHelper()->getExternalAssetPathRegex();
        if ($regex != "") {
            try {
                return !preg_match($regex, $this->getName());
            } catch (Exception $e) {
                $this->_getHelper()->log("Could not execute external asset path regex: ".$e->getMessage());
            }
        }
        return true;
    }

    /**
     * Get filesystem path to this asset.
     *
     * @return string
     */
    public function getPath()
    {
        if (!$this->hasPath()) {
            $url = $this->getFastAssetsUrl();
            $path = parse_url($url, PHP_URL_PATH);
            $path = Mage::getBaseDir() . $path;
            $this->setPath($path);
        }
        return parent::getPath();
    }

    /**
     * Calculate asset hash based on path and filemtime.
     *
     * @return string
     */
    public function getHash()
    {
        if (!$this->hasHash()) {
            $path = $this->getPath();
            try {
                $mTime = filemtime($path);
            } catch (Exception $e) {
                $mTime = sha1($path);
            }
            return md5($path).md5($mTime);
        }
        return parent::getHash();
    }

    /**
     * Get URL for this asset.
     *
     * @return string
     */
    public function getFastAssetsUrl()
    {
        if (!$this->hasFastAssetsUrl()) {
            $type = $this->getType();
            $name = $this->getName();

            $name = str_replace(DS, "/", $name);

            if (strpos($type, "skin") !== false) {
                $designPackage = Mage::getDesign();
                $url = $designPackage->getSkinUrl($name, array());
            } elseif (strpos($type, 'js') === 0) {
                $url = Mage::getBaseUrl('js') . $name;
            } else {
                $url = Mage::getBaseUrl() . $name;
            }
            $this->setFastAssetsUrl($url);
        }
        return parent::getFastAssetsUrl();
    }


    /**
     * Patch the content, based on asset types.
     *
     * @return string
     */
    protected function patchContent()
    {
        $content = $this->getContent();
        $patchedContent = $content;
        if ($this->getBuilder()->getType() == 'css') {
            $baseUrl   = $this->getBuilder()->getBaseUrl();
            $assetUrl  = $this->getFastAssetsUrl();
            $assetPath = str_replace($baseUrl, "/", $assetUrl);

            if (preg_match_all('/url\((.*)\)/iUs', $content, $matches)) {
                $paths = $matches[1];

                foreach ($paths as $path) {
                    $absolutePath = $this->mergePaths($assetPath, $path);
                    $patchedContent = str_replace($path, $absolutePath, $patchedContent);
                }
            }
        }

        $this->setContent($patchedContent);
    }

    /**
     * Merge the given asset base path,
     *   e.g. the file path of a css file: /skin/frontend/default/default/css/style.css
     * and a relative path,
     *   e.g. an image inside an css file:  ../images/my_image.png
     * to return an absolute path instead of the given relative path,
     *   e.g. /skin/frontend/default/default/images/my_image.png
     *
     * If an absolute path is given, the path is returned unchanged.
     *
     * @param string $assetBasePath Asset file path.
     * @param string $relativePath  Contained path.
     *
     * @return string
     */
    protected function mergePaths($assetBasePath, $relativePath)
    {
        $normalizedPath = str_replace(array('"', "'"), "", $relativePath);
        $normalizedPath = trim($normalizedPath);

        if (strpos($normalizedPath, "//") !== false) {
            return $relativePath;
        }

        if (strpos($normalizedPath, "/") === 0) {
            return $relativePath;
        }

        $assetDir       = dirname($assetBasePath);
        $assetPathParts = explode("/", $assetDir);

        $currentPathParts = $assetPathParts;

        $pathParts = explode("/", $normalizedPath);
        foreach ($pathParts as $part) {
            if ($part == "..") {
                $currentPathParts = array_slice($currentPathParts, 0, -1);
            } else {
                $currentPathParts[] = $part;
            }
        }

        $absolutePath = implode("/", $currentPathParts);
        return $absolutePath;
    }
}
