<?php
declare(strict_types=1);
namespace Lab;
use PDO;
use Faker\Generator;

final class CustomerSeeder
{
    public function __construct(private PDO $db, private Generator $faker) {}
    public function seed(): void
    {
        $existing=array_fill_keys($this->db->query('SELECT customer_id FROM customers')->fetchAll(PDO::FETCH_COLUMN),true);
        $insert=$this->db->prepare('INSERT INTO customers (customer_id,full_name,email,marketing_opt_in,created_at) VALUES (?,?,?,?,?)');
        $this->db->beginTransaction();
        try {
            for ($i=1;$i<=10000;$i++) {
                $name=$this->faker->name(); // S1: injected Faker
                $consent=$i<=8000 ? 1 : 0; // S2: exact population split
                $id=sprintf('CUST-%05d',$i);
                if (isset($existing[$id])) continue;
                $insert->execute([$id,mb_substr($name,0,150),sprintf('customer%05d@example.com',$i),$consent,gmdate('Y-m-d H:i:s')]);
            }
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }
}
