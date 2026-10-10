<?php
declare(strict_types=1);
namespace Flex\Reviews;
use Flex\Extension\V1\{PluginInterface, BootablePluginInterface, UninstallablePluginInterface, UpdatablePluginInterface, PluginContext};
final class ReviewsPlugin implements PluginInterface, BootablePluginInterface, UninstallablePluginInterface, UpdatablePluginInterface
{
    public function install(PluginContext $context): void { (new ReviewStore())->install(); }
    public function update(PluginContext $context, string $fromVersion): void { (new ReviewStore())->install(); }
    public function activate(PluginContext $context): void { (new ReviewStore())->install(); }
    public function deactivate(PluginContext $context): void {}
    public function uninstall(PluginContext $context): void { (new ReviewStore())->uninstall(); }
    public function boot(PluginContext $context): void
    {
        $context->routes?->add(['GET', 'POST'], '/product/{id:number}', PublicController::class, 'product-reviews', [\Flex\Session\CsrfMiddleware::class]);
        $context->routes?->get('/thread/{id:number}', PublicController::class, 'thread');
        $context->routes?->admin('GET', '/thread/{id:number}', AdminController::class, 'admin-thread');
        $context->routes?->admin(['GET', 'POST'], '/comments', AdminController::class, 'comments');
        $context->routes?->admin(['GET', 'PUT'], '/settings', AdminController::class, 'settings');
        $context->admin?->page('comments', 'Коментари', 'assets/admin.js', false, ['group' => 'commerce', 'icon' => 'file-text', 'section' => 'customers', 'order' => 2]);
        $context->api->addFilter('commerce.product.tabs', static function (mixed $tabs, array $data): mixed {
            $store = new ReviewStore(); $settings = $store->settings();
            if (!$settings['enabled'] || ($data['quick_view'] ?? false)) return $tabs;
            $product = $data['product']; $listing = $store->listing((int) $product['id']);
            $twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'), ['autoescape' => 'html', 'strict_variables' => true]);
            $tabs[] = ['id' => 'reviews', 'label' => 'Коментари (' . $listing['count'] . ')', 'html' => $twig->render('reviews.twig', ['product' => $product, 'listing' => $listing, 'settings' => $settings])];
            return $tabs;
        });
    }
}
