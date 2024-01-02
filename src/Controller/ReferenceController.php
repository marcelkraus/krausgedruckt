<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ReferenceController extends AbstractController
{
    #[Route('/referenzen', name: 'app_reference', methods: ['GET'])]
    public function index(): Response
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );

        $references = $serializer->deserialize(
            file_get_contents("../config/references.json"),
            'App\Entity\Reference[]',
            'json'
        );

        return $this->render('references/index.html.twig', [
            'references' => $references,
        ]);
    }
}
