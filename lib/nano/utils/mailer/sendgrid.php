<?php

namespace Nano\Utils\Mailer;

class Sendgrid
{
  protected $parent;     // The Nano Mailer object.
  protected $mailer;     // The Sendgrid object.
  public $message;       // The Sendgrid\Email object.

  public function __construct ($parent, $opts=[])
  {
    $this->parent = $parent;

    if (isset($opts['apikey']))
    {
      $this->mailer = new \SendGrid($opts['apikey'], $opts);
    }
    else
    {
      throw new \Exception("Use of Sendgrid requires 'apikey' parameter.");
    }

    if (class_exists('\SendGrid\Mail\Mail'))
      $this->message = new \SendGrid\Mail\Mail();
    elseif (class_exists('\SendGrid\Email'))
      $this->message = new \SendGrid\Email();
    else
      throw new \Exception("Unknown version of SendGrid API");

    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    if (isset($opts['from']))
      $this->message->setFrom($opts['from']);

    if (isset($opts['to']))
      $this->message->addTo($opts['to']);
    if (isset($opts['cc']))
      $this->message->addCc($opts['cc']);
    if (isset($opts['bcc']))
      $this->message->addBcc($opts['bcc']);
  }

  public function send_message ($message, $opts=[])
  {
    // Find the subject.
    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    // Find the recipient.
    if (isset($opts['to']))
      $this->message->addTo($opts['to']);
    if (isset($opts['cc']))
      $this->message->addCc($opts['cc']);
    if (isset($opts['bcc']))
      $this->message->addBcc($opts['bcc']);

    if (is_array($message))
    {
      $html = $message[0];
      $text = $message[1];
      if ($this->message instanceof \SendGrid\Email)
      {
        $this->message->setHtml($html);
        $this->message->setText($text);
      }
      else
      {
        $this->message->addContent('text/html', $html);
        $this->message->addContent('text/plain', $text);
      }
    }
    elseif (is_string($message))
    {
      if (substr($message, 0, 1) === '<')
      {
        if ($this->message instanceof \SendGrid\Email)
          $this->message->setHtml($message);
        else
          $this->message->addContent('text/html', $message);
      }
      else
      {
        if ($this->message instanceof \SendGrid\Email)
          $this->message->setText($message);
        else
          $this->message->addContent('text/plain', $message);
      }
    }
    else
    {
      throw new \Exception("Unsupported message format");
    }

    try
    {
      $res = $this->mailer->send($this->message);
    }
    catch (\SendGrid\Exception $e)
    {
      $this->parent->failures[] = $e;
    }
    return $res;
  }

}
