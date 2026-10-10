<?php
declare(strict_types=1);
namespace Flex\Reviews;
use Flex\Contracts\Http\ResponseFactoryInterface;
use Flex\Contracts\Configuration\ConfigRepositoryInterface;
use Flex\Http\RequestInput;
use Flex\Session\CsrfTokenManager;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
final readonly class PublicController
{
    public function __construct(private ResponseFactoryInterface $responses, private RequestInput $input, private CsrfTokenManager $csrf, private ConfigRepositoryInterface $config, private Notifications $notifications) {}
    public function __invoke(ServerRequestInterface $request, array $arguments): ResponseInterface
    {
        try {
            $store = new ReviewStore(); $id = (int) $arguments['id'];
            if (str_contains($request->getUri()->getPath(), '/thread/')) return $this->responses->json($store->thread($id, (int) ($request->getQueryParams()['page'] ?? 1)), 200, ['Cache-Control'=>'no-store']);
            if ($request->getMethod() === 'POST') {
                $user = ReviewIdentity::resolve($request->getAttribute('auth.user'));
                $body = $this->input->all($request);
                if ($user !== null) { $body['name']=$user['name']; $body['email']=$user['email']; }
                $result = $store->submit($id, $body, $user['id'] ?? null, (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''), $this->config->string('app.key'), $user['source'] ?? 'user');
                $this->notifications->newReview($result['id']);
                if ($result['root_id'] && $result['status']==='approved') $this->notifications->reply($result['id']);
                return $this->responses->json(['result' => $result, 'message' => $result['status'] === 'pending' ? 'Благодарим! Коментарът очаква одобрение.' : 'Коментарът е публикуван.'], 201, ['Cache-Control' => 'no-store']);
            }
            $user = ReviewIdentity::resolve($request->getAttribute('auth.user'));
            return $this->responses->json($store->listing($id, max(1, (int) ($request->getQueryParams()['page'] ?? 1))) + ['csrf_token' => $this->csrf->token(), 'user' => $user ? ['name'=>$user['name'],'email'=>$user['email']] : null], 200, ['Cache-Control' => 'no-store']);
        } catch (\InvalidArgumentException $e) { return $this->responses->json(['error' => ['message' => $e->getMessage()]], $e->getCode() ?: 422, ['Cache-Control' => 'no-store']); }
    }
}
