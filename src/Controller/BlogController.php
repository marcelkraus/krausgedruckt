<?php

declare(strict_types=1);

namespace App\Controller;

use Krausgebaut\KongtentBundle\Client;
use Krausgebaut\KongtentBundle\Content;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The year in the address comes from the post's date, so correcting a published
 * date moves the page.
 */
final class BlogController extends AbstractController
{
    public function __construct(private readonly Client $kongtent)
    {
    }

    #[Route('/blog', name: 'app_blog', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('content/blog/index.html.twig', [
            'posts' => $this->kongtent->all(),
        ]);
    }

    // The list of a channel carries no blocks, so the feed asks once per post.
    #[Route('/blog/feed.xml', name: 'app_blog_feed', methods: ['GET'])]
    public function feed(): Response
    {
        $posts = array_values(array_filter(array_map(
            fn (Content $summary): ?Content => $this->kongtent->one($summary->slug),
            $this->kongtent->all(),
        )));

        $response = $this->render('content/blog/feed.xml.twig', [
            'posts' => $posts,
            'updated' => [] === $posts ? null : $posts[0]->date,
        ]);
        $response->headers->set('Content-Type', 'application/atom+xml; charset=UTF-8');

        return $response;
    }

    #[Route(
        '/blog/{year}/{slug}',
        name: 'app_blog_post',
        requirements: ['year' => '\d{4}', 'slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function post(string $year, string $slug): Response
    {
        $post = $this->kongtent->one($slug);

        // kongtent knows the slug alone; an unlisted content is never a post.
        if (null === $post || false === $post->listed || $post->getYear() !== $year) {
            throw $this->createNotFoundException();
        }

        return $this->render('content/blog/post.html.twig', ['post' => $post]);
    }

    #[Route('/referenzen', name: 'app_references_redirect', methods: ['GET'])]
    public function references(): RedirectResponse
    {
        return $this->redirectToRoute('app_blog', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/referenzen/{year}/{slug}',
        name: 'app_reference_redirect',
        requirements: ['year' => '\d{4}', 'slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function reference(string $year, string $slug): RedirectResponse
    {
        return $this->redirectToRoute(
            'app_blog_post',
            ['year' => $year, 'slug' => $slug],
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
