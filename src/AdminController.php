<?php
declare(strict_types=1);
namespace Flex\Reviews;
use Flex\Contracts\Http\ResponseFactoryInterface;
use Flex\Http\RequestInput;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
final readonly class AdminController
{
    public function __construct(private ResponseFactoryInterface $responses, private RequestInput $input, private Notifications $notifications) {}
    public function __invoke(ServerRequestInterface $request, array $arguments = []): ResponseInterface
    {
        try {
            $store = new ReviewStore();
            if (str_contains($request->getUri()->getPath(), '/thread/')) return $this->responses->json($store->adminThread((int)$arguments['id'], (int)($request->getQueryParams()['page']??1)), 200, ['Cache-Control'=>'no-store']);
            if (str_ends_with($request->getUri()->getPath(), '/settings')) {
                return $this->responses->json(['settings' => $request->getMethod() === 'GET' ? $store->settings() : $store->saveSettings($this->input->all($request))], 200, ['Cache-Control' => 'no-store']);
            }
            if ($request->getMethod() === 'POST') {
                $body = $this->input->all($request);
                if (!is_array($body['ids'] ?? null) || !is_string($body['status'] ?? null) || (isset($body['reply']) && !is_string($body['reply']))) throw new \InvalidArgumentException('Невалидна заявка.', 422);
                if (isset($body['reply'])) {
                    if (count($body['ids'])!==1) throw new \InvalidArgumentException('Изберете един коментар за отговор.',422);
                    $result=$store->storeReply((int)$body['ids'][0], $body['reply'], $request->getAttribute('auth.user')?->id);
                    $this->notifications->reply($result['id']);
                } else {
                    $before = array_map(fn($id)=>$store->notification((int)$id),$body['ids']);
                    $store->moderate($body['ids'], $body['status']);
                    foreach ($before as $row) if ($row && $row['status']!=='approved' && $body['status']==='approved') $this->notifications->reply((int)$row['id']);
                }
            }
            $q = $request->getQueryParams();
            if (!is_string($q['q'] ?? '')) throw new \InvalidArgumentException('Невалидно търсене.', 422);
            return $this->responses->json($store->adminList((string) ($q['status'] ?? 'pending'), $q['q'] ?? '', (int) ($q['page'] ?? 1)), 200, ['Cache-Control' => 'no-store']);
        } catch (\InvalidArgumentException $e) { return $this->responses->json(['error' => ['message' => $e->getMessage()]], $e->getCode() ?: 422, ['Cache-Control' => 'no-store']); }
    }
}
