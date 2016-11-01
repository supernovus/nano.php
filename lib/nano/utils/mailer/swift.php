<?php

namespace Nano\Utils\Mailer;

class Swift
{
  protected $parent;     // The Nano Mailer object.
  protected $mailer;     // The SwiftMailer object.
  public $message;       // The Swift Message object.

  public function __construct ($parent, $opts=[])
  {
    $this->parent = $parent;

    if (isset($opts['transport']))
      $transport = $opts['transport'];
    elseif (isset($opts['host']))
    { // Using SMTP transport.
      $transport = \Swift_SmtpTransport::newInstance($opts['host']);
      if (isset($opts['port']))
        $transport->setPort($opts['port']);
      if (isset($opts['enc']))
        $transport->setEncryption($opts['enc']);
      if (isset($opts['user']))
        $transport->setUsername($opts['user']);
      if (isset($opts['pass']))
        $transport->setPassword($opts['pass']);
    }
    else
    { // Using sendmail transport.
      $transport = \Swift_SendmailTransport::newInstance();
    }

    $this->mailer = \Swift_Mailer::newInstance($transport);

    $this->message = \Swift_Message::newInstance();

    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    if (isset($opts['from']))
      $this->message->setFrom($opts['from']);

    if (isset($opts['to']))
      $this->message->setTo($opts['to']);
  }

  public function send_message ($message, $opts=[])
  {
    // Find the subject.
    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    // Find the recipient.
    if (isset($opts['to']))
      $this->message->setTo($opts['to']);

    $this->message->setBody($message);
    return $this->mailer->send($this->message, $this->parent->failures);
  }

}
