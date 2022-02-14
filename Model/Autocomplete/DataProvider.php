<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Elastic Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\ElasticsuiteRetailer
 * @author    Fanny DECLERCK <fadec@smile.fr>
 * @copyright 2018 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */

namespace GlueTeam\ExtendedSearch\Model\Autocomplete;

use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Framework\Data\CollectionFactory;

/**
 * Retailer autocomplete data provider.
 *
 * @category Smile
 * @package  Smile\ElasticsuiteRetailer
 * @author   Fanny DECLERCK <fadec@smile.fr>
 */
class DataProvider implements DataProviderInterface
{
    /**
     * Autocomplete type
     */
    const AUTOCOMPLETE_TYPE = "craft_content";

    /**
     * Autocomplete result item factory
     *
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * Query factory
     *
     * @var QueryFactory
     */
    protected $queryFactory;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    /**
     * @var string Autocomplete result type
     */
    private $type;

    /**
     * Constructor.
     *
     * @param ItemFactory $itemFactory Suggest item factory.
     * @param QueryFactory $queryFactory Search query factory.
     * @param string $type Autocomplete provider type.
     */
    public function __construct(
        ItemFactory       $itemFactory,
        QueryFactory      $queryFactory,
        CollectionFactory $collectionFactory,
        string            $type = self::AUTOCOMPLETE_TYPE
    )
    {
        $this->itemFactory       = $itemFactory;
        $this->queryFactory      = $queryFactory;
        $this->type              = $type;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function getItems(): array
    {
        $result            = [];
        $craftContentItems = $this->getCraftContent();
        if ($craftContentItems) {
            foreach ($craftContentItems as $craftContent) {
                $result[] = $this->itemFactory->create(
                    [
                        'title'        => $craftContent['title'],
                        'url'          => $craftContent['url'],
                        'craft_handle' => $craftContent['typeHandle'],
                        'type'         => $this->getType()
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * Craft content collections.
     * Returns null if no suggested search terms.
     * @throws \ErrorException
     */
    private function getCraftContent()
    {
        $searchQuery = $this->queryFactory->get()->getQueryText();
        $endpoint    = "https://craft-prod.zowizoo.hypernode.io/api";

        $query = <<<'GRAPHQL'
        query Entries($search: String!) {
           entries(limit: 5, search: $search) {
            title
            url
            typeHandle
          }
        }
        GRAPHQL;

        $result = $this->graphql_query($endpoint, $query, ['search' => $searchQuery]);

        return $result['data']['entries'];
    }

    /**
     * @throws \ErrorException
     */
    public function graphql_query(string $endpoint, string $query, array $variables = [], ?string $token = null): array
    {
        $headers = ['Content-Type: application/json'];
        if (null !== $token) {
            $headers[] = "Authorization: bearer $token";
        }

        if (false === $data = @file_get_contents($endpoint, false, stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => $headers,
                    'content' => json_encode(['query' => $query, 'variables' => $variables]),
                ]
            ]))) {
            $error = error_get_last();
            throw new \ErrorException($error['message'], $error['type']);
        }

        return json_decode($data, true);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
