<?php

namespace App\Mail;

use App\Settings\BrandSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 6-digit sign-in code for email login, in the language the customer is browsing in. */
class LoginCodeMail extends Mailable
{
    private const COPY = [
        'bn' => [
            'subject' => '{code} আপনার {shop} লগইন কোড',
            'body' => 'আপনার {shop} অ্যাকাউন্টে লগইনের কোড নিচে দেওয়া হলো। কোডটি ৫ মিনিট কাজ করবে।',
            'ignore' => 'আপনি লগইন করতে না চাইলে এই ইমেইলটি এড়িয়ে যান — কোড ছাড়া কেউ আপনার অ্যাকাউন্টে ঢুকতে পারবে না।',
        ],
        'en' => [
            'subject' => '{code} is your {shop} sign-in code',
            'body' => 'Here is the code to sign in to your {shop} account. It works for 5 minutes.',
            'ignore' => "If you didn't try to sign in, ignore this email — nobody can get in without the code.",
        ],
    ];

    public function __construct(public string $code, public string $lang = 'bn') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->copy('subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.login-code', with: [
            'shop' => app(BrandSettings::class)->name,
            'body' => $this->copy('body'),
            'ignore' => $this->copy('ignore'),
            'code' => $this->code,
            'lang' => $this->copyLang(),
        ]);
    }

    private function copy(string $key): string
    {
        return strtr(self::COPY[$this->copyLang()][$key], ['{code}' => $this->code, '{shop}' => app(BrandSettings::class)->name]);
    }

    private function copyLang(): string
    {
        return isset(self::COPY[$this->lang]) ? $this->lang : 'bn';
    }
}
