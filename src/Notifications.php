<?php
declare(strict_types=1);
namespace Flex\Reviews;
use Flex\Contracts\Configuration\ConfigRepositoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\{Email, Address};
final readonly class Notifications
{
    public function __construct(private MailerInterface $mailer, private ConfigRepositoryInterface $config) {}
    public function newReview(int $id): void
    {
        $store = new ReviewStore(); $s = $store->settings(); $review = $store->notification($id);
        if ($s['notify_admin'] && $review) $this->send($s['admin_email'], 'Нов коментар за продукт', $review['name'] . "\n\n" . $review['body'] . "\n\nПрегледайте в административния раздел Коментари.");
    }
    public function reply(int $id): void
    {
        $store = new ReviewStore(); $review = $store->notification($id);
        if ($review) foreach ($store->notificationRecipients($id) as $email) $this->send($email, 'Нов отговор в разговора', $review['name'] . ":\n\n" . $review['body']);
    }
    private function send(string $to, string $subject, string $body): void
    {
        try { $this->mailer->send((new Email())->from(new Address($this->config->string('mail.from.address'), $this->config->string('mail.from.name')))->to($to)->subject($subject)->text($body)); }
        catch (\Throwable) { error_log('Flex Reviews: notification delivery failed.'); }
    }
}
