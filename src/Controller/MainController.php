<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        // Redirect to login/signup
        if ($this->getUser()) {
            // If already logged in, go to dashboard
            return $this->redirectToRoute('app_user_dashboard');
        }

        // Not logged in, go to login page
        return $this->redirectToRoute('app_login');
    }
}
