<?php
declare(strict_types=1);
namespace Flex\Reviews\Tests;
use Flex\Configuration\ConfigurationRepository;
use Flex\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Flex\Reviews\ReviewStore;
require_once __DIR__ . '/../src/ReviewStore.php';
require_once __DIR__ . '/../src/ReviewIdentity.php';
final class ReviewStoreTest extends TestCase
{
    private DatabaseManager $db;
    private ReviewStore $store;
    protected function setUp(): void
    {
        $this->db = new DatabaseManager(new ConfigurationRepository(['database'=>['default'=>'sqlite','connections'=>['sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]]]));
        $this->db->schema()->create('flex_commerce_products', static function(Blueprint $t):void {$t->increments('id');$t->string('name');$t->string('status');$t->softDeletes();});
        $this->db->connection()->table('flex_commerce_products')->insert(['id'=>1,'name'=>'Подарък','status'=>'active']);
        $this->store=new ReviewStore($this->db->connection());$this->store->install();
    }
    protected function tearDown(): void {$this->db->disconnect();}
    private function input():array {return ['name'=>'Автор','email'=>'author@example.com','body'=>'Много красив подарък!','rating'=>5];}
    public function testInvalidReplyRollsBackModeration(): void
    {
        $r = $this->store->submit(1, $this->input(), null, 'ip', 'secret');
        try { $this->store->moderate([$r['id']], 'approved', str_repeat('x', 3001)); self::fail('Invalid reply accepted'); }
        catch (\InvalidArgumentException $e) { self::assertSame(422, $e->getCode()); }
        self::assertSame('pending', $this->store->notification($r['id'])['status']);
        self::assertSame(0, $this->store->listing(1)['count']);
    }
    public function testAbsentAccountsFallsBackToServerUser(): void
    {
        self::assertNull(\Flex\Reviews\ReviewIdentity::resolve(null));
        $identity = \Flex\Reviews\ReviewIdentity::resolve((object) ['id'=>9,'name'=>'Server user','email'=>'server@example.com']);
        self::assertSame('user', $identity['source']);
        self::assertSame('Server user', $identity['name']);
    }
    public function testCustomerAndPlatformIdentitiesDoNotCollide(): void
    {
        $this->store->saveSettings(['guests' => false, 'trusted_authors' => true]);
        $r = $this->store->submit(1, $this->input(), 7, 'admin-ip', 'secret');
        $this->store->moderate([$r['id']], 'approved');
        $r = $this->store->submit(1, $this->input(), 7, 'customer-ip', 'secret', 'account');
        self::assertSame('pending', $r['status']);
        self::assertSame('account', $this->store->notification($r['id'])['identity_source']);
        self::assertSame(2, $this->db->connection()->table('flex_reviews')->count());
    }
    public function testThrottleAppliesAcrossProducts(): void
    {
        $this->db->connection()->table('flex_commerce_products')->insert(['id'=>2,'name'=>'Second', 'status'=>'active']);
        $this->store->submit(1, $this->input(), null, 'ip', 'secret');
        $this->expectExceptionCode(429);
        $this->store->submit(2, $this->input(), null, 'other-ip', 'secret');
    }
    public function testPendingIsPrivateAndModerationPublishesSafeFields():void
    {
        $result=$this->store->submit(1,$this->input(),null,'127.0.0.1','secret');
        self::assertSame('pending',$result['status']);self::assertSame(0,$this->store->listing(1)['count']);
        $this->store->moderate([$result['id']],'approved','Благодарим!');
        $list=$this->store->listing(1);self::assertSame(1,$list['count']);self::assertSame('Благодарим!',$list['records'][0]['reply']);
        self::assertArrayNotHasKey('email',$list['records'][0]);self::assertArrayNotHasKey('ip_hash',$list['records'][0]);
        $this->store->moderate([$result['id']],'spam');self::assertSame(0,$this->store->listing(1)['count']);
    }
    public function testThrottleAndDuplicates():void
    {
        $this->store->submit(1,$this->input(),null,'ip','secret');
        try{$this->store->submit(1,$this->input(),null,'ip','secret');self::fail('Throttle missing');}catch(\InvalidArgumentException $e){self::assertSame(429,$e->getCode());}
        $this->db->connection()->table('flex_reviews')->update(['created_at'=>'2020-01-01 00:00:00']);
        try{$this->store->submit(1,$this->input(),null,'other','secret');self::fail('Duplicate accepted');}catch(\InvalidArgumentException $e){self::assertSame(409,$e->getCode());}
    }
    public function testInvalidRatingHoneypotAndLinksAreRejected():void
    {
        foreach([['rating'=>6],['website'=>'bot'],['body'=>'https://example.com test'],['email'=>'invalid']] as $patch){
            try{$this->store->submit(1,array_replace($this->input(),$patch),null,'ip','secret');self::fail('Invalid review accepted');}catch(\InvalidArgumentException $e){self::assertSame(422,$e->getCode());}
        }
        self::assertSame(0,$this->db->connection()->table('flex_reviews')->count());
    }
    public function testSettingsAccessAndDataRetention():void
    {
        $this->store->saveSettings(['guests'=>false]);
        try{$this->store->submit(1,$this->input(),null,'ip','secret');self::fail('Guest accepted');}catch(\InvalidArgumentException $e){self::assertSame(403,$e->getCode());}
        $this->store->saveSettings(['moderation'=>false,'ratings'=>false]);
        $r=$this->store->submit(1,$this->input(),9,'ip','secret');self::assertSame('approved',$r['status']);self::assertNull($this->store->listing(1)['records'][0]['rating']);
        $this->store->uninstall();self::assertTrue($this->db->schema()->hasTable('flex_reviews'));
        $this->store->saveSettings(['delete_on_uninstall'=>true]);$this->store->uninstall();self::assertFalse($this->db->schema()->hasTable('flex_reviews'));
    }
    public function testDisabledAndInvalidProductCannotReceiveReviews():void
    {
        try{$this->store->submit(999,$this->input(),null,'ip','secret');self::fail('Missing product accepted');}catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode());}
        $this->store->saveSettings(['enabled'=>false]);
        try{$this->store->submit(1,$this->input(),null,'ip','secret');self::fail('Disabled accepted');}catch(\InvalidArgumentException $e){self::assertSame(403,$e->getCode());}
    }
    public function testAdminSearchMatchesProductBodyAndAuthorAndKeepsStatusFilter(): void
    {
        $this->db->connection()->table('flex_commerce_products')->where('id', 1)->update(['name' => 'Desk Gift']);
        $r = $this->store->submit(1, ['name'=>'Alice', 'email'=>'alice@example.com', 'body'=>'Wonderful handmade item 100%!', 'rating'=>4], null, 'ip', 'secret');
        foreach (['desk', 'handmade', 'ALICE', '100%'] as $term) {
            $result = $this->store->adminList('pending', $term);
            self::assertCount(1, $result['records']);
            self::assertArrayNotHasKey('products', $result);
        }
        self::assertCount(1, $this->store->adminList('all', 'alice')['records']);
        self::assertSame([], $this->store->adminList('approved', 'alice')['records']);
        self::assertSame([], $this->store->adminList('pending', 'missing')['records']);
        self::assertSame([], $this->store->adminList('pending', '_')['records']);
    }

    public function testRepliesAreModeratedAndOnlyRootCommentsAreCounted(): void
    {
        $root=$this->store->submit(1,$this->input(),null,'root-ip','secret');$this->store->moderate([$root['id']],'approved');
        $answer=$this->store->submit(1,array_replace($this->input(),['email'=>'reply@example.com','parent_id'=>$root['id'],'is_store'=>true]),null,'reply-ip','secret');
        self::assertSame('pending',$answer['status']);self::assertSame(0,$this->store->thread($root['id'])['count']);
        $row=$this->store->notification($answer['id']);self::assertFalse((bool)$row['is_store']);self::assertNull($row['rating']);
        $this->store->moderate([$answer['id']],'approved');
        self::assertSame(1,$this->store->listing(1)['count']);self::assertSame(1,$this->store->thread($root['id'])['count']);
        $official=$this->store->storeReply($answer['id'],'Благодарим за мнението!',8);
        self::assertSame(2,$this->store->thread($root['id'])['count']);self::assertTrue((bool)$this->store->notification($official['id'])['is_store']);
        $this->store->moderate([$root['id']],'trash');
        try{$this->store->thread($root['id']);self::fail('Hidden conversation leaked');}catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode());}
        self::assertSame(3,$this->db->connection()->table('flex_reviews')->count());
    }
    public function testLegacyStoreReplyMigrationIsIdempotentAndPaginated(): void
    {
        $root=$this->store->submit(1,$this->input(),null,'ip','secret');$this->store->moderate([$root['id']],'approved');
        $this->db->connection()->table('flex_reviews')->where('id',$root['id'])->update(['reply'=>'Стар официален отговор']);
        $this->store->install();$this->store->install();
        self::assertSame(1,$this->store->thread($root['id'])['count']);self::assertNull($this->store->notification($root['id'])['reply']);
        $this->store->saveSettings(['replies_per_page'=>1]);$this->store->storeReply($root['id'],'Втори официален отговор',8);
        self::assertSame(2,$this->store->thread($root['id'])['pages']);self::assertCount(1,$this->store->thread($root['id'],2)['records']);
        self::assertSame('Стар официален отговор',$this->store->thread($root['id'])['records'][0]['body']);
    }
    public function testReplySettingsHiddenParentsAndNotifications(): void
    {
        $root=$this->store->submit(1,array_replace($this->input(),['notify_reply'=>true]),null,'ip','secret');
        try{$this->store->submit(1,array_replace($this->input(),['email'=>'x@example.com','parent_id'=>$root['id']]),null,'other','secret');self::fail('Pending parent accepted');}catch(\InvalidArgumentException $e){self::assertSame(404,$e->getCode());}
        $this->store->moderate([$root['id']],'approved');$this->store->saveSettings(['guest_replies'=>false]);
        try{$this->store->submit(1,array_replace($this->input(),['parent_id'=>$root['id']]),null,'other','secret');self::fail('Guest reply accepted');}catch(\InvalidArgumentException $e){self::assertSame(403,$e->getCode());}
        $this->store->saveSettings(['notify_reply'=>true]);$answer=$this->store->storeReply($root['id'],'Благодарим за коментара!',8);
        self::assertSame(['author@example.com'],$this->store->notificationRecipients($answer['id']));self::assertSame([],$this->store->notificationRecipients($answer['id']));
    }

}
