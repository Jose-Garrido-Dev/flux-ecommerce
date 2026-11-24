<?php

namespace App\Domain\Notifications\Mail;

use App\Domain\Notifications\Services\EmailTemplateRegistry;
use App\Domain\Notifications\Services\EmailTemplates;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Prueba de una plantilla con datos de ejemplo. Se envía al instante, solo al usuario del panel. */
class TemplatePreviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $key, public string $subjectText, public string $headingText, public string $introText) {}

    /** Asunto, título y texto con las variables de ejemplo reemplazadas. */
    public static function texts(string $key, string $subject, string $heading, string $intro): array
    {
        $vars = ['tienda' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name'))] + EmailTemplates::sample($key);

        return [
            'subject' => EmailTemplates::fill($subject, $vars, markdown: false),
            'heading' => EmailTemplates::fill($heading, $vars, markdown: true),
            'intro' => EmailTemplates::fill($intro, $vars, markdown: true),
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Prueba] '.self::texts($this->key, $this->subjectText, $this->headingText, $this->introText)['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.system.preview', with: [
            'tpl' => self::texts($this->key, $this->subjectText, $this->headingText, $this->introText),
            'description' => EmailTemplateRegistry::get($this->key)['description'],
            'store' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name')),
        ]);
    }
}
