<?php

namespace I3\OpayoReportingApi;

use Exception;
use SimpleXMLElement;

class GetBatchDetail extends OpayoReportingApi
{
    public function __construct(array $args)
    {
        if (!array_key_exists('vendor', $args)) {
            throw new Exception('vendor key is missing');
        }

        if (!array_key_exists('user', $args)) {
            throw new Exception('user key is missing');
        }

        if (!array_key_exists('password', $args)) {
            throw new Exception('password key is missing');
        }

        $this->setCommand('getBatchDetail');
        $this->setVendor($args['vendor']);
        $this->setUser($args['user']);
        $this->setPassword($args['password']);
        parent::__construct();
    }

    public function send()
    {
        $xml = $this->createPayload($this->elements);
        $result = $this->client->request('POST', $this->getApiUrl(), [
            'form_params' => [
                'XML' => '<vspaccess>' . $xml . '</vspaccess>',
            ]
        ]);

        $status = $result->getStatusCode();
        $body = $result->getBody();
        $getContents = $body->getContents();

        return new SimpleXMLElement($getContents);
    }
}