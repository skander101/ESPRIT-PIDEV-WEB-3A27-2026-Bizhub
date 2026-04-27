<?php

namespace App\Command;

use App\Service\Marketplace\TwilioService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

#[AsCommand(
    name: 'app:twilio:test',
    description: 'Send a test WhatsApp message via Twilio to verify credentials and sandbox.',
)]
class TwilioTestCommand extends Command
{
    public function __construct(
        private readonly string $twilioAccountSid,
        private readonly string $twilioAuthToken,
        private readonly string $twilioWhatsappFrom,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('phone', InputArgument::REQUIRED, 'Destination phone number in E.164 format (e.g. +21698123456)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $phone = $input->getArgument('phone');

        $io->title('Twilio WhatsApp Test');
        $io->table(['Parameter', 'Value'], [
            ['Account SID', $this->twilioAccountSid ?: '(empty)'],
            ['Auth Token', $this->twilioAuthToken ? substr($this->twilioAuthToken, 0, 6) . '...' : '(empty)'],
            ['From (sandbox)', 'whatsapp:' . $this->twilioWhatsappFrom],
            ['To', 'whatsapp:' . $phone],
        ]);

        if (empty($this->twilioAccountSid) || empty($this->twilioAuthToken)) {
            $io->error('TWILIO_ACCOUNT_SID or TWILIO_AUTH_TOKEN is empty. Check your .env file.');
            return Command::FAILURE;
        }

        $io->section('Sending test message...');
        $io->note([
            'For the Twilio WhatsApp sandbox, the recipient must first opt-in by sending:',
            '  "join <your-sandbox-keyword>"',
            '  to: whatsapp:+14155238886',
            'Find your sandbox keyword at: console.twilio.com > Messaging > Try it out > Send a WhatsApp message',
        ]);

        try {
            $client = new Client($this->twilioAccountSid, $this->twilioAuthToken);
            $message = $client->messages->create(
                'whatsapp:' . $phone,
                [
                    'from' => 'whatsapp:' . $this->twilioWhatsappFrom,
                    'body' => '✅ BizHub — Test Twilio WhatsApp. Si vous recevez ce message, la configuration est correcte.',
                ]
            );

            $io->success('Message sent successfully!');
            $io->table(['Field', 'Value'], [
                ['SID', $message->sid],
                ['Status', $message->status],
                ['To', $message->to],
                ['From', $message->from],
            ]);

            return Command::SUCCESS;

        } catch (TwilioException $e) {
            $io->error([
                'Twilio error (code ' . $e->getCode() . '): ' . $e->getMessage(),
                '',
                'Common causes:',
                '  21408 — Permission to send an SMS has not been enabled for the region.',
                '  21614 — Not a valid phone number.',
                '  63016 — Recipient has not joined the WhatsApp sandbox.',
                '  20003 — Authentication error (wrong SID/token).',
            ]);

            return Command::FAILURE;
        }
    }
}
