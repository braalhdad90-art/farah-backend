<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

// فرح: إرسال الإيميلات عبر Brevo API (HTTPS). Railway يمنع SMTP على خطة Hobby،
// فهذا الطريق يشتغل بدون ترقية. فعّله بـ MAIL_MAILER=brevo و BREVO_API_KEY في Railway.
class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? null;
        $address = fn (Address $a) => array_filter(['email' => $a->getAddress(), 'name' => $a->getName() ?: null]);

        $payload = array_filter([
            'sender' => $from ? $address($from) : null,
            'to' => array_map($address, $email->getTo()),
            'cc' => array_map($address, $email->getCc()) ?: null,
            'bcc' => array_map($address, $email->getBcc()) ?: null,
            'replyTo' => ($email->getReplyTo()[0] ?? null) ? $address($email->getReplyTo()[0]) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
        ], fn ($value) => $value !== null && $value !== '');

        Http::withHeaders(['api-key' => $this->apiKey, 'accept' => 'application/json'])
            ->timeout(15)
            ->post('https://api.brevo.com/v3/smtp/email', $payload)
            ->throw();
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }
}
