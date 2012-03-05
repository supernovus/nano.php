<?php

/* NanoMailer: A quick class to send e-mail.
   Use it as a standalone component, or extend it for additional features.
 */

class NanoMailer
{
  // Internal rules.
  protected $fields;     // Field rules. 'true' required, 'false' optional.
  protected $recipient;  // Default recipient.
  protected $template;   // Default template to use for e-mails.
  protected $views;      // Nano loader to use to load template.

  // Public fields. Reset on each send().
  public $sent;         // Set to true if the last send() was successful.
  public $missing;      // Set to an array if a required field wasn't set.

  // Set to true to enable logging errors.
  public $log_errors = False;

  public function __construct ($fields, $opts=array())
  {
    if (!is_array($fields))
      throw new NanoException('NanoMailer requires a field list.');
    $this->fields = $fields;
    if (isset($opts['recipient']))
      $this->recipient = $opts['recipient'];
    if (isset($opts['template']))
      $this->template = $opts['template'];

    if (isset($opts['views']))
      $this->views = $opts['views'];
    elseif (!isset($this->views))
      $this->views = 'views'; // Default if nothing else is set.

  }

  public function send ($subject, $data, $opts=array())
  {
    // First, let's reset our special attributes.
    $this->sent = false;
    $this->missing = array();

    // Find the recipient.
    if (isset($opts['recipient']))
      $recipient = $opts['recipient'];
    elseif (isset($this->recipient))
      $recipient = $this->recipient;
    else
      throw new NanoException('NanoMailer requires a recipient.');

    // Find the template to use.
    if (isset($opts['template']))
      $template = $opts['template'];
    elseif (isset($this->template))
      $template = $this->template;
    else
      $template = Null; // We're not using a template.

    // Populate the fields for the e-mail message.
    $fields = array();
    foreach ($this->fields as $field=>$required)
    {
      if (isset($data[$field]) && $data[$field] != '')
        $fields[$field] = $data[$field];
      elseif ($required)
        $this->missing[$field] = true;
    }

    // We can only continue if all required fields are present.
    if (count($this->missing))
    { // We have missing values.
      if ($this->log_errors)
      {
        error_log("Message data: ".json_encode($message));
        error_log("Mailer missing: ".json_encode($this->missing));
      }
      return false;
    }

    // Are we using templates or not?
    // Templates are highly recommended.
    if (isset($template))
    { // We're using templates (recommended.)
      $nano = get_nano_instance();
      $loader = $this->views;
      if (isset($nano->lib[$loader]))
      { // We're using a view loader.
        $message = $nano->lib[$loader]->load($template, $fields);
      }
      else
      { // View library wasn't found. Assuming a full PHP include file path.
        $message = get_php_content($template, $fields);
      }
    }
    else
    { // We're not using a template. Build the message manually.
      $message = "---\n";
      foreach ($fields as $field=>$value)
      {
        $message .= " $field: $value\n";
      }
      $message .= "---\n";
    }
    $this->sent = mail($recipient, $subject, $message);
    if ($this->log_errors && !$this->sent)
    {
      error_log("Error sending mail to '$recipient' with subject: $subject");
    }
    return $this->sent;
  }

}

// End of class.
