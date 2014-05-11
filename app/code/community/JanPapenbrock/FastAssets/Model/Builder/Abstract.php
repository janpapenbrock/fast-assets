<?php

/**
 * Class JanPapenbrock_FastAssets_Model_Builder_Abstract
 *
 * @method Mage_Core_Model_Layout getLayout
 * @method setLayout(Mage_Core_Model_Layout $layout)
 * @method setBaseUrl(string $baseUrl)
 */
abstract class JanPapenbrock_FastAssets_Model_Builder_Abstract extends Mage_Core_Model_Abstract
{
    const DIR_NAME = 'fast-assets';

    /**
     * Should be set by child classes.
     */
    protected $_type;
    protected $_assetType;
    protected $_assetBlock;
    protected $_itemTypes;
    protected $_precompilePath;

    protected $_assets = null;

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
     * Get caching helper.
     *
     * @return JanPapenbrock_FastAssets_Helper_Cache
     */
    protected function getCacheHelper()
    {
        return Mage::helper("fast_assets/cache");
    }

    /**
     * Merge assets in layout 'head' and replace them with single merged asset.
     *
     * @return string
     */
    public function replaceAssets()
    {
        $cache = $this->getCacheHelper();
        $hash = $cache->getAssetHash($this->_type);
        $hashFromCache = (bool) $hash;

        $assets = $this->getAssets();
        if (!$hash) {
            $hash = $this->calculateAssetsHash($assets);
        }

        $assetFile = $this->getPathForHashWithBase($hash);
        $assetPath = null;

        $assetValidMarker = $cache->getAssetValid($this->_type, $hash);
        $assetFileExists  = file_exists($assetFile);

        $assetValid = $assetValidMarker && $assetFileExists;

        if (!$assetValid) {
            if ($this->getHelper()->compileAsynchronously()) {
                // generate file asynchronously
                $this->cacheForAsynchronousMerge($assets, $hash);
            } else {
                // generate file now and deliver it
                $this->merge($assets, $hash);
                $assetValid = true;
            }
        }

        if ($assetValid) {
            $assetPath = $this->getNameForHash($hash);

            $this->removeAssets();
            $html = $this->addAsset($assetPath, $hash);

            if (!$hashFromCache) {
                $cache->setAssetHash($this->_type, $hash);
            }

            return $html;
        }

        return "";
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
     * Merge the given asset URLs into a single asset file with the given hash id.
     *
     * @param JanPapenbrock_FastAssets_Model_Builder_Asset[] $assets Assets.
     * @param string                                         $hash   Asset file hash.
     * @param string|null                                    $saveTo Asset file target. Is calculated if null given.
     *
     * @return bool|string
     */
    protected function merge($assets, $hash, $saveTo = null)
    {
        $contents = array();
        foreach ($assets as $asset) {

            if ($asset->isLocal()) {
                $this->getHelper()->log(
                    sprintf(
                        "Fetching asset '%s' from local filesystem path '%s'.",
                        $asset->getName(),
                        $asset->getPath()
                    )
                );
                $content = file_get_contents($asset->getPath());
            } else {
                $url = $asset->getFastAssetsUrl();
                $this->getHelper()->log(
                    sprintf(
                        "Fetching asset '%s' with web request from '%s'.",
                        $asset->getName(),
                        $url
                    )
                );
                $content = $this->request($url);
            }


            // if one request fails, the merge fails
            if ($content === false) {
                $this->getHelper()->log(
                    sprintf(
                        "Error when getting asset file '%s'. Terminating.",
                        $asset['name']
                    )
                );
                return false;
            }

            if ($content) {
                $content = $this->patchAssetContent($asset, $content);
                $contents[] = $content;
            }
        }

        $contents = implode("\n", $contents);
        if (!$saveTo) {
            $saveTo = $this->getPathForHashWithBase($hash);
        }
        $writeSuccess = $this->writeFile($saveTo, $contents);

        if ($writeSuccess) {
            $this->getCacheHelper()->setAssetValid($this->_type, $hash);
            return $this->getNameForHash($hash);
        }

        return false;
    }

    /**
     * Store a merge request for the given asset URLs and the given hash id.
     *
     * @param array  $assets Assets.
     * @param string $hash   Asset file hash.
     *
     * @return void
     */
    protected function cacheForAsynchronousMerge($assets, $hash)
    {
        $this->getAssetUrls($assets);

        $data = array(
            'store_id' => Mage::app()->getStore()->getId(),
            'assets'   => $assets,
            'hash'     => $hash,
            'type'     => $this->_type,
            'save_to'  => $this->getPathForHashWithBase($hash),
            'base_url' => $this->getBaseUrl()
        );

        $this->getCacheHelper()->addMergeRequest($data);
    }

    /**
     * Perform a merge for the given data.
     *
     * @param array $data Merge data.
     *
     * @return bool|string
     */
    public function asynchronousMerge($data)
    {
        if (empty($data['hash'])) {
            return false;
        }
        $hash = $data['hash'];

        if (empty($data['assets'])) {
            return false;
        }
        $assets = $data['assets'];

        if (empty($data['save_to'])) {
            return false;
        }
        $saveTo = $data['save_to'];

        if (empty($data['base_url'])) {
            return false;
        }
        $this->setBaseUrl($data['base_url']);

        return $this->merge($assets, $hash, $saveTo);
    }

    /**
     * Write the given content to the given file path.
     *
     * @param string $filePath File path to write to.
     * @param string $content  Content to write.
     *
     * @return bool|int
     */
    protected function writeFile($filePath, $content)
    {
        $ioObject = new Varien_Io_File();
        $ioObject->open();

        $ioObject->checkAndCreateFolder(dirname($filePath));

        $writeSuccess = $ioObject->write($filePath, $content);

        $ioObject->close();
        return $writeSuccess;
    }

    /**
     * Return full filesystem path for an asset file with given hash.
     *
     * @param string $hash Asset file hash.
     *
     * @return string
     */
    protected function getPathForHashWithBase($hash)
    {
        $designPackage = Mage::getDesign();
        $baseDir = $designPackage->getSkinBaseDir(array());

        if ($this->getHelper()->storeInMediaDir()) {
            $name = $this->getNameForHash($hash, false);

            $skinDir       = Mage::getBaseDir('skin');
            $mediaDir      = Mage::getBaseDir('media');
            $fastAssetsDir = $mediaDir . DS . self::DIR_NAME;
            $baseDir       = str_replace($skinDir, $fastAssetsDir, $baseDir);
        } else {
            $name = $this->getNameForHash($hash);
        }

        $path = $baseDir . DS . $name;
        return $path;
    }

    /**
     * Get path asset file for the given hash, relative to magento root.
     *
     * @param string $hash Asset hash.
     *
     * @return string
     */
    protected function getPathForHash($hash)
    {
        $path = $this->getPathForHashWithBase($hash);
        return str_replace(Mage::getBaseDir() . DS, "", $path);
    }

    /**
     * Get file path for a given asset hash.
     *
     * @param string $hash       Asset hash.
     * @param bool   $prependDir Whether to prepend the directory name.
     *
     * @return string
     */
    protected function getNameForHash($hash, $prependDir = true)
    {
        $path = sprintf($this->_precompilePath, $hash);
        if ($prependDir) {
            $path = self::DIR_NAME . "/" . $path;
        }
        return $path;
    }

    /**
     * Remove assets from layout.
     *
     * @return void
     */
    protected function removeAssets()
    {
        $head = $this->getHead();
        $assets = $this->getAssets();
        foreach ($assets as $asset) {
            $head->removeItem($asset['type'], $asset['name']);
        }
    }

    /**
     * Add an asset to the layout.
     *
     * @param string $name Asset name.
     * @param string $hash Asset hash.
     *
     * @return string|null
     */
    protected function addAsset($name, $hash)
    {
        $head = $this->getHead();

        if (!$head) {
            return null;
        }

        if ($this->getHelper()->storeInMediaDir()) {
            /** @var JanPapenbrock_FastAssets_Model_Builder_Asset $asset */
            $asset = Mage::getModel("fast_assets/builder_asset");
            $asset->setName($this->getPathForHash($hash));
            $asset->setType("custom");
            $url = $asset->getFastAssetsUrl();
            $assetHtml = sprintf($this->_assetBlock, $url);
            return $assetHtml;
        } else {
            $head->addItem($this->_assetType, $name);
        }
        return "";
    }

    /**
     * Get layout block named 'head'.
     *
     * @return Mage_Page_Block_Html_Head
     */
    protected function getHead()
    {
        $layout = $this->getLayout();

        /** @var Mage_Page_Block_Html_Head $head */
        $head = $layout->getBlock('head');

        return $head;
    }

    /**
     * Get all assets from layout head matching the current builder's item types.
     * Only local assets are allowed.
     *
     * @return JanPapenbrock_FastAssets_Model_Builder_Asset[]
     */
    protected function getAssets()
    {
        if (!$this->_assets) {
            $this->_assets = array();

            $head = $this->getHead();

            if (!$head) {
                return $this->_assets;
            }

            $assets  = array();
            $items = $head->getData('items');
            foreach ($items as $item) {

                /** @var JanPapenbrock_FastAssets_Model_Builder_Asset $asset */
                $asset = Mage::getModel("fast_assets/builder_asset");
                $asset->setData($item);

                if (!$asset->canBeMerged($this->_itemTypes)) {
                    $this->getHelper()->log(sprintf("Cannot merge asset '%s'.", $asset->getName()));
                    continue;
                }

                $assets[] = $asset;
            }

            $this->_assets = $assets;
        }

        return $this->_assets;
    }

    /**
     * Get URLs for all assets.
     *
     * @param array|null &$assets List of assets to get URLs for.
     *
     * @return string[]
     */
    protected function getAssetUrls(&$assets = null)
    {
        if (!$assets) {
            $assets = $this->getAssets();
        }

        $urls = array();
        foreach ($assets as $asset) {
            $urls[] = $asset->getFastAssetsUrl();
        }
        return $urls;
    }

    /**
     * Calculate asset hash based on path and filemtime.
     *
     * @param JanPapenbrock_FastAssets_Model_Builder_Asset $asset Asset.
     *
     * @return string
     */
    protected function calculateAssetHash($asset)
    {
        $path = $asset->getPath();
        $mTime = filemtime($path);
        return md5($path).md5($mTime);
    }

    /**
     * Calculate hash for a group of assets.
     *
     * @param array $assets Assets.
     *
     * @return string
     */
    protected function calculateAssetsHash($assets)
    {
        $hashes = array();
        foreach ($assets as $asset) {
            $hashes[] = $this->calculateAssetHash($asset);
        }

        $concatenatedHashes = implode("", $hashes);

        $md5  = md5($concatenatedHashes);
        $sha1 = sha1($concatenatedHashes);
        return substr($md5, 0, 16).substr($sha1, 0, 16);
    }

    /**
     * Wrapper to allow setting of a custom base url.
     *
     * @return string
     */
    protected function getBaseUrl()
    {
        $baseUrl = $this->getData('base_url');
        if (!$baseUrl) {
            $baseUrl = Mage::getBaseUrl();
            $this->setData('base_url', $baseUrl);
        }
        return $baseUrl;
    }

    /**
     * Patch the content, based on asset types.
     *
     * @param JanPapenbrock_FastAssets_Model_Builder_Asset $asset   Asset.
     * @param string                                       $content Asset file contents.
     *
     * @return string
     */
    protected function patchAssetContent($asset, $content)
    {
        $patchedContent = $content;
        if ($this->_type == 'css') {
            $baseUrl   = $this->getBaseUrl();
            $assetUrl  = $asset->getFastAssetsUrl();
            $assetPath = str_replace($baseUrl, "/", $assetUrl);

            if (preg_match_all('/url\((.*)\)/iUs', $content, $matches)) {
                $paths = $matches[1];

                foreach ($paths as $path) {
                    $absolutePath = $this->mergePaths($assetPath, $path);
                    $patchedContent = str_replace($path, $absolutePath, $patchedContent);
                }
            }
        }

        return $patchedContent;
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
