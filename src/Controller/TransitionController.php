<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransitionController extends AbstractController
{
    #[Route('/transition', name: 'app_transition')]
    public function index(): Response
    {
        return $this->render('transition/index.html.twig');
    }
}
