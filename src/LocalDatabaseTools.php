<?php
declare(strict_types=1);
namespace Lab;
use PDO;

final class LocalDatabaseTools
{
    public function __construct(private PDO $db) {}
    public function initialize(string $schemaPath): void
    {
        $sql=file_get_contents($schemaPath);
        if ($sql===false) throw new \RuntimeException('Cannot read schema');
        $this->db->exec($sql);
    }
    public function inspect(string $campaign='CAMP-001'): array
    {
        $outbox=$this->db->prepare('SELECT status, COUNT(*) AS total FROM marketing_outbox WHERE campaign_id=? GROUP BY status'); $outbox->execute([$campaign]);
        $history=$this->db->prepare('SELECT status, COUNT(*) AS total FROM sent_marketing_emails WHERE campaign_id=? GROUP BY status'); $history->execute([$campaign]);
        $selection=$this->db->prepare('SELECT COUNT(*) AS requests, COUNT(DISTINCT customer_id) AS distinct_recipients FROM marketing_outbox WHERE campaign_id=?'); $selection->execute([$campaign]);
        return ['timestamp'=>gmdate('c'),'campaign'=>$campaign,'customers'=>(int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'consent'=>$this->db->query('SELECT marketing_opt_in, COUNT(*) AS total FROM customers GROUP BY marketing_opt_in')->fetchAll(),
            'outbox'=>$outbox->fetchAll(),'selection'=>$selection->fetch(),'history'=>$history->fetchAll(),
            'sample'=>$this->db->query('SELECT customer_id,full_name,email,marketing_opt_in FROM customers ORDER BY customer_id LIMIT 10')->fetchAll()];
    }
}
