<?php
declare(strict_types=1);
namespace Flex\Reviews;

use Illuminate\Database\{Connection, Eloquent\Model, Schema\Blueprint};

final class ReviewStore
{
    private Connection $db;
    public const DEFAULTS = ['enabled' => true, 'ratings' => true, 'guests' => true, 'moderation' => true,
        'trusted_authors' => false, 'one_per_product' => true, 'replies' => true, 'per_page' => 10,
        'user_replies' => true, 'guest_replies' => true, 'reply_moderation' => true, 'replies_per_page' => 3, 'sort' => 'newest', 'min_length' => 10, 'max_length' => 3000, 'interval_seconds' => 60,
        'max_links' => 0, 'notify_admin' => false, 'admin_email' => '', 'notify_reply' => false, 'delete_on_uninstall' => false];
    public function __construct(?Connection $db = null) { $this->db = $db ?? Model::resolveConnection(); }
    public function install(): void
    {
        if (!$this->db->getSchemaBuilder()->hasTable('flex_commerce_products')) throw new \RuntimeException('Първо инсталирайте Flex Commerce.');
        if (!$this->db->getSchemaBuilder()->hasTable('flex_reviews')) {
            $this->db->getSchemaBuilder()->create('flex_reviews', static function (Blueprint $t): void {
                $t->increments('id'); $t->unsignedInteger('product_id'); $t->unsignedInteger('user_id')->nullable();
                $t->string('name', 100); $t->string('email', 254); $t->string('author_hash', 64);
                $t->boolean('notify_reply')->default(false); $t->string('ip_hash', 64); $t->text('body'); $t->unsignedTinyInteger('rating')->nullable();
                $t->string('status', 16)->default('pending'); $t->text('reply')->nullable(); $t->timestamps();
                $t->index(['product_id', 'status', 'id']); $t->index(['author_hash', 'product_id']); $t->index(['ip_hash', 'created_at']);
            });
        }
        if (!$this->db->getSchemaBuilder()->hasColumn('flex_reviews', 'root_id')) {
            $this->db->getSchemaBuilder()->table('flex_reviews', static function (Blueprint $t): void {
                $t->unsignedInteger('root_id')->nullable(); $t->unsignedInteger('parent_id')->nullable();
                $t->boolean('is_store')->default(false); $t->timestamp('notified_at')->nullable();
                $t->index(['root_id', 'status', 'id']);
            });
        }
        if (!$this->db->getSchemaBuilder()->hasColumn('flex_reviews', 'identity_source')) {
            $this->db->getSchemaBuilder()->table('flex_reviews', static function (Blueprint $t): void { $t->string('identity_source', 16)->default('user'); });
        }
        // Transactional and resumable: clear the old field only after preserving its text.
        foreach ($this->db->table('flex_reviews')->whereNull('root_id')->whereNotNull('reply')->get() as $old) {
            $this->db->transaction(function () use ($old): void {
                $row = $this->db->table('flex_reviews')->where('id', $old->id)->lockForUpdate()->first();
                if ($row === null || $row->reply === null) return;
                if (trim($row->reply) !== '') $this->db->table('flex_reviews')->insert(['product_id'=>$row->product_id, 'root_id'=>$row->id, 'parent_id'=>$row->id, 'is_store'=>true,
                    'name'=>'Магазин', 'email'=>'', 'author_hash'=>'', 'ip_hash'=>'', 'body'=>$row->reply, 'rating'=>null, 'status'=>'approved', 'notify_reply'=>false,
                    'created_at'=>$row->updated_at ?? $row->created_at, 'updated_at'=>$row->updated_at ?? $row->created_at, 'notified_at'=>gmdate('Y-m-d H:i:s')]);
                $this->db->table('flex_reviews')->where('id', $row->id)->update(['reply'=>null]);
            });
        }
        if (!$this->db->getSchemaBuilder()->hasTable('flex_reviews_settings')) {
            $this->db->getSchemaBuilder()->create('flex_reviews_settings', static function (Blueprint $t): void { $t->integer('id')->primary(); $t->text('values'); });
            $this->db->table('flex_reviews_settings')->insert(['id' => 1, 'values' => '{}']);
        }
    }
    public function settings(): array { return array_replace(self::DEFAULTS, json_decode($this->db->table('flex_reviews_settings')->where('id', 1)->value('values') ?? '{}', true)); }
    public function saveSettings(array $input): array
    {
        $values = $this->settings();
        foreach (self::DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $input)) continue;
            $value = $input[$key];
            if (is_bool($default) && !is_bool($value)) throw new \InvalidArgumentException('Невалидна настройка: ' . $key, 422);
            if (is_int($default) && (!is_int($value) || $value < 0)) throw new \InvalidArgumentException('Невалидна числова настройка.', 422);
            $values[$key] = $value;
        }
        if (!in_array($values['sort'], ['newest', 'oldest'], true) || $values['per_page'] < 1 || $values['per_page'] > 50
            || $values['min_length'] < 1 || $values['max_length'] > 10000 || $values['max_length'] < $values['min_length']
            || $values['replies_per_page'] < 1 || $values['replies_per_page'] > 50 || $values['interval_seconds'] < 10 || $values['interval_seconds'] > 86400 || $values['max_links'] > 10) throw new \InvalidArgumentException('Проверете ограниченията на настройките.', 422);
        if (!is_string($values['admin_email']) || ($values['notify_admin'] && !filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL))) throw new \InvalidArgumentException('Въведете валиден имейл за известия.', 422);
        $this->db->table('flex_reviews_settings')->where('id', 1)->update(['values' => json_encode($values, JSON_THROW_ON_ERROR)]);
        return $values;
    }
    public function listing(int $productId, int $page = 1): array
    {
        $s = $this->settings();
        if (!$this->db->table('flex_commerce_products')->where('id', $productId)->where('status', 'active')->whereNull('deleted_at')->exists()) throw new \InvalidArgumentException('Продуктът не е намерен.', 404);
        $q = $this->db->table('flex_reviews')->where('product_id', $productId)->whereNull('root_id')->where('status', 'approved');
        $count = $s['enabled'] ? $q->count() : 0;
        $pages = max(1, (int) ceil($count / $s['per_page'])); $page = max(1, min($pages, $page));
        $rows = $count ? $q->orderBy('id', $s['sort'] === 'newest' ? 'desc' : 'asc')->offset(($page - 1) * $s['per_page'])->limit($s['per_page'])->get(['id', 'name', 'body', 'rating', 'reply', 'created_at'])->map(static fn($r) => (array) $r)->all() : [];
        foreach ($rows as &$entry) {
            $thread = $this->thread((int) $entry['id']); $entry['replies'] = $thread['records']; $entry['reply_count'] = $thread['count'];
            $entry['reply'] = null;
            foreach ($entry['replies'] as $answer) if ($answer['is_store']) { $entry['reply'] = $answer['body']; break; }
        }
        unset($entry);
        if (!$s['ratings']) foreach ($rows as &$row) $row['rating'] = null;
        if (!$s['replies']) foreach ($rows as &$row) $row['reply'] = null;
        return ['records' => $rows, 'count' => $count, 'page' => $page, 'pages' => $pages];
    }
    public function submit(int $productId, array $input, ?int $userId, string $ip, string $secret, string $identitySource = 'user'): array
    {
        $s = $this->settings();
        $parentId = $input['parent_id'] ?? null;
        if ($parentId !== null && (!is_int($parentId) || $parentId < 1)) throw new \InvalidArgumentException('Невалиден отговор.', 422);
        if ($parentId !== null && (!$s['replies'] || !$s['user_replies'] || ($userId === null && !$s['guest_replies']))) throw new \InvalidArgumentException('Отговорите са изключени за този потребител.', 403);
        if (!$s['enabled'] || (!$s['guests'] && $userId === null)) throw new \InvalidArgumentException('Необходимо е да влезете в профила си или коментарите са изключени.', 403);
        if (!$this->db->table('flex_commerce_products')->where('id', $productId)->where('status', 'active')->whereNull('deleted_at')->exists()) throw new \InvalidArgumentException('Продуктът не е намерен.', 404);
        foreach (['name', 'email', 'body'] as $key) if (!is_string($input[$key] ?? null)) throw new \InvalidArgumentException('Попълнете всички задължителни полета.', 422);
        $name = trim($input['name']); $email = mb_strtolower(trim($input['email'])); $body = trim($input['body']);
        if ($name === '' || mb_strlen($name) > 100 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($body) < $s['min_length'] || mb_strlen($body) > $s['max_length']) throw new \InvalidArgumentException('Проверете името, имейла и дължината на коментара.', 422);
        if (!empty($input['website']) || preg_match_all('~https?://|www\.~i', $body) > $s['max_links']) throw new \InvalidArgumentException('Коментарът не може да бъде приет.', 422);
        $rating = $parentId === null && $s['ratings'] ? ($input['rating'] ?? null) : null;
        if ($rating !== null && (!is_int($rating) || $rating < 1 || $rating > 5)) throw new \InvalidArgumentException('Оценката трябва да е между 1 и 5.', 422);
        if (!in_array($identitySource, ['user', 'account'], true)) throw new \InvalidArgumentException('Невалиден профил.', 422);
        $author = hash_hmac('sha256', $userId === null ? $email : $identitySource . ':' . $userId, $secret);
        $ipHash = hash_hmac('sha256', $ip, $secret);
        return $this->db->transaction(function () use ($productId, $input, $userId, $name, $email, $body, $rating, $author, $ipHash, $s, $parentId, $identitySource): array {
            // Serialize cross-product throttling as well as same-product duplicate checks.
            $this->db->table('flex_reviews_settings')->where('id', 1)->lockForUpdate()->first();
            $product = $this->db->table('flex_commerce_products')->where('id', $productId)->lockForUpdate()->first();
            if ($product === null || $product->status !== 'active' || $product->deleted_at !== null) throw new \InvalidArgumentException('Продуктът не е намерен.', 404);
            if ($this->db->table('flex_reviews')->where(static fn($q) => $q->where('ip_hash', $ipHash)->orWhere('author_hash', $author))->where('created_at', '>', gmdate('Y-m-d H:i:s', time() - $s['interval_seconds']))->exists()) throw new \InvalidArgumentException('Изчакайте преди следващ коментар.', 429);
            $rootId = null;
            if ($parentId !== null) {
                $parent = $this->db->table('flex_reviews')->where('id', $parentId)->where('product_id', $productId)->where('status', 'approved')->first();
                if ($parent === null) throw new \InvalidArgumentException('Коментарът не е достъпен за отговор.', 404);
                $rootId = $parent->root_id ?? $parent->id;
                $this->visibleRoot((int) $rootId);
            }
            if ($parentId === null && $s['one_per_product'] && $this->db->table('flex_reviews')->where('product_id', $productId)->where('author_hash', $author)->whereNull('root_id')->whereIn('status', ['pending', 'approved'])->exists()) throw new \InvalidArgumentException('Вече сте оставили коментар за този продукт.', 409);
            $trusted = $s['trusted_authors'] && $this->db->table('flex_reviews')->where('author_hash', $author)->where('status', 'approved')->exists();
            // Guest email ownership is not verified: trusted-author approval is limited to signed-in accounts.
            $status = !($parentId === null ? $s['moderation'] : $s['reply_moderation']) || ($trusted && $userId !== null) ? 'approved' : 'pending';
            $now = gmdate('Y-m-d H:i:s');
            $id = $this->db->table('flex_reviews')->insertGetId(['root_id'=>$rootId, 'parent_id'=>$parentId, 'is_store'=>false, 'notify_reply' => ($input['notify_reply'] ?? false) === true, 'product_id' => $productId, 'user_id' => $userId, 'identity_source' => $userId === null ? 'guest' : $identitySource, 'name' => $name, 'email' => $email, 'body' => $body, 'rating' => $rating, 'author_hash' => $author, 'ip_hash' => $ipHash, 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
            return ['id' => $id, 'status' => $status, 'root_id' => $rootId];
        });
    }
    public function adminList(string $status, string $search = '', int $page = 1): array
    {
        if (!in_array($status, ['all', 'pending', 'approved', 'spam', 'trash'], true)) throw new \InvalidArgumentException('Невалиден статус.', 422);
        $q = $this->db->table('flex_reviews as r')->leftJoin('flex_commerce_products as p', 'p.id', '=', 'r.product_id');
        if ($status !== 'all') $q->where('r.status', $status);
        $search = trim($search);
        if (mb_strlen($search) > 200) throw new \InvalidArgumentException('Търсенето е ограничено до 200 символа.', 422);
        if ($search !== '') {
            // Parameterized LIKE with literal wildcard characters, compatible with MySQL and SQLite.
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)) . '%';
            $q->where(static function ($query) use ($pattern): void {
                $query->whereRaw("LOWER(p.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(r.body) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(r.name) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $count = $q->count(); $pages = max(1, (int) ceil($count / 25)); $page = max(1, min($pages, $page));
        return ['records' => $q->orderByDesc('r.id')->offset(($page - 1) * 25)->limit(25)->get(['r.*', 'p.name as product_name'])->map(static function ($r): array { $a = (array) $r; unset($a['author_hash'], $a['ip_hash']); return $a; })->all(), 'pages' => $pages, 'page' => $page];
    }
    public function moderate(array $ids, string $status, ?string $reply = null): void
    {
        if (!$ids || count($ids) > 100 || !in_array($status, ['pending', 'approved', 'spam', 'trash'], true)) throw new \InvalidArgumentException('Невалидно действие.', 422);
        foreach ($ids as $id) if (!is_int($id) || $id < 1) throw new \InvalidArgumentException('Невалиден коментар.', 422);
        $values = ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')];
        $this->db->transaction(function () use ($ids, $values, $reply): void {
            $this->db->table('flex_reviews')->whereIn('id', $ids)->update($values);
            if ($reply !== null && trim($reply) !== '') $this->storeReply($ids[0], $reply, null);
        });
    }
    private function visibleRoot(int $id): object
    {
        $row = $this->db->table('flex_reviews')->where('id', $id)->whereNull('root_id')->where('status', 'approved')->first();
        if ($row === null || !$this->settings()['enabled'] || !$this->db->table('flex_commerce_products')->where('id', $row->product_id)->where('status', 'active')->whereNull('deleted_at')->exists()) throw new \InvalidArgumentException('Разговорът не е достъпен.', 404);
        return $row;
    }
    public function thread(int $id, int $page = 1): array
    {
        $root = $this->visibleRoot($id); $s = $this->settings();
        $q = $this->db->table('flex_reviews as r')->leftJoin('flex_reviews as parent', 'parent.id', '=', 'r.parent_id')->where('r.root_id', $id)->where('r.status', 'approved');
        $count = $s['replies'] ? $q->count() : 0;
        $pages = max(1, (int) ceil($count / $s['replies_per_page'])); $page = max(1, min($pages, $page));
        $rows = $count ? $q->orderBy('r.id')->offset(($page-1)*$s['replies_per_page'])->limit($s['replies_per_page'])->get(['r.id','r.parent_id','r.name','r.body','r.is_store','r.created_at','parent.name as parent_name','parent.status as parent_status'])->map(static function ($r): array {
            $a=(array)$r; $a['is_store']=(bool)$r->is_store; $a['parent_name'] = $r->parent_status === 'approved' ? $r->parent_name : null; $a['parent_id'] = $r->parent_status === 'approved' ? (int)$r->parent_id : null; unset($a['parent_status']); return $a;
        })->all() : [];
        return ['records'=>$rows,'count'=>$count,'page'=>$page,'pages'=>$pages];
    }
    public function adminThread(int $id, int $page = 1): array
    {
        $row = $this->db->table('flex_reviews')->where('id',$id)->first();
        if ($row === null) throw new \InvalidArgumentException('Коментарът не е намерен.',404);
        $root = $row->root_id ?? $row->id;
        $q = $this->db->table('flex_reviews')->where(static fn($query)=>$query->where('root_id',$root)->orWhere('id',$root));
        $count=$q->count(); $pages=max(1,(int)ceil($count/25)); $page=max(1,min($page,$pages));
        return ['records'=>$q->orderBy('id')->offset(($page-1)*25)->limit(25)->get(['id','name','body','status','is_store','root_id','created_at'])->map(static fn($r)=>(array)$r)->all(),'page'=>$page,'pages'=>$pages];
    }
    public function storeReply(int $parentId, string $body, ?int $userId): array
    {
        $s=$this->settings();
        if (!$s['replies'] || trim($body)==='' || mb_strlen($body)>$s['max_length']) throw new \InvalidArgumentException('Невалиден отговор.',422);
        $parent=$this->db->table('flex_reviews')->where('id',$parentId)->first();
        if ($parent===null) throw new \InvalidArgumentException('Коментарът не е намерен.',404);
        $now=gmdate('Y-m-d H:i:s');
        $id=$this->db->table('flex_reviews')->insertGetId(['product_id'=>$parent->product_id,'root_id'=>$parent->root_id ?? $parent->id,'parent_id'=>$parentId,'is_store'=>true,'user_id'=>$userId,'name'=>'Магазин','email'=>'','body'=>trim($body),'rating'=>null,'status'=>'approved','author_hash'=>'','ip_hash'=>'','notify_reply'=>false,'created_at'=>$now,'updated_at'=>$now]);
        return ['id'=>$id,'status'=>'approved'];
    }
    public function notificationRecipients(int $id): array
    {
        $reply=$this->notification($id); if (!$reply || !$reply['root_id'] || $reply['status']!=='approved' || !$this->settings()['notify_reply']) return [];
        try { $this->visibleRoot((int)$reply['root_id']); } catch (\InvalidArgumentException) { return []; }
        $rows=$this->db->table('flex_reviews')->where(static fn($q)=>$q->where('id',$reply['root_id'])->orWhere('root_id',$reply['root_id']))->where('status','approved')->where('notify_reply',true)->where('email','!=','')->where('email','!=',$reply['email'])->distinct()->pluck('email')->all();
        if ($rows && $this->db->table('flex_reviews')->where('id',$id)->whereNull('notified_at')->update(['notified_at'=>gmdate('Y-m-d H:i:s')])===0) return [];
        return $rows;
    }

    public function notification(int $id): ?array
    {
        $row = $this->db->table('flex_reviews')->where('id', $id)->first();
        return $row === null ? null : (array) $row;
    }
    public function uninstall(): void
    {
        if ($this->settings()['delete_on_uninstall']) { $this->db->getSchemaBuilder()->dropIfExists('flex_reviews'); $this->db->getSchemaBuilder()->dropIfExists('flex_reviews_settings'); }
    }
}

