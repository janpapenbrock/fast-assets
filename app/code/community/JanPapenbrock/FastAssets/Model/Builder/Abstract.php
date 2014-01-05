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
    /**
     * Should be set by child classes.
     */
    protected $_type;
    protected $_assetType;
    protected $_itemTypes;
    protected $_precompilePath;

    protected $_assets = null;

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
     * @return void
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

        $assetPath = $this->getPathForHash($hash);

        if (file_exists($assetPath)) {
            $assetPath = $this->getPathForHash($hash, false);
        } else {
            $this->cacheForAsynchronousMerge($assets, $hash);
            $assetPath = null;
        }

        if ($assetPath) {
            $this->removeAssets();
            $this->addAsset($assetPath);

            if (!$hashFromCache) {
                $cache->setAssetHash($this->_type, $hash);
            }
        }
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
     * @param array       $assets Assets.
     * @param string      $hash   Asset file hash.
     * @param string|null $saveTo Asset file target. Is calculated if null given.
     *
     * @return bool|string
     */
    protected function merge($assets, $hash, $saveTo = null)
    {
        $contents = array();
        foreach ($assets as $asset) {
            $url = $this->getAssetUrl($asset);

            $content = $this->request($url);

            // if one request fails, the merge fails
            if ($content === false) {
                return false;
            }

            if ($content) {
                $content = $this->patchAssetContent($asset, $content);
                $contents[] = $content;
            }
        }

        $contents = implode("\n", $contents);
        if (!$saveTo) {
            $saveTo = $this->getPathForHash($hash);
        }
        $writeSuccess = $this->writeFile($saveTo, $contents);

        if ($writeSuccess) {
            return $this->getPathForHash($hash, false);
        }
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
            'save_to'  => $this->getPathForHash($hash),
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
     * Get file path for a given asset hash.
     *
     * @param string $hash           Asset hash.
     * @param bool   $includeBaseDir Whether to include Mage base dir to path.
     *
     * @return string
     */
    protected function getPathForHash($hash, $includeBaseDir = true)
    {
        $path = sprintf($this->_precompilePath, $hash);
        if ($includeBaseDir) {
            $designPackage = Mage::getDesign();
            $path = $designPackage->getSkinBaseDir(array()) . DS . $path;
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
     *
     * @return void
     */
    protected function addAsset($name)
    {
        $head = $this->getHead();
        $head->addItem($this->_assetType, $name);
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
     * @return array
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
                if (!is_null($item['cond']) && !$this->getData($item['cond']) || !isset($item['name'])) {
                    continue;
                }
                if (!in_array($item['type'], $this->_itemTypes)) {
                    continue;
                }

                if (strpos($item['name'], "//") !== false) {
                    continue;
                }

                // we dont want to store cond and if data
                unset($item['cond']);
                unset($item['if']);

                // all data should be string
                foreach ($item as $key => $data) {
                    $item[$key] = (string) $data;
                }

                $assets[] = $item;
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
        foreach ($assets as $assetKey => $asset) {
            $urls[] = $this->getAssetUrl($asset);
            $assets[$assetKey] = $asset;
        }
        return $urls;
    }

    /**
     * Get URL for a single asset.
     *
     * @param array &$asset Asset.
     *
     * @return string
     */
    protected function getAssetUrl(&$asset)
    {
        if (!isset($asset['fast_assets_url'])) {
            $type = $asset['type'];
            $name = $asset['name'];
            if (strpos($type, "skin") !== false) {
                $designPackage = Mage::getDesign();
                $asset['fast_assets_url'] =  $designPackage->getSkinUrl($name, array());
            } elseif (strpos($type, 'js') === 0) {
                $asset['fast_assets_url'] =  Mage::getBaseUrl('js') . $name;
            } else {
                $asset['fast_assets_url'] =  Mage::getBaseUrl() . $name;
            }
        }
        return $asset['fast_assets_url'];
    }

    /**
     * Get filesystem path to an asset.
     *
     * @param array $asset Asset.
     *
     * @return string
     */
    protected function getAssetPath($asset)
    {
        $baseUrl  = Mage::getBaseUrl();
        $basePath = str_replace($baseUrl, "", $asset['name']);
        return Mage::getBaseDir() . DS . $basePath;
    }

    /**
     * Calculate asset hash based on path and filemtime.
     *
     * @param string $asset Asset.
     *
     * @return string
     */
    protected function calculateAssetHash($asset)
    {
        $path = $this->getAssetPath($asset);
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
        return substr($md5, 0, 4).substr($sha1, 0, 4);
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
     * @param array  $asset   Asset.
     * @param string $content Asset file contents.
     *
     * @return string
     */
    protected function patchAssetContent($asset, $content)
    {
        $patchedContent = $content;
        if ($this->_type == 'css') {
            $baseUrl   = $this->getBaseUrl();
            $assetUrl  = $this->getAssetUrl($asset);
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
