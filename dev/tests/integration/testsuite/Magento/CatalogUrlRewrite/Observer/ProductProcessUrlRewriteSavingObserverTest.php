<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 */
class ProductProcessUrlRewriteSavingObserverTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->fixtures = $this->objectManager->get(DataFixtureStorageManager::class)->getStorage();
    }

    /**
     * @param array $filter
     * @return array
     */
    private function getActualResults(array $filter)
    {
        /** @var UrlFinderInterface $urlFinder */
        $urlFinder = $this->objectManager->get(UrlFinderInterface::class);
        $actualResults = [];
        foreach ($urlFinder->findAllByData($filter) as $url) {
            $actualResults[] = [
                'request_path' => $url->getRequestPath(),
                'target_path' => $url->getTargetPath(),
                'is_auto_generated' => (int)$url->getIsAutogenerated(),
                'redirect_type' => $url->getRedirectType(),
                'store_id' => $url->getStoreId()
            ];
        }
        return $actualResults;
    }

    /**
     * @magentoDataFixture Magento/CatalogUrlRewrite/_files/product_rewrite_multistore.php
     * @magentoAppIsolation enabled
     */
    public function testUrlKeyHasChangedInGlobalContext()
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore4 = $this->storeManager->getStore('test');

        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        /** @var Product $product*/
        $product = $this->productRepository->get('product1');

        $productFilter = [
            UrlRewrite::ENTITY_TYPE => 'product',
        ];

        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContainsEquals($row, $actual);
        }

        $product->setData('save_rewrites_history', true);
        $product->setUrlKey('new-url');
        $product->setUrlPath('new-path');
        $this->productRepository->save($product);

        $expected = [
            [
                'request_path' => "new-url.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "new-url.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "new-url.html",
                'is_auto_generated' => 0,
                'redirect_type' => 301,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "new-url.html",
                'is_auto_generated' => 0,
                'redirect_type' => 301,
                'store_id' => $testStore4->getId(),
            ],
        ];

        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContainsEquals($row, $actual);
        }
    }

    /**
     * @magentoDataFixture Magento/CatalogUrlRewrite/_files/product_rewrite_multistore.php
     * @magentoAppIsolation enabled
     */
    public function testUrlKeyHasChangedInStoreviewContextWithPermanentRedirection()
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore4 = $this->storeManager->getStore('test');

        $this->storeManager->setCurrentStore($testStore1);

        /** @var Product $product*/
        $product = $this->productRepository->get('product1');

        $productFilter = [
            UrlRewrite::ENTITY_TYPE => 'product',
        ];

        $product->setData('save_rewrites_history', true);
        $product->setUrlKey('new-url');
        $product->setUrlPath('new-path');
        $this->productRepository->save($product);

        $expected = [
            [
                'request_path' => "new-url.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "new-url.html",
                'is_auto_generated' => 0,
                'redirect_type' => 301,
                'store_id' => $testStore1->getId(),
            ],
        ];

        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContainsEquals($row, $actual);
        }
    }

    /**
     * @magentoDataFixture Magento/CatalogUrlRewrite/_files/product_rewrite_multistore.php
     * @magentoAppIsolation enabled
     */
    public function testUrlKeyHasChangedInStoreviewContextWithoutPermanentRedirection()
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore4 = $this->storeManager->getStore('test');

        $this->storeManager->setCurrentStore(1);

        /** @var Product $product*/
        $product = $this->productRepository->get('product1');

        $productFilter = [
            UrlRewrite::ENTITY_TYPE => 'product',
        ];

        $product->setData('save_rewrites_history', false);
        $product->setUrlKey('new-url');
        $product->setUrlPath('new-path');
        $this->productRepository->save($product);

        $expected = [
            [
                'request_path' => "new-url.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
        ];

        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoDataFixture Magento/CatalogUrlRewrite/_files/product_rewrite_multistore.php
     * @magentoAppIsolation enabled
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testAddAndRemoveProductFromWebsite()
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore2 = $this->storeManager->getStore('fixture_second_store');
        $testStore3 = $this->storeManager->getStore('fixture_third_store');
        $testStore4 = $this->storeManager->getStore('test');

        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        /** @var Product $product*/
        $product = $this->productRepository->get('product1');

        $productFilter = [
            UrlRewrite::ENTITY_TYPE => 'product',
        ];

        //Product in 1st website. Should result in being in 1st and 4th stores.
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }

        //Add product to websites corresponding to all 4 stores.
        //Rewrites should be present for all stores.
        $product->setWebsiteIds(
            array_unique(
                [
                    $testStore1->getWebsiteId(),
                    $testStore2->getWebsiteId(),
                    $testStore3->getWebsiteId(),
                    $testStore4->getWebsiteId(),
                ]
            )
        );
        $this->productRepository->save($product);
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore2->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore3->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ]
        ];

        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }

        //Remove product from stores 1 and 4 and leave assigned to stores 2 and 3.
        $product->setWebsiteIds(
            array_unique(
                [
                    $testStore2->getWebsiteId(),
                    $testStore3->getWebsiteId(),
                ]
            )
        );
        $this->productRepository->save($product);
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore2->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore3->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoDataFixture Magento/CatalogUrlRewrite/_files/product_rewrite_multistore.php
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testChangeVisibilityGlobalScope()
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore2 = $this->storeManager->getStore('fixture_second_store');
        $testStore3 = $this->storeManager->getStore('fixture_third_store');
        $testStore4 = $this->storeManager->getStore('test');

        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        /** @var Product $product*/
        $product = $this->productRepository->get('product1');

        $productFilter = [
            UrlRewrite::ENTITY_TYPE => 'product',
        ];

        //Product in 1st website. Should result in being in 1st and 4th stores.
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ]
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }

        //Set product to be not visible at global scope
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $product->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productRepository->save($product);
        $this->assertEmpty($this->getActualResults($productFilter));

        //Add product to websites corresponding to all 4 stores.
        //Rewrites should not be present as the product is hidden
        //at the global scope.
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $product->setWebsiteIds(
            array_unique(
                [
                    $testStore1->getWebsiteId(),
                    $testStore2->getWebsiteId(),
                    $testStore3->getWebsiteId(),
                    $testStore4->getWebsiteId(),
                ]
            )
        );
        $this->productRepository->save($product);
        $expected = [];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }

        //Set product to be visible at global scope
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $product->setVisibility(Visibility::VISIBILITY_BOTH);
        $this->productRepository->save($product);
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore2->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore3->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoDataFixture Magento/CatalogUrlRewrite/_files/product_rewrite_multistore.php
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testChangeVisibilityLocalScope()
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore2 = $this->storeManager->getStore('fixture_second_store');
        $testStore3 = $this->storeManager->getStore('fixture_third_store');
        $testStore4 = $this->storeManager->getStore('test');

        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);

        /** @var Product $product*/
        $product = $this->productRepository->get('product1');

        $productFilter = [
            UrlRewrite::ENTITY_TYPE => 'product',
        ];

        //Product in 1st website. Should result in being in 1st and 4th stores.
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }

        //Set product to be not visible at store 4 scope
        //Rewrite should only be present for store 1
        $this->storeManager->setCurrentStore($testStore4);
        $product = $this->productRepository->get('product1');
        $product->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productRepository->save($product);
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }
        self::assertCount(count($expected), $actual);

        //Add product to websites corresponding to all 4 stores.
        //Rewrites should be present for stores 1,2 and 3.
        //No rewrites should be present for store 4 as that is not visible
        //at local scope.
        $this->storeManager->setCurrentStore(Store::DEFAULT_STORE_ID);
        $product = $this->productRepository->get('product1');
        $product->getExtensionAttributes()->setWebsiteIds(
            array_unique(
                [
                    $testStore1->getWebsiteId(),
                    $testStore2->getWebsiteId(),
                    $testStore3->getWebsiteId(),
                    $testStore4->getWebsiteId()
                ],
            )
        );
        $this->productRepository->save($product);
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore2->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore3->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContains($row, $actual);
        }

        //Set product to be visible at store 4 scope only
        $this->storeManager->setCurrentStore($testStore4);
        $product = $this->productRepository->get('product1');
        $product->setVisibility(Visibility::VISIBILITY_BOTH);
        $this->productRepository->save($product);
        $expected = [
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore2->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore3->getId(),
            ],
            [
                'request_path' => "product-1.html",
                'target_path' => "catalog/product/view/id/" . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore4->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($expected as $row) {
            $this->assertContainsEquals($row, $actual);
        }
    }

    /**
     * phpcs:disable Generic.Files.LineLength.TooLong
     * @magentoDataFixture Magento\Store\Test\Fixture\Website as:website
     * @magentoDataFixture Magento\Store\Test\Fixture\Group with:{"website_id":"$website.id$"} as:store_group
     * @magentoDataFixture Magento\Store\Test\Fixture\Store with:{"store_group_id":"$store_group.id$"} as:store
     * @magentoDataFixture Magento\Catalog\Test\Fixture\Product with:{"sku":"simple1","website_ids":[1,"$website.id$"]} as:product
     * @magentoAppIsolation enabled
     */
    public function testRemoveProductFromAllWebsites(): void
    {
        $testStore1 = $this->storeManager->getStore('default');
        $testStore2 = $this->fixtures->get('store');

        $productFilter = [UrlRewrite::ENTITY_TYPE => 'product'];

        /** @var Product $product*/
        $product = $this->productRepository->get('simple1');
        $product->setWebsiteIds([])
            ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $this->productRepository->save($product);
        $unexpected = [
            [
                'request_path' => 'simple1.html',
                'target_path' => 'catalog/product/view/id/' . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore1->getId(),
            ],
            [
                'request_path' => 'simple1.html',
                'target_path' => 'catalog/product/view/id/' . $product->getId(),
                'is_auto_generated' => 1,
                'redirect_type' => 0,
                'store_id' => $testStore2->getId(),
            ],
        ];
        $actual = $this->getActualResults($productFilter);
        foreach ($unexpected as $row) {
            $this->assertNotContains($row, $actual);
        }
    }
}
