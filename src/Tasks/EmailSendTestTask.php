<?php
/**
 * In RM#67766, we changed the field "Question" into "QuestionHeading"
 * in Question.php class. This task is created to help migrate "Question" data
 * to "QuestionHeading".
 *
 * @category SilverStripe_Project
 * @package SDLT
 * @author Catalyst IT <silverstripedev@catalyst.net.nz>
 * @copyright NZ Transport Agency
 * @license BSD-3
 * @link https://nzta.govt.nz
 **/
namespace NZTA\SDLT\Tasks;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\Email\Email;
use Swift_SmtpTransport;
use Swift_Mailer;
use Swift_Message;

class EmailSendTestTask extends BuildTask {

    /**
     * @var string
     */
    private static $segment = 'EmailSendTestTask';

    /**
     * @var string
     */
    public $title = 'Test Legacy Email Sending';

    /**
     * @var string
     */
    public $description = 'This will test sending an email';

    /**
     * Default "run" method, required when implementing BuildTask
     *
     * @param HTTPRequest $request default parameter
     * @return void
     */
    public function run($request)
    {
      $smtpServer = getenv('SDLT_SMTPMAIL_SERVER');
      $smtpPort = getenv('SDLT_SMTPMAIL_PORT');
      $smtpEncryption = getenv('SDLT_SMTPMAIL_ENCRYPTION');

      echo("Connecting to SMTP server $smtpServer on port $smtpPort with encryption $smtpEncryption\n");
      $transport = new Swift_SmtpTransport($smtpServer, $smtpPort, $smtpEncryption);

      $smtpUsername = getenv('SDLT_SMTP_USERNAME');
      echo("Logging in with username: $smtpUsername\n");
      $transport->setUsername($smtpUsername);
      $transport->setPassword(getenv('SDLT_SMTP_PASSWORD'));

      echo("Creating Mailer\n");
      $mailer = new Swift_Mailer($transport);
      echo("Creating Email Message\n");
      $emailFrom = getenv('SDLT_SMTP_TEST_FROM');
      $emailTo = getenv('SDLT_SMTP_TEST_TO');
      echo("Email from $emailFrom will be sent to $emailTo\n");
      // Create the message
      $message = new Swift_Message();
      $message->setSubject('Test SDLT Email Message');
      $message->setFrom($emailFrom);
      $message->setTo($emailTo);
      // Give it a body
      $message->setBody('This is a test message from your SDLT installation');
      $result = $mailer->send($message);
      if ($result) {
        echo("Message was sent successfully\n");
      } else {
        echo ("Failed to send email message\n");
      }
    }
}
