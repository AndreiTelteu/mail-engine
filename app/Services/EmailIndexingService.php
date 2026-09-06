<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\Models\Email;
use App\Models\MailFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface EmailIndexingService
{
    /**
     * @param  array<int, EmailData>  $emails
     * @return Collection<int, Email>
     */
    public function indexEmails(
        User $user,
        MailFolder $mailFolder,
        array $emails,
    ): Collection;
}
