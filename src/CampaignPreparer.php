<?php
declare(strict_types=1);
namespace Lab;
use PDO;

final class CampaignPreparer
{
    public function __construct(private PDO $db) {}
    public function prepare(string $campaignId, string $promoCode): int
    {
        foreach (['campaignId'=>$campaignId,'promoCode'=>$promoCode] as $name=>$value) {
            if (trim($value)==='' || mb_strlen($value)>64) throw new \InvalidArgumentException("Invalid $name");
        }
        $this->db->beginTransaction();
        try {
            $count=$this->db->prepare('SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id=?');$count->execute([$campaignId]);
            $existing=(int)$count->fetchColumn(); // C3: preserve existing selection
            if ($existing===50) { $this->db->commit();return 0; }
            if ($existing!==0) throw new \RuntimeException('Campaign has a partial selection; investigate before continuing');
            $ids=$this->db->query('SELECT customer_id FROM customers WHERE marketing_opt_in=1 ORDER BY RAND() LIMIT 50')->fetchAll(PDO::FETCH_COLUMN); // C2
            if (count($ids)!==50) throw new \RuntimeException('At least 50 opted-in customers are required');
            $insert=$this->db->prepare('INSERT INTO marketing_outbox (request_id,campaign_id,customer_id,promo_code,subject,body,created_at) VALUES (?,?,?,?,?,?,?)');
            foreach ($ids as $id) $insert->execute([bin2hex(random_bytes(16)),$campaignId,$id,$promoCode,'Laboratory promotion','You have been selected to receive our promotional offer.',gmdate('Y-m-d H:i:s')]); // C1
            $this->db->commit();return 50;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();throw $e; }
    }
}
