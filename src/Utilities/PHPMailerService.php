<?php
declare(strict_types=1);

namespace Utilities;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Service Layer for PHPMailer
 */
class PHPMailerService 
{
    private $defaultReturnPathEmail;
    private $defaultFromEmail;
    private $defaultFromName;
    private $failMessageStart;
    private $protocol;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $phpMailer;
    private $errorInfo;
    private $webmasterEmail;

    /**
     * if $webmasterEmail is null, will not send email on dev servers
     * otherwise, will send only to webmaster for testing
     */
    public function __construct(string $defaultReturnPathEmail, string $defaultFromEmail, string $defaultFromName, string $failMessageStart, ?string $protocol = 'smtp', ?string $smtpHost = null, ?int $smtpPort = null, ?string $smtpUsername = null, ?string $smtpPassword = null, ?string $webmasterEmail = null)
    {
        $this->defaultReturnPathEmail = $defaultReturnPathEmail;
        $this->defaultFromEmail = $defaultFromEmail;
        $this->defaultFromName = $defaultFromName;
        $this->failMessageStart = $failMessageStart;
        $this->protocol = strtolower($protocol);
        $this->smtpHost = $smtpHost;
        $this->smtpPort = $smtpPort;
        $this->smtpUsername = $smtpUsername;
        $this->smtpPassword = $smtpPassword;
        $this->webmasterEmail = $webmasterEmail;
        $this->phpMailer = $this->create();
    }

    /**
     * calls the PHPMailer send function
     * email addresses should be validated before calling
     */
    public function send(string $subject, string $body, array $toEmails, ?string $fromEmail = null, ?string $fromName = null, ?bool $isHtml = false, ?string $altBody = null, ?array $cc = null, ?array $bcc = null, ?string $replyToEmail = null, ?string $replyToName = null, ?string $returnPathEmailOverride = null): bool
    {
        if (count($toEmails) == 0) {
            throw new \Exception("No email(s) provided");
        }
        $toEmails = array_unique($toEmails);
        $this->phpMailer->Sender = $returnPathEmailOverride ?? $this->defaultReturnPathEmail; /** The Sender email (Return-Path) of the message. */
        $this->phpMailer->Subject = $subject;
        $this->phpMailer->Body = $body;
        $this->phpMailer->isHTML($isHtml);
        $this->phpMailer->AltBody = $altBody ?? '';
        $toEmailsString = ''; /** used in error message in case of failure */
        $sendCount = 0;
        if (!$_ENV['IS_LIVE']) {
            if ($this->webmasterEmail != null) {
                $toEmails = [$this->webmasterEmail];
            } else {
                return false;
            }
        }
        foreach ($toEmails as $email) {
            if (is_string($email) && mb_strlen($email) > 0) {
                $sendCount++;
                $toEmailsString .= "$email ";
                $this->phpMailer->addAddress(strtolower($email));    
            }
        }
        if ($sendCount == 0) {
            throw new \Exception("No valid email(s) provided");
        }
        if (!is_null($cc)) {
            foreach ($cc as $ccEmail) {
                $this->phpMailer->addCC($ccEmail);
            }
        }
        if (!is_null($bcc)) {
            foreach ($bcc as $bccEmail) {
                $this->phpMailer->addBCC($bccEmail);
            }
        }
        if ($fromEmail == null) {
            $fromEmail = $this->defaultFromEmail;
        }
        if ($fromName == null) {
            $fromName = $this->defaultFromName;
        }
        $this->phpMailer->setFrom($fromEmail, $fromName);
        if ($replyToEmail != null) {
            $replyToName = $replyToName === null ? '' : $replyToName; // blank string instead of null for compatibility with fn arg below
            $this->phpMailer->addReplyTo($replyToEmail, $replyToName);
        }
        if (!$this->phpMailer->send()) {
            $this->errorInfo = $this->phpMailer->ErrorInfo;
            $this->clear();
            $errorMessage = $this->failMessageStart . ": " . $this->errorInfo . PHP_EOL .
                "subject: $subject" . PHP_EOL .
                "body: $body" . PHP_EOL .
                "to: $toEmailsString" . PHP_EOL .
                "from: $fromEmail";
            throw new Exception("$errorMessage");
        }
        $this->clear();
        return true;
    }

    /**
     * clears the current phpmailer
     */
    private function clear() {
        if (!isset($this->phpMailer)) {
            return;
        }
        $this->phpMailer->clearAddresses();
        $this->phpMailer->clearCCs();
        $this->phpMailer->clearBCCs();
        $this->phpMailer->clearReplyTos();
        $this->phpMailer->clearAllRecipients();
        $this->phpMailer->clearAttachments();
        $this->phpMailer->clearCustomHeaders();
    }

    /**
     * Creates a fresh mailer
     */
    private function create() {
        $m = new PHPMailer();
        $m->CharSet = 'utf-8';
        switch ($this->protocol) {
            case 'sendmail':
                $m->isSendmail();
                break;
            case 'smtp':
                $m->isSMTP();
                $m->Host = $this->smtpHost;
                $m->SMTPAuth = true;
                $m->Port = $this->smtpPort;
                $m->Username = $this->smtpUsername;
                $m->Password = $this->smtpPassword;
                break;
            case 'mail':
                $m->isMail();
                break;
            case 'qmail':
                $m->isQmail();
                break;
            default:
                throw new \Exception('bad phpmailerType: '.$this->protocol);
        }
        return $m;
    }

    public function getErrorInfo(): ?string 
    {
        return $this->errorInfo;
    }
}
