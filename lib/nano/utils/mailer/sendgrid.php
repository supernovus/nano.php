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

    $this->message = new \SendGrid\Email();

    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    if (isset($opts['from']))
      $this->message->setFrom($opts['from']);

    if (isset($opts['to']))
      $this->message->addTo($opts['to']);
  }

  public function send_message ($message, $opts=[])
  {
    // Find the subject.
    if (isset($opts['subject']))
      $this->message->setSubject($opts['subject']);

    // Find the recipient.
    if (isset($opts['to']))
      $this->message->addTo($opts['to']);

    if (is_array($message))
    {
      $html = $message[0];
      $text = $message[1];
      $this->message->setText($text);
      $this->message->setHtml($html);
    }
    elseif (is_string($message))
    {
      if (substr($message, 0, 1) === '<')
      {
        $this->message->setHtml($message);
      }
      else
      {
        $this->message->setText($message);
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
