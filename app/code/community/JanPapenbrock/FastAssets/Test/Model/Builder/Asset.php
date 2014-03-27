<?php

class JanPapenbrock_FastAssets_Test_Model_Builder_Asset extends EcomDev_PHPUnit_Test_Case
{
    /**
     * Test isLocal for empty config value.
     *
     * @param string $assetName
     *
     * @test
     * @loadFixture
     * @dataProvider dataProvider
     */
    public function isLocalIfConfigValueIsEmpty($assetName)
    {
        $asset = Mage::getModel("fast_assets/builder_asset");
        $asset->setName($assetName);
        $local = $asset->isLocal();
        $this->assertTrue($local);
    }

}
