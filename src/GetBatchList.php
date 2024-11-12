<?php

namespace I3\OpayoReportingApi;

use DateTimeImmutable;
use Exception;
use SimpleXMLElement;
use XMLWriter;

class GetBatchList extends OpayoReportingApi
{
    const FIFTY = 50;
    private $getBatchDetailApi;

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

        $this->setCommand('getBatchList');
        $this->setVendor($args['vendor']);
        $this->setUser($args['user']);
        $this->setStartDate($args['startDate'] ?? $this->getStartDate()); // format is m/d/Y H:i:s // '01/12/2022 00:00:00'
        $this->setEndDate($args['endDate'] ?? $this->getEndDate()); // format is m/d/Y H:i:s // '13/12/2022 23:59:59'
        $this->setPassword($args['password']);
        $this->setSignature($this->getSignature($this->elements));

        $this->getBatchDetailApi = new GetBatchDetail($args);
        $this->getBatchDetailApi->setLiveMode();

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

        $document= new SimpleXMLElement($getContents);
//        $document = simplexml_load_string($getContents);
        $payments = [];
        $settlementDate = null;

        $batches = (array) $document->batches;
        if (is_object($batches['batch'])){
            $batches['batch'] = [$batches['batch']];
        }

        foreach ($batches['batch'] as $batch) {
            var_dump('batch is');
            var_dump($batch);
            $batchId = (int) $batch->batchid;
            $authProcessorName = (string) $batch->authprocessorname;
            $settlementDate = DateTimeImmutable::createFromFormat('d/m/Y H:i:s.u', (string) $batch->completed, new \DateTimeZone('Europe/London'))->getTimestamp();
            $this->getBatchDetailApi->setBatchId($batchId);
            $this->getBatchDetailApi->setAuthProcessor($authProcessorName);

            $transactiongroups = (array) $batch->transactiongroups;
            $numberOfTransactions = 0;

            # if not an array, then wrap it in one
            $transactiongroups['transactiongroup'] = !is_array($transactiongroups['transactiongroup']) ? [$transactiongroups['transactiongroup']] : $transactiongroups['transactiongroup'];

            foreach ($transactiongroups['transactiongroup'] as $transactiongroup) {
                # find out the total number of payments in this batch,
                # by adding payments from all currencies
                $numberOfTransactions += (int) $transactiongroup->paymentnumber;
            }

//            foreach ($batch->transactiongroups as $transactiongroupElement) {
//                $transactiongroup = $transactiongroupElement->transactiongroup;
//                $numberOfTransactions = (int) $transactiongroup->paymentnumber[0];
                var_dump('numberoftransactions are :', $numberOfTransactions);
                if ($numberOfTransactions > self::FIFTY) {
                    $transactionChunks = array_chunk(range(1, $numberOfTransactions), self::FIFTY);
                    foreach ($transactionChunks as $index => $chunk){
                        $chunkCount = count($chunk);
                        $startRow = $chunk[0];
                        $endRow = $chunk[$chunkCount-1];
                        var_dump('chunkcount is :', $chunkCount, $startRow, $endRow);
                        $rows = $this->getTransactionsFromResponse($this->getBatchDetailApi, $startRow, $endRow, $settlementDate, $batchId);
                        $payments = $payments + $rows;
                    }
                } else {
                    $rows = $this->getTransactionsFromResponse($this->getBatchDetailApi, 0, self::FIFTY, $settlementDate, $batchId);
                    $payments = $payments + $rows;
                }
//            }
        }

        return $payments;
    }
}