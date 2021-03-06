<?php

namespace Drupal\lightning_api;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to request-related events.
 */
class RequestSubscriber implements EventSubscriberInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The OAuth key service.
   *
   * @var \Drupal\lightning_api\OAuthKey
   */
  protected $key;

  /**
   * RequestSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match service.
   * @param \Drupal\lightning_api\OAuthKey $key
   *   The OAuth keys service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   */
  public function __construct(RouteMatchInterface $route_match, OAuthKey $key, TranslationInterface $translation) {
    $this->routeMatch = $route_match;
    $this->key = $key;
    $this->setStringTranslation($translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => 'onRequest',
    ];
  }

  /**
   * Reacts when a request begins.
   */
  public function onRequest() {
    if ($this->routeMatch->getRouteName() == 'oauth2_token.settings' && $this->key->exists() == FALSE) {
      $warning = $this->t('You may wish to <a href=":generate_keys">generate a key pair</a> for OAuth authentication.', [
        ':generate_keys' => Url::fromRoute('lightning_api.generate_keys')->toString(),
      ]);
      $this->messenger()->addWarning($warning);
    }
  }

}
