<?php

namespace App\controller;

use App\application\login\LoginService;
use App\infrastructure\router\Router;
use Twig\Environment;

class LoginController extends Controller {

    private LoginService $loginService;

    public function __construct(Environment $twig, LoginService $signupService) {
        parent::__construct($twig);
        $this->loginService = $signupService;
    }

    public function get(Router $router, array $parameters): void {
        $this->render('login.twig', ['router' => $router]);
    }

    public function post(Router $router, array $parameters): void {

        $formParameters = [
            'login' => $_POST['login'] ?? '',
            'password' => $_POST['password'] ?? ''
        ];

        $error = $this->loginService->login($formParameters);

        $this->render('login.twig', ['router' => $router, 'error' => $error ?? '']);
    }

}