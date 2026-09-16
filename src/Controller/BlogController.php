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
 * The blog: the overview, one page per post and the feed, all read out of
 * kongtent.
 *
 * **The year in the address is this site's, the slug is kongtent's.** The
 * year comes from the post's own date, so a published date must not be
 * corrected afterwards: that moves the page.
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

    /**
     * The feed carries the full text, so it asks once per post: the list of a
     * channel carries no blocks.
     */
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

        // kongtent is asked for the slug alone, so the year is compared here.
        if (null === $post || $post->getYear() !== $year) {
            throw $this->createNotFoundException();
        }

        return $this->render('content/blog/post.html.twig', ['post' => $post]);
    }

    /**
     * The references moved into the blog under the same slug and date, so
     * the old addresses keep their links and their place in search engines.
     */
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
