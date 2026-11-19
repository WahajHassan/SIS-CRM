<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FetchGsuiteEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch incoming emails from GSuite via IMAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Connecting to IMAP...");
        try {
            $client = \Webklex\IMAP\Facades\Client::account('default');
            $client->connect();
            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();

            $this->info("Found " . $messages->count() . " new messages.");

            foreach($messages as $message){
                // Save to DB
                $emailRecord = \App\Models\Email::create([
                    'message_id' => $message->getMessageId(),
                    'direction' => 'inbound',
                    'from_address' => $message->getFrom()[0]->mail ?? 'unknown',
                    'to_address' => $message->getTo()[0]->mail ?? 'unknown',
                    'subject' => $message->getSubject(),
                    'body_html' => $message->getHTMLBody() ?? $message->getTextBody(),
                    'status' => 'received'
                ]);
                $message->setFlag('Seen');
            }
            $this->info("Emails fetched successfully.");
        } catch (\Exception $e) {
            $this->error("Failed to fetch emails: " . $e->getMessage());
        }
    }
}
