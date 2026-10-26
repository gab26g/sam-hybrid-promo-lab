<?php
declare(strict_types=1);
namespace Lab;
use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    private \Closure $mailerFactory;
    public function __construct(private array $settings, ?callable $mailerFactory=null)
    {
        $this->mailerFactory=$mailerFactory===null ? static fn()=>new PHPMailer(true) : \Closure::fromCallable($mailerFactory);
    }
    public function send(array $customer, array $job): string
    {
        $mail=($this->mailerFactory)();
        $mail->isSMTP();$mail->Host=$this->settings['host'];$mail->Port=(int)$this->settings['port'];
        $mail->SMTPAuth=true;$mail->Username=$this->settings['username'];$mail->Password=$this->settings['password'];
        $mail->SMTPSecure=PHPMailer::ENCRYPTION_STARTTLS;$mail->Timeout=30;$mail->Timelimit=30;
        $mail->CharSet='UTF-8';$mail->setFrom($this->settings['from'],'Laboratory Marketing');
        $mail->clearAllRecipients();
        $mail->addAddress($customer['email'],$customer['full_name']); // E1
        $mail->isHTML(false);$mail->Subject=$job['subject'];
        $mail->Body="Hello {$customer['full_name']},\n\n{$job['body']}\n\nYour promo code: {$job['promoCode']}\n\nLaboratory Marketing"; // E2
        $mail->send();
        return $mail->getLastMessageID();
    }
}
