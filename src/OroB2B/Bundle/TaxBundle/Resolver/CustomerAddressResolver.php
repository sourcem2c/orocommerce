<?php

namespace OroB2B\Bundle\TaxBundle\Resolver;

use OroB2B\Bundle\TaxBundle\Model\Result;
use OroB2B\Bundle\TaxBundle\Model\Taxable;

class CustomerAddressResolver implements ResolverInterface
{
    /** @var CustomerAddressItemResolver */
    protected $itemResolver;

    /**
     * @param CustomerAddressItemResolver $itemResolver
     */
    public function __construct(CustomerAddressItemResolver $itemResolver)
    {
        $this->itemResolver = $itemResolver;
    }

    /** {@inheritdoc} */
    public function resolve(Taxable $taxable)
    {
        if (!$taxable->getItems()->count()) {
            return;
        }

        $itemsResult = [];
        foreach ($taxable->getItems() as $taxableItem) {
            $this->itemResolver->resolve($taxableItem);
            $itemsResult[$taxableItem->getHash()] = $taxableItem->getResult();
        }

        $taxable->getResult()->offsetSet(Result::ITEMS, $itemsResult);
    }
}
