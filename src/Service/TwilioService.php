<?php

namespace App\Service;

use Twilio\Rest\Client;
use Psr\Log\LoggerInterface;

class TwilioService
{
    private Client $client;
    private string $from;
    private LoggerInterface $logger;

    public function __construct(string $sid, string $token, string $from, LoggerInterface $logger)
    {
        $this->client = new Client($sid, $token);
        $this->from = $from;
        $this->logger = $logger;
    }

    /**
     * Envoie un SMS via Twilio
     */
    public function sendSms(string $to, string $message): bool
    {
        try {
            $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi SMS Twilio: ' . $e->getMessage());
            return false;
        }
    }
}
