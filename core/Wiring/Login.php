<?php

declare(strict_types=1);

namespace Wiring;

use Admin\LoginController;
use Auth\CodeDelivery;
use Http\Endpoint;
use Http\Response;
use Http\Route;

/* The login floor: the one door that starts a session for a visitor holding none. */
final readonly class Login
{
  public function __construct(private Common $common) {}

  public function answer(Route $route): Response
  {
    $request = $this->common->request;
    return match ($route->endpoint) {
      Endpoint::LoginForm => $this->controller()->form($this->common->account !== null, $request),
      Endpoint::LoginAsk => $this->controller()->ask($request),
      Endpoint::LoginRedeem => $this->controller()->redeem($request),
      Endpoint::Logout => $this->controller()->logOut($request, $this->common->account),
    };
  }

  private function controller(): LoginController
  {
    $common = $this->common;
    return new LoginController($common->session, $common->accounts, $common->entities,
      $common->codes(),
      new CodeDelivery($common->premises->data . '/login', $common->relay),
      $common->limit(10, 3600), $common->limit(5, 900), $common->limit(30, 900), $common->limit(20, 3600),
      $common->csrf, $common->template, $common->words, $common->premises->address);
  }
}
