<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecommendationNotif extends Mailable
{
    use Queueable, SerializesModels;

    public $erkap_id;
    public $nama_investasi;
    public $kategori_investasi;
    public $tahun;
    public $rekomendasi;
    public $risk_owner;
    // public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($erkap_id, $nama_investasi, $kategori_investasi, $tahun, $rekomendasi, $risk_owner)
    {
        // $this->data = $data;
        $this->erkap_id = $erkap_id;
        $this->nama_investasi = $nama_investasi;
        $this->kategori_investasi = $kategori_investasi;
        $this->tahun = $tahun;
        $this->rekomendasi = $rekomendasi;
        $this->risk_owner = $risk_owner;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SEMESTA-NOTIFICATION]: Rekomendasi Baru',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.RecommendationMail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
