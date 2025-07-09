<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class DynamicController extends AbstractController
{
    protected Serializer $serializer;

    function __construct() {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );
    }

    #[Route('/haeufig-gestellte-fragen', name: 'app_faq', methods: ['GET'])]
    function faq(): Response
    {
        $questions = $this->serializer->deserialize(
            file_get_contents("../config/faq.json"),
            'App\Entity\Question[]',
            'json'
        );

        return $this->render('dynamic/faq.html.twig', [
            'questions' => $questions,
        ]);
    }

    #[Route('/referenzen', name: 'app_reference', methods: ['GET'])]
    function reference(): Response
    {
        $references = $this->serializer->deserialize(
            file_get_contents("../config/references.json"),
            'App\Entity\Reference[]',
            'json'
        );

        return $this->render('dynamic/reference.html.twig', [
            'references' => $references,
        ]);
    }
}
