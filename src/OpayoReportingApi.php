<?php

namespace I3\OpayoReportingApi;

use DateTimeImmutable;
use GuzzleHttp\Client;
use XMLWriter;

abstract class OpayoReportingApi
{
    public const LIVE_URL = 'https://live.sagepay.com/access/access.htm';
    public const SANDBOX_URL = 'https://test.sagepay.com/access/access.htm';
    public $elements = [];
    protected $client;
    private $mode;
    private $password;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function setSandboxMode(): self
    {
        $this->mode = 'sandbox';
        return $this;
    }

    public function setLiveMode(): self
    {
        $this->mode = 'live';
        return $this;
    }

    public function getApiUrl(): string
    {
        return $this->mode === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
    }

    public function setCommand($command)
    {
        $this->elements['command'] = $command;
        return $this;
    }

    public function setVendor($vendor)
    {
        $this->elements['vendor'] = $vendor;
        return $this;
    }

    public function setUser($user)
    {
        $this->elements['user'] = $user;
        return $this;
    }

    public function setStartDate($startdate)
    {
        $this->elements['startdate'] = $startdate;
        return $this;
    }

    public function setEndDate($enddate)
    {
        $this->elements['enddate'] = $enddate;
        return $this;
    }

    public function setBatchId($batchid)
    {
        $this->elements['batchid'] = $batchid;
        return $this;
    }

    public function setAuthProcessor($authprocessor)
    {
        $this->elements['authprocessor'] = $authprocessor;
        return $this;
    }

    public function setStartRow($startrow)
    {
        $this->elements['startrow'] = $startrow;
        return $this;
    }

    public function setEndRow($endrow)
    {
        $this->elements['endrow'] = $endrow;
        return $this;
    }

    public function setSignature($signature)
    {
        $this->elements['signature'] = $signature;
        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getStartDate()
    {
        return (new DateTimeImmutable())->modify('-3 day')->setTime(0, 0, 0)->format('d/m/Y H:i:s');
    }

    public function getEndDate()
    {
        return (new DateTimeImmutable())->modify('-3 day')->setTime(23, 59, 59)->format('d/m/Y H:i:s');
    }

    public function getSignature(array $elements)
    {
        unset($elements['signature']);
        $elements['password'] = $this->password;
        $xml = $this->createXml($elements);
        return md5($xml);
    }

    public function createPayload($elements)
    {
        return $this->createXml($elements);
    }

    private function createXml($elements)
    {
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();

        array_map(function ($node, $value) use ($xmlWriter) {
            $xmlWriter->writeElement($node, $value);
        }, array_keys($elements), array_values($elements));

        $xmlWriter->endElement();
        return $xmlWriter->outputMemory();
    }

    protected function getTransactionsFromResponse(GetBatchDetail $getBatchDetailApi, $startRow, $endRow, $settlementDate, $batchId): array
    {
        $getBatchDetailApi->setStartRow($startRow);
        $getBatchDetailApi->setEndRow($endRow);
        $getBatchDetailApi->setSignature($getBatchDetailApi->getSignature($getBatchDetailApi->elements));
        $response = $getBatchDetailApi->send();
        $payments = [];
        foreach ((array) $response->transactions as $transactions){
            foreach ($transactions as $transaction){
                if ((string) $transaction->refunded != 'NO'){
                    continue;
                }
                $key = (string) $transaction->vendortxcode;
                $payments[$key] = [
                    'started' => DateTimeImmutable::createFromFormat('d/m/Y H:i:s.u', (string) $transaction->started, new \DateTimeZone('GMT'))->getTimestamp(),
                    'completed' => $settlementDate,
                    'vpstxid' => (string) $transaction->vpstxid,
                    'batchid' => $batchId,
                ];
            }
        }

        return $payments;
    }
}