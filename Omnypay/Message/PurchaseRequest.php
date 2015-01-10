<?php

namespace Thin\Omnipay\Message;

/**
 * Stripe Purchase Request
 */
class PurchaseRequest extends AuthorizeRequest
{
    public function getData()
    {
        $data = parent::getData();
        $data['capture'] = 'true';

        return $data;
    }
}
